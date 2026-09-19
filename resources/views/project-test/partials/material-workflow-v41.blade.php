{{-- EGO MATERIAL FASTFLOW V9: giao diện gọn, đối chiếu theo dòng, xử lý sau xuất kho. --}}
<link rel="stylesheet" href="{{ asset('css/project-material-fastflow-v9.css') }}?v={{ @filemtime(public_path('css/project-material-fastflow-v9.css')) ?: 1 }}">

@php
    $allMaterialRequests = collect($project->materialRequests ?? []);
    $workflowStatuses = ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'revision', 'issued'];
    $officialRequests = $allMaterialRequests
        ->filter(fn ($request) => in_array((string) ($request->status ?? ''), $workflowStatuses, true)
            && collect($request->items ?? [])->contains(fn ($item) => trim((string) ($item->item_name ?? '')) !== ''))
        ->sortByDesc('id')
        ->values();

    $selectedMaterialRequestId = (int) request('material_request', 0);
    $materialRequest = $selectedMaterialRequestId > 0
        ? $officialRequests->firstWhere('id', $selectedMaterialRequestId)
        : $officialRequests->first();

    $items = collect($materialRequest?->items ?? [])
        ->filter(fn ($item) => trim((string) ($item->item_name ?? '')) !== '')
        ->values();

    $user = auth()->user();
    $isTechnicalUser = (bool) ($can['technical'] ?? false);
    $isWarehouseUser = (bool) ($can['warehouse'] ?? false) || $user?->hasAnyRole(['warehouse', 'kho']);
    $isManagerUser = (bool) ($can['admin'] ?? false) || $user?->hasAnyRole(['admin', 'management', 'manager', 'technical_manager', 'technical_leader']);
    $isAdminCostUser = $user?->hasAnyRole(['admin', 'management', 'manager', 'accounting']) ?? false;
    try {
        $isAdminCostUser = $isAdminCostUser || (bool) $user?->can('finance.project_profit.view');
    } catch (\Throwable) {
        // Không có permission cũng không làm hỏng màn hình.
    }
    $canViewCost = $isWarehouseUser || $isAdminCostUser;
    $canViewLiveStock = $isWarehouseUser || $isManagerUser || $isAdminCostUser;

    $hasWarehouseWork = $items->contains(fn ($item) => collect($item->allocations ?? [])->isNotEmpty());
    $canCreateTechnicalRequest = ! $materialRequest
        && $isTechnicalUser
        && in_array($project->status, ['materials_pending', 'materials_revision', 'materials_admin_review', 'warehouse_preparing'], true);
    $canEditTechnicalRequest = $materialRequest
        && $isTechnicalUser
        && ($materialRequest->status === 'revision' || ($materialRequest->status === 'warehouse_check' && ! $hasWarehouseWork));
    $canWarehouseEdit = $materialRequest
        && $isWarehouseUser
        && in_array((string) $materialRequest->status, ['warehouse_check', 'approved'], true)
        && $materialRequest->warehouse_status !== 'reserved';
    $canManagerReview = $materialRequest && $isManagerUser && $materialRequest->status === 'pending_manager';

    $statusLabels = [
        'warehouse_check' => 'Kho đang đối chiếu',
        'pending_manager' => 'Chờ Admin/Quản lý duyệt',
        'approved' => 'Đã duyệt · Chờ giữ hàng',
        'preparing' => 'Đã giữ hàng · Chờ xuất',
        'revision' => 'Kỹ thuật cần điều chỉnh',
        'issued' => 'Đã xuất kho',
    ];
    $requestStatusLabel = $materialRequest ? ($statusLabels[$materialRequest->status] ?? 'Đang xử lý') : 'Chưa có đề nghị vật tư';
    if ($materialRequest?->status === 'approved' && $materialRequest?->warehouse_status === 'waiting_replenishment') {
        $requestStatusLabel = 'Đã duyệt · Chờ điều hàng/nhập bổ sung';
    }

    $rowStatusMeta = [
        'ready' => ['Đủ hàng', 'is-ready', 'bi-check-circle-fill'],
        'shortage' => ['Thiếu hàng', 'is-shortage', 'bi-x-circle-fill'],
        'transfer' => ['Điều chuyển', 'is-transfer', 'bi-arrow-left-right'],
        'waiting_purchase' => ['Chờ nhập', 'is-waiting', 'bi-cart-plus-fill'],
        'reserved' => ['Đã giữ', 'is-ready', 'bi-lock-fill'],
        'issued' => ['Đã xuất', 'is-ready', 'bi-box-arrow-up-right'],
        '' => ['Chưa kiểm tra', 'is-neutral', 'bi-clock'],
    ];

    $checkedRows = 0;
    $readyRows = 0;
    $shortageRows = 0;
    $totalCost = 0.0;
    foreach ($items as $item) {
        $allocation = collect($item->allocations ?? [])->first();
        if (! $allocation) continue;
        $checkedRows++;
        $status = (string) ($allocation->status ?? '');
        if (in_array($status, ['ready', 'reserved', 'issued'], true)) $readyRows++;
        if (in_array($status, ['shortage', 'transfer', 'waiting_purchase'], true)) $shortageRows++;
        $unitCost = (float) ($allocation->live_unit_cost ?? $allocation->unit_cost ?? 0);
        $qty = (float) ($allocation->issued_quantity ?: $allocation->allocated_quantity ?: $item->quantity);
        if ($canViewCost && $unitCost > 0) $totalCost += $unitCost * $qty;
    }
    $totalRows = $items->count();
    $allRowsReady = $totalRows > 0 && $readyRows === $totalRows;

    $technicalFormAction = $materialRequest
        ? route('project-test.materials.update', [$project, $materialRequest])
        : route('project-test.materials.submit', $project);
    $productsById = collect($products ?? [])->keyBy(fn ($product) => (int) data_get($product, 'id', 0));
    $warehousesById = collect($warehouses ?? [])->keyBy(fn ($warehouse) => (int) data_get($warehouse, 'id', 0));

    $oldItemNames = old('item_name');
    if (is_array($oldItemNames)) {
        $technicalRows = collect($oldItemNames)->map(fn ($itemName, $index) => (object) [
            'id' => (int) old("item_id.{$index}", 0),
            'item_name' => trim((string) $itemName),
            'product_id' => (int) old("product_id.{$index}", 0),
            'quantity' => old("quantity.{$index}", 1),
            'unit' => old("unit.{$index}", 'cái'),
            'note' => old("item_note.{$index}"),
            'allocations' => collect(),
        ]);
    } elseif ($canEditTechnicalRequest) {
        $technicalRows = $items;
    } else {
        $technicalRows = collect([null]);
    }

    $stepIndex = match ($materialRequest?->status) {
        'warehouse_check' => 2,
        'pending_manager' => 3,
        'approved', 'preparing', 'issued' => 4,
        'revision' => 1,
        default => $materialRequest ? 2 : 1,
    };

    $materialView = request('material_view', 'proposal');
    if (! in_array($materialView, ['proposal', 'issue', 'return'], true)) $materialView = 'proposal';

    $aftercareRequests = collect($project->materialAftercareRequests ?? [])->sortByDesc('id')->values();
    $aftercareTypeLabels = [
        'shortage' => 'Thiếu hàng sau bàn giao',
        'additional' => 'Đề nghị bổ sung',
        'return' => 'Trả lại vật tư',
        'exchange' => 'Đổi vật tư',
        'damaged' => 'Hàng lỗi / hư hỏng',
    ];
    $aftercareStatusLabels = [
        'warehouse_review' => 'Kho kiểm tra',
        'revision' => 'Kỹ thuật điều chỉnh',
        'pending_manager' => 'Chờ Admin duyệt',
        'approved' => 'Đã duyệt thu hồi',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn tất',
        'rejected' => 'Từ chối',
    ];
