<?php

namespace App\Http\Controllers\Projects;

use App\Enums\SerialUnitState;
use App\Http\Controllers\Controller;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\MaterialAllocation;
use App\Models\ProjectTest\MaterialAftercareRequest;
use App\Models\ProjectTest\MaterialRequest;
use App\Services\StockLotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectTestWarehouseController extends Controller
{
    // EGO_MATERIAL_WORKFLOW_V41_CLEAN
    public const STATES = [
        'waiting_match' => 'Chờ kiểm tra tồn',
        'ready' => 'Đủ hàng',
        'shortage' => 'Thiếu hàng',
        'transfer' => 'Cần điều chuyển',
        'waiting_purchase' => 'Chờ nhập',
        'waiting_replenishment' => 'Chờ bổ sung hàng',
        'pending_manager' => 'Chờ Quản lý duyệt',
        'approved' => 'Đã duyệt xuất kho',
        'reserved' => 'Đã giữ hàng',
        'issued' => 'Đã xuất kho',
    ];

    public function index(Request $request): View
    {
        // EGO_MATERIAL_WORKFLOW_V4_WAREHOUSE_INDEX
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

    /* EGO_PROJECT_WAREHOUSE_EMBEDDED_V2_REDIRECT */
    public function show(MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'issued'], true), 404);
        abort_unless($materialRequest->items()->whereNotNull('item_name')->where('item_name', '!=', '')->exists(), 404);

        return redirect()->route('project-test.show', [
            'project' => $materialRequest->project_id,
            'tab' => 'materials',
            'material_view' => $materialRequest->status === 'issued' ? 'issue' : 'proposal',
            'material_request' => $materialRequest->id,
        ]);
    }
    public function products(Request $request, MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeWarehouse();
        abort_unless(in_array($materialRequest->status, ['warehouse_check', 'pending_manager', 'preparing', 'approved', 'issued'], true), 422);

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
        abort_unless(in_array($materialRequest->status, ['warehouse_check', 'pending_manager', 'preparing', 'approved', 'issued'], true), 422);

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

        $warehouseColumns = [
            'warehouses.id',
            'warehouses.name',
            'warehouses.company_id',
        ];
        $warehouseGroupColumns = $warehouseColumns;
        if (Schema::hasColumn('crm_warehouses', 'location')) {
            $warehouseColumns[] = 'warehouses.location';
            $warehouseGroupColumns[] = 'warehouses.location';
        }

        $query = DB::table('crm_warehouses as warehouses')
            ->leftJoin('crm_product_stock as stocks', function ($join) use ($productId): void {
                $join->on('stocks.warehouse_id', '=', 'warehouses.id')
                    ->where('stocks.product_id', '=', $productId);
            });

        $hasStockLots = Schema::hasTable('crm_product_stock_lots')
            && Schema::hasColumn('crm_product_stock_lots', 'qty_remaining')
            && Schema::hasColumn('crm_product_stock_lots', 'actual_cost_after_vat');

        if ($hasStockLots) {
            $costExpression = 'COALESCE(NULLIF(actual_cost_after_vat, 0), NULLIF(cost_after_vat, 0), NULLIF(cost_before_vat, 0), 0)';
            $costSubquery = DB::table('crm_product_stock_lots')
                ->where('product_id', $productId)
                ->where('qty_remaining', '>', 0)
                ->groupBy('warehouse_id')
                ->selectRaw("warehouse_id, SUM(qty_remaining * {$costExpression}) / NULLIF(SUM(qty_remaining), 0) as estimated_unit_cost");

            $query->leftJoinSub($costSubquery, 'stock_costs', function ($join): void {
                $join->on('stock_costs.warehouse_id', '=', 'warehouses.id');
            });
        }

        $query->select($warehouseColumns)
            ->selectRaw('COALESCE(SUM(stocks.qty), 0) as stock_qty')
            ->selectRaw($hasStockLots ? 'MAX(stock_costs.estimated_unit_cost) as estimated_unit_cost' : 'NULL as estimated_unit_cost')
            ->groupBy($warehouseGroupColumns);

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
            ->map(function ($warehouse) use ($reserved, $productId): array {
                $stock = (float) $warehouse->stock_qty;
                $held = (float) ($reserved[(int) $warehouse->id] ?? 0);

                return [
                    'id' => (int) $warehouse->id,
                    'name' => (string) $warehouse->name,
                    'location' => (string) ($warehouse->location ?? ''),
                    'stock_qty' => $stock,
                    'reserved_test_qty' => $held,
                    'available_qty' => max(0, $stock - $held),
                    'unit_cost' => $warehouse->estimated_unit_cost !== null
                        ? round((float) $warehouse->estimated_unit_cost, 2)
                        : null,
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
        // EGO_MATERIAL_WORKFLOW_V13_SAVE_AND_RECHECK
        $this->authorizeWarehouse();
        $this->assertEditable($materialRequest);

        $data = $this->validateMappingPayload($request);

        $result = DB::transaction(function () use ($request, $materialRequest, $data): array {
            $lockedRequest = MaterialRequest::query()
                ->with(['project', 'items.allocations'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            $this->assertEditable($lockedRequest);

            return $this->persistMapping($request, $lockedRequest, $data);
        });

        if ($result['approved_recheck']) {
            return back()->with(
                'success',
                $result['all_rows_ready']
                    ? 'Đã cập nhật tồn sau điều hàng/nhập bổ sung. Tất cả vật tư đã đủ, Kho có thể giữ hàng và xuất kho.'
                    : 'Đã cập nhật kết quả bổ sung hàng. Phiếu vẫn giữ trạng thái đã duyệt; tiếp tục cập nhật đến khi đủ tồn thực tế.'
            );
        }

        return back()->with('success', 'Đã lưu sản phẩm/SKU, kho cấp và kết quả đối chiếu tồn.');
    }


    public function returnToTechnical(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        $this->assertEditable($materialRequest);

        if ((string) $materialRequest->status !== 'warehouse_check') {
            $this->failValidation('Phiếu đã được Admin/Quản lý duyệt nên không thể trả trực tiếp về Kỹ thuật. Hãy dùng luồng trả lại tại bước phê duyệt.');
        }

        $data = $request->validate([
            'feedback_note' => ['required', 'string', 'max:3000'],
        ], [
            'feedback_note.required' => 'Bắt buộc nhập nội dung cần Kỹ thuật điều chỉnh.',
        ]);

        $materialRequest->load(['project', 'items.allocations']);
        if ($materialRequest->items->isEmpty()) {
            $this->failValidation('Phiếu chưa có dòng vật tư.');
        }

        DB::transaction(function () use ($request, $materialRequest, $data): void {
            $locked = MaterialRequest::query()->with('project')->lockForUpdate()->findOrFail($materialRequest->id);
            if ((string) $locked->status !== 'warehouse_check') {
                $this->failValidation('Phiếu vừa được người khác xử lý. Hãy tải lại trang.');
            }

            $note = trim((string) $data['feedback_note']);
            $locked->update([
                'status' => 'revision',
                'warehouse_status' => 'needs_revision',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => 'Kho phản hồi: '.$note,
                'reserved_at' => null,
            ]);

            $project = $locked->project;
            $from = (string) $project->status;
            $project->update([
                'status' => 'materials_revision',
                'current_owner_role' => 'technical',
                'progress' => max((int) $project->progress, 53),
            ]);

            History::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => 'Kho phản hồi sai thông tin và trả Kỹ thuật điều chỉnh',
                'from_status' => $from,
                'to_status' => 'materials_revision',
                'note' => $note,
                'meta' => [
                    'material_request_id' => (int) $locked->id,
                    'allocations_preserved' => true,
                    'workflow_version' => 'v13',
                ],
            ]);
        });

        return back()->with('success', 'Đã trả Kỹ thuật điều chỉnh. Kết quả Kho cũ được giữ lại; chỉ dòng Kỹ thuật sửa mới phải đối chiếu lại.');
    }


    public function submitForManager(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        // EGO_MATERIAL_WORKFLOW_V13_SAVE_AND_SUBMIT_MANAGER
        $this->authorizeWarehouse();

        $mappingData = $request->has('items')
            ? $this->validateMappingPayload($request)
            : null;

        $result = DB::transaction(function () use ($request, $materialRequest, $mappingData): array {
            $lockedRequest = MaterialRequest::query()
                ->with(['project', 'items.allocations'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if ((string) $lockedRequest->status !== 'warehouse_check') {
                $this->failValidation('Phiếu không còn ở bước Kho đối chiếu hoặc vừa được người khác xử lý. Hãy tải lại trang.');
            }

            if ($mappingData !== null) {
                $this->persistMapping($request, $lockedRequest, $mappingData);
                $lockedRequest->refresh()->load(['project', 'items.allocations']);
            }

            if ($lockedRequest->items->isEmpty()) {
                $this->failValidation('Phiếu chưa có dòng vật tư.');
            }

            $invalidRows = collect();
            foreach ($lockedRequest->items as $item) {
                $allocation = $item->allocations->first();
                $reason = null;

                if ((int) $item->product_id <= 0) {
                    $reason = 'chưa gắn sản phẩm/SKU';
                } elseif (! $allocation) {
                    $reason = 'chưa lưu kết quả đối chiếu';
                } elseif ((int) $allocation->product_id !== (int) $item->product_id) {
                    $reason = 'SKU đối chiếu không khớp';
                } elseif (! in_array((string) $allocation->status, ['ready', 'shortage', 'transfer', 'waiting_purchase'], true)) {
                    $reason = 'chưa chọn kết quả xử lý';
                }

                if ($reason !== null) {
                    $invalidRows->push([
                        'name' => (string) $item->item_name,
                        'reason' => $reason,
                    ]);
                }
            }

            if ($invalidRows->isNotEmpty()) {
                $preview = $invalidRows->take(4)
                    ->map(fn (array $row): string => $row['name'].' ('.$row['reason'].')')
                    ->implode('; ');
                $more = $invalidRows->count() > 4 ? '; và '.($invalidRows->count() - 4).' dòng khác' : '';
                $this->failValidation(
                    'Còn '.$invalidRows->count().' dòng chưa đối chiếu hoàn chỉnh: '.$preview.$more.'. Hãy chọn sản phẩm/SKU, kho cấp, kết quả rồi bấm “Gửi Admin duyệt” lại.'
                );
            }

            $warehouseState = $this->allocationAggregateState($lockedRequest);
            if ($warehouseState === 'waiting_match') {
                $this->failValidation('Kết quả đối chiếu chưa hoàn chỉnh. Hãy kiểm tra lại toàn bộ dòng vật tư.');
            }
            $allRowsReady = $warehouseState === 'ready';

            $lockedRequest->update([
                'status' => 'pending_manager',
                'warehouse_status' => $warehouseState,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            $project = $lockedRequest->project;
            $from = (string) $project->status;
            $project->update([
                'status' => 'materials_admin_review',
                'current_owner_role' => 'admin',
                'progress' => max((int) $project->progress, 62),
            ]);

            History::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => $allRowsReady
                    ? 'Kho xác nhận đủ vật tư và gửi Admin/Quản lý phê duyệt'
                    : 'Kho gửi Admin/Quản lý phê duyệt dù còn thiếu hàng, cần điều chuyển hoặc chờ nhập',
                'from_status' => $from,
                'to_status' => 'materials_admin_review',
                'note' => $lockedRequest->issue_note,
                'meta' => [
                    'material_request_id' => (int) $lockedRequest->id,
                    'warehouse_status' => $warehouseState,
                    'all_lines_ready' => $allRowsReady,
                    'saved_and_submitted_in_one_request' => $mappingData !== null,
                    'workflow_version' => 'v13',
                ],
            ]);

            return compact('allRowsReady');
        });

        return redirect()->route('project-test.show', [
            'project' => $materialRequest->project_id,
            'tab' => 'materials',
            'material_view' => 'proposal',
            'material_request' => $materialRequest->id,
        ])->with(
            'success',
            $result['allRowsReady']
                ? 'Đã lưu đối chiếu và gửi Admin/Quản lý phê duyệt chuyển xuất kho.'
                : 'Đã lưu đối chiếu và gửi Admin/Quản lý phê duyệt. Các dòng thiếu, điều chuyển hoặc chờ nhập vẫn được giữ nguyên để Admin chỉ đạo.'
        );
    }


    public function syncStock(Request $request, MaterialRequest $materialRequest): RedirectResponse|JsonResponse
    {
        // EGO_MATERIAL_WORKFLOW_V14_AUTO_STOCK_SYNC
        $this->authorizeWarehouse();

        if (! in_array((string) $materialRequest->status, ['warehouse_check', 'approved'], true)) {
            $this->failValidation('Chỉ được đồng bộ tồn khi Kho đang đối chiếu hoặc phiếu đã được Admin/Quản lý duyệt chờ bổ sung hàng.');
        }

        $result = DB::transaction(function () use ($request, $materialRequest): array {
            $lockedRequest = MaterialRequest::query()
                ->with(['project', 'items.allocations'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            $result = $this->synchronizeAllocationStock($lockedRequest);

            $this->history($lockedRequest, 'Kho đồng bộ tồn thực tế từ module Nhập kho vào phiếu công trình', [
                'ready_rows' => $result['ready_rows'],
                'short_rows' => $result['short_rows'],
                'changed_rows' => $result['changed_rows'],
                'warehouse_status' => $result['warehouse_status'],
                'source' => 'crm_product_stock',
                'workflow_version' => 'v14',
            ]);

            return $result;
        });

        $message = $result['all_rows_ready']
            ? 'Đã lấy tồn mới nhất từ Kho. Tất cả vật tư đã đủ; có thể chuyển sang giữ hàng và xuất kho.'
            : 'Đã lấy tồn mới nhất từ Kho: '.$result['ready_rows'].' dòng đủ, '.$result['short_rows'].' dòng còn thiếu.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }


    public function quickReceive(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        // EGO_MATERIAL_WORKFLOW_V16_FULL_RECEIPT_PAYMENT
        $this->authorizeWarehouse();

        if (! in_array((string) $materialRequest->status, ['warehouse_check', 'approved'], true)) {
            $this->failValidation('Phiếu không ở trạng thái cho phép nhập bổ sung hàng.');
        }

        $data = $request->validate([
            'allocation_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'invoice_date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
            'payment_due_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'serial_codes' => ['nullable', 'string', 'max:30000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'supplier_id.required' => 'Bắt buộc chọn nhà cung cấp.',
            'invoice_date.required' => 'Bắt buộc chọn ngày hóa đơn/ngày nhập.',
            'quantity.required' => 'Bắt buộc nhập số lượng nhập kho.',
            'quantity.integer' => 'Số lượng nhập kho phải là số nguyên.',
            'unit_price.required' => 'Bắt buộc nhập đơn giá chưa VAT.',
            'payment_status.required' => 'Bắt buộc chọn trạng thái thanh toán.',
        ]);

        $result = DB::transaction(function () use ($request, $materialRequest, $data): array {
            $lockedRequest = MaterialRequest::query()
                ->with(['project', 'items.allocations'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if (! in_array((string) $lockedRequest->status, ['warehouse_check', 'approved'], true)) {
                $this->failValidation('Phiếu vừa được người khác xử lý. Hãy tải lại trang.');
            }

            $allocation = MaterialAllocation::query()
                ->whereKey((int) $data['allocation_id'])
                ->whereHas('item', fn ($query) => $query->where('material_request_id', $lockedRequest->id))
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                $this->failValidation('Dòng vật tư nhập bổ sung không thuộc phiếu này hoặc đã thay đổi.');
            }

            $product = DB::table('crm_product_catalog')
                ->where('id', (int) $allocation->product_id)
                ->where('is_active', 1)
                ->first();
            $warehouse = DB::table('crm_warehouses')
                ->where('id', (int) $allocation->warehouse_id)
                ->first();

            if (! $product || ! $warehouse) {
                $this->failValidation('Sản phẩm hoặc kho cấp không còn tồn tại.');
            }

            foreach (['crm_product_stock', 'crm_product_stock_lots', 'product_goods_receipts', 'product_goods_receipt_items'] as $requiredTable) {
                if (! Schema::hasTable($requiredTable)) {
                    $this->failValidation('Thiếu bảng dữ liệu '.$requiredTable.'. Không thể nhập kho nhanh an toàn.');
                }
            }

            $quantity = (int) $data['quantity'];
            $unitPrice = round((float) $data['unit_price'], 4);
            $vatPercent = round((float) ($data['vat_percent'] ?? 0), 4);
            $amount = round($quantity * $unitPrice * (1 + ($vatPercent / 100)), 4);
            $companyId = (int) ($lockedRequest->project?->company_id ?? 0);
            if ($companyId <= 0) {
                $this->failValidation('Công trình chưa gắn công ty nên không thể tạo phiếu nhập kho.');
            }

            if (! Schema::hasTable('product_suppliers')) {
                $this->failValidation('Thiếu bảng nhà cung cấp. Hãy nhập hàng bằng module Nhập kho đầy đủ.');
            }

            $supplier = DB::table('product_suppliers')
                ->where('id', (int) $data['supplier_id'])
                ->where('is_active', 1)
                ->when(
                    Schema::hasColumn('product_suppliers', 'company_id'),
                    fn ($query) => $query->where('company_id', $companyId)
                )
                ->first();

            if (! $supplier) {
                $this->failValidation('Nhà cung cấp không tồn tại, đã ngừng dùng hoặc không thuộc công ty của công trình.');
            }

            $paymentStatus = (string) $data['payment_status'];
            $paidAmount = round((float) ($data['paid_amount'] ?? 0), 4);

            if ($paymentStatus === 'unpaid') {
                $paidAmount = 0;
            } elseif ($paymentStatus === 'paid') {
                $paidAmount = $amount;
            } elseif ($amount <= 0 || $paidAmount <= 0 || $paidAmount >= $amount) {
                $this->failValidation('Thanh toán một phần phải có số tiền đã thanh toán lớn hơn 0 và nhỏ hơn tổng tiền phiếu nhập.');
            }

            if ($paidAmount > $amount) {
                $this->failValidation('Số tiền đã thanh toán không được lớn hơn tổng giá trị phiếu nhập.');
            }

            $debtAmount = max(0, round($amount - $paidAmount, 4));
            $warehouseId = (int) $allocation->warehouse_id;
            $productId = (int) $allocation->product_id;
            $isSerialized = (bool) ($product->is_serialized ?? false);
            $serialCodes = collect(preg_split('/[\r\n,;]+/', (string) ($data['serial_codes'] ?? '')))
                ->map(fn ($code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values();

            if ($isSerialized && $serialCodes->count() !== $quantity) {
                $this->failValidation('Sản phẩm “'.($product->name ?? ('#'.$productId)).'” quản lý serial. Hãy nhập đúng '.$quantity.' mã serial, mỗi mã một dòng.');
            }
            if (! $isSerialized && $serialCodes->isNotEmpty()) {
                $this->failValidation('Sản phẩm này không quản lý serial; hãy để trống ô serial.');
            }

            if ($isSerialized) {
                foreach (['crm_serial_units', 'crm_serial_unit_states', 'crm_serial_identifiers', 'crm_serial_unit_identifiers'] as $serialTable) {
                    if (! Schema::hasTable($serialTable)) {
                        $this->failValidation('Thiếu bảng serial '.$serialTable.'. Hãy nhập bằng module Nhập kho đầy đủ.');
                    }
                }

                $duplicated = DB::table('crm_serial_identifiers')
                    ->whereIn('code', $serialCodes->all())
                    ->pluck('code');
                if ($duplicated->isNotEmpty()) {
                    $this->failValidation('Serial đã tồn tại: '.$duplicated->take(5)->implode(', '));
                }
            }

            $receiptCode = 'NH-CT-'.now()->format('YmdHis').'-'.$lockedRequest->id.'-'.random_int(10, 99);
            $receiptId = DB::table('product_goods_receipts')->insertGetId([
                'code' => $receiptCode,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'supplier_id' => (int) $supplier->id,
                'supplier_name' => (string) $supplier->name,
                'supplier_phone' => $supplier->phone ?? null,
                'supplier_tax_code' => $supplier->tax_code ?? null,
                'supplier_address' => $supplier->address ?? null,
                'invoice_no' => trim((string) ($data['invoice_no'] ?? '')) ?: null,
                'invoice_date' => (string) $data['invoice_date'],
                'payment_status' => $paymentStatus,
                'payment_due_date' => $data['payment_due_date'] ?? null,
                'total_amount' => $amount,
                'paid_amount' => $paidAmount,
                'debt_amount' => $debtAmount,
                'status' => 'posted',
                'note' => trim((string) ($data['note'] ?? '')) ?: 'Nhập bổ sung cho phiếu '.$lockedRequest->code,
                'created_by' => $request->user()->id,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_goods_receipt_items')->insert([
                'receipt_id' => $receiptId,
                'product_id' => $productId,
                'qty' => $quantity,
                'unit_price' => $unitPrice,
                'vat_percent' => $vatPercent,
                'amount' => $amount,
                'note' => 'Công trình '.$lockedRequest->project?->code.' · Phiếu '.$lockedRequest->code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $costAfterVat = round($unitPrice * (1 + ($vatPercent / 100)), 2);
            DB::table('crm_product_stock_lots')->insert([
                'product_id' => $productId,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'lot_code' => 'CT-'.$lockedRequest->id.'-'.$allocation->id.'-'.now()->format('YmdHis'),
                'lot_name' => 'Nhập bổ sung '.$lockedRequest->code,
                'received_at' => now(),
                'qty_in' => $quantity,
                'qty_remaining' => $quantity,
                'cost_before_vat' => $unitPrice,
                'cost_vat_percent' => $vatPercent,
                'cost_after_vat' => $costAfterVat,
                'extra_cost' => 0,
                'actual_cost_after_vat' => $costAfterVat,
                'source_type' => 'product_goods_receipt',
                'source_id' => $receiptId,
                'note' => trim((string) ($data['note'] ?? '')) ?: 'Nhập bổ sung tại hồ sơ công trình',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = DB::table('crm_product_stock')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                DB::table('crm_product_stock')->where('id', $stock->id)->update([
                    'qty' => (int) $stock->qty + $quantity,
                    'company_id' => $companyId ?? $stock->company_id,
                    'last_updated' => now(),
                ]);
            } else {
                DB::table('crm_product_stock')->insert([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'company_id' => $companyId,
                    'qty' => $quantity,
                    'serials_json' => $isSerialized ? $serialCodes->values()->toJson(JSON_UNESCAPED_UNICODE) : null,
                    'last_updated' => now(),
                ]);
            }

            if ($isSerialized) {
                foreach ($serialCodes as $serialCode) {
                    $identifierId = DB::table('crm_serial_identifiers')->insertGetId([
                        'type' => 'serial',
                        'code' => $serialCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $unitId = DB::table('crm_serial_units')->insertGetId([
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);
                    DB::table('crm_serial_unit_identifiers')->insert([
                        'serial_unit_id' => $unitId,
                        'serial_identifier_id' => $identifierId,
                        'is_primary' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('crm_serial_unit_states')->insert([
                        'serial_unit_id' => $unitId,
                        'warehouse_id' => $warehouseId,
                        'company_id' => $companyId,
                        'state' => SerialUnitState::IN_STOCK->value,
                        'last_event_id' => null,
                        'synced_at' => now(),
                        'note' => 'Nhập bổ sung từ phiếu '.$lockedRequest->code,
                    ]);
                }
            }

            $syncResult = $this->synchronizeAllocationStock($lockedRequest->fresh(['project', 'items.allocations']));

            $this->history($lockedRequest, 'Kho nhập bổ sung sản phẩm trực tiếp tại hồ sơ công trình', [
                'material_request_id' => (int) $lockedRequest->id,
                'allocation_id' => (int) $allocation->id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_received' => $quantity,
                'receipt_id' => $receiptId,
                'receipt_code' => $receiptCode,
                'serialized' => $isSerialized,
                'stock_after' => $this->stockQty($productId, $warehouseId),
                'all_rows_ready' => $syncResult['all_rows_ready'],
                'supplier_id' => (int) $supplier->id,
                'supplier_name' => (string) $supplier->name,
                'payment_status' => $paymentStatus,
                'total_amount' => $amount,
                'paid_amount' => $paidAmount,
                'debt_amount' => $debtAmount,
                'workflow_version' => 'v16',
            ]);

            return [
                'receipt_code' => $receiptCode,
                'quantity' => $quantity,
                'product_name' => (string) ($product->name ?? ('Sản phẩm #'.$productId)),
                'warehouse_name' => (string) ($warehouse->name ?? ('Kho #'.$warehouseId)),
                'supplier_name' => (string) $supplier->name,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'debt_amount' => $debtAmount,
                'all_rows_ready' => $syncResult['all_rows_ready'],
            ];
        });

        return back()->with(
            'success',
            'Đã tạo phiếu nhập '.$result['receipt_code'].' từ '.$result['supplier_name'].'; nhập '.$result['quantity'].' '.$result['product_name'].' vào '.$result['warehouse_name'].'. '
            .'Đã thanh toán '.number_format((float) $result['paid_amount'], 0, ',', '.').' đ, còn công nợ '.number_format((float) $result['debt_amount'], 0, ',', '.').' đ. '
            .($result['all_rows_ready'] ? 'Toàn bộ phiếu đã đủ hàng; có thể giữ hàng và xuất kho.' : 'Tồn công trình đã được đồng bộ lại tự động.')
        );
    }


    public function reserve(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        // EGO_MATERIAL_WORKFLOW_V13_RESERVE
        $this->authorizeWarehouse();
        if (! in_array((string) $materialRequest->status, ['approved', 'preparing'], true)) {
            $this->failValidation('Admin/Quản lý chưa phê duyệt chuyển xuất kho.');
        }
        if ((string) $materialRequest->warehouse_status === 'waiting_replenishment') {
            $this->failValidation('Phiếu đã được duyệt nhưng còn thiếu hàng. Hãy vào “Vật tư đề xuất”, cập nhật tồn sau điều hàng/nhập bổ sung đến khi tất cả dòng đủ hàng.');
        }
        if ((string) $materialRequest->warehouse_status === 'reserved') {
            $this->failValidation('Hàng của phiếu này đã được giữ.');
        }
        $materialRequest->load(['project', 'items.allocations']);

        DB::transaction(function () use ($request, $materialRequest): void {
            foreach ($materialRequest->items as $item) {
                $allocation = $item->allocations->first();
                if (! $allocation || (string) $allocation->status !== 'ready') {
                    $this->failValidation('Dòng “'.$item->item_name.'” chưa đủ hàng hoặc chưa được cập nhật tồn mới nhất.');
                }

                $quantity = $this->wholeQuantity((float) $allocation->allocated_quantity, $item->item_name);
                $available = max(0, $this->stockQty($allocation->product_id, $allocation->warehouse_id)
                    - $this->reservedQty($allocation->product_id, $allocation->warehouse_id, $materialRequest->id));
                if ($available < $quantity) {
                    $this->failValidation('Tồn kho vừa thay đổi và không đủ để giữ “'.$item->item_name.'”. Hãy cập nhật lại đối chiếu tồn.');
                }

                if ($allocation->is_serialized) {
                    $ids = collect($allocation->selected_serial_unit_ids ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->values();
                    if ($ids->count() < $quantity) {
                        if (! Schema::hasTable('crm_serial_unit_states')) {
                            $this->failValidation('Thiếu dữ liệu serial cho “'.$item->item_name.'”.');
                        }
                        $reservedIds = $this->reservedSerialUnitIds($materialRequest->id);
                        $autoSerials = DB::table('crm_serial_unit_states as states')
                            ->join('crm_serial_units as units', 'units.id', '=', 'states.serial_unit_id')
                            ->where('states.warehouse_id', $allocation->warehouse_id)
                            ->where('states.state', 'in_stock')
                            ->where('units.product_id', $allocation->product_id)
                            ->when($reservedIds !== [], fn ($query) => $query->whereNotIn('units.id', $reservedIds))
                            ->orderBy('units.id')
                            ->limit($quantity)
                            ->pluck('units.id')
                            ->map(fn ($id): int => (int) $id);
                        if ($autoSerials->count() < $quantity) {
                            $this->failValidation('Không đủ serial khả dụng cho “'.$item->item_name.'”.');
                        }
                        $ids = $autoSerials;
                    }

                    $valid = $this->validSerials($ids->all(), $allocation->product_id, $allocation->warehouse_id, $materialRequest->id);
                    if ($valid->count() !== $ids->count()) {
                        $this->failValidation('Serial của “'.$item->item_name.'” vừa thay đổi trạng thái. Hãy kiểm tra lại.');
                    }
                    $this->setSerialStates($ids->all(), SerialUnitState::RESERVED->value, $allocation->warehouse_id);
                    $allocation->selected_serial_unit_ids = $ids->all();
                    $allocation->selected_serial_codes = $valid->pluck('code')->implode("\n");
                }

                $allocation->reserved_quantity = $quantity;
                $allocation->available_snapshot = $available;
                $allocation->status = 'reserved';
                $allocation->reserved_by = $request->user()->id;
                $allocation->reserved_at = now();
                $allocation->save();
            }

            $materialRequest->update([
                'status' => 'preparing',
                'warehouse_status' => 'reserved',
                'reserved_at' => now(),
            ]);

            $this->history($materialRequest, 'Kho giữ hàng theo phiếu đã được Admin/Quản lý phê duyệt', ['workflow_version' => 'v13']);
        });

        return back()->with('success', 'Đã giữ đủ hàng. Có thể xác nhận xuất kho chính thức.');
    }


    public function release(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeWarehouse();
        if ((string) $materialRequest->status === 'issued') {
            $this->failValidation('Phiếu đã xuất và bàn giao nên không thể bỏ giữ hàng.');
        }

        $materialRequest->load(['project', 'items.allocations']);
        DB::transaction(function () use ($materialRequest): void {
            foreach ($materialRequest->items as $item) {
                foreach ($item->allocations as $allocation) {
                    if ($allocation->is_serialized) {
                        $this->setSerialStates(
                            collect($allocation->selected_serial_unit_ids ?? [])->map(fn ($id): int => (int) $id)->all(),
                            SerialUnitState::IN_STOCK->value,
                            $allocation->warehouse_id
                        );
                    }

                    $allocation->update([
                        'reserved_quantity' => 0,
                        'status' => 'ready',
                        'reserved_by' => null,
                        'reserved_at' => null,
                    ]);
                }
            }

            $materialRequest->update([
                'status' => 'approved',
                'warehouse_status' => 'ready',
                'reserved_at' => null,
            ]);
            $this->history($materialRequest, 'Kho bỏ giữ hàng; phiếu quay lại trạng thái đã duyệt và sẵn sàng giữ lại', [
                'workflow_version' => 'v13',
            ]);
        });

        return back()->with('success', 'Đã bỏ giữ hàng. Serial đã được trả về trạng thái trong kho.');
    }


    public function issue(Request $request, MaterialRequest $materialRequest, StockLotService $stockLotService): RedirectResponse
    {
        $this->authorizeWarehouse();
        if (! in_array((string) $materialRequest->status, ['approved', 'preparing'], true)) {
            $this->failValidation('Admin/Quản lý chưa phê duyệt chuyển xuất kho.');
        }
        if ((string) $materialRequest->warehouse_status !== 'reserved') {
            $this->failValidation('Phải kiểm tra đúng SKU và giữ đủ hàng trước khi xuất kho.');
        }

        $data = $request->validate([
            'receiver_id' => ['required', 'integer'],
            'issue_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'receiver_id.required' => 'Bắt buộc chọn người nhận vật tư.',
        ]);

        $materialRequest->load(['project', 'items.allocations']);
        if (! DB::table('users')->where('id', $data['receiver_id'])->exists()) {
            $this->failValidation('Người nhận vật tư không tồn tại hoặc đã bị xóa.');
        }

        DB::transaction(function () use ($request, $materialRequest, $data, $stockLotService): void {
            $freshRequest = MaterialRequest::query()->whereKey($materialRequest->id)->lockForUpdate()->firstOrFail();
            if ((string) $freshRequest->status === 'issued') {
                $this->failValidation('Phiếu này đã được xuất kho trước đó.');
            }

            $materialRequest->load(['project', 'items.allocations']);
            $companyId = (int) ($materialRequest->project?->company_id ?? 0);

            foreach ($materialRequest->items as $item) {
                $allocation = $item->allocations->first();
                if (! $allocation || (string) $allocation->status !== 'reserved') {
                    $this->failValidation('Dòng “'.$item->item_name.'” chưa được giữ đủ hàng.');
                }

                $quantity = $this->wholeQuantity((float) $allocation->reserved_quantity, $item->item_name);
                $productId = (int) $allocation->product_id;
                $warehouseId = (int) $allocation->warehouse_id;
                if ($quantity <= 0 || $productId <= 0 || $warehouseId <= 0) {
                    $this->failValidation('Dữ liệu xuất kho không hợp lệ ở dòng “'.$item->item_name.'”.');
                }

                $lotAllocations = $stockLotService->issueLots($productId, $companyId ?: null, $warehouseId, $quantity, [
                    'reference_type' => 'project_material_request',
                    'reference_id' => (int) $materialRequest->id,
                    'reason' => 'Xuất kho công trình '.$materialRequest->project?->code,
                    'note' => trim((string) ($data['issue_note'] ?? '')) ?: 'Xuất vật tư theo phiếu '.$materialRequest->code,
                ]);

                if ($allocation->is_serialized) {
                    $serialIds = collect($allocation->selected_serial_unit_ids ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
                    if ($serialIds->count() !== $quantity) {
                        $this->failValidation('Số serial không khớp số lượng xuất ở dòng “'.$item->item_name.'”.');
                    }
                    $this->assertReservedSerials($serialIds->all(), $productId, $warehouseId);
                    $this->setSerialStates($serialIds->all(), SerialUnitState::REMOVED->value, null);
                }

                $weightedCost = collect($lotAllocations)->sum(fn (array $lot): float => (float) ($lot['total_cost_after_vat'] ?? 0));

                $allocation->update([
                    'issued_quantity' => $quantity,
                    'selected_lots_json' => $lotAllocations !== [] ? $lotAllocations : null,
                    'unit_cost' => $quantity > 0 && $weightedCost > 0 ? round($weightedCost / $quantity, 2) : null,
                    'status' => 'issued',
                    'issued_by' => $request->user()->id,
                    'issued_at' => now(),
                ]);

                $item->update([
                    'issued_quantity' => $quantity,
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
            $isFollowUpRequest = ($materialRequest->request_kind ?? 'standard') !== 'standard';

            if (! $isFollowUpRequest) {
                $project->update([
                    'status' => 'assignment_pending',
                    'current_owner_role' => 'technical_manager',
                    'progress' => max((int) $project->progress, 70),
                ]);
            }

            if ($materialRequest->aftercare_request_id) {
                $aftercare = MaterialAftercareRequest::query()->lockForUpdate()->find($materialRequest->aftercare_request_id);
                if ($aftercare) {
                    $returnDone = (bool) $aftercare->return_processed_at;
                    $completed = $aftercare->type !== 'exchange' || $returnDone;
                    $aftercare->update([
                        'status' => $completed ? 'completed' : 'processing',
                        'completed_at' => $completed ? now() : null,
                    ]);
                }
            }

            History::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => $isFollowUpRequest
                    ? 'Kho xuất vật tư bổ sung/đổi theo yêu cầu sau xuất kho'
                    : 'Kho xuất vật tư thật và chuyển Trưởng phòng Kỹ thuật phân công',
                'from_status' => $from,
                'to_status' => $isFollowUpRequest ? $from : 'assignment_pending',
                'note' => $data['issue_note'] ?? null,
                'meta' => [
                    'material_request_id' => (int) $materialRequest->id,
                    'request_kind' => $materialRequest->request_kind ?? 'standard',
                    'aftercare_request_id' => $materialRequest->aftercare_request_id,
                    'warehouse_ids' => $materialRequest->items->flatMap(fn ($item) => $item->allocations->pluck('warehouse_id'))->unique()->values()->all(),
                    'receiver_id' => (int) $data['receiver_id'],
                    'production_mode' => true,
                    'real_stock_deducted' => true,
                    'workflow_version' => 'v13',
                ],
            ]);
        }, 3);

        return back()->with('success', ($materialRequest->request_kind ?? 'standard') === 'standard'
            ? 'Đã xuất kho chính thức, trừ tồn theo FIFO và chuyển Trưởng phòng Kỹ thuật phân công.'
            : 'Đã xuất vật tư bổ sung/đổi, trừ tồn theo FIFO và cập nhật yêu cầu sau xuất kho.');
    }


    private function baseRequestQuery()
    {
        return MaterialRequest::query()
            ->with([
                'project:id,code,name,address,status,lead_technician_id,proposed_installation_at',
                'project.leadTechnician:id,name',
                'requester:id,name',
                'receiver:id,name',
                'items.product:id,name,sku,unit,is_serialized',
                'items.allocations.product:id,name,sku,unit,is_serialized',
                'items.allocations.warehouse:id,name',
            ])
            ->whereIn('status', ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'issued'])
            ->whereHas('items', fn ($items) => $items->whereNotNull('item_name')->where('item_name', '!=', ''))
            ->latest('id');
    }

    private function applyStateFilter($query, string $state): void
    {
        if ($state === 'issued') {
            $query->where('status', 'issued');
            return;
        }
        if ($state === 'pending_manager') {
            $query->where('status', 'pending_manager');
            return;
        }
        if ($state === 'waiting_replenishment') {
            $query->where('status', 'approved')->where('warehouse_status', 'waiting_replenishment');
            return;
        }
        if ($state === 'approved') {
            $query->where('status', 'approved')->where(function ($where): void {
                $where->whereNull('warehouse_status')->orWhere('warehouse_status', '!=', 'waiting_replenishment');
            });
            return;
        }
        if ($state === 'reserved') {
            $query->where('status', 'preparing')->where('warehouse_status', 'reserved');
            return;
        }

        $query->where('status', 'warehouse_check');
        if ($state === 'waiting_match') {
            $query->where(function ($where): void {
                $where->whereNull('warehouse_status')->orWhere('warehouse_status', 'waiting_match');
            });
            return;
        }
        $query->where('warehouse_status', $state);
    }


    private function stateOf(MaterialRequest $materialRequest): string
    {
        return match ((string) $materialRequest->status) {
            'issued' => 'issued',
            'preparing' => (string) $materialRequest->warehouse_status === 'reserved' ? 'reserved' : 'approved',
            'approved' => (string) $materialRequest->warehouse_status === 'waiting_replenishment'
                ? 'waiting_replenishment'
                : 'approved',
            'pending_manager' => 'pending_manager',
            default => $materialRequest->warehouse_status && isset(self::STATES[$materialRequest->warehouse_status])
                ? $materialRequest->warehouse_status
                : ($materialRequest->items->contains(fn ($item): bool => $item->allocations->isNotEmpty())
                    ? $this->allocationAggregateState($materialRequest)
                    : 'waiting_match'),
        };
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
            if (in_array((string) $allocation->status, ['shortage', 'transfer', 'waiting_purchase'], true)) {
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
            return 'waiting_match';
        }
        if ($allocations->contains(fn ($allocation): bool => $allocation->status === 'waiting_purchase')) {
            return 'waiting_purchase';
        }
        if ($allocations->contains(fn ($allocation): bool => $allocation->status === 'transfer')) {
            return 'transfer';
        }
        if ($allocations->contains(fn ($allocation): bool => $allocation->status === 'shortage')) {
            return 'shortage';
        }
        if ($allocations->every(fn ($allocation): bool => $allocation->status === 'reserved')) {
            return 'reserved';
        }
        if ($allocations->every(fn ($allocation): bool => $allocation->status === 'issued')) {
            return 'issued';
        }
        if ($allocations->every(fn ($allocation): bool => in_array($allocation->status, ['ready', 'reserved'], true))) {
            return 'ready';
        }
        return 'waiting_match';
    }

    private function estimatedUnitCost(int $productId, int $warehouseId): ?float
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
            return null;
        }

        $lots = DB::table('crm_product_stock_lots')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('received_at IS NULL')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get([
                'qty_remaining',
                'actual_cost_after_vat',
                'cost_after_vat',
                'cost_before_vat',
            ]);

        $weightedCost = 0.0;
        $weightedQty = 0.0;

        foreach ($lots as $lot) {
            $qty = max(0, (float) ($lot->qty_remaining ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $cost = (float) ($lot->actual_cost_after_vat ?? 0);
            if ($cost <= 0) {
                $cost = (float) ($lot->cost_after_vat ?? 0);
            }
            if ($cost <= 0) {
                $cost = (float) ($lot->cost_before_vat ?? 0);
            }
            if ($cost <= 0) {
                continue;
            }

            $weightedQty += $qty;
            $weightedCost += $qty * $cost;
        }

        return $weightedQty > 0 ? round($weightedCost / $weightedQty, 2) : null;
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
            ->lockForUpdate()
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
            ->whereIn('roles.name', ['ky_thuat', 'technical', 'technician', 'technical_staff', 'technical_leader', 'technical_manager'])
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    private function validateMappingPayload(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.warehouse_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.check_status' => ['required', Rule::in(['ready', 'shortage', 'transfer', 'waiting_purchase'])],
            'items.*.serial_unit_ids' => ['nullable', 'array'],
            'items.*.serial_unit_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'warehouse_note' => ['nullable', 'string', 'max:500'],
        ], [
            'items.required' => 'Chưa có dữ liệu đối chiếu vật tư.',
            'items.*.product_id.required' => 'Còn dòng chưa chọn sản phẩm/SKU.',
            'items.*.warehouse_id.required' => 'Còn dòng chưa chọn kho cấp.',
            'items.*.check_status.required' => 'Còn dòng chưa chọn kết quả đối chiếu.',
        ]);
    }

    private function persistMapping(Request $request, MaterialRequest $materialRequest, array $data): array
    {
        $materialRequest->loadMissing(['project', 'items.allocations']);
        if ($materialRequest->items->isEmpty()) {
            $this->failValidation('Phiếu chưa có dòng vật tư chính thức.');
        }

        $submittedItems = collect($data['items']);
        $usedWarehouseIds = collect();
        $approvedRecheck = (string) $materialRequest->status === 'approved';

        foreach ($materialRequest->items as $item) {
            $payload = $submittedItems->get((string) $item->id) ?? $submittedItems->get($item->id);
            if (! is_array($payload)) {
                $this->failValidation('Thiếu kết quả kiểm tra cho dòng “'.$item->item_name.'”.');
            }

            $productId = (int) Arr::get($payload, 'product_id');
            $warehouseId = (int) Arr::get($payload, 'warehouse_id');
            $quantity = (float) Arr::get($payload, 'quantity');
            $requestedQty = (float) $item->quantity;
            $requestedStatus = (string) Arr::get($payload, 'check_status', '');
            $lineNote = trim((string) Arr::get($payload, 'note', ''));

            if (abs($quantity - $requestedQty) > 0.0001) {
                $this->failValidation('Kho phải đối chiếu đúng số lượng Kỹ thuật yêu cầu ở dòng “'.$item->item_name.'”; không được sửa số lượng.');
            }

            $product = DB::table('crm_product_catalog')->where('id', $productId)->where('is_active', 1)->first();
            if (! $product) {
                $this->failValidation('Sản phẩm/SKU đã chọn cho dòng “'.$item->item_name.'” không tồn tại hoặc đã ngừng dùng.');
            }
            $warehouse = DB::table('crm_warehouses')->where('id', $warehouseId)->first();
            if (! $warehouse) {
                $this->failValidation('Kho cấp đã chọn cho dòng “'.$item->item_name.'” không tồn tại.');
            }

            $stock = $this->stockQty($productId, $warehouseId);
            $reservedOther = $this->reservedQty($productId, $warehouseId, $materialRequest->id);
            $available = max(0, $stock - $reservedOther);
            $isSerialized = (bool) ($product->is_serialized ?? false);
            $existingAllocation = $item->allocations->first();

            if (
                $approvedRecheck
                && $existingAllocation
                && (int) $existingAllocation->product_id !== $productId
            ) {
                $this->failValidation('Dòng “'.$item->item_name.'” đã được Admin/Quản lý duyệt theo SKU cũ. Kho không được tự đổi sản phẩm/model sau duyệt; hãy trả phiếu để phê duyệt lại nếu cần thay thế.');
            }

            $postedSerialIds = Arr::get($payload, 'serial_unit_ids');
            if (is_array($postedSerialIds)) {
                $selectedIds = collect($postedSerialIds)
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();
            } elseif (
                $existingAllocation
                && (int) $existingAllocation->product_id === $productId
                && (int) $existingAllocation->warehouse_id === $warehouseId
            ) {
                $selectedIds = collect($existingAllocation->selected_serial_unit_ids ?? [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();
            } else {
                $selectedIds = collect();
            }

            $serialCodes = collect();
            if ($isSerialized && $selectedIds->isNotEmpty()) {
                $validSerials = $this->validSerials($selectedIds->all(), $productId, $warehouseId, $materialRequest->id);
                if ($validSerials->count() !== $selectedIds->count()) {
                    $this->failValidation('Có serial của dòng “'.$item->item_name.'” không còn khả dụng trong kho đã chọn.');
                }
                $serialCodes = $validSerials->pluck('code');
            }

            if ($requestedStatus === 'transfer') {
                $allocationStatus = 'transfer';
            } elseif ($requestedStatus === 'waiting_purchase') {
                $allocationStatus = 'waiting_purchase';
            } elseif ($requestedStatus === 'shortage') {
                $allocationStatus = 'shortage';
            } elseif ($available < $requestedQty) {
                $allocationStatus = 'shortage';
            } else {
                $allocationStatus = 'ready';
            }

            if ($allocationStatus !== 'ready' && $lineNote === '') {
                $label = match ($allocationStatus) {
                    'transfer' => 'cần điều chuyển',
                    'waiting_purchase' => 'chờ nhập',
                    default => 'thiếu hoặc không có hàng',
                };
                $this->failValidation('Dòng “'.$item->item_name.'” đang '.$label.'. Bắt buộc ghi rõ phương án hoặc lý do tại ô ghi chú của dòng.');
            }

            $item->update(['product_id' => $productId]);

            $allocation = $existingAllocation ?: new MaterialAllocation(['material_item_id' => $item->id]);
            $allocation->fill([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'allocated_quantity' => $requestedQty,
                'reserved_quantity' => 0,
                'issued_quantity' => 0,
                'available_snapshot' => $available,
                'is_serialized' => $isSerialized,
                'selected_serial_unit_ids' => $selectedIds->all(),
                'selected_serial_codes' => $serialCodes->implode("\n") ?: null,
                'unit_cost' => $this->estimatedUnitCost($productId, $warehouseId),
                'status' => $allocationStatus,
                'allocated_by' => $request->user()->id,
                'reserved_by' => null,
                'issued_by' => null,
                'reserved_at' => null,
                'issued_at' => null,
                'note' => $lineNote !== '' ? $lineNote : null,
            ]);
            $allocation->material_item_id = $item->id;
            $allocation->save();

            $item->allocations()->where('id', '!=', $allocation->id)->delete();
            $usedWarehouseIds->push($warehouseId);
        }

        $materialRequest->refresh()->load('items.allocations');
        $aggregateState = $this->allocationAggregateState($materialRequest);
        $allRowsReady = $aggregateState === 'ready';
        $uniqueWarehouseIds = $usedWarehouseIds->unique()->values();
        $warehouseStatus = $approvedRecheck
            ? ($allRowsReady ? 'ready' : 'waiting_replenishment')
            : $aggregateState;

        $materialRequest->update([
            'warehouse_id' => $uniqueWarehouseIds->count() === 1 ? $uniqueWarehouseIds->first() : null,
            'status' => $approvedRecheck ? 'approved' : 'warehouse_check',
            'warehouse_status' => $warehouseStatus,
            'issue_note' => array_key_exists('warehouse_note', $data)
                ? ($data['warehouse_note'] ?: null)
                : $materialRequest->issue_note,
            'reserved_at' => null,
        ]);

        $this->history(
            $materialRequest,
            $approvedRecheck
                ? 'Kho cập nhật tồn sau điều hàng/nhập bổ sung cho phiếu đã duyệt'
                : 'Kho đối chiếu sản phẩm/SKU thật và kiểm tra tồn cho đề nghị Kỹ thuật',
            [
                'warehouse_ids' => $uniqueWarehouseIds->all(),
                'warehouse_status' => $warehouseStatus,
                'allocation_state' => $aggregateState,
                'all_rows_ready' => $allRowsReady,
                'approved_recheck' => $approvedRecheck,
                'warehouse_mapped_product' => true,
                'quantity_locked' => true,
                'workflow_version' => 'v13',
            ]
        );

        return [
            'warehouse_state' => $warehouseStatus,
            'allocation_state' => $aggregateState,
            'all_rows_ready' => $allRowsReady,
            'approved_recheck' => $approvedRecheck,
        ];
    }


    private function synchronizeAllocationStock(MaterialRequest $materialRequest): array
    {
        $materialRequest->loadMissing(['project', 'items.allocations']);

        $readyRows = 0;
        $shortRows = 0;
        $changedRows = 0;

        foreach ($materialRequest->items as $item) {
            $allocation = $item->allocations->first();
            if (! $allocation) {
                $shortRows++;
                continue;
            }

            $stock = $this->stockQty((int) $allocation->product_id, (int) $allocation->warehouse_id);
            $reservedOther = $this->reservedQty(
                (int) $allocation->product_id,
                (int) $allocation->warehouse_id,
                (int) $materialRequest->id
            );
            $available = max(0, $stock - $reservedOther);
            $needed = (float) ($allocation->allocated_quantity ?: $item->quantity);
            $oldStatus = (string) $allocation->status;

            if ($available >= $needed) {
                $newStatus = 'ready';
                $readyRows++;
            } else {
                $newStatus = in_array($oldStatus, ['transfer', 'waiting_purchase'], true)
                    ? $oldStatus
                    : 'shortage';
                $shortRows++;
            }

            if ($oldStatus !== $newStatus || abs((float) $allocation->available_snapshot - $available) > 0.0001) {
                $changedRows++;
            }

            $allocation->update([
                'available_snapshot' => $available,
                'unit_cost' => $this->estimatedUnitCost((int) $allocation->product_id, (int) $allocation->warehouse_id),
                'status' => $newStatus,
                'reserved_quantity' => 0,
                'reserved_by' => null,
                'reserved_at' => null,
            ]);
        }

        $materialRequest->refresh()->load('items.allocations');
        $allRowsReady = $materialRequest->items->isNotEmpty()
            && $materialRequest->items->every(
                fn ($item): bool => (string) optional($item->allocations->first())->status === 'ready'
            );

        $warehouseStatus = (string) $materialRequest->status === 'approved'
            ? ($allRowsReady ? 'ready' : 'waiting_replenishment')
            : $this->allocationAggregateState($materialRequest);

        $materialRequest->update([
            'warehouse_status' => $warehouseStatus,
            'reserved_at' => null,
        ]);

        return [
            'ready_rows' => $readyRows,
            'short_rows' => $shortRows,
            'changed_rows' => $changedRows,
            'all_rows_ready' => $allRowsReady,
            'warehouse_status' => $warehouseStatus,
        ];
    }

    private function failValidation(string $message, string $field = 'materials'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function assertEditable(MaterialRequest $materialRequest): void
    {
        $status = (string) $materialRequest->status;
        $canMap = $status === 'warehouse_check'
            || ($status === 'approved' && (string) $materialRequest->warehouse_status !== 'reserved');

        if (! $canMap) {
            $this->failValidation('Phiếu không còn ở bước Kho đối chiếu/cập nhật tồn. Hãy tải lại trang để xem trạng thái mới nhất.');
        }
        if ((string) $materialRequest->warehouse_status === 'reserved') {
            $this->failValidation('Hãy bỏ giữ hàng trước khi cập nhật đối chiếu tồn.');
        }
        if (! $materialRequest->items()->whereNotNull('item_name')->where('item_name', '!=', '')->exists()) {
            $this->failValidation('Phiếu chưa có vật tư đề xuất từ Kỹ thuật.');
        }
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

    private function wholeQuantity(float $quantity, string $label): int
    {
        $rounded = (int) round($quantity);
        if (abs($quantity - $rounded) > 0.00001) {
            $this->failValidation('Tồn kho hiện tại chỉ hỗ trợ số lượng nguyên. Hãy chỉnh số lượng của “'.$label.'”.');
        }
        return $rounded;
    }

    private function assertReservedSerials(array $ids, int $productId, int $warehouseId): void
    {
        if ($ids === [] || ! Schema::hasTable('crm_serial_unit_states')) {
            return;
        }

        $count = DB::table('crm_serial_unit_states as states')
            ->join('crm_serial_units as units', 'units.id', '=', 'states.serial_unit_id')
            ->whereIn('units.id', $ids)
            ->where('units.product_id', $productId)
            ->where('states.warehouse_id', $warehouseId)
            ->where('states.state', SerialUnitState::RESERVED->value)
            ->lockForUpdate()
            ->count();

        if ($count !== count($ids)) {
            $this->failValidation('Có serial không còn ở trạng thái đã giữ. Hãy kiểm tra lại trước khi xuất.');
        }
    }

    private function setSerialStates(array $ids, string $state, ?int $warehouseId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === [] || ! Schema::hasTable('crm_serial_unit_states')) {
            return;
        }

        $payload = ['state' => $state, 'warehouse_id' => $warehouseId, 'synced_at' => now()];
        if (Schema::hasColumn('crm_serial_unit_states', 'company_id') && $warehouseId) {
            $payload['company_id'] = DB::table('crm_warehouses')->where('id', $warehouseId)->value('company_id');
        }

        DB::table('crm_serial_unit_states')->whereIn('serial_unit_id', $ids)->update($payload);
    }

    private function authorizeWarehouse(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasAnyRole(['admin', 'warehouse', 'kho']) || $user->can('project-test.warehouse')), 403);
    }
}
