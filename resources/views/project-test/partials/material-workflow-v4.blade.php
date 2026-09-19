{{--
    EGO MATERIAL WORKFLOW V4 CLEAN
    Kỹ thuật chốt đúng sản phẩm -> Kho kiểm tra đúng SKU -> Quản lý duyệt -> Kho giữ và xuất.
    Dữ liệu vật tư ghi tay cũ không tham gia luồng Kho nhưng vẫn được giữ trong database/lịch sử.
--}}
@php
    $allMaterialRequests = collect($project->materialRequests ?? []);
    $v4RequestStatuses = ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'revision', 'issued'];
    $officialRequests = $allMaterialRequests
        ->filter(function ($request) use ($v4RequestStatuses): bool {
            return in_array((string) ($request->status ?? ''), $v4RequestStatuses, true)
                && collect($request->items ?? [])->contains(fn ($item): bool => (int) data_get($item, 'product_id', 0) > 0);
        })
        ->sortByDesc('id')
        ->values();

    $selectedMaterialRequestId = (int) request('material_request', 0);
    $materialRequest = $selectedMaterialRequestId > 0
        ? $officialRequests->firstWhere('id', $selectedMaterialRequestId)
        : $officialRequests->first();

    $items = collect($materialRequest?->items ?? [])
        ->filter(fn ($item): bool => (int) data_get($item, 'product_id', 0) > 0)
        ->values();

    $legacyRequests = $allMaterialRequests->filter(function ($request) use ($v4RequestStatuses): bool {
        $hasExactProduct = collect($request->items ?? [])->contains(fn ($item): bool => (int) data_get($item, 'product_id', 0) > 0);
        return ! $hasExactProduct || ! in_array((string) ($request->status ?? ''), $v4RequestStatuses, true);
    });
    $legacyRows = $legacyRequests->sum(fn ($request): int => collect($request->items ?? [])->count());

    $user = auth()->user();
    $isTechnicalUser = (bool) ($can['technical'] ?? false);
    $isWarehouseUser = (bool) ($can['warehouse'] ?? false) || $user?->hasAnyRole(['warehouse', 'kho']);
    $isManagerUser = (bool) ($can['admin'] ?? false) || $user?->hasAnyRole(['admin', 'management', 'manager', 'technical_manager', 'technical_leader']);
    $isSalesUser = (bool) ($can['sales'] ?? false);
    $canViewCost = $isWarehouseUser || $isManagerUser || $user?->can('finance.project_profit.view');

    $hasWarehouseWork = $items->contains(fn ($item): bool => collect($item->allocations ?? [])->isNotEmpty());
    $canCreateTechnicalRequest = ! $materialRequest
        && $isTechnicalUser
        && in_array($project->status, ['materials_pending', 'materials_revision', 'materials_admin_review', 'warehouse_preparing'], true);
    $canEditTechnicalRequest = $materialRequest
        && $isTechnicalUser
        && (
            $materialRequest->status === 'revision'
            || ($materialRequest->status === 'warehouse_check' && ! $hasWarehouseWork)
        );
    $canWarehouseEdit = $materialRequest
        && $isWarehouseUser
        && $materialRequest->status === 'warehouse_check'
        && $materialRequest->warehouse_status !== 'reserved';
    $canManagerReview = $materialRequest && $isManagerUser && $materialRequest->status === 'pending_manager';

    $statusLabels = [
        'warehouse_check' => 'Kho kiểm tra tồn',
        'pending_manager' => 'Chờ Quản lý phê duyệt',
        'approved' => 'Đã duyệt · Chờ Kho giữ hàng',
        'preparing' => 'Đã giữ hàng · Chờ xuất kho',
        'revision' => 'Kỹ thuật cần điều chỉnh',
        'issued' => 'Đã xuất kho',
    ];
    $requestStatusLabel = $materialRequest
        ? ($statusLabels[$materialRequest->status] ?? 'Đang xử lý')
        : 'Chưa có danh sách vật tư chính thức';

    $rowStatusMeta = [
        'ready' => ['label' => 'Đủ hàng', 'class' => 'is-ready', 'icon' => 'bi-check-circle-fill'],
        'shortage' => ['label' => 'Thiếu hàng', 'class' => 'is-shortage', 'icon' => 'bi-x-circle-fill'],
        'transfer' => ['label' => 'Cần điều chuyển', 'class' => 'is-transfer', 'icon' => 'bi-arrow-left-right'],
        'waiting_purchase' => ['label' => 'Chờ nhập', 'class' => 'is-waiting', 'icon' => 'bi-cart-plus-fill'],
        'reserved' => ['label' => 'Đã giữ hàng', 'class' => 'is-ready', 'icon' => 'bi-lock-fill'],
        'issued' => ['label' => 'Đã xuất', 'class' => 'is-ready', 'icon' => 'bi-box-arrow-up-right'],
        '' => ['label' => 'Chưa kiểm tra', 'class' => 'is-neutral', 'icon' => 'bi-clock'],
    ];

    $checkedRows = 0;
    $readyRows = 0;
    $shortageRows = 0;
    $transferRows = 0;
    $waitingRows = 0;
    $totalCost = 0.0;

    foreach ($items as $item) {
        $allocation = collect($item->allocations ?? [])->first();
        if (! $allocation) {
            continue;
        }
        $checkedRows++;
        $status = (string) ($allocation->status ?? '');
        if (in_array($status, ['ready', 'reserved', 'issued'], true)) {
            $readyRows++;
        } elseif ($status === 'shortage') {
            $shortageRows++;
        } elseif ($status === 'transfer') {
            $transferRows++;
        } elseif ($status === 'waiting_purchase') {
            $waitingRows++;
        }
        if ($canViewCost && (float) ($allocation->unit_cost ?? 0) > 0) {
            $totalCost += (float) $allocation->unit_cost * (float) ($allocation->allocated_quantity ?? 0);
        }
    }

    $totalRows = $items->count();
    $allRowsReady = $totalRows > 0 && $readyRows === $totalRows;
    $checkedPercent = $totalRows > 0 ? (int) round(($checkedRows / $totalRows) * 100) : 0;

    $materialView = request('material_view', '');
    if (! in_array($materialView, ['needs', 'stock', 'issue', 'return'], true)) {
        $materialView = ($canCreateTechnicalRequest || $canEditTechnicalRequest || (! $isWarehouseUser && ! $isManagerUser)) ? 'needs' : 'stock';
    }

    $technicalFormAction = $materialRequest
        ? route('project-test.materials.update', [$project, $materialRequest])
        : route('project-test.materials.submit', $project);

    $productsById = collect($products ?? [])->keyBy(fn ($product) => (int) data_get($product, 'id', 0));

    $oldProductIds = old('product_id');
    if (is_array($oldProductIds)) {
        $technicalRows = collect($oldProductIds)->map(function ($productId, $index) {
            return (object) [
                'product_id' => (int) $productId,
                'quantity' => old("quantity.{$index}", 1),
                'unit' => old("unit.{$index}", 'cái'),
                'note' => old("item_note.{$index}"),
            ];
        });
    } elseif ($canEditTechnicalRequest) {
        $technicalRows = $items;
    } else {
        $technicalRows = collect([null]);
    }

    $stepIndex = match ($materialRequest?->status) {
        'warehouse_check' => 2,
        'pending_manager' => 3,
        'approved' => 4,
        'preparing' => 4,
        'issued' => 5,
        'revision' => 1,
        default => $materialRequest ? 2 : 1,
    };
