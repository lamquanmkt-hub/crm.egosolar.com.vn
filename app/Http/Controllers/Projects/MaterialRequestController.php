<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Enums\MaterialRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialRequest\StoreMaterialRequestRequest;
use App\Http\Requests\MaterialRequest\UpdateMaterialRequestRequest;
use App\Models\Inventory\Catalog\Product;
use App\Models\Projects\MaterialRequest;
use App\Models\Projects\Site;
use App\Services\InventoryService;
use App\Services\MaterialRequestService;
use App\Support\SchemaCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Quản lý đơn vật tư công trình: CRUD, luồng duyệt (admin/kho), tra cứu tồn kho, xuất Excel.
 */
class MaterialRequestController extends Controller
{
    /**
     * Khởi tạo controller với service tồn kho và service đơn vật tư.
     */
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly MaterialRequestService $materialRequestService
    ) {}

    /**
     * Danh sách đơn vật tư với bộ lọc từ khóa, trạng thái, công trình.
     */
    public function index(): View
    {
        $columns = [
            'id',
            'site_id',
            'created_by',
            'status',
            'created_at',
            'note',
        ];

        if (SchemaCache::hasColumn('material_requests', 'total_cost')) {
            $columns[] = 'total_cost';
        }

        if (SchemaCache::hasColumn('material_requests', 'warehouse_id')) {
            $columns[] = 'warehouse_id';
        }

        $query = MaterialRequest::query()
            ->select($columns)
            ->with(['site:id,name', 'creator:id,name', 'items.product', 'warehouse:id,name']);

        if (request()->filled('q')) {
            $q = trim((string) request('q'));

            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhere('note', 'like', "%{$q}%")
                    ->orWhereHas('site', function ($siteQ) use ($q) {
                        $siteQ->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if (request()->filled('status')) {
            $query->where('status', (string) request('status'));
        }

        if (request()->filled('site_id')) {
            $query->where('site_id', (int) request('site_id'));
        }

        if (request()->filled('source_type')) {
            $this->filterMaterialRequestSource($query, (string) request('source_type'));
        }

        $activeTab = (string) request('tab', 'construction');

        if (! in_array($activeTab, ['construction', 'warranty', 'dispatch'], true)) {
            $activeTab = 'construction';
        }

        $tabCounts = [];

        foreach (['construction', 'warranty', 'dispatch'] as $tab) {
            $tabQuery = clone $query;
            $this->applyMaterialRequestTab($tabQuery, $tab);
            $tabCounts[$tab] = $tabQuery->count();
        }

        $this->applyMaterialRequestTab($query, $activeTab);

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'pending_admin' => (clone $summaryQuery)->where('status', MaterialRequestStatus::SUBMITTED->value)->count(),
            'pending_warehouse' => (clone $summaryQuery)->where('status', MaterialRequestStatus::ADMIN_APPROVED->value)->count(),
            'exported' => (clone $summaryQuery)->where('status', MaterialRequestStatus::EXPORTED->value)->count(),
        ];

        $requests = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $linkedProposals = $this->linkedMaterialProposals($requests->getCollection()->pluck('id')->all());
        $requestSources = $requests->getCollection()->mapWithKeys(function (MaterialRequest $request) use ($linkedProposals): array {
            return [$request->id => $this->describeMaterialRequestSource($request, $linkedProposals[$request->id] ?? collect())];
        });
        $canViewCost = $this->canManageMaterialWarehouse(auth()->user());

        return view('material_requests.index', compact('requests', 'linkedProposals', 'requestSources', 'summary', 'canViewCost', 'activeTab', 'tabCounts'));
    }

    /**
     * Trang chi tiết đơn vật tư, làm tươi lại giá vốn trước khi hiển thị.
     */
    public function show(MaterialRequest $materialRequest): View
    {
        $mr = $materialRequest->loadMissing([
            'site',
            'items.product',
            'creator',
        ]);

        $this->materialRequestService->refreshCosts($mr);

        $mr->refresh()->loadMissing([
            'site',
            'items.product',
            'creator',
        ]);

        $linkedProposals = $this->linkedMaterialProposals([(int) $mr->id]);
        $proposalLines = $linkedProposals[(int) $mr->id] ?? collect();
        $requestSource = $this->describeMaterialRequestSource($mr, $proposalLines);
        $canViewCost = $this->canManageMaterialWarehouse(auth()->user());
        $canAllocate = $canViewCost && (string) $mr->status === MaterialRequestStatus::ADMIN_APPROVED->value;
        $materialHistory = $this->materialActivityHistory($mr, $proposalLines);
        $warehouseNames = collect($this->warehouseDropdown())->keyBy('id');
        $dispatchWarehouses = $warehouseNames->filter(
            fn ($warehouse): bool => str_contains(mb_strtoupper((string) $warehouse->name), 'EGO_VN')
        );
        $dispatchWarehouseId = (int) ($dispatchWarehouses->first()->id ?? 0);
        $inventoryByProduct = collect($this->inventoryRows())->groupBy('product_id');

        return view('material_requests.show', compact('mr', 'proposalLines', 'requestSource', 'canViewCost', 'canAllocate', 'materialHistory', 'warehouseNames', 'dispatchWarehouses', 'dispatchWarehouseId', 'inventoryByProduct'));
    }

    /**
     * Form tạo đơn vật tư mới kèm danh sách công trình, kho và tồn kho.
     */
    public function create(): View
    {
        return view('material_requests.create', [
            'sites' => $this->siteDropdown(),
            'warehouses' => $this->warehouseDropdown(),
            'inventories' => $this->inventoryRows(),
            'selectedSiteId' => request()->filled('site_id') ? (int) request('site_id') : null,
        ]);
    }

    /**
     * Tạo đơn vật tư mới ở trạng thái nháp.
     */
    public function store(StoreMaterialRequestRequest $request): RedirectResponse
    {
        $materialRequest = $this->materialRequestService->createDraft($request->validated());
        $this->recordMaterialActivity($materialRequest, 'created', 'Tạo yêu cầu vật tư.', null, (string) $materialRequest->status);

        return redirect()
            ->route('material-requests.index')
            ->with('success', 'Đã lưu đơn vật tư ở trạng thái nháp!');
    }

    /**
     * Form chỉnh sửa đơn vật tư kèm dữ liệu tồn kho và giá vốn đã lưu.
     */
    public function edit(MaterialRequest $materialRequest): View
    {
        $materialRequest->load('items.product');

        $this->materialRequestService->refreshCosts($materialRequest);

        $materialRequest->refresh()->load('items.product');

        return view('material_requests.edit', [
            'materialRequest' => $materialRequest,
            'sites' => $this->siteDropdown(),
            'products' => Cache::remember('products.dropdown', 3600, fn () => Product::select('id', 'name')->orderBy('name')->get()
            ),
            'warehouses' => $this->warehouseDropdown(),
            'productsInventories' => $this->inventoryRowsForEdit($materialRequest),
            'inventories' => $this->inventoryRowsForEdit($materialRequest),
        ]);
    }

    /**
     * Cập nhật đơn vật tư nháp và khôi phục lại giá vốn đã gửi để tránh lệch giá.
     */
    public function update(UpdateMaterialRequestRequest $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $before = $this->materialRequestAuditSnapshot($materialRequest);
        $statusBefore = (string) $materialRequest->status;
        $this->materialRequestService->updateDraft($materialRequest, $request->validated());

        // Giữ nguyên giá vốn đã lưu của dòng vật tư khi vào màn sửa.
        // Tránh trường hợp màn sửa tự lấy giá mới từ catalog/kho làm lệch với màn xem.
        $this->restoreSubmittedMaterialRequestCosts($materialRequest, (array) $request->input('items', []));
        $after = $this->materialRequestAuditSnapshot($materialRequest);
        $this->recordMaterialRequestEditHistory($materialRequest, $before, $after, $statusBefore, (string) $materialRequest->status);
        $this->recordMaterialActivity($materialRequest, 'updated', 'Cập nhật nội dung yêu cầu vật tư.', $statusBefore, (string) $materialRequest->status, ['before' => $before, 'after' => $after]);

        return redirect()
            ->route('material-requests.index')
            ->with('success', 'Đã cập nhật đơn vật tư!');
    }

    /**
     * Xóa đơn vật tư: admin xóa được mọi trạng thái trừ đã xuất kho (dọn kèm dữ liệu liên quan);
     * người khác chỉ xóa được đơn nháp.
     */
    public function destroy(MaterialRequest $materialRequest): RedirectResponse
    {
        /* EGO_MR_ADMIN_DESTROY_ANY_STATUS_START */
        $__egoMrUser = auth()->user();
        $__egoMrIsAdmin = false;

        if ($__egoMrUser) {
            if (method_exists($__egoMrUser, 'hasRole')) {
                $__egoMrIsAdmin = $__egoMrUser->hasRole('admin');
            } elseif (method_exists($__egoMrUser, 'hasAnyRole')) {
                $__egoMrIsAdmin = $__egoMrUser->hasAnyRole(['admin']);
            } elseif (isset($__egoMrUser->role)) {
                $__egoMrIsAdmin = (string) $__egoMrUser->role === 'admin';
            }
        }

        if ($__egoMrIsAdmin) {
            $__egoMrId = 0;

            if (isset($materialRequest)) {
                $__egoMrId = is_object($materialRequest)
                    ? (int) ($materialRequest->id ?? 0)
                    : (int) $materialRequest;
            }

            if (! $__egoMrId && isset($id)) {
                $__egoMrId = (int) $id;
            }

            if (! $__egoMrId) {
                $__routeMr = request()->route('materialRequest');
                $__egoMrId = is_object($__routeMr)
                    ? (int) ($__routeMr->id ?? 0)
                    : (int) $__routeMr;
            }

            abort_unless($__egoMrId > 0, 404);

            $__exists = DB::table('material_requests')
                ->where('id', $__egoMrId)
                ->exists();

            abort_unless($__exists, 404);

            $__egoMrRow = DB::table('material_requests')
                ->where('id', $__egoMrId)
                ->first();

            $__egoMrStatus = strtoupper(trim((string) ($__egoMrRow->status ?? '')));

            if (in_array($__egoMrStatus, ['EXPORTED', 'COMPLETED', 'COMPLETE', 'DONE', 'FINISHED', 'DA_XUAT_KHO', 'HOAN_THANH'], true)) {
                return back()->with('error', 'Đơn vật tư đã xuất kho nên không được xóa để tránh lệch tồn kho. Hãy tạo phiếu hoàn/điều chỉnh kho nếu cần.');
            }

            DB::transaction(function () use ($__egoMrId) {
                $__db = DB::class;
                $__schema = Schema::class;

                foreach (['material_request_items', 'material_request_edit_histories'] as $__table) {
                    if ($__schema::hasTable($__table) && $__schema::hasColumn($__table, 'material_request_id')) {
                        $__db::table($__table)
                            ->where('material_request_id', $__egoMrId)
                            ->delete();
                    }
                }

                if (
                    $__schema::hasTable('crm_stock_movements') &&
                    $__schema::hasColumn('crm_stock_movements', 'reason') &&
                    $__schema::hasColumn('crm_stock_movements', 'reference_id')
                ) {
                    $__db::table('crm_stock_movements')
                        ->where('reason', 'material_request_export')
                        ->where('reference_id', $__egoMrId)
                        ->delete();
                }

                if ($__schema::hasTable('crm_inventory_event_refs')) {
                    $__eventIds = $__db::table('crm_inventory_event_refs')
                        ->where(function ($q) use ($__egoMrId) {
                            $q->where('ref_id', $__egoMrId)
                                ->whereIn('ref_type', ['material_request', 'material_request_export']);
                        })
                        ->pluck('event_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    $__db::table('crm_inventory_event_refs')
                        ->where(function ($q) use ($__egoMrId) {
                            $q->where('ref_id', $__egoMrId)
                                ->whereIn('ref_type', ['material_request', 'material_request_export']);
                        })
                        ->delete();

                    if (! empty($__eventIds) && $__schema::hasTable('crm_inventory_events')) {
                        $__db::table('crm_inventory_events')
                            ->whereIn('id', $__eventIds)
                            ->delete();
                    }
                }

                $__db::table('material_requests')
                    ->where('id', $__egoMrId)
                    ->delete();
            });

            return redirect('/don-vat-tu')->with('success', 'Admin đã xóa đơn vật tư #'.$__egoMrId.'.');
        }
        /* EGO_MR_ADMIN_DESTROY_ANY_STATUS_END */

        if ((string) $materialRequest->status !== MaterialRequestStatus::DRAFT->value) {
            abort(403, 'Chỉ được xoá đơn vật tư ở trạng thái nháp.');
        }

        DB::transaction(function () use ($materialRequest) {
            $materialRequest->items()->delete();
            $materialRequest->delete();
        });

        return redirect()
            ->route('material-requests.index')
            ->with('success', 'Đã xoá đơn vật tư!');
    }

    /**
     * Gửi đơn vật tư cho admin duyệt.
     */
    public function submit(MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorize('submit', $materialRequest);

        $statusBefore = (string) $materialRequest->status;
        $this->materialRequestService->submit($materialRequest);
        $this->recordMaterialActivity($materialRequest->fresh(), 'submitted', 'Kỹ thuật gửi yêu cầu vật tư cho Admin duyệt.', $statusBefore, MaterialRequestStatus::SUBMITTED->value);

        return redirect()
            ->route('material-requests.index')
            ->with('success', 'Đã gửi admin duyệt đơn vật tư!');
    }

    /**
     * Admin duyệt đơn vật tư.
     */
    public function adminApprove(MaterialRequest $materialRequest): RedirectResponse
    {
        $statusBefore = (string) $materialRequest->status;
        $this->materialRequestService->adminApprove($materialRequest);
        $this->recordMaterialActivity($materialRequest->fresh(), 'admin_approved', 'Admin duyệt và chuyển yêu cầu cho Kho.', $statusBefore, MaterialRequestStatus::ADMIN_APPROVED->value);

        return redirect()
            ->route('material-requests.show', $materialRequest->id)
            ->with('success', 'Admin đã duyệt đơn vật tư!');
    }

    /**
     * Kho duyệt / xuất kho đơn vật tư và ghi giá vốn vào công trình.
     */
    public function warehouseApprove(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'allocations' => ['required', 'array'],
            'allocations.*.product_id' => ['nullable', 'integer'],
            'allocations.*.warehouse_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($request, $materialRequest, $validated): void {
            $this->applyWarehouseAllocations(
                $materialRequest,
                (array) $validated['allocations'],
                (int) $validated['warehouse_id'],
                true
            );

            $materialRequest->refresh()->load('items');

            if ($materialRequest->items->contains(fn ($item): bool => empty($item->product_id))) {
                throw ValidationException::withMessages([
                    'allocations' => 'Kho phải chọn sản phẩm có tồn cho tất cả dòng vật tư trước khi xuất.',
                ]);
            }

            $this->materialRequestService->warehouseApprove(
                $materialRequest,
                $request->input('costs', [])
            );

            $this->recordMaterialActivity(
                $materialRequest->fresh(),
                'warehouse_exported',
                'Kho xác nhận xuất vật tư và trừ tồn kho.',
                MaterialRequestStatus::ADMIN_APPROVED->value,
                MaterialRequestStatus::EXPORTED->value
            );
        });

        return redirect()
            ->route('material-requests.show', $materialRequest->id)
            ->with('success', 'Kho đã duyệt / xuất kho thành công và đã ghi giá vốn vào công trình!');
    }

    /**
     * Tìm SKU đang có tồn để Kho ghép với nhu cầu Kỹ thuật nhập tay.
     */
    public function searchWarehouseProducts(Request $request): JsonResponse
    {
        abort_unless($this->canManageMaterialWarehouse($request->user()), 403);

        $keyword = trim((string) $request->query('q', ''));
        $requestedProductIds = collect(explode(',', (string) $request->query('product_ids', '')))
            ->map(fn (string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(100)
            ->values();

        if (mb_strlen($keyword) < 2 && $requestedProductIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $warehouseId = (int) $request->query('warehouse_id', 0);
        $warehouses = collect($this->warehouseDropdown())->keyBy('id');
        $normalizedKeyword = mb_strtolower($keyword);

        $rows = collect($this->inventoryRows())
            ->filter(function (array $row) use ($normalizedKeyword, $requestedProductIds): bool {
                if ($requestedProductIds->isNotEmpty()) {
                    return $requestedProductIds->contains((int) ($row['product_id'] ?? 0));
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['product_name'] ?? ''),
                    (string) ($row['sku'] ?? ''),
                    (string) ($row['product_id'] ?? ''),
                ]));

                return str_contains($haystack, $normalizedKeyword);
            })
            ->groupBy('product_id')
            ->take($requestedProductIds->isNotEmpty() ? 100 : 20)
            ->map(function ($productRows) use ($warehouses, $warehouseId): array {
                $targetRow = $productRows->first(fn (array $row): bool => (int) ($row['warehouse_id'] ?? 0) === $warehouseId);
                $row = $targetRow ?? $productRows->first();
                $warehouse = $warehouses->get($warehouseId) ?? $warehouses->get((int) ($row['warehouse_id'] ?? 0));
                $stockByWarehouse = $productRows->map(function (array $stockRow) use ($warehouses): array {
                    $stockWarehouse = $warehouses->get((int) ($stockRow['warehouse_id'] ?? 0));

                    return [
                        'warehouse_id' => (int) ($stockRow['warehouse_id'] ?? 0),
                        'warehouse_name' => (string) ($stockWarehouse->name ?? 'Kho #'.($stockRow['warehouse_id'] ?? '')),
                        'quantity' => (float) ($stockRow['quantity'] ?? 0),
                    ];
                })->values();

                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'sku' => (string) ($row['sku'] ?? ''),
                    'warehouse_id' => $warehouseId ?: (int) ($row['warehouse_id'] ?? 0),
                    'warehouse_name' => (string) ($warehouse->name ?? 'Kho #'.($row['warehouse_id'] ?? '')),
                    'quantity' => (float) ($targetRow['quantity'] ?? 0),
                    'total_quantity' => (float) $productRows->sum('quantity'),
                    'stock_by_warehouse' => $stockByWarehouse,
                    'unit' => (string) ($row['unit'] ?? ''),
                    'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                ];
            })
            ->values();

        $catalog = Product::query()
            ->when($requestedProductIds->isNotEmpty(), function ($query) use ($requestedProductIds): void {
                $query->whereIn('id', $requestedProductIds->all());
            }, function ($query) use ($keyword): void {
                $query->where(function ($search) use ($keyword): void {
                    $search->where('name', 'like', '%'.$keyword.'%');

                    if (SchemaCache::hasColumn('crm_product_catalog', 'sku')) {
                        $search->orWhere('sku', 'like', '%'.$keyword.'%');
                    }

                    if (ctype_digit($keyword)) {
                        $search->orWhere('id', (int) $keyword);
                    }
                });
            })
            ->whereNotIn('id', $rows->pluck('product_id')->all())
            ->limit($requestedProductIds->isNotEmpty() ? 100 : 20)
            ->get();

        foreach ($catalog as $product) {
            $targetWarehouse = $warehouses->get($warehouseId);
            $rows->push([
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'warehouse_id' => $warehouseId,
                'warehouse_name' => (string) ($targetWarehouse->name ?? 'Kho EGO_VN'),
                'quantity' => 0,
                'total_quantity' => 0,
                'stock_by_warehouse' => [],
                'unit' => (string) ($product->unit ?? ''),
                'unit_cost' => (float) ($product->price_agent_vat ?? $product->price_agent ?? $product->price ?? 0),
            ]);
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * Kho lưu SKU đã ghép nhưng chưa xuất kho hoặc trừ tồn.
     */
    public function saveWarehouseAllocation(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        abort_unless($this->canManageMaterialWarehouse($request->user()), 403);
        abort_unless((string) $materialRequest->status === MaterialRequestStatus::ADMIN_APPROVED->value, 422);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'allocations' => ['required', 'array'],
            'allocations.*.product_id' => ['nullable', 'integer'],
            'allocations.*.warehouse_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($materialRequest, $validated): void {
            $changes = $this->applyWarehouseAllocations(
                $materialRequest,
                (array) $validated['allocations'],
                (int) $validated['warehouse_id']
            );

            if ($changes !== []) {
                $this->recordMaterialActivity(
                    $materialRequest->fresh(),
                    'warehouse_allocated',
                    'Kho ghép sản phẩm thực tế với yêu cầu vật tư.',
                    MaterialRequestStatus::ADMIN_APPROVED->value,
                    MaterialRequestStatus::ADMIN_APPROVED->value,
                    ['items' => $changes]
                );
            }
        });

        return redirect()
            ->route('material-requests.show', $materialRequest->id)
            ->with('success', 'Đã lưu sản phẩm Kho ghép cho yêu cầu vật tư. Chưa xuất và chưa trừ tồn.');
    }

    private function canManageMaterialWarehouse($user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'warehouse', 'kho']);
        }

        return method_exists($user, 'hasRole') && (
            $user->hasRole('admin') || $user->hasRole('warehouse') || $user->hasRole('kho')
        );
    }

    private function linkedMaterialProposals(array $requestIds): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));

        if ($requestIds === []
            || ! SchemaCache::hasTable('project_material_proposal_items')
            || ! SchemaCache::hasTable('project_material_proposals')) {
            return [];
        }

        $columns = [
            'i.id as proposal_item_id',
            'i.material_request_id',
            'i.proposal_id',
            'i.requested_name',
            'i.requested_spec',
            'i.requested_qty',
            'i.requested_unit',
            'i.selected_product_id',
            'i.selected_warehouse_id',
            'p.site_id',
            'p.status as proposal_status',
            'p.priority',
            'p.purpose',
            'p.note as proposal_note',
            'p.created_at as proposal_created_at',
            'creator.name as requester_name',
        ];

        foreach (['proposal_type', 'warranty_scope', 'admin_approved_at', 'admin_approved_by', 'needed_at'] as $column) {
            if (SchemaCache::hasColumn('project_material_proposals', $column)) {
                $columns[] = 'p.'.$column;
            }
        }

        $query = DB::table('project_material_proposal_items as i')
            ->join('project_material_proposals as p', 'p.id', '=', 'i.proposal_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'p.created_by')
            ->whereIn('i.material_request_id', $requestIds)
            ->select($columns)
            ->orderBy('i.material_request_id')
            ->orderBy('i.id');

        return $query->get()->groupBy('material_request_id')->all();
    }

    private function describeMaterialRequestSource(MaterialRequest $materialRequest, $proposalLines): array
    {
        $proposal = collect($proposalLines)->first();
        $proposalType = strtoupper((string) ($proposal->proposal_type ?? ''));
        $context = mb_strtolower(implode(' ', [
            (string) ($proposal->purpose ?? ''),
            (string) ($proposal->proposal_note ?? ''),
            (string) ($materialRequest->note ?? ''),
            (string) ($materialRequest->site->name ?? ''),
        ]));

        $isMaintenance = str_contains($context, 'bảo trì') || str_contains($context, 'bao tri');
        $isWarranty = str_contains($context, 'bảo hành') || str_contains($context, 'bao hanh');

        [$key, $label, $tone] = match (true) {
            $proposalType === 'REPLACEMENT' && $isMaintenance => ['maintenance', 'Bảo trì', 'amber'],
            $proposalType === 'REPLACEMENT', $isWarranty => ['warranty', 'Bảo hành / thay thế', 'purple'],
            $isMaintenance => ['maintenance', 'Bảo trì', 'amber'],
            $proposalType === 'ADDITIONAL' => ['additional', 'Vật tư phát sinh', 'orange'],
            default => ['initial', 'Công trình mới', 'teal'],
        };

        $scope = match ((string) ($proposal->warranty_scope ?? '')) {
            'in_scope' => 'Trong bảo hành',
            'out_of_scope' => 'Ngoài bảo hành',
            'pending_assessment' => 'Chờ xác định bảo hành',
            default => null,
        };

        return [
            'key' => $key,
            'label' => $label,
            'tone' => $tone,
            'scope' => $scope,
            'priority' => (string) ($proposal->priority ?? 'normal'),
            'requester_name' => (string) ($proposal->requester_name ?? $materialRequest->creator->name ?? 'Chưa xác định'),
            'proposal_id' => isset($proposal->proposal_id) ? (int) $proposal->proposal_id : null,
            'needed_at' => $proposal->needed_at ?? null,
        ];
    }

    private function filterMaterialRequestSource($query, string $sourceType): void
    {
        if (! in_array($sourceType, ['initial', 'additional', 'maintenance', 'warranty'], true)) {
            return;
        }

        if (! SchemaCache::hasTable('project_material_proposal_items')
            || ! SchemaCache::hasTable('project_material_proposals')) {
            return;
        }

        $query->whereExists(function ($subquery) use ($sourceType): void {
            $subquery->selectRaw('1')
                ->from('project_material_proposal_items as source_item')
                ->join('project_material_proposals as source_proposal', 'source_proposal.id', '=', 'source_item.proposal_id')
                ->whereColumn('source_item.material_request_id', 'material_requests.id');

            if ($sourceType === 'initial') {
                $subquery->where('source_proposal.proposal_type', 'INITIAL');
            } elseif ($sourceType === 'additional') {
                $subquery->where('source_proposal.proposal_type', 'ADDITIONAL');
            } else {
                $subquery->where('source_proposal.proposal_type', 'REPLACEMENT');

                if ($sourceType === 'maintenance') {
                    $subquery->where(function ($maintenance): void {
                        $maintenance->where('source_proposal.purpose', 'like', '%bảo trì%')
                            ->orWhere('source_proposal.note', 'like', '%bảo trì%');
                    });
                }
            }
        });
    }

    private function applyMaterialRequestTab($query, string $tab): void
    {
        if ($tab === 'dispatch') {
            $query->where('status', MaterialRequestStatus::EXPORTED->value);

            return;
        }

        $warrantyCondition = function ($scope): void {
            $terms = ['bảo hành', 'bao hanh', 'bảo trì', 'bao tri'];

            foreach ($terms as $term) {
                $scope->orWhereRaw("COALESCE(material_requests.note, '') LIKE ?", ['%'.$term.'%'])
                    ->orWhereHas('site', function ($site) use ($term): void {
                        $site->where('name', 'like', '%'.$term.'%');
                    });
            }

            if (SchemaCache::hasTable('project_material_proposal_items')
                && SchemaCache::hasTable('project_material_proposals')) {
                $scope->orWhereExists(function ($proposal): void {
                    $proposal->selectRaw('1')
                        ->from('project_material_proposal_items as tab_item')
                        ->join('project_material_proposals as tab_proposal', 'tab_proposal.id', '=', 'tab_item.proposal_id')
                        ->whereColumn('tab_item.material_request_id', 'material_requests.id')
                        ->where(function ($source): void {
                            if (SchemaCache::hasColumn('project_material_proposals', 'proposal_type')) {
                                $source->orWhere('tab_proposal.proposal_type', 'REPLACEMENT');
                            }

                            foreach (['bảo hành', 'bao hanh', 'bảo trì', 'bao tri'] as $term) {
                                $source->orWhere('tab_proposal.purpose', 'like', '%'.$term.'%')
                                    ->orWhere('tab_proposal.note', 'like', '%'.$term.'%');
                            }
                        });
                });
            }
        };

        if ($tab === 'warranty') {
            $query->where($warrantyCondition);

            return;
        }

        $query->whereNot($warrantyCondition);
    }

    private function applyWarehouseAllocations(MaterialRequest $materialRequest, array $allocations, int $selectedWarehouseId, bool $requireAvailableStock = false): array
    {
        if ($selectedWarehouseId <= 0) {
            throw ValidationException::withMessages(['warehouse_id' => 'Vui lòng chọn kho xuất vật tư.']);
        }

        $dispatchWarehouse = collect($this->warehouseDropdown())->first(
            fn ($warehouse): bool => (int) $warehouse->id === $selectedWarehouseId
                && str_contains(mb_strtoupper((string) $warehouse->name), 'EGO_VN')
        );

        if (! $dispatchWarehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Phiếu vật tư công trình chỉ được xuất từ kho EGO_VN.']);
        }

        $materialRequest = MaterialRequest::query()
            ->with(['items.product', 'site'])
            ->lockForUpdate()
            ->findOrFail($materialRequest->id);

        abort_unless((string) $materialRequest->status === MaterialRequestStatus::ADMIN_APPROVED->value, 422);

        $inventory = collect($this->inventoryRows());
        $options = $inventory->keyBy(
            fn (array $row): string => (int) $row['warehouse_id'].'|'.(int) $row['product_id']
        );
        $productOptions = $inventory->keyBy('product_id');
        $proposalItems = collect($this->linkedMaterialProposals([(int) $materialRequest->id])[$materialRequest->id] ?? []);
        $changes = [];
        $itemColumns = array_flip(Schema::getColumnListing('material_request_items'));
        $allocatedQuantities = [];

        foreach ($materialRequest->items->values() as $index => $item) {
            $selection = (array) ($allocations[$item->id] ?? []);
            $productId = (int) ($selection['product_id'] ?? $item->product_id ?? 0);
            $submittedWarehouseId = (int) ($selection['warehouse_id'] ?? 0);
            $warehouseId = $selectedWarehouseId;

            if ($productId > 0 && $submittedWarehouseId > 0 && $submittedWarehouseId !== $selectedWarehouseId) {
                throw ValidationException::withMessages([
                    'allocations.'.$item->id => 'Sản phẩm phải thuộc đúng kho xuất đã chọn.',
                ]);
            }

            if ($productId <= 0) {
                if (! empty($item->product_id)) {
                    $cleared = array_intersect_key([
                        'product_id' => null,
                        'warehouse_id' => null,
                        'unit_cost' => 0,
                        'line_total' => 0,
                        'updated_at' => now(),
                    ], $itemColumns);
                    DB::table('material_request_items')->where('id', $item->id)->update($cleared);

                    $proposalItem = $proposalItems->get($index);
                    if ($proposalItem) {
                        $proposalColumns = array_flip(Schema::getColumnListing('project_material_proposal_items'));
                        DB::table('project_material_proposal_items')
                            ->where('id', $proposalItem->proposal_item_id)
                            ->update(array_intersect_key([
                                'selected_product_id' => null,
                                'selected_warehouse_id' => null,
                                'selected_qty' => null,
                                'warehouse_selected_by' => null,
                                'warehouse_selected_at' => null,
                                'updated_at' => now(),
                            ], $proposalColumns));
                    }

                    $changes[] = ['request_item_id' => (int) $item->id, 'product_id' => null, 'warehouse_id' => $warehouseId];
                }

                continue;
            }

            $option = $options->get($warehouseId.'|'.$productId) ?? $productOptions->get($productId);

            if (! $option) {
                $catalogProduct = Product::query()->find($productId);

                if (! $catalogProduct) {
                    throw ValidationException::withMessages([
                        'allocations.'.$item->id => 'Sản phẩm được chọn không còn trong danh mục.',
                    ]);
                }

                $option = [
                    'product_name' => (string) $catalogProduct->name,
                    'unit' => (string) ($catalogProduct->unit ?? ''),
                    'unit_cost' => (float) ($catalogProduct->price_agent_vat ?? $catalogProduct->price_agent ?? $catalogProduct->price ?? 0),
                ];
            }

            $actualStock = DB::table('crm_product_stock')
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->get()
                ->sum('qty');

            $stockKey = $warehouseId.'|'.$productId;
            $requiredQuantity = (float) ($allocatedQuantities[$stockKey] ?? 0) + (float) $item->qty;

            if ($requireAvailableStock && (float) $actualStock < $requiredQuantity) {
                throw ValidationException::withMessages([
                    'allocations.'.$item->id => 'Kho EGO_VN chưa đủ '.$option['product_name'].': còn '.(float) $actualStock.', cần '.$requiredQuantity.'. Hãy điều hàng về kho trước khi xuất.',
                ]);
            }

            $allocatedQuantities[$stockKey] = $requiredQuantity;

            $unitCost = (float) ($option['unit_cost'] ?? 0);
            $payload = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'unit' => (string) ($option['unit'] ?: ($item->unit ?? '')),
                'unit_cost' => $unitCost,
                'line_total' => $unitCost * (float) $item->qty,
                'updated_at' => now(),
            ];
            DB::table('material_request_items')
                ->where('id', $item->id)
                ->update(array_intersect_key($payload, $itemColumns));

            $proposalItem = $proposalItems->get($index);
            if ($proposalItem) {
                $proposalPayload = [
                    'selected_product_id' => $productId,
                    'selected_warehouse_id' => $warehouseId,
                    'selected_qty' => (float) $item->qty,
                    'warehouse_status' => 'ALLOCATED',
                    'warehouse_selected_by' => auth()->id(),
                    'warehouse_selected_at' => now(),
                    'updated_at' => now(),
                ];
                $proposalColumns = array_flip(Schema::getColumnListing('project_material_proposal_items'));
                DB::table('project_material_proposal_items')
                    ->where('id', $proposalItem->proposal_item_id)
                    ->update(array_intersect_key($proposalPayload, $proposalColumns));
            }

            if ((int) $item->product_id !== $productId || (int) ($item->warehouse_id ?? $materialRequest->warehouse_id) !== $warehouseId) {
                $changes[] = [
                    'request_item_id' => (int) $item->id,
                    'requested_name' => (string) ($proposalItem->requested_name ?? $item->note ?? ''),
                    'product_id' => $productId,
                    'product_name' => (string) $option['product_name'],
                    'warehouse_id' => $warehouseId,
                    'quantity' => (float) $item->qty,
                ];
            }
        }

        if (SchemaCache::hasColumn('material_requests', 'warehouse_id')) {
            DB::table('material_requests')->where('id', $materialRequest->id)->update([
                'warehouse_id' => $selectedWarehouseId,
                'updated_at' => now(),
            ]);
        }

        $this->materialRequestService->refreshCosts($materialRequest->fresh(['items.product', 'site']));

        return $changes;
    }

    private function recordMaterialActivity(
        ?MaterialRequest $materialRequest,
        string $action,
        string $note,
        ?string $statusBefore,
        ?string $statusAfter,
        array $details = []
    ): void {
        if (! $materialRequest || ! SchemaCache::hasTable('material_request_activity_histories')) {
            return;
        }

        $user = auth()->user();
        $roles = '';
        if ($user && isset($user->roles)) {
            $roles = $user->roles->pluck('name')->join(', ');
        }

        DB::table('material_request_activity_histories')->insert([
            'material_request_id' => $materialRequest->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? $user?->email,
            'user_role' => $roles !== '' ? $roles : null,
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'details' => $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function materialActivityHistory(MaterialRequest $materialRequest, $proposalLines)
    {
        $history = collect();

        if (SchemaCache::hasTable('material_request_activity_histories')) {
            $history = $history->concat(
                DB::table('material_request_activity_histories')
                    ->where('material_request_id', $materialRequest->id)
                    ->orderByDesc('id')
                    ->get()
            );
        }

        if (SchemaCache::hasTable('material_request_edit_histories')) {
            $history = $history->concat(
                DB::table('material_request_edit_histories')
                    ->where('material_request_id', $materialRequest->id)
                    ->where('user_name', '!=', 'SYSTEM TEST')
                    ->get()
                    ->map(function ($item) {
                        $item->action = $item->action ?? 'updated';
                        $item->details = $item->changes ?? null;

                        return $item;
                    })
            );
        }

        $proposal = collect($proposalLines)->first();
        if ($proposal && ! empty($proposal->proposal_created_at)) {
            $history->push((object) [
                'action' => 'technical_requested',
                'note' => 'Kỹ thuật gửi đề xuất vật tư #'.$proposal->proposal_id.'.',
                'user_name' => $proposal->requester_name ?? 'Kỹ thuật',
                'user_role' => 'technical',
                'status_before' => null,
                'status_after' => MaterialRequestStatus::SUBMITTED->value,
                'details' => null,
                'created_at' => $proposal->proposal_created_at,
            ]);
        }

        if ($proposal && ! empty($proposal->admin_approved_at)) {
            $history->push((object) [
                'action' => 'admin_approved',
                'note' => 'Admin đã duyệt đề xuất và chuyển sang Kho.',
                'user_name' => 'Admin',
                'user_role' => 'admin',
                'status_before' => MaterialRequestStatus::SUBMITTED->value,
                'status_after' => MaterialRequestStatus::ADMIN_APPROVED->value,
                'details' => null,
                'created_at' => $proposal->admin_approved_at,
            ]);
        }

        if ($history->isEmpty()) {
            $history->push((object) [
                'action' => 'created',
                'note' => 'Tạo yêu cầu vật tư cho công trình.',
                'user_name' => $materialRequest->creator->name ?? 'Hệ thống',
                'user_role' => null,
                'status_before' => null,
                'status_after' => (string) $materialRequest->status,
                'details' => null,
                'created_at' => $materialRequest->created_at,
            ]);

            if ((string) $materialRequest->status === MaterialRequestStatus::EXPORTED->value) {
                $history->push((object) [
                    'action' => 'warehouse_exported',
                    'note' => 'Yêu cầu đã được Kho xuất và trừ tồn.',
                    'user_name' => 'Kho',
                    'user_role' => 'warehouse',
                    'status_before' => MaterialRequestStatus::ADMIN_APPROVED->value,
                    'status_after' => MaterialRequestStatus::EXPORTED->value,
                    'details' => null,
                    'created_at' => $materialRequest->updated_at ?? $materialRequest->created_at,
                ]);
            }
        }

        return $history->sortByDesc(fn ($item): int => strtotime((string) ($item->created_at ?? now())))->values();
    }

    /**
     * Trả JSON thông tin công trình: hợp đồng, đã thu, còn phải thu, chi phí vật tư.
     */
    public function siteInfo(Request $request)
    {
        $siteId = (int) $request->query('site_id');

        $site = Site::query()
            ->select($this->siteSelectColumns())
            ->find($siteId);

        if (! $site) {
            return response()->json([
                'ok' => false,
                'message' => 'Không tìm thấy công trình.',
            ], 404);
        }

        $contractAmount = SchemaCache::hasColumn('sites', 'contract_amount')
            ? (float) ($site->contract_amount ?? 0)
            : 0;

        $receivedAmount = 0;

        if (SchemaCache::hasTable('receipts') && SchemaCache::hasColumn('receipts', 'site_id')) {
            $receivedAmount = (float) DB::table('receipts')
                ->where('site_id', $site->id)
                ->sum('amount');
        }

        $materialCost = 0;

        if (
            SchemaCache::hasTable('material_requests')
            && SchemaCache::hasColumn('material_requests', 'site_id')
            && SchemaCache::hasColumn('material_requests', 'total_cost')
        ) {
            $materialCost = (float) DB::table('material_requests')
                ->where('site_id', $site->id)
                ->sum('total_cost');
        }

        return response()->json([
            'ok' => true,
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'address' => $site->address ?? null,
                'contact_name' => $site->contact_name ?? null,
                'contact_phone' => $site->contact_phone ?? null,
                'contract_amount' => $contractAmount,
                'received_amount' => $receivedAmount,
                'remaining_receivable' => max(0, $contractAmount - $receivedAmount),
                'material_cost' => $materialCost,
            ],
        ]);
    }

    /**
     * Danh sách công trình cho ô chọn (chỉ lấy các cột đang tồn tại).
     */
    private function siteDropdown()
    {
        return Site::query()
            ->select($this->siteSelectColumns())
            ->orderBy('name')
            ->get();
    }

    /**
     * Danh sách kho cho ô chọn, cache 1 giờ.
     */
    private function warehouseDropdown()
    {
        return Cache::remember('warehouses.dropdown', 3600, fn () => DB::table('crm_warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
        );
    }

    /**
     * Dữ liệu tồn kho theo kho/sản phẩm kèm đơn vị, giá vốn, VAT cho UI tạo đơn.
     */
    private function inventoryRows()
    {
        $stockRows = $this->inventoryService
            ->getProductsWithStock()
            ->values();

        $productIds = $stockRows
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $products = collect();

        if ($productIds->isNotEmpty()) {
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');
        }

        return $stockRows
            ->map(function ($r) use ($products) {
                $productId = (int) ($r->product_id ?? 0);
                $product = $products->get($productId);

                $unit = $product->unit
                    ?? $r->unit
                    ?? '';

                $unitCost = $this->stockUnitCost($product, $r);

                $vatPercent = (float) (
                    $product->cost_vat_percent
                    ?? $product->vat_percent
                    ?? $r->cost_vat_percent
                    ?? $r->vat_percent
                    ?? 0
                );

                return [
                    'warehouse_id' => (string) ($r->warehouse_id ?? ''),
                    'product_id' => $productId,
                    'product_name' => (string) ($r->product_name ?? $product->name ?? ''),
                    'sku' => (string) ($product->sku ?? $product->code ?? ''),
                    'quantity' => (float) ($r->quantity ?? 0),

                    // Dùng cho UI tạo đơn vật tư
                    'unit' => (string) $unit,
                    'unit_cost' => (float) $unitCost,
                    'vat_percent' => $vatPercent,
                    'cost_label' => number_format((float) $unitCost, 0, ',', '.').' đ',
                ];
            })
            ->values();
    }

    /**
     * Dữ liệu tồn kho cho màn sửa đơn: ưu tiên giá vốn đã lưu, bổ sung dòng cho sản phẩm thiếu.
     */
    private function inventoryRowsForEdit(MaterialRequest $materialRequest)
    {
        $rows = collect($this->inventoryRows())->values();

        $materialRequest->loadMissing('items');

        $productIds = collect($materialRequest->items ?? [])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return $rows;
        }

        $warehouseIds = collect($this->warehouseDropdown())
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $requestWarehouseId = (string) ($materialRequest->warehouse_id ?? '');

        if ($requestWarehouseId !== '' && ! $warehouseIds->contains($requestWarehouseId)) {
            $warehouseIds->push($requestWarehouseId);
        }

        if ($warehouseIds->isEmpty()) {
            return $rows;
        }

        $products = collect();

        if (SchemaCache::hasTable('crm_product_catalog')) {
            $products = DB::table('crm_product_catalog')
                ->whereIn('id', $productIds->all())
                ->get()
                ->keyBy('id');
        }

        $itemsByProduct = collect($materialRequest->items ?? [])
            ->filter(fn ($item) => ! empty($item->product_id))
            ->keyBy(fn ($item) => (int) $item->product_id);

        $itemsByProductId = collect($materialRequest->items ?? [])
            ->filter(fn ($item) => ! empty($item->product_id))
            ->keyBy(fn ($item) => (int) $item->product_id);

        $rows = $rows
            ->map(function ($row) use ($itemsByProductId) {
                $productId = (int) ($row['product_id'] ?? 0);
                $savedItem = $itemsByProductId->get($productId);

                if ($savedItem && (float) ($savedItem->unit_cost ?? 0) > 0) {
                    $savedUnitCost = (float) ($savedItem->unit_cost ?? 0);
                    $row['unit_cost'] = $savedUnitCost;
                    $row['cost_label'] = number_format($savedUnitCost, 0, ',', '.').' đ';

                    if (isset($savedItem->unit) && (string) $savedItem->unit !== '') {
                        $row['unit'] = (string) $savedItem->unit;
                    }

                    if (isset($savedItem->vat_percent)) {
                        $row['vat_percent'] = (float) $savedItem->vat_percent;
                    }
                }

                return $row;
            })
            ->values();

        $existingKeys = $rows
            ->mapWithKeys(function ($row) {
                return [((string) ($row['warehouse_id'] ?? '')).'|'.((int) ($row['product_id'] ?? 0)) => true];
            })
            ->all();

        foreach ($warehouseIds as $warehouseId) {
            foreach ($productIds as $productId) {
                $key = (string) $warehouseId.'|'.(int) $productId;

                if (isset($existingKeys[$key])) {
                    continue;
                }

                $product = $products->get($productId);
                $item = $itemsByProduct->get($productId);

                $productName = is_object($product) ? (string) ($product->name ?? '') : '';
                $productUnit = is_object($product) ? (string) ($product->unit ?? '') : '';
                $productVat = is_object($product) ? (float) ($product->cost_vat_percent ?? $product->vat_percent ?? 0) : 0.0;

                $unitCost = (float) ($item->unit_cost ?? 0);

                if ($unitCost <= 0) {
                    $unitCost = $this->stockUnitCost(is_object($product) ? $product : null, (object) []);
                }

                $vatPercent = (float) (
                    $item->vat_percent
                    ?? $productVat
                    ?? 0
                );

                $unit = (string) (
                    $item->unit
                    ?? $productUnit
                    ?? ''
                );

                $rows->push([
                    'warehouse_id' => (string) $warehouseId,
                    'product_id' => (int) $productId,
                    'product_name' => $productName !== '' ? $productName : ('Sản phẩm #'.$productId),
                    'quantity' => 0,
                    'unit' => $unit,
                    'unit_cost' => (float) $unitCost,
                    'vat_percent' => $vatPercent,
                    'cost_label' => number_format((float) $unitCost, 0, ',', '.').' đ',
                ]);

                $existingKeys[$key] = true;
            }
        }

        return $rows->values();
    }

    /**
     * Tính giá vốn vật tư: ưu tiên price_agent_vat, rồi price_agent, cuối cùng là price.
     */
    private function stockUnitCost(?object $product, object $stockRow): float
    {
        /*
        |--------------------------------------------------------------------------
        | Giá vốn vật tư trong kho
        |--------------------------------------------------------------------------
        | Ưu tiên lấy từ catalog sản phẩm:
        | 1. price_agent_vat
        | 2. price_agent
        | 3. price
        | Sau đó fallback về dữ liệu stock row nếu InventoryService có trả thêm giá.
        */
        $priceAgentVat = (float) ($product->price_agent_vat ?? $stockRow->price_agent_vat ?? 0);
        $priceAgent = (float) ($product->price_agent ?? $stockRow->price_agent ?? 0);
        $price = (float) ($product->price ?? $stockRow->price ?? 0);

        if ($priceAgentVat > 0) {
            return $priceAgentVat;
        }

        if ($priceAgent > 0) {
            return $priceAgent;
        }

        return $price;
    }

    /**
     * Danh sách cột của bảng sites cần select, lọc theo cột thực tế đang tồn tại.
     */
    private function siteSelectColumns(): array
    {
        $want = [
            'id',
            'name',
            'address',
            'contact_name',
            'contact_phone',
            'contract_amount',
        ];

        return array_values(array_filter($want, function ($column) {
            return SchemaCache::hasColumn('sites', $column);
        }));
    }

    /* EGO_MR_EDIT_HELPERS_START */
    /**
     * Kiểm tra quyền sửa đơn vật tư theo role và trạng thái (admin/kho/kỹ thuật).
     */
    private function canEditMaterialRequest(MaterialRequest $materialRequest, $user): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        $status = (string) ($materialRequest->status ?? '');

        if ($user->hasRole('admin')) {
            return in_array($status, [
                MaterialRequestStatus::DRAFT->value,
                MaterialRequestStatus::SUBMITTED->value,
                MaterialRequestStatus::ADMIN_APPROVED->value,
            ], true);
        }

        if ($user->hasRole('warehouse') || $user->hasRole('kho')) {
            return $status === MaterialRequestStatus::ADMIN_APPROVED->value;
        }

        if ($user->hasRole('technical') || $user->hasRole('ky_thuat')) {
            return $status === MaterialRequestStatus::DRAFT->value;
        }

        return false;
    }

    /**
     * Chụp trạng thái đơn vật tư (thông tin đơn + các dòng vật tư) để ghi lịch sử chỉnh sửa.
     */
    private function materialRequestAuditSnapshot(MaterialRequest $materialRequest): array
    {
        $materialRequest->refresh()->loadMissing(['site', 'items.product']);

        return [
            'request' => [
                'id' => $materialRequest->id,
                'site_id' => $materialRequest->site_id ?? null,
                'site_name' => $materialRequest->site->name ?? null,
                'warehouse_id' => $materialRequest->warehouse_id ?? null,
                'status' => (string) ($materialRequest->status ?? ''),
                'note' => $materialRequest->note ?? null,
                'total_cost' => $materialRequest->total_cost ?? null,
            ],
            'items' => $materialRequest->items
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? null,
                        'qty' => $item->qty,
                        'unit' => $item->unit,
                        'unit_cost' => $item->unit_cost,
                        'vat_percent' => $item->vat_percent,
                        'line_total' => $item->line_total,
                        'note' => $item->note,
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Ghi lịch sử chỉnh sửa đơn vật tư (trước/sau, người sửa, trạng thái) nếu có thay đổi.
     */
    private function recordMaterialRequestEditHistory(
        MaterialRequest $materialRequest,
        array $before,
        array $after,
        string $statusBefore,
        string $statusAfter
    ): void {
        try {
            if ($before == $after) {
                return;
            }

            if (! SchemaCache::hasTable('material_request_edit_histories')) {
                return;
            }

            $user = auth()->user();

            $userRole = null;

            try {
                if ($user && isset($user->roles)) {
                    $userRole = $user->roles->pluck('name')->join(', ');
                }
            } catch (\Throwable $e) {
                $userRole = null;
            }

            $payload = [
                'material_request_id' => $materialRequest->id,
                'user_id' => $user ? $user->id : null,
                'user_name' => $user ? ($user->name ?? $user->email ?? null) : null,
                'user_role' => $userRole,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'changes' => json_encode([
                    'before' => $before,
                    'after' => $after,
                ], JSON_UNESCAPED_UNICODE),
                'note' => 'Chỉnh sửa đơn vật tư',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $columns = DB::getSchemaBuilder()->getColumnListing('material_request_edit_histories');
            $payload = array_intersect_key($payload, array_flip($columns));

            DB::table('material_request_edit_histories')->insert($payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }
    /* EGO_MR_EDIT_HELPERS_END */

    /**
     * Khôi phục giá vốn/VAT theo dữ liệu form gửi lên cho các dòng vật tư và tính lại tổng chi phí.
     */
    private function restoreSubmittedMaterialRequestCosts(MaterialRequest $materialRequest, array $submittedItems): void
    {
        try {
            if (
                ! SchemaCache::hasTable('material_request_items')
                || ! SchemaCache::hasColumn('material_request_items', 'material_request_id')
                || ! SchemaCache::hasColumn('material_request_items', 'product_id')
                || ! SchemaCache::hasColumn('material_request_items', 'unit_cost')
            ) {
                return;
            }

            $submittedByProduct = collect($submittedItems)
                ->filter(fn ($row) => ! empty($row['product_id']))
                ->mapWithKeys(function ($row) {
                    return [
                        (int) $row['product_id'] => [
                            'unit_cost' => isset($row['unit_cost']) ? (float) $row['unit_cost'] : null,
                            'vat_percent' => isset($row['vat_percent']) ? (float) $row['vat_percent'] : null,
                        ],
                    ];
                });

            if ($submittedByProduct->isEmpty()) {
                return;
            }

            $items = DB::table('material_request_items')
                ->where('material_request_id', $materialRequest->id)
                ->whereIn('product_id', $submittedByProduct->keys()->all())
                ->get();

            foreach ($items as $item) {
                $meta = $submittedByProduct->get((int) $item->product_id);

                if (! $meta || $meta['unit_cost'] === null || $meta['unit_cost'] <= 0) {
                    continue;
                }

                $qty = (float) ($item->qty ?? 0);
                $unitCost = (float) $meta['unit_cost'];

                $payload = [
                    'unit_cost' => $unitCost,
                ];

                if (SchemaCache::hasColumn('material_request_items', 'line_total')) {
                    $payload['line_total'] = $qty * $unitCost;
                }

                if (
                    $meta['vat_percent'] !== null
                    && SchemaCache::hasColumn('material_request_items', 'vat_percent')
                ) {
                    $payload['vat_percent'] = (float) $meta['vat_percent'];
                }

                if (SchemaCache::hasColumn('material_request_items', 'updated_at')) {
                    $payload['updated_at'] = now();
                }

                DB::table('material_request_items')
                    ->where('id', $item->id)
                    ->update($payload);
            }

            if (
                SchemaCache::hasColumn('material_requests', 'total_cost')
                && SchemaCache::hasColumn('material_request_items', 'line_total')
            ) {
                $total = (float) DB::table('material_request_items')
                    ->where('material_request_id', $materialRequest->id)
                    ->sum('line_total');

                DB::table('material_requests')
                    ->where('id', $materialRequest->id)
                    ->update([
                        'total_cost' => $total,
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Xuất đơn vật tư ra file Excel (.xls dạng HTML) với đầy đủ nhóm vật tư và tổng kết.
     */
    public function exportDispatchPdf(MaterialRequest $materialRequest)
    {
        abort_unless($this->canManageMaterialWarehouse(auth()->user()), 403);
        abort_unless((string) $materialRequest->status === MaterialRequestStatus::EXPORTED->value, 422, 'Phiếu chưa xuất kho.');

        $mr = $materialRequest->loadMissing(['site', 'items.product', 'creator', 'warehouse']);
        $linkedProposals = $this->linkedMaterialProposals([(int) $mr->id]);
        $requestSource = $this->describeMaterialRequestSource($mr, $linkedProposals[(int) $mr->id] ?? collect());
        $voucherCode = 'PXK-'.str_pad((string) $mr->id, 5, '0', STR_PAD_LEFT);
        $issuedAt = $mr->updated_at ?? $mr->created_at ?? now();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('material_requests.dispatch-note-pdf', compact(
            'mr',
            'requestSource',
            'voucherCode',
            'issuedAt'
        ))->setPaper('a4')->download('phieu-xuat-kho-'.$voucherCode.'.pdf');
    }

    public function exportDispatchExcel(MaterialRequest $materialRequest)
    {
        abort_unless($this->canManageMaterialWarehouse(auth()->user()), 403);
        abort_unless((string) $materialRequest->status === MaterialRequestStatus::EXPORTED->value, 422, 'Phiếu chưa xuất kho.');

        $mr = $materialRequest->loadMissing(['site', 'items.product', 'creator', 'warehouse']);
        $voucherCode = 'PXK-'.str_pad((string) $mr->id, 5, '0', STR_PAD_LEFT);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Phieu xuat kho');
        $sheet->mergeCells('A1:G1')->setCellValue('A1', 'EGO SOLAR - PHIẾU XUẤT KHO');
        $sheet->mergeCells('A2:G2')->setCellValue('A2', 'Số phiếu: '.$voucherCode);
        $sheet->mergeCells('A4:G4')->setCellValue('A4', 'Công trình: '.($mr->site->name ?? '—'));
        $sheet->mergeCells('A5:G5')->setCellValue('A5', 'Kho xuất: '.($mr->warehouse->name ?? 'EGO_VN'));
        $sheet->mergeCells('A6:G6')->setCellValue('A6', 'Người đề xuất: '.($mr->creator->name ?? '—'));
        $sheet->mergeCells('A7:G7')->setCellValue('A7', 'Ngày xuất: '.optional($mr->updated_at ?? $mr->created_at)->format('d/m/Y H:i'));

        foreach (['STT', 'Sản phẩm thực xuất', 'Mã SKU', 'Số lượng', 'ĐVT', 'Giá vốn', 'Thành tiền'] as $index => $heading) {
            $sheet->setCellValue(chr(65 + $index).'9', $heading);
        }

        $row = 10;
        $total = 0;

        foreach ($mr->items as $index => $item) {
            $quantity = (float) ($item->qty ?? 0);
            $unitCost = (float) ($item->unit_cost ?? 0);
            $amount = (float) ($item->line_total ?? ($quantity * $unitCost));
            $total += $amount;

            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValue('B'.$row, $item->product->name ?? 'Sản phẩm #'.$item->product_id);
            $sheet->setCellValue('C'.$row, $item->product->sku ?? '#'.$item->product_id);
            $sheet->setCellValue('D'.$row, $quantity);
            $sheet->setCellValue('E'.$row, $item->unit ?? $item->product->unit ?? '—');
            $sheet->setCellValue('F'.$row, $unitCost);
            $sheet->setCellValue('G'.$row, $amount);
            $row++;
        }

        $sheet->mergeCells('A'.$row.':F'.$row)->setCellValue('A'.$row, 'TỔNG GIÁ TRỊ XUẤT KHO');
        $sheet->setCellValue('G'.$row, $total);
        $sheet->getStyle('A1:G2')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16)->getColor()->setARGB('FF087C64');
        $sheet->getStyle('A9:G9')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A9:G9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF087C64');
        $sheet->getStyle('F10:G'.$row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);

        foreach (['A' => 8, 'B' => 54, 'C' => 18, 'D' => 14, 'E' => 12, 'F' => 20, 'G' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A10');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'phieu-xuat-kho-'.$voucherCode.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Xuất đơn vật tư ra file Excel (.xls dạng HTML) với đầy đủ nhóm vật tư và tổng kết.
     */
    public function exportExcel(MaterialRequest $materialRequest)
    {
        $mr = $materialRequest->loadMissing([
            'site',
            'items.product',
            'creator',
        ]);

        $fileName = 'don-vat-tu-'.$mr->id.'-'.now()->format('Ymd-His').'.xls';

        $e = function ($value) {
            return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $money = function ($value) {
            return number_format((float) ($value ?? 0), 0, ',', '.').' đ';
        };

        $qtyFormat = function ($value) {
            $value = (float) ($value ?? 0);

            if (floor($value) == $value) {
                return number_format($value, 0, ',', '.');
            }

            return number_format($value, 2, ',', '.');
        };

        $stripPrefix = function ($note) {
            $note = (string) $note;
            $note = preg_replace('/^\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u', '', $note);

            return trim($note);
        };

        $detectGroup = function ($item) {
            $note = (string) ($item->note ?? '');

            if (! empty($item->product_id)) {
                if (str_contains($note, '[Vật tư phụ - Trong kho]')) {
                    return 'Vật tư phụ - Trong kho';
                }

                return 'Thiết bị chính - Trong kho';
            }

            if (str_contains($note, '[Thiết bị chính - Ngoài kho]')) {
                return 'Thiết bị chính - Ngoài kho';
            }

            return 'Vật tư phụ - Ngoài kho';
        };

        $externalName = function ($item) use ($stripPrefix) {
            $note = $stripPrefix((string) ($item->note ?? ''));

            $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
                return $x !== '';
            }));

            return $parts[0] ?? 'Vật tư ngoài kho';
        };

        $externalUnitFromNote = function ($item) {
            $note = (string) ($item->note ?? '');

            $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
                return $x !== '';
            }));

            foreach ($parts as $part) {
                if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $m)) {
                    return trim($m[1] ?? '');
                }
            }

            return '';
        };

        $statusLabels = [
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Chờ admin duyệt',
            'ADMIN_APPROVED' => 'Chờ kho duyệt',
            'EXPORTED' => 'Đã xuất kho',
            'COMPLETED' => 'Hoàn tất',
            'REJECTED' => 'Từ chối',
        ];

        $statusText = $statusLabels[(string) $mr->status] ?? (string) $mr->status;
        $createdAt = $mr->created_at ? $mr->created_at->format('d/m/Y H:i') : '';

        $totalQty = 0;
        $totalAmount = 0;
        $inStockCount = 0;
        $outStockCount = 0;
        $rowsHtml = '';

        foreach ($mr->items as $index => $item) {
            $product = $item->product;

            $qty = (float) ($item->qty ?? 0);
            $unitCost = (float) ($item->unit_cost ?? 0);
            $vatPercent = (float) ($item->vat_percent ?? 0);
            $lineTotal = (float) ($item->line_total ?? 0);

            if ($lineTotal <= 0) {
                $lineTotal = $qty * $unitCost;
            }

            $rawNote = (string) ($item->note ?? '');
            $cleanNote = $stripPrefix($rawNote);
            $isInStock = ! empty($item->product_id);

            if ($isInStock) {
                $inStockCount++;
                $name = $product->name ?? '—';
                $unit = $item->unit ?: ($product->unit ?? '');
                $typeClass = 'stock';
            } else {
                $outStockCount++;
                $name = $externalName($item);
                $unit = $item->unit ?: $externalUnitFromNote($item);
                $typeClass = 'external';
            }

            $totalQty += $qty;
            $totalAmount += $lineTotal;

            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td class="center">'.($index + 1).'</td>';
            $rowsHtml .= '<td class="text-bold">'.$e($name).'</td>';
            $rowsHtml .= '<td class="center">'.$e($item->product_id ?? '').'</td>';
            $rowsHtml .= '<td class="right">'.$e($qtyFormat($qty)).'</td>';
            $rowsHtml .= '<td class="center">'.$e($unit).'</td>';
            $rowsHtml .= '<td class="right money">'.$e($money($unitCost)).'</td>';
            $rowsHtml .= '<td class="right">'.$e($qtyFormat($vatPercent)).'%</td>';
            $rowsHtml .= '<td class="right money total">'.$e($money($lineTotal)).'</td>';
            $rowsHtml .= '<td class="'.$typeClass.'">'.$e($detectGroup($item)).'</td>';
            $rowsHtml .= '<td>'.$e($cleanNote).'</td>';
            $rowsHtml .= '</tr>';
        }

        $html = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Don vat tu</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #111827;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td, th {
            border: 1px solid #cbd5e1;
            padding: 7px;
            vertical-align: middle;
        }

        .title {
            background: #0f766e;
            color: #ffffff;
            font-size: 20pt;
            font-weight: bold;
            text-align: center;
            padding: 14px;
        }

        .subtitle {
            background: #ccfbf1;
            color: #134e4a;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            padding: 9px;
        }

        .section {
            background: #e0f2fe;
            color: #075985;
            font-weight: bold;
            font-size: 12pt;
        }

        .label {
            background: #f1f5f9;
            color: #334155;
            font-weight: bold;
            width: 15%;
        }

        .value {
            background: #ffffff;
            font-weight: bold;
        }

        .header th {
            background: #115e59;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .text-bold {
            font-weight: bold;
        }

        .money {
            color: #047857;
            font-weight: bold;
        }

        .total {
            color: #111827;
            font-weight: bold;
        }

        .stock {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: bold;
            text-align: center;
        }

        .external {
            background: #fef3c7;
            color: #b45309;
            font-weight: bold;
            text-align: center;
        }

        .summary-label {
            background: #f8fafc;
            font-weight: bold;
            color: #334155;
        }

        .summary-value {
            background: #ffffff;
            font-weight: bold;
            text-align: right;
        }

        .grand-total-label {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            font-size: 12pt;
        }

        .grand-total-value {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            font-size: 12pt;
            text-align: right;
        }
    </style>
</head>
<body>
    <table>
        <colgroup>
            <col style="width:50px">
            <col style="width:330px">
            <col style="width:80px">
            <col style="width:100px">
            <col style="width:80px">
            <col style="width:130px">
            <col style="width:80px">
            <col style="width:140px">
            <col style="width:190px">
            <col style="width:260px">
        </colgroup>

        <tr>
            <td colspan="10" class="title">ĐƠN VẬT TƯ #'.$e($mr->id).'</td>
        </tr>

        <tr>
            <td colspan="10" class="subtitle">Công trình: '.$e($mr->site->name ?? '').'</td>
        </tr>

        <tr>
            <td colspan="2" class="label">Mã công trình</td>
            <td colspan="3" class="value">'.$e($mr->site_id ?? '').'</td>
            <td colspan="2" class="label">Trạng thái</td>
            <td colspan="3" class="value">'.$e($statusText).'</td>
        </tr>

        <tr>
            <td colspan="2" class="label">Raw</td>
            <td colspan="3" class="value">'.$e((string) $mr->status).'</td>
            <td colspan="2" class="label">Ngày tạo</td>
            <td colspan="3" class="value">'.$e($createdAt).'</td>
        </tr>

        <tr>
            <td colspan="2" class="label">Người tạo</td>
            <td colspan="3" class="value">'.$e($mr->creator->name ?? '').'</td>
            <td colspan="2" class="label">Ghi chú</td>
            <td colspan="3" class="value">'.$e($mr->note ?? '').'</td>
        </tr>

        <tr>
            <td colspan="10" class="section">DANH SÁCH VẬT TƯ / THIẾT BỊ</td>
        </tr>

        <tr class="header">
            <th>STT</th>
            <th>Vật tư</th>
            <th>Mã SP</th>
            <th>Số lượng</th>
            <th>ĐVT</th>
            <th>Giá vốn</th>
            <th>VAT %</th>
            <th>Thành tiền</th>
            <th>Nhóm</th>
            <th>Ghi chú</th>
        </tr>

        '.$rowsHtml.'

        <tr>
            <td colspan="10" class="section">TÓM TẮT</td>
        </tr>

        <tr>
            <td colspan="8" class="summary-label">Tổng số dòng</td>
            <td colspan="2" class="summary-value">'.$e($mr->items->count()).'</td>
        </tr>

        <tr>
            <td colspan="8" class="summary-label">Trong kho</td>
            <td colspan="2" class="summary-value">'.$e($inStockCount).'</td>
        </tr>

        <tr>
            <td colspan="8" class="summary-label">Ngoài kho</td>
            <td colspan="2" class="summary-value">'.$e($outStockCount).'</td>
        </tr>

        <tr>
            <td colspan="8" class="summary-label">Tổng số lượng</td>
            <td colspan="2" class="summary-value">'.$e($qtyFormat($totalQty)).'</td>
        </tr>

        <tr>
            <td colspan="8" class="grand-total-label">Tổng thành tiền</td>
            <td colspan="2" class="grand-total-value">'.$e($money($totalAmount)).'</td>
        </tr>
    </table>
</body>
</html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