@endphp

<div class="emw9" data-emw9-root data-material-view="{{ $materialView }}">
    <section class="pt-card emw9-head">
        <div class="emw9-head__main">
            <span class="emw9-head__icon"><i class="bi bi-box-seam"></i></span>
            <div>
                <small>{{ $materialRequest?->code ?: 'VẬT TƯ CÔNG TRÌNH' }} · {{ $requestStatusLabel }}</small>
                <strong>{{ $materialRequest ? 'Cần ngày '.(optional($materialRequest->needed_at)->format('d/m/Y') ?: '—') : 'Kỹ thuật chưa gửi danh sách' }}</strong>
            </div>
        </div>
        <div class="emw9-head__stats">
            <span><b>{{ $totalRows }}</b> dòng</span>
            <span><b>{{ $checkedRows }}/{{ $totalRows }}</b> đối chiếu</span>
            <span><b>{{ $readyRows }}/{{ $totalRows }}</b> đủ hàng</span>
            @if($canViewCost)<span class="is-cost"><b>{{ number_format($totalCost, 0, ',', '.') }} đ</b> giá vốn</span>@endif
        </div>
    </section>

    <section class="pt-card emw9-flow" aria-label="Quy trình vật tư">
        @foreach([1 => 'Đề xuất', 2 => 'Kho đối chiếu', 3 => 'Phê duyệt', 4 => 'Xuất kho'] as $number => $label)
            <div class="emw9-flow__step {{ $number < $stepIndex ? 'is-done' : ($number === $stepIndex ? 'is-current' : '') }}">
                <span>{{ $number < $stepIndex ? '✓' : $number }}</span><strong>{{ $label }}</strong>
            </div>
            @if($number < 4)<i class="bi bi-chevron-right"></i>@endif
        @endforeach
    </section>

    @include('project-test.partials.material-unified-workflow-v1')

    <section class="emw9-view" data-emw9-view="issue">
        @if(! $materialRequest)
            <article class="pt-card emw9-empty"><i class="bi bi-box-arrow-up-right"></i><div><h3>Chưa thể xuất kho</h3><p>Chưa có phiếu vật tư chính thức.</p></div></article>
        @else
            <article class="pt-card emw9-card">
                <header class="emw9-card__head"><div><h3>Xuất kho · {{ $materialRequest->code }}</h3><p>Chỉ trừ tồn sau khi đã giữ đủ hàng.</p></div><span class="emw9-chip">KHO</span></header>
                @if($materialRequest->status === 'issued')
                    <div class="emw9-success"><i class="bi bi-check-circle-fill"></i><div><strong>Đã xuất kho</strong><span>{{ optional($materialRequest->issued_at)->format('d/m/Y H:i') ?: '—' }} · Người nhận {{ $materialRequest->receiver?->name ?: '—' }}</span></div></div>
                @elseif(! in_array($materialRequest->status, ['approved', 'preparing'], true))
                    <div class="emw9-notice"><i class="bi bi-lock"></i> Chưa được phép xuất. Kho phải đối chiếu đủ và Admin/Quản lý phê duyệt.</div>
                @elseif($materialRequest->status === 'approved' && $materialRequest->warehouse_status === 'waiting_replenishment')
                    <div class="emw9-notice"><i class="bi bi-hourglass-split"></i> Admin/Quản lý đã duyệt phương án nhưng phiếu còn thiếu hàng, cần điều chuyển hoặc chờ nhập. Kho vào tab <strong>Vật tư đề xuất</strong>, cập nhật tồn sau bổ sung đến khi tất cả dòng đủ hàng rồi mới giữ hàng.</div>
                @elseif($materialRequest->warehouse_status !== 'reserved')
                    <div class="emw9-inline-action"><div><strong>Bước 1 · Giữ hàng</strong><span>Kiểm tra lại tồn và khóa serial khả dụng.</span></div>
                        @if($isWarehouseUser)<form method="POST" action="{{ route('project-test.warehouse.reserve', $materialRequest) }}" data-confirm="Xác nhận giữ đủ hàng?">@csrf<button class="pt-btn pt-btn--brand"><i class="bi bi-lock-fill"></i> Giữ hàng</button></form>@endif
                    </div>
                @else
                    <div class="emw9-success"><i class="bi bi-lock-fill"></i><div><strong>Đã giữ đủ hàng</strong><span>Sẵn sàng xuất và trừ tồn thật.</span></div></div>
                    @if($isWarehouseUser)
                        <div class="emw9-issue-actions">
                            <form method="POST" action="{{ route('project-test.warehouse.release', $materialRequest) }}" data-confirm="Bỏ giữ toàn bộ hàng?">@csrf<button class="pt-btn pt-btn--soft"><i class="bi bi-unlock"></i> Bỏ giữ</button></form>
                            <form method="POST" action="{{ route('project-test.warehouse.issue', $materialRequest) }}" class="emw9-issue-form" data-confirm="Xác nhận xuất kho và trừ tồn?">@csrf
                                <select class="pt-select" name="receiver_id" required><option value="">Người nhận vật tư</option>@foreach($technicians as $receiver)<option value="{{ $receiver->id }}">{{ $receiver->name }}</option>@endforeach</select>
                                <input class="pt-input" name="issue_note" placeholder="Ghi chú bàn giao...">
                                <button class="pt-btn pt-btn--brand"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</button>
                            </form>
                        </div>
                    @endif
                @endif
            </article>
        @endif
    </section>

    <section class="emw9-view" data-emw9-view="return">
        <div class="emw9-aftercare-grid">
            <article class="pt-card emw9-card">
                <header class="emw9-card__head"><div><h3>Yêu cầu sau xuất kho</h3><p>Thiếu hàng, bổ sung, đổi, trả hoặc báo hàng lỗi.</p></div><span class="emw9-chip">KỸ THUẬT</span></header>
                @if($materialRequest?->status === 'issued' && $isTechnicalUser)
                    <form method="POST" action="{{ route('project-test.materials.aftercare.store', [$project, $materialRequest]) }}" class="emw9-aftercare-form">@csrf
                        <label><span>Loại yêu cầu</span><select class="pt-select" name="type" required data-emw9-aftercare-type>
                            <option value="shortage">Thiếu hàng sau bàn giao</option><option value="additional">Đề nghị bổ sung</option><option value="exchange">Đổi vật tư</option><option value="return">Trả lại vật tư</option><option value="damaged">Hàng lỗi / hư hỏng</option>
                        </select></label>
                        <label><span>Vật tư đã xuất</span><select class="pt-select" name="material_item_id" data-emw9-source-item><option value="">-- Vật tư mới / bổ sung --</option>@foreach($items as $item)<option value="{{ $item->id }}" data-name="{{ $item->item_name }}" data-unit="{{ $item->unit }}">{{ $item->item_name }} · đã xuất {{ rtrim(rtrim(number_format((float)($item->issued_quantity ?: $item->quantity),3,'.',''),'0'),'.') }} {{ $item->unit }}</option>@endforeach</select></label>
                        <label><span>Tên vật tư cần bổ sung / đổi</span><input class="pt-input" name="desired_item_name" placeholder="Để trống nếu giữ nguyên loại cũ"></label>
                        <label><span>Số lượng</span><div class="emw9-qty"><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity" value="1" required><input class="pt-input" name="unit" value="cái" data-emw9-aftercare-unit required></div></label>
                        <label><span>Tình trạng</span><select class="pt-select" name="condition"><option value="missing">Thiếu / chưa nhận đủ</option><option value="new">Còn mới</option><option value="used">Đã sử dụng</option><option value="damaged">Lỗi / hư hỏng</option></select></label>
                        <label class="is-wide"><span>Lý do và yêu cầu xử lý</span><textarea class="pt-textarea" name="technical_note" required placeholder="Mô tả rõ số lượng thiếu, lỗi, nhu cầu đổi/trả..."></textarea></label>
                        <div class="is-wide emw9-form-actions"><button class="pt-btn pt-btn--brand"><i class="bi bi-send-check"></i> Gửi Kho kiểm tra</button></div>
                    </form>
                @elseif($materialRequest?->status !== 'issued')
                    <div class="emw9-notice"><i class="bi bi-info-circle"></i> Chức năng mở sau khi phiếu đã xuất kho. Trước khi xuất, Admin có thể trả phiếu về Kho hoặc Kỹ thuật ngay tại bước phê duyệt.</div>
                @endif
            </article>

            <div class="emw9-aftercare-list">
                @forelse($aftercareRequests as $aftercare)
                    @php
                        $acItem = collect($aftercare->items ?? [])->first();
                        $acSource = $acItem?->materialItem;
                        $acAllocation = collect($acSource?->allocations ?? [])->first();
                        $acReplacementProduct = $acItem?->replacementProduct;
                        $acReplacementWarehouse = $acItem?->replacementWarehouse;
                        $acReplacementStock = $acItem?->replacement_stock_snapshot;
                        $acReplacementUnitCost = (float) ($acItem?->replacement_unit_cost_snapshot ?? 0);
                        $serialIds = collect($acAllocation?->selected_serial_unit_ids ?? [])->map(fn($id)=>(int)$id)->values();
                        $serialCodes = collect(preg_split('/\r\n|\r|\n/', trim((string)($acAllocation?->selected_serial_codes ?? ''))))->filter()->values();
                    @endphp
                    <article class="pt-card emw9-aftercare-item">
                        <header><div><small>{{ $aftercare->code }}</small><h4>{{ $aftercareTypeLabels[$aftercare->type] ?? $aftercare->type }}</h4></div><span class="emw9-status is-{{ $aftercare->status }}">{{ $aftercareStatusLabels[$aftercare->status] ?? $aftercare->status }}</span></header>
                        <div class="emw9-aftercare-summary">
                            <div><small>Vật tư</small><strong>{{ $acItem?->item_name ?: '—' }}</strong></div>
                            <div><small>Số lượng</small><strong>{{ rtrim(rtrim(number_format((float)($acItem?->quantity ?? 0),3,'.',''),'0'),'.') }} {{ $acItem?->unit }}</strong></div>
                            @if($acItem?->desired_item_name)<div><small>Cần đổi/bổ sung</small><strong>{{ $acItem->desired_item_name }}</strong></div>@endif
                            @if($acReplacementProduct)<div><small>Kho đề xuất cấp</small><strong>{{ $acReplacementProduct->name }} · {{ $acReplacementWarehouse?->name ?: 'Chưa chọn kho' }}</strong></div>@endif
                            @if($canViewLiveStock)<div><small>{{ $acReplacementProduct ? 'Tồn hàng cấp mới' : 'Tồn vật tư gốc' }}</small><strong>{{ ($acReplacementProduct ? $acReplacementStock : $acItem?->stock_snapshot) !== null ? rtrim(rtrim(number_format((float)($acReplacementProduct ? $acReplacementStock : $acItem?->stock_snapshot),3,'.',''),'0'),'.') : '—' }}</strong></div>@endif
                            @if($canViewCost)<div><small>{{ $acReplacementProduct ? 'Giá vốn hàng cấp mới' : 'Giá vốn vật tư gốc' }}</small><strong>{{ ($acReplacementProduct ? $acReplacementUnitCost : (float)($acItem?->unit_cost_snapshot ?? 0)) > 0 ? number_format(($acReplacementProduct ? $acReplacementUnitCost : (float)$acItem?->unit_cost_snapshot),0,',','.') .' đ' : '—' }}</strong></div>@endif
                        </div>
                        <div class="emw9-notes"><p><b>Kỹ thuật:</b> {{ $aftercare->technical_note }}</p>@if($aftercare->warehouse_note)<p><b>Kho:</b> {{ $aftercare->warehouse_note }}</p>@endif @if($aftercare->manager_note)<p><b>Admin:</b> {{ $aftercare->manager_note }}</p>@endif</div>
                        @if($aftercare->linkedMaterialRequest)
                            <a class="emw9-linked" href="{{ route('project-test.show', ['project'=>$project,'tab'=>'materials','material_view'=>'proposal','material_request'=>$aftercare->linkedMaterialRequest->id]) }}"><i class="bi bi-box-seam"></i> Phiếu xử lý {{ $aftercare->linkedMaterialRequest->code }} · {{ $statusLabels[$aftercare->linkedMaterialRequest->status] ?? $aftercare->linkedMaterialRequest->status }}</a>
                        @endif

                        @if($aftercare->status === 'revision' && $isTechnicalUser)
                            <form method="POST" action="{{ route('project-test.materials.aftercare.update', [$project, $aftercare]) }}" class="emw9-mini-form">@csrf
                                <input class="pt-input" type="number" min="0.001" step="0.001" name="quantity" value="{{ (float)$acItem?->quantity }}" required>
                                <input class="pt-input" name="desired_item_name" value="{{ $acItem?->desired_item_name }}" placeholder="Tên vật tư cần đổi/bổ sung">
                                <input type="hidden" name="condition" value="{{ $acItem?->condition }}">
                                <textarea class="pt-textarea" name="technical_note" required>{{ $aftercare->technical_note }}</textarea>
                                <button class="pt-btn pt-btn--brand"><i class="bi bi-send-check"></i> Gửi lại Kho</button>
                            </form>
                        @elseif($aftercare->status === 'warehouse_review' && $isWarehouseUser)
                            <form method="POST" action="{{ route('project-test.materials.aftercare.warehouse-review', [$project, $aftercare]) }}" class="emw9-decision-form">@csrf
                                @if(in_array($aftercare->type, ['additional','exchange'], true))
                                    <div class="emw9-aftercare-map" data-emw9-stock-row data-required="{{ (float)$acItem?->quantity }}">
                                        <label><span>Sản phẩm / SKU cấp mới</span><select class="pt-select" name="items[{{ $acItem?->id }}][replacement_product_id]" required data-emw9-product-select data-url="{{ route('project-test.warehouse.products', $materialRequest) }}" data-selected="{{ $acItem?->replacement_product_id }}"><option value="">Chọn sản phẩm / SKU</option>@if($acReplacementProduct)<option value="{{ $acReplacementProduct->id }}" selected>{{ $acReplacementProduct->name }} · {{ $acReplacementProduct->sku ?: '—' }}</option>@endif</select></label>
                                        <label><span>Kho cấp</span><select class="pt-select" name="items[{{ $acItem?->id }}][replacement_warehouse_id]" required data-emw9-warehouse-select data-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}" data-selected="{{ $acItem?->replacement_warehouse_id }}"><option value="">Chọn kho cấp</option>@if($acReplacementWarehouse)<option value="{{ $acReplacementWarehouse->id }}" selected>{{ $acReplacementWarehouse->name }}</option>@endif</select></label>
                                        <div><small>Tồn khả dụng</small><strong data-emw9-live-stock>{{ $acReplacementStock !== null ? rtrim(rtrim(number_format((float)$acReplacementStock,3,'.',''),'0'),'.') : '—' }}</strong></div>
                                        @if($canViewCost)<div><small>Giá vốn / thành tiền</small><strong data-emw9-unit-cost>{{ $acReplacementUnitCost > 0 ? number_format($acReplacementUnitCost,0,',','.') .' đ' : '—' }}</strong><span data-emw9-line-cost>{{ $acReplacementUnitCost > 0 ? number_format($acReplacementUnitCost * (float)$acItem?->quantity,0,',','.') .' đ' : 'Chưa có giá lô' }}</span></div>@endif
                                    </div>
                                @else
                                    <div class="emw9-notice is-good"><i class="bi bi-box-seam"></i> Vật tư gốc: {{ $acItem?->item_name }} · {{ $acItem?->warehouse?->name ?: $acAllocation?->warehouse?->name ?: 'Chưa xác định kho' }}. Kho kiểm tra tình trạng thực tế rồi gửi Admin.</div>
                                @endif
                                <textarea class="pt-textarea" name="warehouse_note" required placeholder="Kết quả kiểm tra thực tế, tồn hàng, phương án cấp bù/đổi/trả..."></textarea>
                                <div><button class="pt-btn pt-btn--brand" name="decision" value="send_manager">Gửi Admin duyệt</button><button class="pt-btn pt-btn--soft" name="decision" value="return_technical">Trả Kỹ thuật</button><button class="pt-btn pt-btn--danger" name="decision" value="reject">Từ chối</button></div>
                            </form>
                        @elseif($aftercare->status === 'pending_manager' && $isManagerUser)
                            <form method="POST" action="{{ route('project-test.materials.aftercare.manager-review', [$project, $aftercare]) }}" class="emw9-decision-form">@csrf
                                <textarea class="pt-textarea" name="manager_note" placeholder="Ghi chú phê duyệt hoặc lý do trả lại..."></textarea>
                                <div><button class="pt-btn pt-btn--brand" name="decision" value="approve">Phê duyệt</button><button class="pt-btn pt-btn--soft" name="decision" value="return_warehouse">Trả Kho</button><button class="pt-btn pt-btn--soft" name="decision" value="return_technical">Trả Kỹ thuật</button><button class="pt-btn pt-btn--danger" name="decision" value="reject">Từ chối</button></div>
                            </form>
                        @elseif(in_array($aftercare->status, ['approved','processing'], true) && in_array($aftercare->type, ['return','exchange','damaged'], true) && ! $aftercare->return_processed_at && $isWarehouseUser)
                            <form method="POST" action="{{ route('project-test.materials.aftercare.process-return', [$project, $aftercare]) }}" class="emw9-return-form">@csrf
                                <select class="pt-select" name="disposition" required><option value="restock">Nhập lại kho</option><option value="quarantine">Cách ly kiểm tra</option><option value="scrap">Loại bỏ / không hoàn tồn</option></select>
                                <select class="pt-select" name="warehouse_id" required><option value="">Kho nhận lại</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int)$acItem?->warehouse_id === (int)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
                                <input class="pt-input" type="number" min="1" step="1" name="items[{{ $acItem?->id }}][quantity]" value="{{ (int)round((float)$acItem?->quantity) }}" required>
                                @if($serialIds->isNotEmpty())<div class="emw9-serials"><span>Chọn serial nhận lại:</span>@foreach($serialIds as $serialIndex => $serialId)<label><input type="checkbox" name="items[{{ $acItem?->id }}][serial_unit_ids][]" value="{{ $serialId }}"> {{ $serialCodes->get($serialIndex, 'SN#'.$serialId) }}</label>@endforeach</div>@endif
                                <textarea class="pt-textarea" name="process_note" required placeholder="Biên bản nhận lại, tình trạng thực tế..."></textarea>
                                <button class="pt-btn pt-btn--brand"><i class="bi bi-arrow-return-left"></i> Xác nhận xử lý</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <article class="pt-card emw9-empty"><i class="bi bi-arrow-return-left"></i><div><h3>Chưa có yêu cầu sau xuất kho</h3><p>Khi thiếu, đổi, trả hoặc phát hiện hàng lỗi, Kỹ thuật tạo yêu cầu ở biểu mẫu bên cạnh.</p></div></article>
                @endforelse
            </div>
        </div>
    </section>
