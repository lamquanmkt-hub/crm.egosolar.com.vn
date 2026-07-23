<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\MaterialAllocation;
use App\Models\ProjectTest\MaterialRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectTestWarehouseController extends Controller
{
    public const STATES = [
        'waiting_match' => 'Chờ ghép hàng',
        'matching' => 'Đang ghép hàng',
        'shortage' => 'Thiếu hàng',
        'ready' => 'Sẵn sàng giữ hàng',
        'reserved' => 'Đã giữ hàng Test',
        'issued' => 'Đã bàn giao',
    ];

    public function index(Request $request): View
    {
        $this->authorizeWarehouse();

        $state = (string) $request->query('state', '');
        $query = $this->baseRequestQuery();

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($keyword): void {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhereHas('project', function ($project) use ($keyword): void {
                        $project->where('code', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%")
                            ->orWhere('address', 'like', "%{$keyword}%");
                    });
            });
        }

        if (isset(self::STATES[$state])) {
            $this->applyStateFilter($query, $state);
        }

        $requests = $query->paginate(18)->withQueryString();
        $requests->getCollection()->each(function (MaterialRequest $materialRequest): void {
            $materialRequest->setAttribute('computed_warehouse_state', $this->stateOf($materialRequest));
            $materialRequest->setAttribute('warehouse_summary', $this->summaryOf($materialRequest));
        });

        $allForCount = $this->baseRequestQuery()->get();
        $stateCounts = collect(array_keys(self::STATES))->mapWithKeys(
            fn (string $key): array => [$key => $allForCount->filter(
                fn (MaterialRequest $materialRequest): bool => $this->stateOf($materialRequest) === $key
            )->count()]
        );

        return view('project-test.warehouse-index-v2', [
            'requests' => $requests,
            'states' => self::STATES,
            'stateCounts' => $stateCounts,
        ]);
    }

    public function show(MaterialRequest $materialRequest): View
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['approved', 'preparing', 'issued'], true), 404);

        $materialRequest->load([
            'project:id,code,name,address,status,company_id,lead_technician_id,proposed_installation_at',
            'project.leadTechnician:id,name',
            'requester:id,name',
            'issuer:id,name',
            'receiver:id,name',
            'warehouse:id,name,company_id',
            'items.allocations.product:id,name,sku,barcode,unit,is_serialized,company_id',
            'items.allocations.warehouse:id,name,company_id',
        ]);

        $warehouses = $this->warehousesFor($materialRequest);
        $materialRequest->setAttribute('computed_warehouse_state', $this->stateOf($materialRequest));
        $summary = $this->summaryOf($materialRequest);
        $receivers = $this->technicalReceivers();

        return view('project-test.warehouse-show-v2', [
            'materialRequest' => $materialRequest,
            'warehouses' => $warehouses,
            'summary' => $summary,
            'states' => self::STATES,
            'receivers' => $receivers,
        ]);
    }

    public function products(Request $request, MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['approved', 'preparing'], true), 422);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $keyword = trim((string) ($data['q'] ?? ''));
        $companyId = (int) ($materialRequest->project()->value('company_id') ?? 0);

        $query = DB::table('crm_product_catalog as products')
            ->leftJoin('crm_product_stock as stocks', 'stocks.product_id', '=', 'products.id')
            ->leftJoin('crm_warehouses as stock_warehouses', 'stock_warehouses.id', '=', 'stocks.warehouse_id')
            ->where('products.is_active', 1)
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.unit',
                'products.is_serialized',
                'products.company_id',
            ])
            ->selectRaw('COALESCE(SUM(stocks.qty), 0) as total_stock_qty')
            ->selectRaw('COUNT(DISTINCT CASE WHEN stocks.qty > 0 THEN stocks.warehouse_id END) as warehouse_count')
            ->groupBy([
                'products.id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.unit',
                'products.is_serialized',
                'products.company_id',
            ]);

        if ($companyId > 0 && Schema::hasColumn('crm_product_catalog', 'company_id')) {
            $query->where(function ($where) use ($companyId): void {
                $where->where('products.company_id', $companyId)
                    ->orWhereNull('products.company_id');
            });
        }

        if ($companyId > 0 && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $query->where(function ($where) use ($companyId): void {
                $where->where('stock_warehouses.company_id', $companyId)
                    ->orWhereNull('stock_warehouses.company_id');
            });
        }

        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword): void {
                $where->where('products.name', 'like', "%{$keyword}%")
                    ->orWhere('products.sku', 'like', "%{$keyword}%")
                    ->orWhere('products.barcode', 'like', "%{$keyword}%");
            });
        }

        $products = $query
            ->orderByRaw('COALESCE(SUM(stocks.qty), 0) > 0 DESC')
            ->orderByDesc('total_stock_qty')
            ->orderBy('products.name')
            ->limit(30)
            ->get()
            ->map(fn ($product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'barcode' => (string) ($product->barcode ?? ''),
                'unit' => (string) ($product->unit ?: 'cái'),
                'is_serialized' => (bool) $product->is_serialized,
                'total_stock_qty' => (float) $product->total_stock_qty,
                'warehouse_count' => (int) $product->warehouse_count,
            ]);

        return response()->json(['data' => $products]);
    }

    public function warehouses(Request $request, MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['approved', 'preparing'], true), 422);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $productId = (int) $data['product_id'];
        abort_unless(
            DB::table('crm_product_catalog')->where('id', $productId)->where('is_active', 1)->exists(),
            422,
            'Sản phẩm không tồn tại hoặc đã ngừng dùng.'
        );

        $reserved = $this->reservedByWarehouse($productId, $materialRequest->id);

        $query = DB::table('crm_warehouses as warehouses')
            ->leftJoin('crm_product_stock as stocks', function ($join) use ($productId): void {
                $join->on('stocks.warehouse_id', '=', 'warehouses.id')
                    ->where('stocks.product_id', '=', $productId);
            })
            ->select([
                'warehouses.id',
                'warehouses.name',
                'warehouses.company_id',
            ])
            ->selectRaw('COALESCE(SUM(stocks.qty), 0) as stock_qty')
            ->groupBy([
                'warehouses.id',
                'warehouses.name',
                'warehouses.company_id',
            ]);

        $companyId = (int) ($materialRequest->project?->company_id ?? 0);
        if ($companyId > 0 && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $query->where(function ($where) use ($companyId): void {
                $where->where('warehouses.company_id', $companyId)
                    ->orWhereNull('warehouses.company_id');
            });
        }

        $warehouses = $query
            ->orderByRaw('COALESCE(SUM(stocks.qty), 0) > 0 DESC')
            ->orderByDesc('stock_qty')
            ->orderBy('warehouses.name')
            ->get()
            ->map(function ($warehouse) use ($reserved): array {
                $stock = (float) $warehouse->stock_qty;
                $held = (float) ($reserved[(int) $warehouse->id] ?? 0);

                return [
                    'id' => (int) $warehouse->id,
                    'name' => (string) $warehouse->name,
                    'stock_qty' => $stock,
                    'reserved_test_qty' => $held,
                    'available_qty' => max(0, $stock - $held),
                ];
            });

        return response()->json(['data' => $warehouses]);
    }

    public function serials(Request $request, MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeWarehouse();

        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
        ]);

        $warehouseId = (int) $data['warehouse_id'];
        $productId = (int) $data['product_id'];

        if (! Schema::hasTable('crm_serial_unit_states')) {
            return response()->json(['data' => []]);
        }

        $reservedIds = $this->reservedSerialUnitIds($materialRequest->id);

        $serials = DB::table('crm_serial_unit_states as states')
            ->join('crm_serial_units as units', 'units.id', '=', 'states.serial_unit_id')
            ->leftJoin('crm_serial_unit_identifiers as links', function ($join): void {
                $join->on('links.serial_unit_id', '=', 'units.id')
                    ->where('links.is_primary', '=', 1);
            })
            ->leftJoin('crm_serial_identifiers as identifiers', 'identifiers.id', '=', 'links.serial_identifier_id')
            ->where('states.warehouse_id', $warehouseId)
            ->where('states.state', 'in_stock')
            ->where('units.product_id', $productId)
            ->when($reservedIds !== [], fn ($query) => $query->whereNotIn('units.id', $reservedIds))
            ->select([
                'units.id',
                DB::raw("COALESCE(identifiers.code, CONCAT('SN#', units.id)) as code"),
            ])
            ->orderBy('code')
            ->limit(200)
            ->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'code' => (string) $row->code]);

        return response()->json(['data' => $serials]);
    }

    public function saveMapping(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        $this->assertEditable($materialRequest);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.warehouse_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.serial_unit_ids' => ['nullable', 'array'],
            'items.*.serial_unit_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $materialRequest->load(['project', 'items.allocations']);
        $submittedItems = collect($data['items']);

        DB::transaction(function () use ($request, $materialRequest, $submittedItems): void {
            $usedWarehouseIds = collect();

            foreach ($materialRequest->items as $item) {
                $payload = $submittedItems->get((string) $item->id) ?? $submittedItems->get($item->id);
                abort_unless(is_array($payload), 422, 'Thiếu hàng cấp cho nhu cầu: '.$item->item_name);

                $productId = (int) Arr::get($payload, 'product_id');
                $warehouseId = (int) Arr::get($payload, 'warehouse_id');
                $quantity = (float) Arr::get($payload, 'quantity');
                abort_if($quantity > (float) $item->quantity, 422, 'Số lượng cấp không được vượt số lượng Kỹ thuật yêu cầu.');

                $product = DB::table('crm_product_catalog')
                    ->where('id', $productId)
                    ->where('is_active', 1)
                    ->first();
                abort_unless($product, 422, 'Sản phẩm kho không tồn tại hoặc đã ngừng dùng.');

                $warehouse = DB::table('crm_warehouses')->where('id', $warehouseId)->first();
                abort_unless($warehouse, 422, 'Kho đã chọn không tồn tại.');

                $stock = $this->stockQty($productId, $warehouseId);
                $reservedOther = $this->reservedQty($productId, $warehouseId, $materialRequest->id);
                $available = max(0, $stock - $reservedOther);
                $isSerialized = (bool) ($product->is_serialized ?? false);
                $selectedIds = collect(Arr::get($payload, 'serial_unit_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $serialCodes = collect();
                if ($isSerialized && $selectedIds->isNotEmpty()) {
                    $validSerials = $this->validSerials($selectedIds->all(), $productId, $warehouseId, $materialRequest->id);
                    abort_if($validSerials->count() !== $selectedIds->count(), 422, 'Có serial không còn khả dụng trong kho đã chọn.');
                    $serialCodes = $validSerials->pluck('code');
                }

                $hasEnoughStock = $available >= $quantity;
                $hasEnoughSerials = ! $isSerialized || $selectedIds->count() >= (int) ceil($quantity);
                $allocationStatus = ! $hasEnoughStock
                    ? 'shortage'
                    : ($hasEnoughSerials ? 'ready' : 'matched');

                $item->allocations()->delete();
                $item->allocations()->create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'allocated_quantity' => $quantity,
                    'reserved_quantity' => 0,
                    'issued_quantity' => 0,
                    'available_snapshot' => $available,
                    'is_serialized' => $isSerialized,
                    'selected_serial_unit_ids' => $selectedIds->all(),
                    'selected_serial_codes' => $serialCodes->implode("
") ?: null,
                    'status' => $allocationStatus,
                    'allocated_by' => $request->user()->id,
                    'note' => Arr::get($payload, 'note'),
                ]);

                $usedWarehouseIds->push($warehouseId);
            }

            $materialRequest->refresh()->load('items.allocations');
            $state = $this->allocationAggregateState($materialRequest);
            $uniqueWarehouseIds = $usedWarehouseIds->unique()->values();

            $materialRequest->update([
                'warehouse_id' => $uniqueWarehouseIds->count() === 1 ? $uniqueWarehouseIds->first() : null,
                'status' => 'preparing',
                'warehouse_status' => $state,
                'reserved_at' => null,
            ]);

            $this->history(
                $materialRequest,
                'Kho chọn SKU trước rồi chọn kho còn tồn cho từng nhu cầu',
                [
                    'warehouse_ids' => $uniqueWarehouseIds->all(),
                    'warehouse_status' => $state,
                ]
            );
        });

        return back()->with('success', 'Đã lưu SKU và kho cấp cho từng dòng. Hệ thống đang chỉ đọc tồn kho, chưa trừ tồn thật.');
    }

    public function reserve(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        $this->assertEditable($materialRequest);
        $materialRequest->load(['project', 'items.allocations']);

        DB::transaction(function () use ($request, $materialRequest): void {
            foreach ($materialRequest->items as $item) {
                $allocation = $item->allocations->first();
                abort_unless($allocation, 422, 'Còn nhu cầu chưa được ghép sản phẩm kho.');

                $available = max(0, $this->stockQty($allocation->product_id, $allocation->warehouse_id)
                    - $this->reservedQty($allocation->product_id, $allocation->warehouse_id, $materialRequest->id));
                abort_if($available < (float) $allocation->allocated_quantity, 422, 'Không đủ tồn khả dụng để giữ hàng: '.$item->item_name);

                if ($allocation->is_serialized) {
                    $ids = collect($allocation->selected_serial_unit_ids ?? []);
                    abort_if($ids->count() < (int) ceil((float) $allocation->allocated_quantity), 422, 'Chưa chọn đủ serial cho '.$item->item_name);
                    $valid = $this->validSerials($ids->all(), $allocation->product_id, $allocation->warehouse_id, $materialRequest->id);
                    abort_if($valid->count() !== $ids->count(), 422, 'Serial đã thay đổi trạng thái. Hãy chọn lại.');
                }

                $allocation->update([
                    'reserved_quantity' => $allocation->allocated_quantity,
                    'available_snapshot' => $available,
                    'status' => 'reserved',
                    'reserved_by' => $request->user()->id,
                    'reserved_at' => now(),
                ]);
            }

            $materialRequest->update([
                'status' => 'preparing',
                'warehouse_status' => 'reserved',
                'reserved_at' => now(),
            ]);

            $this->history($materialRequest, 'Kho giữ hàng Test cho công trình', ['test_mode' => true]);
        });

        return back()->with('success', 'Đã giữ hàng Test. Tồn thật chưa bị thay đổi; số đã giữ được trừ khỏi “tồn khả dụng” trong module Test.');
    }

    public function release(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        abort_if($materialRequest->status === 'issued', 422, 'Phiếu đã bàn giao không thể bỏ giữ.');

        $materialRequest->load(['project', 'items.allocations']);
        DB::transaction(function () use ($materialRequest): void {
            foreach ($materialRequest->items as $item) {
                foreach ($item->allocations as $allocation) {
                    $allocation->update([
                        'reserved_quantity' => 0,
                        'status' => $allocation->is_serialized
                            && count($allocation->selected_serial_unit_ids ?? []) < (int) ceil((float) $allocation->allocated_quantity)
                                ? 'matched'
                                : 'ready',
                        'reserved_by' => null,
                        'reserved_at' => null,
                    ]);
                }
            }

            $materialRequest->refresh()->load('items.allocations');
            $materialRequest->update([
                'warehouse_status' => $this->allocationAggregateState($materialRequest),
                'reserved_at' => null,
            ]);
            $this->history($materialRequest, 'Kho bỏ giữ hàng Test', ['test_mode' => true]);
        });

        return back()->with('success', 'Đã bỏ giữ hàng Test.');
    }

    public function issue(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['approved', 'preparing'], true), 422, 'Phiếu không còn chờ xuất kho.');
        abort_unless($materialRequest->warehouse_status === 'reserved', 422, 'Phải ghép SKU thật và giữ đủ hàng trước khi bàn giao.');

        $data = $request->validate([
            'receiver_id' => ['required', 'integer'],
            'issue_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $materialRequest->load(['project', 'items.allocations']);
        abort_unless(DB::table('users')->where('id', $data['receiver_id'])->exists(), 422, 'Người nhận không tồn tại.');

        DB::transaction(function () use ($request, $materialRequest, $data): void {
            foreach ($materialRequest->items as $item) {
                $allocation = $item->allocations->first();
                abort_unless($allocation && $allocation->status === 'reserved', 422, 'Có dòng vật tư chưa được giữ đủ.');

                $allocation->update([
                    'issued_quantity' => $allocation->reserved_quantity,
                    'status' => 'issued',
                    'issued_by' => $request->user()->id,
                    'issued_at' => now(),
                ]);

                $item->update([
                    'issued_quantity' => $allocation->reserved_quantity,
                    'serials' => $allocation->selected_serial_codes,
                ]);
            }

            $materialRequest->update([
                'status' => 'issued',
                'warehouse_status' => 'issued',
                'receiver_id' => (int) $data['receiver_id'],
                'issued_by' => $request->user()->id,
                'issued_at' => now(),
                'handed_over_at' => now(),
                'issue_note' => $data['issue_note'] ?? null,
            ]);

            $project = $materialRequest->project;
            $from = $project->status;
            $project->update([
                'status' => 'assignment_pending',
                'current_owner_role' => 'technical_manager',
                'progress' => max((int) $project->progress, 70),
            ]);

            History::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => 'Kho bàn giao hàng thật đã ghép trong module Test; chuyển Trưởng phòng Kỹ thuật phân công',
                'from_status' => $from,
                'to_status' => 'assignment_pending',
                'note' => $data['issue_note'] ?? null,
                'meta' => [
                    'warehouse_ids' => $materialRequest->items->flatMap(fn ($item) => $item->allocations->pluck('warehouse_id'))->unique()->values()->all(),
                    'receiver_id' => (int) $data['receiver_id'],
                    'test_mode' => true,
                    'real_stock_deducted' => false,
                ],
            ]);
        });

        return back()->with('success', 'Đã bàn giao Test và chuyển Trưởng phòng Kỹ thuật phân công. Tồn kho thật chưa bị trừ.');
    }

    private function baseRequestQuery()
    {
        return MaterialRequest::query()
            ->with([
                'project:id,code,name,address,status,lead_technician_id,proposed_installation_at',
                'project.leadTechnician:id,name',
                'requester:id,name',
                'receiver:id,name',
                'items.allocations.product:id,name,sku,unit,is_serialized',
                'items.allocations.warehouse:id,name',
            ])
            ->whereIn('status', ['approved', 'preparing', 'issued'])
            ->latest('id');
    }

    private function applyStateFilter($query, string $state): void
    {
        if ($state === 'issued') {
            $query->where('status', 'issued');
            return;
        }

        $query->whereIn('status', ['approved', 'preparing']);
        if ($state === 'waiting_match') {
            $query->where(function ($where): void {
                $where->whereNull('warehouse_status')
                    ->orWhere('warehouse_status', 'waiting_match');
            });
            return;
        }

        $query->where('warehouse_status', $state);
    }

    private function stateOf(MaterialRequest $materialRequest): string
    {
        if ($materialRequest->status === 'issued') {
            return 'issued';
        }

        if ($materialRequest->warehouse_status && isset(self::STATES[$materialRequest->warehouse_status])) {
            return $materialRequest->warehouse_status;
        }

        return $materialRequest->items->contains(fn ($item): bool => $item->allocations->isNotEmpty())
            ? $this->allocationAggregateState($materialRequest)
            : 'waiting_match';
    }

    private function summaryOf(MaterialRequest $materialRequest): array
    {
        $total = $materialRequest->items->count();
        $mapped = 0;
        $shortage = 0;
        $ready = 0;
        $requestedQty = 0.0;
        $allocatedQty = 0.0;
        $serialRequired = 0;
        $serialSelected = 0;

        foreach ($materialRequest->items as $item) {
            $requestedQty += (float) $item->quantity;
            $allocation = $item->allocations->first();
            if (! $allocation) {
                continue;
            }
            $mapped++;
            $allocatedQty += (float) $allocation->allocated_quantity;
            if ($allocation->status === 'shortage') {
                $shortage++;
            }
            if (in_array($allocation->status, ['ready', 'reserved', 'issued'], true)) {
                $ready++;
            }
            if ($allocation->is_serialized) {
                $serialRequired += (int) ceil((float) $allocation->allocated_quantity);
                $serialSelected += count($allocation->selected_serial_unit_ids ?? []);
            }
        }

        return compact(
            'total',
            'mapped',
            'shortage',
            'ready',
            'requestedQty',
            'allocatedQty',
            'serialRequired',
            'serialSelected'
        );
    }

    private function allocationAggregateState(MaterialRequest $materialRequest): string
    {
        if ($materialRequest->items->isEmpty()) {
            return 'waiting_match';
        }

        $allocations = $materialRequest->items->map(fn ($item) => $item->allocations->first());
        if ($allocations->contains(null)) {
            return 'matching';
        }
        if ($allocations->contains(fn ($allocation): bool => $allocation->status === 'shortage')) {
            return 'shortage';
        }
        if ($allocations->every(fn ($allocation): bool => $allocation->status === 'reserved')) {
            return 'reserved';
        }
        if ($allocations->every(fn ($allocation): bool => in_array($allocation->status, ['ready', 'reserved'], true))) {
            return 'ready';
        }

        return 'matching';
    }

    private function warehousesFor(MaterialRequest $materialRequest): Collection
    {
        if (! Schema::hasTable('crm_warehouses')) {
            return collect();
        }

        $query = DB::table('crm_warehouses')->select('id', 'name', 'company_id')->orderBy('name');
        $companyId = (int) ($materialRequest->project?->company_id ?? 0);
        if ($companyId > 0 && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $query->where(function ($where) use ($companyId): void {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query->get();
    }

    private function stockQty(int $productId, int $warehouseId): float
    {
        if (! Schema::hasTable('crm_product_stock')) {
            return 0;
        }

        return (float) DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('qty');
    }

    private function reservedQty(int $productId, int $warehouseId, ?int $excludeRequestId = null): float
    {
        if (! Schema::hasTable('project_test_material_allocations')) {
            return 0;
        }

        $query = DB::table('project_test_material_allocations as allocations')
            ->join('project_test_material_items as items', 'items.id', '=', 'allocations.material_item_id')
            ->join('project_test_material_requests as requests', 'requests.id', '=', 'items.material_request_id')
            ->where('allocations.product_id', $productId)
            ->where('allocations.warehouse_id', $warehouseId)
            ->where('allocations.status', 'reserved');

        if ($excludeRequestId) {
            $query->where('requests.id', '!=', $excludeRequestId);
        }

        return (float) $query->sum('allocations.reserved_quantity');
    }

    private function reservedByProduct(int $warehouseId, ?int $excludeRequestId = null): array
    {
        if (! Schema::hasTable('project_test_material_allocations')) {
            return [];
        }

        $query = DB::table('project_test_material_allocations as allocations')
            ->join('project_test_material_items as items', 'items.id', '=', 'allocations.material_item_id')
            ->join('project_test_material_requests as requests', 'requests.id', '=', 'items.material_request_id')
            ->where('allocations.warehouse_id', $warehouseId)
            ->where('allocations.status', 'reserved')
            ->groupBy('allocations.product_id')
            ->selectRaw('allocations.product_id, SUM(allocations.reserved_quantity) as qty');

        if ($excludeRequestId) {
            $query->where('requests.id', '!=', $excludeRequestId);
        }

        return $query->pluck('qty', 'product_id')->map(fn ($qty): float => (float) $qty)->all();
    }


    private function reservedByWarehouse(int $productId, ?int $excludeRequestId = null): array
    {
        if (! Schema::hasTable('project_test_material_allocations')) {
            return [];
        }

        $query = DB::table('project_test_material_allocations as allocations')
            ->join('project_test_material_items as items', 'items.id', '=', 'allocations.material_item_id')
            ->join('project_test_material_requests as requests', 'requests.id', '=', 'items.material_request_id')
            ->where('allocations.product_id', $productId)
            ->where('allocations.status', 'reserved')
            ->groupBy('allocations.warehouse_id')
            ->selectRaw('allocations.warehouse_id, SUM(allocations.reserved_quantity) as qty');

        if ($excludeRequestId) {
            $query->where('requests.id', '!=', $excludeRequestId);
        }

        return $query->pluck('qty', 'warehouse_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();
    }

    private function reservedSerialUnitIds(?int $excludeRequestId = null): array
    {
        if (! Schema::hasTable('project_test_material_allocations')) {
            return [];
        }

        $query = MaterialAllocation::query()
            ->where('status', 'reserved')
            ->whereNotNull('selected_serial_unit_ids');

        if ($excludeRequestId) {
            $query->whereHas('item.request', fn ($request) => $request->where('id', '!=', $excludeRequestId));
        }

        return $query->get()
            ->flatMap(fn (MaterialAllocation $allocation): array => $allocation->selected_serial_unit_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function validSerials(array $ids, int $productId, int $warehouseId, ?int $excludeRequestId = null): Collection
    {
        if ($ids === [] || ! Schema::hasTable('crm_serial_unit_states')) {
            return collect();
        }

        $reservedIds = $this->reservedSerialUnitIds($excludeRequestId);

        return DB::table('crm_serial_unit_states as states')
            ->join('crm_serial_units as units', 'units.id', '=', 'states.serial_unit_id')
            ->leftJoin('crm_serial_unit_identifiers as links', function ($join): void {
                $join->on('links.serial_unit_id', '=', 'units.id')
                    ->where('links.is_primary', '=', 1);
            })
            ->leftJoin('crm_serial_identifiers as identifiers', 'identifiers.id', '=', 'links.serial_identifier_id')
            ->whereIn('units.id', $ids)
            ->where('units.product_id', $productId)
            ->where('states.warehouse_id', $warehouseId)
            ->where('states.state', 'in_stock')
            ->when($reservedIds !== [], fn ($query) => $query->whereNotIn('units.id', $reservedIds))
            ->select([
                'units.id',
                DB::raw("COALESCE(identifiers.code, CONCAT('SN#', units.id)) as code"),
            ])
            ->get();
    }

    private function technicalReceivers(): Collection
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return collect();
        }

        return DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\Models\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['ky_thuat', 'technical_manager'])
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    private function assertEditable(MaterialRequest $materialRequest): void
    {
        abort_unless(in_array($materialRequest->status, ['approved', 'preparing'], true), 422, 'Phiếu không còn ở bước Kho xử lý.');
        abort_if($materialRequest->warehouse_status === 'reserved', 422, 'Hãy bỏ giữ hàng trước khi chỉnh SKU.');
    }

    private function history(MaterialRequest $materialRequest, string $action, array $meta = []): void
    {
        History::create([
            'project_id' => $materialRequest->project_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $materialRequest->project?->status,
            'to_status' => $materialRequest->project?->status,
            'note' => null,
            'meta' => array_merge(['material_request_id' => $materialRequest->id], $meta),
        ]);
    }

    private function authorizeWarehouse(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('project-test.warehouse')), 403);
    }
}