@endphp

<div class="emw8" data-emw8-root data-material-view="{{ $materialView }}">
    <section class="pt-card emw8-head">
        <div class="emw8-head__identity">
            <span class="emw8-head__icon"><i class="bi bi-box-seam"></i></span>
            <div>
                <small>VẬT TƯ CÔNG TRÌNH{{ $materialRequest ? ' · '.$materialRequest->code : '' }}</small>
                <h2>{{ $project->name }}</h2>
                <p>{{ $requestStatusLabel }}@if($materialRequest) · Cần ngày {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}@endif</p>
            </div>
        </div>
        <div class="emw8-head__numbers">
            <div><small>Danh sách chính thức</small><strong>{{ $totalRows }} dòng</strong></div>
            <div><small>Kho đã kiểm tra</small><strong>{{ $checkedRows }}/{{ $totalRows }}</strong></div>
            <div><small>Đủ hàng</small><strong>{{ $readyRows }}/{{ $totalRows }}</strong></div>
        </div>
    </section>

    <section class="pt-card emw8-flow" aria-label="Quy trình vật tư">
        @foreach([
            1 => ['Kỹ thuật lập danh sách', 'Chọn đúng thương hiệu, model, SKU, thông số và số lượng', 'bi-list-check'],
            2 => ['Kho kiểm tra tồn', 'Kiểm tra đúng sản phẩm đã được Kỹ thuật chốt', 'bi-clipboard-data'],
            3 => ['Quản lý phê duyệt', 'Chỉ duyệt khi toàn bộ dòng đủ hàng', 'bi-shield-check'],
            4 => ['Kho giữ hàng', 'Khóa đúng hàng và serial trước khi xuất', 'bi-lock'],
            5 => ['Xuất kho', 'Trừ tồn thật và bàn giao cho Kỹ thuật', 'bi-box-arrow-up-right'],
        ] as $number => $flowStep)
            <div class="emw8-flow__step {{ $number < $stepIndex ? 'is-done' : ($number === $stepIndex ? 'is-current' : '') }}">
                <span><i class="bi {{ $number < $stepIndex ? 'bi-check-lg' : $flowStep[2] }}"></i></span>
                <div><strong>{{ $flowStep[0] }}</strong><small>{{ $flowStep[1] }}</small></div>
            </div>
        @endforeach
    </section>

    @if($legacyRows > 0 && ! $materialRequest)
        <div class="emw8-legacy-note"><i class="bi bi-archive"></i> {{ $legacyRows }} dòng thuộc logic vật tư cũ đã được tách khỏi quy trình Kho. Dữ liệu vẫn được giữ để đối chiếu, nhưng không tính là danh sách vật tư chính thức.</div>
    @endif

    <section class="emw8-view" data-emw8-view="needs">
        @if($canCreateTechnicalRequest || $canEditTechnicalRequest)
            <article class="pt-card emw8-card">
                <header class="emw8-card__head">
                    <div><h2><i class="bi bi-list-check"></i> DANH SÁCH VẬT TƯ KỸ THUẬT</h2><p>Chỉ chọn sản phẩm có trong danh mục Kho. Không nhập tên vật tư tự do.</p></div>
                    <span class="emw8-role">KỸ THUẬT</span>
                </header>

                @if($materialRequest?->review_note)
                    <div class="emw8-return"><i class="bi bi-arrow-return-left"></i><div><strong>Phiếu được trả lại</strong><span>{{ $materialRequest->review_note }}</span></div></div>
                @endif

                <form method="POST" action="{{ $technicalFormAction }}" data-emw8-tech-form data-existing-request="{{ $materialRequest ? '1' : '0' }}">
                    @csrf
                    <div class="emw8-meta-grid">
                        <label><span>Ngày cần vật tư</span><input class="pt-input" type="date" name="needed_at" value="{{ old('needed_at', optional($materialRequest?->needed_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></label>
                        <label><span>Ghi chú chung</span><input class="pt-input" name="request_note" value="{{ old('request_note', $materialRequest?->request_note) }}" placeholder="Phạm vi thi công, thời gian giao, lưu ý hợp đồng..."></label>
                    </div>

                    <div class="emw8-table-wrap">
                        <table class="emw8-table emw8-tech-table">
                            <thead><tr><th>STT</th><th>Sản phẩm chính thức</th><th>SL</th><th>ĐVT</th><th>Thông số / lưu ý</th><th></th></tr></thead>
                            <tbody data-emw8-tech-body>
                                @foreach($technicalRows as $index => $row)
                                    @php
                                        $rowProductId = (int) data_get($row, 'product_id', 0);
                                        $rowProduct = $productsById->get($rowProductId);
                                        $rowQuantity = data_get($row, 'quantity', 1);
                                        $rowUnit = trim((string) data_get($row, 'unit', data_get($rowProduct, 'unit', 'cái'))) ?: 'cái';
                                        $rowNote = data_get($row, 'note', '');
                                    @endphp
                                    <tr data-emw8-tech-row>
                                        <td data-emw8-tech-index>{{ $index + 1 }}</td>
                                        <td>
                                            <select class="pt-select" name="product_id[]" required data-emw8-product-select data-selected="{{ $rowProductId }}">
                                                <option value="">-- Tìm và chọn đúng sản phẩm / SKU --</option>
                                                @if($rowProduct)
                                                    <option value="{{ $rowProductId }}" selected>{{ data_get($rowProduct, 'name') }} · SKU {{ data_get($rowProduct, 'sku', '—') }}</option>
                                                @endif
                                            </select>
                                            <small data-emw8-product-help>
                                                @if($rowProduct)
                                                    {{ data_get($rowProduct, 'brand_name') ?: 'Chưa gắn thương hiệu' }} · SKU {{ data_get($rowProduct, 'sku') ?: '—' }} · Tồn tham khảo {{ rtrim(rtrim(number_format((float) data_get($rowProduct, 'total_stock_qty', 0), 3, '.', ''), '0'), '.') }}
                                                @else
                                                    Bắt buộc chọn đúng thương hiệu, model/chủng loại và SKU.
                                                @endif
                                            </small>
                                        </td>
                                        <td><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="{{ $rowQuantity }}" required></td>
                                        <td><input class="pt-input" name="unit[]" value="{{ $rowUnit }}" readonly data-emw8-unit></td>
                                        <td><input class="pt-input" name="item_note[]" value="{{ $rowNote }}" placeholder="Thông số bắt buộc, vị trí lắp, lưu ý thi công..."></td>
                                        <td><button type="button" class="emw8-icon-btn is-danger" data-emw8-remove-row title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="emw8-form-actions">
                        <button type="button" class="pt-btn pt-btn--soft" data-emw8-add-row><i class="bi bi-plus-lg"></i> Thêm dòng</button>
                        <span data-emw8-row-count>{{ $technicalRows->count() }} dòng</span>
                        <button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-send-check"></i> Chốt danh sách & chuyển Kho kiểm tra</button>
                    </div>
                </form>
            </article>
        @elseif($materialRequest)
            <article class="pt-card emw8-card">
                <header class="emw8-card__head"><div><h2><i class="bi bi-list-check"></i> DANH SÁCH KỸ THUẬT ĐÃ CHỐT</h2><p>Kho chỉ được kiểm tra tồn đúng các mã hàng dưới đây.</p></div><span class="emw8-role">ĐÃ KHÓA</span></header>
                @include('project-test.partials.material-official-table-v4', ['items' => $items])
            </article>
        @else
            <article class="pt-card emw8-empty"><i class="bi bi-list-check"></i><div><h2>Chưa có danh sách vật tư chính thức</h2><p>Kỹ thuật cần chọn sản phẩm trong danh mục Kho trước khi chuyển bước.</p></div></article>
        @endif
    </section>

    <section class="emw8-view" data-emw8-view="stock">
        @if(! $materialRequest)
            <article class="pt-card emw8-empty"><i class="bi bi-clipboard-data"></i><div><h2>Kho chưa có phiếu để kiểm tra</h2><p>Dữ liệu ghi tay cũ không được dùng. Hãy chờ Kỹ thuật chốt đúng sản phẩm/SKU.</p></div></article>
        @else
            <div class="emw8-stock-layout">
                <article class="pt-card emw8-card">
                    <header class="emw8-card__head">
                        <div><h2><i class="bi bi-clipboard-data"></i> KIỂM TRA TỒN ĐÚNG SẢN PHẨM</h2><p>Kho không được đổi mã hàng hoặc số lượng Kỹ thuật đã chốt.</p></div>
                        <span class="emw8-role">KHO</span>
                    </header>

                    <form method="POST" action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}" id="emw8-stock-form" data-emw8-stock-form>
                        @csrf
                        <div class="emw8-table-wrap">
                            <table class="emw8-table emw8-stock-table">
                                <thead><tr><th>STT</th><th>Vật tư Kỹ thuật yêu cầu</th><th>SL yêu cầu</th><th>Kho kiểm tra</th><th>Tồn khả dụng</th><th>Kết quả</th><th>Ghi chú</th></tr></thead>
                                <tbody>
                                    @foreach($items as $index => $item)
                                        @php
                                            $allocation = collect($item->allocations ?? [])->first();
                                            $rowStatus = (string) ($allocation?->status ?? '');
                                            $product = $item->product;
                                        @endphp
                                        <tr data-emw8-stock-row data-required="{{ (float) $item->quantity }}" data-current-status="{{ $rowStatus }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <strong>{{ $product?->name ?: $item->item_name }}</strong>
                                                <small>{{ data_get($product, 'brand_name') ?: '' }}{{ data_get($product, 'sku') ? ' · SKU '.data_get($product, 'sku') : '' }}</small>
                                                @if($item->note)<small class="emw8-note">{{ $item->note }}</small>@endif
                                                <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">
                                                <input type="hidden" name="items[{{ $item->id }}][quantity]" value="{{ (float) $item->quantity }}">
                                            </td>
                                            <td><strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</strong><small>{{ $item->unit }}</small></td>
                                            <td>
                                                @if($canWarehouseEdit)
                                                    <select class="pt-select" name="items[{{ $item->id }}][warehouse_id]" required data-emw8-warehouse-select data-product-id="{{ $item->product_id }}" data-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}" data-selected="{{ $allocation?->warehouse_id }}">
                                                        <option value="">-- Chọn kho --</option>
                                                        @if($allocation?->warehouse)
                                                            <option value="{{ $allocation->warehouse_id }}" selected>{{ $allocation->warehouse->name }}</option>
                                                        @endif
                                                    </select>
                                                @else
                                                    <strong>{{ $allocation?->warehouse?->name ?: 'Chưa chọn' }}</strong>
                                                @endif
                                            </td>
                                            <td><strong data-emw8-available>{{ $allocation ? rtrim(rtrim(number_format((float) $allocation->available_snapshot, 3, '.', ''), '0'), '.') : '—' }}</strong><small>{{ $item->unit }}</small></td>
                                            <td>
                                                @if($canWarehouseEdit)
                                                    <select class="pt-select" name="items[{{ $item->id }}][check_status]" data-emw8-status-select>
                                                        <option value="ready" @selected($rowStatus === 'ready')>Đủ hàng</option>
                                                        <option value="shortage" @selected($rowStatus === 'shortage')>Thiếu hàng</option>
                                                        <option value="transfer" @selected($rowStatus === 'transfer')>Cần điều chuyển</option>
                                                        <option value="waiting_purchase" @selected($rowStatus === 'waiting_purchase')>Chờ nhập</option>
                                                    </select>
                                                @else
                                                    @php($meta = $rowStatusMeta[$rowStatus] ?? $rowStatusMeta[''])
                                                    <span class="emw8-status {{ $meta['class'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($canWarehouseEdit)
                                                    <input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $allocation?->note }}" placeholder="Vị trí, thời gian điều chuyển/nhập...">
                                                @else
                                                    <span>{{ $allocation?->note ?: '—' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($canWarehouseEdit)
                            <label class="emw8-general-note"><span>Ghi chú kiểm tra chung</span><textarea class="pt-textarea" name="warehouse_note" maxlength="500" placeholder="Tình trạng tồn, phương án điều chuyển, thời gian dự kiến nhập...">{{ $materialRequest->issue_note }}</textarea></label>
                        @endif
                    </form>
                </article>

                <aside class="emw8-side">
                    <article class="pt-card emw8-side-card">
                        <h3>TỔNG HỢP KIỂM TRA</h3>
                        <div><span>Đã kiểm tra</span><strong>{{ $checkedRows }}/{{ $totalRows }}</strong></div>
                        <div><span>Đủ hàng</span><strong class="is-green">{{ $readyRows }}</strong></div>
                        <div><span>Thiếu hàng</span><strong class="is-red">{{ $shortageRows }}</strong></div>
                        <div><span>Cần điều chuyển</span><strong class="is-orange">{{ $transferRows }}</strong></div>
                        <div><span>Chờ nhập</span><strong>{{ $waitingRows }}</strong></div>
                        <div class="emw8-progress"><span><i style="width:{{ $checkedPercent }}%"></i></span><strong>{{ $checkedPercent }}%</strong></div>
                    </article>

                    @if($canViewCost)
                        <article class="pt-card emw8-side-card"><h3>GIÁ VỐN TẠM TÍNH</h3><strong class="emw8-cost">{{ number_format($totalCost, 0, ',', '.') }} đ</strong><small>Chỉ Kho / Quản lý / Admin được xem.</small></article>
                    @endif

                    <article class="pt-card emw8-side-card emw8-actions">
                        <h3>THAO TÁC</h3>
                        @if($canWarehouseEdit)
                            <button class="pt-btn pt-btn--soft" type="submit" form="emw8-stock-form"><i class="bi bi-floppy"></i> Lưu kiểm tra tồn</button>
                            <form method="POST" action="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}" data-emw8-manager-submit data-all-ready="{{ $allRowsReady ? '1' : '0' }}">
                                @csrf
                                <button class="pt-btn pt-btn--brand" type="submit" @disabled(! $allRowsReady)><i class="bi bi-send-check"></i> Gửi Quản lý phê duyệt</button>
                            </form>
                            @if(! $allRowsReady)<small>Chỉ gửi duyệt khi toàn bộ dòng ở trạng thái <strong>Đủ hàng</strong>.</small>@endif
                        @elseif($canManagerReview)
                            <form method="POST" action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}" data-emw8-review-form>
                                @csrf
                                <label><input type="radio" name="decision" value="approve" checked><span><strong>Phê duyệt chuyển xuất kho</strong><small>Toàn bộ dòng phải đủ hàng.</small></span></label>
                                <label><input type="radio" name="decision" value="return_warehouse"><span><strong>Trả Kho kiểm tra lại</strong><small>Sai tồn, kho cấp hoặc trạng thái.</small></span></label>
                                <label><input type="radio" name="decision" value="return_technical"><span><strong>Trả Kỹ thuật điều chỉnh</strong><small>Sai thương hiệu, model, SKU, thông số hoặc số lượng.</small></span></label>
                                <textarea class="pt-textarea" name="review_note" placeholder="Ghi chú duyệt hoặc lý do trả lại..."></textarea>
                                <button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-shield-check"></i> Xác nhận quyết định</button>
                            </form>
                        @else
                            <div class="emw8-readonly"><i class="bi bi-info-circle"></i><span>{{ $requestStatusLabel }}</span></div>
                        @endif
                    </article>
                </aside>
            </div>
        @endif
    </section>

    <section class="emw8-view" data-emw8-view="issue">
        @if(! $materialRequest)
            <article class="pt-card emw8-empty"><i class="bi bi-box-arrow-up-right"></i><div><h2>Chưa thể xuất kho</h2><p>Chưa có phiếu vật tư chính thức.</p></div></article>
        @else
            <div class="emw8-issue-layout">
                <article class="pt-card emw8-card">
                    <header class="emw8-card__head"><div><h2><i class="bi bi-box-arrow-up-right"></i> XUẤT KHO CÔNG TRÌNH</h2><p>Chỉ thực hiện sau khi Quản lý phê duyệt.</p></div><span class="emw8-role">KHO</span></header>

                    @if($materialRequest->status === 'issued')
                        <div class="emw8-success"><i class="bi bi-check-circle-fill"></i><div><strong>Đã xuất kho</strong><span>{{ optional($materialRequest->issued_at)->format('d/m/Y H:i') ?: '—' }} · Người nhận {{ $materialRequest->receiver?->name ?: '—' }}</span></div></div>
                    @elseif(! in_array($materialRequest->status, ['approved', 'preparing'], true))
                        <div class="emw8-lock"><i class="bi bi-lock-fill"></i><div><strong>Chưa được phép xuất</strong><span>Kho phải kiểm tra đủ hàng và Quản lý phải phê duyệt.</span></div></div>
                    @elseif($materialRequest->warehouse_status !== 'reserved')
                        <div class="emw8-next"><span>1</span><div><strong>Giữ hàng đã duyệt</strong><small>Hệ thống kiểm tra lại tồn và tự khóa serial khả dụng.</small></div></div>
                        @if($isWarehouseUser)
                            <form method="POST" action="{{ route('project-test.warehouse.reserve', $materialRequest) }}" data-confirm="Xác nhận giữ đủ hàng cho công trình?">@csrf<button class="pt-btn pt-btn--brand"><i class="bi bi-lock-fill"></i> Giữ hàng đã duyệt</button></form>
                        @endif
                    @else
                        <div class="emw8-success"><i class="bi bi-lock-fill"></i><div><strong>Đã giữ đủ hàng</strong><span>Sẵn sàng xuất kho và trừ tồn thật.</span></div></div>
                        @if($isWarehouseUser)
                            <div class="emw8-issue-actions">
                                <form method="POST" action="{{ route('project-test.warehouse.release', $materialRequest) }}" data-confirm="Bỏ giữ toàn bộ hàng của phiếu này?">@csrf<button class="pt-btn pt-btn--danger"><i class="bi bi-unlock"></i> Bỏ giữ hàng</button></form>
                                <form method="POST" action="{{ route('project-test.warehouse.issue', $materialRequest) }}" class="emw8-issue-form" data-confirm="Xác nhận xuất kho chính thức và trừ tồn?">
                                    @csrf
                                    <label><span>Người nhận vật tư</span><select class="pt-select" name="receiver_id" required><option value="">-- Chọn Kỹ thuật nhận hàng --</option>@foreach($technicians as $receiver)<option value="{{ $receiver->id }}">{{ $receiver->name }}</option>@endforeach</select></label>
                                    <label><span>Ghi chú xuất kho</span><textarea class="pt-textarea" name="issue_note" placeholder="Biên bản bàn giao, phương tiện, thời gian nhận..."></textarea></label>
                                    <button class="pt-btn pt-btn--brand"><i class="bi bi-box-arrow-up-right"></i> Xác nhận xuất kho</button>
                                </form>
                            </div>
                        @endif
                    @endif
                </article>
                <aside class="pt-card emw8-side-card"><h3>TÓM TẮT PHIẾU</h3><div><span>Mã phiếu</span><strong>{{ $materialRequest->code }}</strong></div><div><span>Tổng dòng</span><strong>{{ $totalRows }}</strong></div><div><span>Trạng thái</span><strong>{{ $requestStatusLabel }}</strong></div>@if($canViewCost)<div><span>Giá vốn tạm tính</span><strong>{{ number_format($totalCost, 0, ',', '.') }} đ</strong></div>@endif</aside>
            </div>
        @endif
    </section>

    <section class="emw8-view" data-emw8-view="return">
        <article class="pt-card emw8-empty"><i class="bi bi-arrow-return-left"></i><div><h2>Thu hồi vật tư</h2><p>Chỉ mở sau khi đã xuất kho. Mọi phiếu thu hồi phải hoàn tồn đúng lô và serial.</p></div></article>
    </section>
</div>

<template data-emw8-product-options>
    @foreach($products as $productOption)
        <option
            value="{{ (int) data_get($productOption, 'id', 0) }}"
            data-unit="{{ trim((string) data_get($productOption, 'unit', '')) ?: 'cái' }}"
            data-brand="{{ data_get($productOption, 'brand_name') }}"
            data-sku="{{ data_get($productOption, 'sku') }}"
            data-description="{{ data_get($productOption, 'description') }}"
            data-stock="{{ (float) data_get($productOption, 'total_stock_qty', 0) }}"
        >{{ data_get($productOption, 'name') }} · {{ data_get($productOption, 'brand_name') ?: 'Không rõ hãng' }} · SKU {{ data_get($productOption, 'sku') ?: '—' }}</option>
    @endforeach
</template>

<template data-emw8-tech-template>
    <tr data-emw8-tech-row>
        <td data-emw8-tech-index></td>
        <td><select class="pt-select" name="product_id[]" required data-emw8-product-select><option value="">-- Tìm và chọn đúng sản phẩm / SKU --</option></select><small data-emw8-product-help>Bắt buộc chọn đúng thương hiệu, model/chủng loại và SKU.</small></td>
        <td><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="1" required></td>
        <td><input class="pt-input" name="unit[]" value="cái" readonly data-emw8-unit></td>
        <td><input class="pt-input" name="item_note[]" placeholder="Thông số bắt buộc, vị trí lắp, lưu ý thi công..."></td>
        <td><button type="button" class="emw8-icon-btn is-danger" data-emw8-remove-row><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