</div>

<datalist id="emw9-product-suggestions">
    @foreach($products as $productOption)
        <option value="{{ data_get($productOption, 'name') }}" data-id="{{ (int)data_get($productOption,'id',0) }}" data-unit="{{ data_get($productOption,'unit') ?: 'cái' }}" data-sku="{{ data_get($productOption,'sku') }}">SKU {{ data_get($productOption,'sku') ?: '—' }}</option>
    @endforeach
</datalist>

<template data-emw9-tech-template>
    <tr data-emw9-tech-row>
        <td class="emw9-material-cell"><input type="hidden" name="item_id[]" value=""><input class="pt-input" name="item_name[]" list="emw9-product-suggestions" placeholder="Tên vật tư / model" required data-emw9-tech-name><input type="hidden" name="product_id[]" value="" data-emw9-tech-product-id></td>
        <td><div class="emw9-qty"><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="1" required><input class="pt-input" name="unit[]" value="cái" required data-emw9-tech-unit></div></td>
        <td><input class="pt-input" name="item_note[]" placeholder="Thông số, vị trí lắp..."></td>
        <td><span class="emw9-status is-neutral">Chờ Kho</span></td>
        <td><button type="button" class="emw9-remove" data-emw9-remove-row title="Xóa"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script src="{{ asset('js/project-material-fastflow-v9.js') }}?v={{ @filemtime(public_path('js/project-material-fastflow-v9.js')) ?: 1 }}" defer></script>
