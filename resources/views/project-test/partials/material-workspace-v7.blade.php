{{--
    EGO Material Workflow V7
    Scope: Project detail -> Chuẩn bị vật tư only.
    Business flow:
    Technical confirms exact catalog product -> Warehouse checks exact SKU -> Manager approves -> Warehouse issues stock.
--}}
@php
    $selectedMaterialRequestId = (int) request('material_request', 0);
    $materialRequest = $selectedMaterialRequestId > 0
        ? $project->materialRequests->firstWhere('id', $selectedMaterialRequestId)
        : $project->materialRequests->sortByDesc('id')->first();

    $items = $materialRequest?->items ?? collect();
    $totalRows = $items->count();

    // EGO_MATERIAL_SAFE_ACCESS_V1: dữ liệu cũ/query stdClass có thể thiếu cột unit.
    $materialUnit = static function ($record, string $fallback = 'cái'): string {
        $unit = trim((string) data_get($record, 'unit', ''));

        return $unit !== '' ? $unit : $fallback;
    };

    // EGO_MATERIAL_LAZY_PRODUCT_OPTIONS_V2
    // Chỉ render danh mục sản phẩm một lần; mỗi dòng chỉ giữ option đang chọn.
    // JavaScript sẽ nạp danh mục vào đúng select khi người dùng tương tác.
    $productsById = collect($products)->keyBy(fn ($product) => (int) data_get($product, 'id', 0));

    $isWarehouseUser = (bool) ($can['warehouse'] ?? false)
        || auth()->user()?->hasAnyRole(['warehouse', 'kho']);
    $isManagerUser = (bool) ($can['admin'] ?? false)
        || auth()->user()?->hasAnyRole(['admin', 'management', 'manager']);
    $isTechnicalUser = (bool) ($can['technical'] ?? false);

    $canViewCost = $isWarehouseUser
        || $isManagerUser
        || auth()->user()?->can('finance.project_profit.view');

    $unconfirmedRows = $items->filter(fn ($item) => (int) $item->product_id <= 0)->count();
    $canCreateTechnicalRequest = ! $materialRequest
        && $isTechnicalUser
        && in_array($project->status, ['materials_pending', 'materials_revision'], true);
    $canEditTechnicalRequest = $materialRequest
        && $isTechnicalUser
        && $materialRequest->status !== 'issued'
        && (
            in_array($materialRequest->status, ['revision', 'pending_admin'], true)
            || $project->status === 'materials_revision'
            || $unconfirmedRows > 0
        );

    $legacyApprovedNeedsCheck = $materialRequest
        && $materialRequest->status === 'approved'
        && blank($materialRequest->warehouse_status);

    $canWarehouseEdit = $materialRequest
        && $isWarehouseUser
        && (
            in_array($materialRequest->status, ['warehouse_check'], true)
            || $legacyApprovedNeedsCheck
        )
        && $materialRequest->warehouse_status !== 'reserved';

    $canManagerReview = $materialRequest
        && $isManagerUser
        && $materialRequest->status === 'pending_manager';

    $requestStatusLabels = [
        'pending_admin' => 'Chờ Kỹ thuật xác nhận đúng sản phẩm',
        'warehouse_check' => 'Kho đang kiểm tra tồn',
        'pending_manager' => 'Chờ Quản lý phê duyệt',
        'approved' => 'Đã duyệt · Chờ Kho giữ hàng',
        'preparing' => 'Kho đã giữ hàng · Chờ xuất',
        'revision' => 'Chờ Sales/Kỹ thuật điều chỉnh',
        'issued' => 'Đã xuất kho',
    ];
    $requestStatusLabel = $materialRequest
        ? ($legacyApprovedNeedsCheck
            ? 'Kho đang kiểm tra tồn'
            : ($requestStatusLabels[$materialRequest->status] ?? 'Đang xử lý'))
        : 'Chưa lập phiếu vật tư';

    $statusMeta = [
        'ready' => ['label' => 'Đủ hàng', 'tone' => 'success', 'icon' => 'bi-check-circle-fill'],
        'reserved' => ['label' => 'Đã giữ hàng', 'tone' => 'success', 'icon' => 'bi-lock-fill'],
        'issued' => ['label' => 'Đã xuất', 'tone' => 'success', 'icon' => 'bi-box-arrow-up-right'],
        'shortage' => ['label' => 'Thiếu hàng', 'tone' => 'danger', 'icon' => 'bi-x-circle-fill'],
        'transfer' => ['label' => 'Cần điều chuyển', 'tone' => 'warning', 'icon' => 'bi-arrow-left-right'],
        'waiting_purchase' => ['label' => 'Chờ nhập', 'tone' => 'pending', 'icon' => 'bi-cart-plus-fill'],
        '' => ['label' => 'Chưa kiểm tra', 'tone' => 'neutral', 'icon' => 'bi-clock'],
    ];

    $checkedRows = 0;
    $readyRows = 0;
    $shortageRows = 0;
    $transferRows = 0;
    $waitingRows = 0;
    $totalCost = 0.0;
    $missingCostRows = 0;

    foreach ($items as $materialItem) {
        $allocation = ($materialItem->allocations ?? collect())->first();
        if (! $allocation) {
            continue;
        }

        $checkedRows++;
        if (in_array($allocation->status, ['ready', 'reserved', 'issued'], true)) {
            $readyRows++;
        } elseif ($allocation->status === 'shortage') {
            $shortageRows++;
        } elseif ($allocation->status === 'transfer') {
            $transferRows++;
        } elseif ($allocation->status === 'waiting_purchase') {
            $waitingRows++;
        }

        $qtyForCost = (float) ($allocation->allocated_quantity ?? 0);
        if ($allocation->unit_cost !== null && (float) $allocation->unit_cost > 0) {
            $totalCost += (float) $allocation->unit_cost * $qtyForCost;
        } else {
            $missingCostRows++;
        }
    }

    $checkedPercent = $totalRows > 0 ? (int) round(($checkedRows / $totalRows) * 100) : 0;

    $materialView = request('material_view');
    if (! in_array($materialView, ['needs', 'stock', 'issue', 'return'], true)) {
        $materialView = ($canCreateTechnicalRequest || $canEditTechnicalRequest || (! $isWarehouseUser && ! $isManagerUser))
            ? 'needs'
            : 'stock';
    }

    $technicalFormAction = $materialRequest
        ? route('project-test.materials.update', [$project, $materialRequest])
        : route('project-test.materials.submit', $project);

    // EGO_MATERIAL_ONE_ROW_FORM_V3
    // Khi mở biểu mẫu chỉ tạo 01 dòng trống. Nếu validate lỗi, dựng lại đúng số dòng người dùng đã nhập.
    // Danh sách cũ chỉ được thay thế sau khi người dùng bấm xác nhận và đồng ý cảnh báo.
    $oldProductIds = old('product_id');
    $oldRowCount = is_array($oldProductIds) ? max(1, count($oldProductIds)) : 1;
    $technicalFormRows = collect(range(0, $oldRowCount - 1))->map(fn () => null);
    $replacingExistingItems = (bool) ($materialRequest && $items->isNotEmpty());
@endphp

<div
    class="emw7-workspace"
    data-emw7-root
    data-material-view="{{ $materialView }}"
    data-can-warehouse-edit="{{ $canWarehouseEdit ? '1' : '0' }}"
    data-page-size="10"
>
    <section class="pt-card emw7-summary">
        <div class="emw7-summary__identity">
            <span class="emw7-summary__icon"><i class="bi bi-box-seam"></i></span>
            <div>
                <small>CHUẨN BỊ VẬT TƯ{{ $materialRequest ? ' · '.$materialRequest->code : '' }}</small>
                <h2>{{ $project->name }}</h2>
                <p>
                    @if($materialRequest)
                        Ngày cần: {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}
                        · Người lập: {{ $materialRequest->requester?->name ?: 'Kỹ thuật' }}
                    @else
                        Kỹ thuật chọn đúng sản phẩm theo hợp đồng trước khi chuyển Kho.
                    @endif
                </p>
                <span class="emw7-request-status">{{ $requestStatusLabel }}</span>
            </div>
        </div>

        <div class="emw7-summary__kpis">
            <div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
            <div><small>Đã kiểm tra</small><strong data-emw7-kpi-checked>{{ $checkedRows }}/{{ $totalRows }} dòng</strong></div>
            <div class="emw7-progress-card">
                <small>Tiến độ</small>
                <strong data-emw7-kpi-percent>{{ $checkedPercent }}%</strong>
                <span><i data-emw7-progress style="width:{{ $checkedPercent }}%"></i></span>
            </div>
        </div>
    </section>

    {{-- NEEDS / TECHNICAL PRODUCT CONFIRMATION --}}
    <section class="emw7-view" data-emw7-view="needs">
        @if($canCreateTechnicalRequest || $canEditTechnicalRequest)
            <article class="pt-card emw7-technical-card">
                <header class="emw7-section-head">
                    <div>
                        <h2><i class="bi bi-list-check"></i> XÁC NHẬN VẬT TƯ THEO HỢP ĐỒNG</h2>
                        <p>Kỹ thuật chọn trực tiếp đúng sản phẩm trong danh mục Kho. Kho không được thay đổi thương hiệu, model hoặc SKU.</p>
                    </div>
                    <span class="emw7-role-chip">KỸ THUẬT</span>
                </header>

                @if($materialRequest && $materialRequest->review_note)
                    <div class="emw7-return-note">
                        <i class="bi bi-arrow-return-left"></i>
                        <div><strong>Lý do trả lại</strong><span>{{ $materialRequest->review_note }}</span></div>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ $technicalFormAction }}"
                    data-emw7-technical-form
                    data-existing-item-count="{{ $totalRows }}"
                    data-replace-existing="{{ $replacingExistingItems ? '1' : '0' }}"
                >
                    @csrf
                    <div class="emw7-tech-meta">
                        <label>
                            <span>Ngày cần vật tư</span>
                            <input class="pt-input" type="date" name="needed_at" value="{{ old('needed_at', optional($materialRequest?->needed_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required>
                        </label>
                        <label>
                            <span>Ghi chú chung</span>
                            <input class="pt-input" name="request_note" value="{{ old('request_note', $materialRequest?->request_note) }}" placeholder="Phạm vi thi công, yêu cầu giao hàng...">
                        </label>
                    </div>

                    <div class="emw7-contract-rule">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Khóa đúng vật tư đã chốt</strong>
                            <small>Kiểm tra đủ thương hiệu, model/chủng loại, SKU, thông số và số lượng trước khi gửi Kho.</small>
                        </div>
                    </div>

                    @if($replacingExistingItems)
                        <div class="emw7-contract-alert">
                            <i class="bi bi-arrow-repeat"></i>
                            <div>
                                <strong>Biểu mẫu mở 01 dòng để lập lại danh sách gọn hơn.</strong>
                                <small>Khi xác nhận, danh sách mới sẽ thay thế {{ $totalRows }} dòng vật tư hiện tại. Hệ thống chỉ thay đổi sau khi bạn đồng ý cảnh báo.</small>
                            </div>
                        </div>
                    @endif

                    <div class="emw7-tech-table-scroll">
                        <table class="emw7-tech-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Sản phẩm trong danh mục Kho</th>
                                    <th>SL cần</th>
                                    <th>ĐVT</th>
                                    <th>Thông số / ghi chú</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-emw7-tech-rows>
                                @foreach($technicalFormRows as $rowIndex => $technicalItem)
                                    @php
                                        $selectedProductId = (int) old("product_id.{$rowIndex}", (int) data_get($technicalItem, 'product_id', 0));
                                        $legacyName = data_get($technicalItem, 'item_name');
                                        $technicalUnit = $materialUnit(
                                            data_get($technicalItem, 'product'),
                                            $materialUnit($technicalItem)
                                        );
                                        $selectedProductOption = $selectedProductId > 0
                                            ? $productsById->get($selectedProductId)
                                            : null;
                                        $selectedProductOption = $selectedProductOption ?: data_get($technicalItem, 'product');
                                        $selectedProductName = (string) data_get($selectedProductOption, 'name', $legacyName ?: 'Sản phẩm đã chọn');
                                        $selectedProductSku = (string) data_get($selectedProductOption, 'sku', '');
                                        $selectedProductStock = (float) data_get($selectedProductOption, 'total_stock_qty', 0);
                                    @endphp
                                    <tr data-emw7-tech-row>
                                        <td data-emw7-tech-index>{{ $rowIndex + 1 }}</td>
                                        <td>
                                            @if($technicalItem)<input type="hidden" name="item_id[]" value="{{ (int) data_get($technicalItem, 'id', 0) }}">@else<input type="hidden" name="item_id[]" value="">@endif
                                            <select class="pt-select emw7-product-select" name="product_id[]" required data-emw7-product-select data-emw7-hydrated="0">
                                                <option value="">-- Tìm và chọn đúng sản phẩm / SKU --</option>
                                                @if($selectedProductId > 0)
                                                    <option
                                                        value="{{ $selectedProductId }}"
                                                        data-unit="{{ $materialUnit($selectedProductOption, $technicalUnit) }}"
                                                        data-stock="{{ $selectedProductStock }}"
                                                        data-description="{{ data_get($selectedProductOption, 'description') }}"
                                                        selected
                                                    >
                                                        {{ $selectedProductName }}{{ $selectedProductSku !== '' ? ' · SKU '.$selectedProductSku : '' }} · Tồn tham khảo {{ rtrim(rtrim(number_format($selectedProductStock, 3, '.', ''), '0'), '.') }}
                                                    </option>
                                                @endif
                                            </select>
                                            <small class="emw7-product-help" data-emw7-product-help>
                                                {{ $legacyName && ! $selectedProductId ? 'Nhu cầu cũ: '.$legacyName.' · Cần chọn đúng SKU.' : 'Chọn theo đúng hợp đồng/phương án kỹ thuật.' }}
                                            </small>
                                        </td>
                                        <td><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="{{ old("quantity.{$rowIndex}", data_get($technicalItem, 'quantity', 1) ?: 1) }}" required></td>
                                        <td><input class="pt-input" name="unit[]" value="{{ old("unit.{$rowIndex}", $technicalUnit) }}" readonly data-emw7-unit></td>
                                        <td><input class="pt-input" name="item_note[]" value="{{ old("item_note.{$rowIndex}", data_get($technicalItem, 'note')) }}" placeholder="Thông số bắt buộc, vị trí lắp, lưu ý thi công..."></td>
                                        <td><button type="button" class="pt-btn pt-btn--danger pt-btn--sm" data-emw7-remove-tech-row title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="emw7-tech-actions">
                        <button type="button" class="pt-btn pt-btn--soft" data-emw7-add-tech-row><i class="bi bi-plus-lg"></i> Thêm dòng</button>
                        <span class="emw7-tech-row-count" data-emw7-tech-count>1 dòng</span>
                        <button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-send-check"></i> Xác nhận đúng mã hàng & gửi Kho kiểm tra</button>
                    </div>
                </form>
            </article>
        @elseif($materialRequest)
            <article class="pt-card emw7-readonly-needs">
                <header class="emw7-section-head">
                    <div><h2><i class="bi bi-list-check"></i> NHU CẦU KỸ THUẬT</h2><p>Sản phẩm đã được khóa theo mã hàng Kỹ thuật xác nhận.</p></div>
                </header>
                <div class="emw7-simple-table-scroll">
                    <table class="emw7-simple-table">
                        <thead><tr><th>STT</th><th>Sản phẩm theo hợp đồng</th><th>SKU</th><th>SL cần</th><th>Ghi chú</th></tr></thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                @php
                                    $readonlyUnit = $materialUnit($item);
                                    $readonlyProduct = data_get($item, 'product');
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ data_get($readonlyProduct, 'name') ?: data_get($item, 'item_name', 'Vật tư') }}</strong></td>
                                    <td>{{ data_get($readonlyProduct, 'sku') ?: 'Chưa xác nhận' }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) data_get($item, 'quantity', 0), 3, '.', ''), '0'), '.') }} {{ $readonlyUnit }}</td>
                                    <td>{{ data_get($item, 'note') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Chưa có vật tư.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @else
            <article class="pt-card emw7-empty">
                <i class="bi bi-box-seam"></i>
                <div><h2>Chưa có phiếu vật tư</h2><p>Công trình chưa đến bước Kỹ thuật lập danh sách vật tư.</p></div>
            </article>
        @endif
    </section>

    {{-- STOCK CHECK --}}
    <section class="emw7-view" data-emw7-view="stock">
        @if(! $materialRequest)
            <article class="pt-card emw7-empty"><i class="bi bi-box-seam"></i><div><h2>Chưa có phiếu vật tư</h2><p>Kỹ thuật cần xác nhận sản phẩm trước khi Kho kiểm tra tồn.</p></div></article>
        @else
            <div class="emw7-layout">
                <main class="emw7-main">
                    <article class="pt-card emw7-table-card">
                        <header class="emw7-section-head">
                            <div>
                                <h2><i class="bi bi-clipboard2-check"></i> KIỂM TRA TỒN KHO</h2>
                                <p>Kho chỉ kiểm tra đúng sản phẩm/SKU Kỹ thuật đã xác nhận; không được đổi hàng.</p>
                            </div>
                            @if($canWarehouseEdit && $unconfirmedRows === 0)
                                <button type="button" class="pt-btn pt-btn--soft" data-emw7-auto-warehouse><i class="bi bi-diagram-3"></i> Đề xuất kho tự động</button>
                            @endif
                        </header>

                        @if($unconfirmedRows > 0)
                            <div class="emw7-contract-alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    <strong>{{ $unconfirmedRows }} dòng chưa có SKU chuẩn.</strong>
                                    <small>Trả Kỹ thuật chọn đúng sản phẩm trong danh mục Kho trước khi kiểm tra tồn.</small>
                                </div>
                            </div>
                        @endif

                        <div class="emw7-toolbar">
                            <label class="emw7-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm vật tư, tên sản phẩm hoặc SKU..." data-emw7-search></label>
                            <select class="pt-select" data-emw7-warehouse-filter><option value="">Tất cả kho</option></select>
                            <select class="pt-select" data-emw7-status-filter>
                                <option value="">Tất cả trạng thái</option>
                                <option value="ready">Đủ hàng</option>
                                <option value="shortage">Thiếu hàng</option>
                                <option value="transfer">Cần điều chuyển</option>
                                <option value="waiting_purchase">Chờ nhập</option>
                                <option value="unchecked">Chưa kiểm tra</option>
                            </select>
                            <button type="button" class="pt-btn pt-btn--soft" data-emw7-filter><i class="bi bi-funnel"></i> Bộ lọc</button>
                            <button type="button" class="pt-btn pt-btn--soft emw7-export" data-emw7-export><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
                        </div>

                        @if($canWarehouseEdit)
                            <form id="emw7-stock-form" method="POST" action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}" data-emw7-stock-form>
                                @csrf
                        @endif

                        <div class="emw7-table-scroll">
                            <table class="emw7-table" data-emw7-table>
                                <thead>
                                    <tr>
                                        <th>STT</th>
                                        <th>Vật tư kỹ thuật yêu cầu</th>
                                        <th>SL yêu cầu</th>
                                        <th>Sản phẩm theo yêu cầu</th>
                                        @if($canViewCost)<th>Giá vốn</th>@endif
                                        <th>Tồn khả dụng</th>
                                        <th>Kho / vị trí</th>
                                        <th>SL chuẩn bị</th>
                                        <th>Tình trạng</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $index => $item)
                                        @php
                                            $allocation = ($item->allocations ?? collect())->first();
                                            $product = $item->product ?: $allocation?->product;
                                            $productId = (int) ($item->product_id ?: 0);
                                            $warehouse = $allocation?->warehouse;
                                            $available = (float) ($allocation?->available_snapshot ?? 0);
                                            $prepared = (float) ($allocation?->allocated_quantity ?? 0);
                                            $rowStatus = (string) ($allocation?->status ?? '');
                                            $meta = $statusMeta[$rowStatus] ?? $statusMeta[''];
                                            $unitCost = $allocation?->unit_cost !== null ? (float) $allocation->unit_cost : null;
                                            $requiredQuantity = (float) data_get($item, 'quantity', 0);
                                            $itemUnit = $materialUnit($item);
                                            $displayUnit = $materialUnit($product, $itemUnit);
                                            $shortageQuantity = max(0, $requiredQuantity - min($available, $prepared));
                                            $searchText = mb_strtolower(implode(' ', array_filter([$item->item_name, $item->note, $product?->name, $product?->sku, $warehouse?->name, $allocation?->note])));
                                        @endphp
                                        <tr
                                            class="emw7-item-row"
                                            data-emw7-row
                                            data-row-index="{{ $index + 1 }}"
                                            data-search="{{ $searchText }}"
                                            data-status="{{ $rowStatus ?: 'unchecked' }}"
                                            data-warehouse="{{ $warehouse?->id ?: '' }}"
                                            data-required="{{ $requiredQuantity }}"
                                            data-unit="{{ $displayUnit }}"
                                            data-unit-cost="{{ $unitCost ?: 0 }}"
                                            data-selected-warehouse-id="{{ $warehouse?->id ?: '' }}"
                                        >
                                            <td class="emw7-stt">{{ $index + 1 }}</td>
                                            <td class="emw7-need-cell">
                                                <span class="emw7-product-thumb">
                                                    @if($product?->image_url)
                                                        <img src="{{ asset($product->image_url) }}" alt="{{ $product->name }}" loading="lazy">
                                                    @else
                                                        <i class="bi bi-box"></i>
                                                    @endif
                                                </span>
                                                <div><strong>{{ $item->item_name }}</strong><small>{{ $item->note ?: ($product?->description ?: 'Theo hợp đồng/phương án đã xác nhận') }}</small></div>
                                            </td>
                                            <td class="emw7-number-cell"><strong>{{ rtrim(rtrim(number_format($requiredQuantity, 3, '.', ''), '0'), '.') }}</strong><small>{{ $itemUnit }}</small></td>
                                            <td class="emw7-product-cell">
                                                <strong>{{ $product?->name ?: 'Chưa xác nhận mã hàng' }}</strong>
                                                <small>{{ $product?->sku ? 'SKU: '.$product->sku : 'Kỹ thuật phải chọn đúng SKU trước' }}</small>
                                                @if($canWarehouseEdit)<input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $productId }}">@endif
                                            </td>
                                            @if($canViewCost)
                                                <td class="emw7-money-cell"><strong data-emw7-unit-cost>{{ $unitCost && $unitCost > 0 ? number_format($unitCost, 0, ',', '.') : '—' }}</strong><small>VND/ĐVT</small></td>
                                            @endif
                                            <td class="emw7-stock-cell">
                                                <strong class="{{ $available > 0 ? 'is-positive' : 'is-zero' }}" data-emw7-available>{{ rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') }} {{ $displayUnit }}</strong>
                                                <small data-emw7-stock-help>{{ $available > 0 ? 'Có thể cấp tại kho chọn' : 'Chưa có tồn khả dụng' }}</small>
                                            </td>
                                            <td class="emw7-warehouse-cell">
                                                @if($canWarehouseEdit && $productId > 0)
                                                    <select class="pt-select" name="items[{{ $item->id }}][warehouse_id]" data-emw7-warehouse-select data-product-id="{{ $productId }}" data-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}" required>
                                                        @if($warehouse)<option value="{{ $warehouse->id }}" selected>{{ $warehouse->name }}{{ $warehouse->location ? ' · '.$warehouse->location : '' }}</option>@else<option value="">-- Chọn kho cấp hàng --</option>@endif
                                                    </select>
                                                    <small data-emw7-location>{{ $warehouse?->location ?: 'Chọn kho để xem tồn và vị trí' }}</small>
                                                @else
                                                    <strong>{{ $warehouse?->name ?: '—' }}</strong><small>{{ $warehouse?->location ?: 'Chưa xác định vị trí' }}</small>
                                                @endif
                                            </td>
                                            <td class="emw7-prepare-cell">
                                                @if($canWarehouseEdit && $productId > 0)
                                                    <div class="emw7-qty-control"><input class="pt-input" type="number" min="0" max="{{ $requiredQuantity }}" step="0.001" name="items[{{ $item->id }}][quantity]" value="{{ $allocation ? $prepared : $requiredQuantity }}" data-emw7-quantity required><span>{{ $itemUnit }}</span></div>
                                                    <small data-emw7-qty-error></small>
                                                @else
                                                    <strong>{{ rtrim(rtrim(number_format($prepared, 3, '.', ''), '0'), '.') }} {{ $itemUnit }}</strong>
                                                @endif
                                            </td>
                                            <td class="emw7-status-cell">
                                                @if($canWarehouseEdit && $productId > 0)
                                                    <select class="pt-select emw7-status-select" name="items[{{ $item->id }}][check_status]" data-emw7-row-status required>
                                                        <option value="ready" @selected(in_array($rowStatus, ['', 'ready'], true))>Đủ hàng</option>
                                                        <option value="shortage" @selected($rowStatus === 'shortage')>Thiếu hàng</option>
                                                        <option value="transfer" @selected($rowStatus === 'transfer')>Cần điều chuyển</option>
                                                        <option value="waiting_purchase" @selected($rowStatus === 'waiting_purchase')>Chờ nhập</option>
                                                    </select>
                                                @else
                                                    <span class="emw7-badge emw7-badge--{{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}</span>
                                                @endif
                                            </td>
                                            <td class="emw7-note-cell">
                                                @if($canWarehouseEdit && $productId > 0)
                                                    <input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $allocation?->note }}" maxlength="1000" placeholder="Điều chuyển, thời gian nhập..." data-emw7-row-note>
                                                @else
                                                    <span>{{ $allocation?->note ?: '—' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr class="emw7-warning-row" data-emw7-warning-row data-parent-index="{{ $index + 1 }}" @if(! in_array($rowStatus, ['shortage', 'transfer', 'waiting_purchase'], true)) hidden @endif>
                                            <td></td>
                                            <td colspan="{{ $canViewCost ? 9 : 8 }}">
                                                <div class="emw7-warning-box emw7-warning-box--{{ $meta['tone'] }}">
                                                    <i class="bi {{ $meta['icon'] }}"></i>
                                                    <div>
                                                        <strong data-emw7-warning-title>
                                                            @if($rowStatus === 'transfer') Cần điều chuyển từ kho khác
                                                            @elseif($rowStatus === 'waiting_purchase') Chờ nhập / mua bổ sung
                                                            @else Thiếu {{ rtrim(rtrim(number_format($shortageQuantity, 3, '.', ''), '0'), '.') }} {{ $itemUnit }}
                                                            @endif
                                                        </strong>
                                                        <small data-emw7-warning-detail>{{ $allocation?->note ?: 'Kho cập nhật phương án xử lý trước khi gửi Quản lý.' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ $canViewCost ? 10 : 9 }}"><div class="emw7-empty-row">Phiếu chưa có dòng vật tư.</div></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($canWarehouseEdit)</form>@endif

                        <footer class="emw7-table-footer">
                            <span data-emw7-result-summary>Hiển thị {{ min(10, $totalRows) }} trong tổng số {{ $totalRows }} dòng</span>
                            <div class="emw7-pagination" data-emw7-pagination></div>
                            <select class="pt-select" data-emw7-page-size><option value="5">5 / trang</option><option value="10" selected>10 / trang</option><option value="20">20 / trang</option><option value="50">50 / trang</option></select>
                        </footer>
                    </article>
                </main>

                <aside class="emw7-side">
                    <article class="pt-card emw7-side-card">
                        <header><h3>TRẠNG THÁI KIỂM TRA TỒN</h3></header>
                        <div class="emw7-status-list">
                            <div><span class="is-neutral"><i class="bi bi-list-ul"></i></span><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
                            <div><span class="is-info"><i class="bi bi-clipboard-check"></i></span><small>Đã kiểm tra</small><strong data-emw7-side-checked>{{ $checkedRows }} dòng</strong></div>
                            <div><span class="is-success"><i class="bi bi-check-circle"></i></span><small>Đủ hàng</small><strong data-emw7-side-ready>{{ $readyRows }} dòng</strong></div>
                            <div><span class="is-danger"><i class="bi bi-x-circle"></i></span><small>Thiếu hàng</small><strong data-emw7-side-shortage>{{ $shortageRows }} dòng</strong></div>
                            <div><span class="is-warning"><i class="bi bi-arrow-left-right"></i></span><small>Cần điều chuyển</small><strong data-emw7-side-transfer>{{ $transferRows }} dòng</strong></div>
                            <div><span class="is-pending"><i class="bi bi-cart-plus"></i></span><small>Chờ nhập</small><strong data-emw7-side-waiting>{{ $waitingRows }} dòng</strong></div>
                        </div>
                    </article>

                    @if($canViewCost)
                        <article class="pt-card emw7-side-card emw7-cost-card">
                            <header><h3>GIÁ VỐN TẠM TÍNH</h3></header>
                            <p data-emw7-total-cost>{{ number_format($totalCost, 0, ',', '.') }} đ</p>
                            <small>Giá vốn nội bộ, chỉ Kho/Quản lý/Admin được xem.</small>
                            @if($missingCostRows > 0)<div class="emw7-cost-warning"><i class="bi bi-exclamation-triangle"></i> {{ $missingCostRows }} dòng chưa có giá vốn.</div>@endif
                        </article>
                    @endif

                    @if($canWarehouseEdit)
                        <article class="pt-card emw7-side-card">
                            <header><h3>GHI CHÚ CHUNG</h3></header>
                            <textarea class="pt-textarea" name="warehouse_note" form="emw7-stock-form" maxlength="500" placeholder="Tình trạng hàng, phương án điều chuyển, thời gian dự kiến nhập..." data-emw7-general-note>{{ $materialRequest->issue_note }}</textarea>
                            <small class="emw7-char-count"><span data-emw7-note-count>{{ mb_strlen((string) $materialRequest->issue_note) }}</span>/500</small>
                        </article>
                    @endif

                    <article class="pt-card emw7-side-card emw7-action-card">
                        <header><h3>THAO TÁC</h3></header>

                        @if($canWarehouseEdit)
                            <button class="pt-btn pt-btn--soft emw7-full-button" type="submit" form="emw7-stock-form" @disabled($unconfirmedRows > 0)><i class="bi bi-floppy"></i> Lưu kiểm tra</button>
                            <form method="POST" action="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}" data-emw7-manager-submit-form>
                                @csrf
                                <button class="pt-btn pt-btn--brand emw7-full-button" type="submit" @disabled($unconfirmedRows > 0)><i class="bi bi-send-check"></i> Gửi Quản lý phê duyệt</button>
                            </form>
                            @if($unconfirmedRows > 0)<small class="emw7-action-warning">Kỹ thuật phải xác nhận đủ SKU trước khi Kho xử lý.</small>@endif
                        @elseif($canManagerReview)
                            <form method="POST" action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}" data-emw7-approval-form>
                                @csrf
                                <label class="emw7-decision"><input type="radio" name="decision" value="approve" checked><span><strong>Phê duyệt & chuyển xuất kho</strong><small>Chỉ hợp lệ khi tất cả dòng đều đủ hàng.</small></span></label>
                                <label class="emw7-decision"><input type="radio" name="decision" value="return_warehouse"><span><strong>Trả Kho kiểm tra lại</strong><small>Sai tồn, kho/vị trí, số lượng hoặc giá vốn.</small></span></label>
                                <label class="emw7-decision"><input type="radio" name="decision" value="return_technical"><span><strong>Trả Sales/Kỹ thuật điều chỉnh</strong><small>Sai thương hiệu, model, SKU hoặc số lượng hợp đồng.</small></span></label>
                                <textarea class="pt-textarea" name="review_note" maxlength="3000" placeholder="Ghi chú phê duyệt hoặc lý do trả lại..." data-emw7-review-note></textarea>
                                <button class="pt-btn pt-btn--brand emw7-full-button" type="submit" data-emw7-review-submit><i class="bi bi-shield-check"></i> Phê duyệt & chuyển xuất kho</button>
                            </form>
                        @else
                            <div class="emw7-readonly-state"><i class="bi bi-info-circle"></i><div><strong>{{ $requestStatusLabel }}</strong><small>Tài khoản hiện tại chỉ được theo dõi.</small></div></div>
                        @endif

                        <button type="button" class="pt-btn pt-btn--soft emw7-full-button" data-emw7-export><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
                    </article>
                </aside>
            </div>
        @endif
    </section>

    {{-- ISSUE --}}
    <section class="emw7-view" data-emw7-view="issue">
        @if(! $materialRequest)
            <article class="pt-card emw7-empty"><i class="bi bi-lock"></i><div><h2>Chưa thể xuất kho</h2><p>Chưa có phiếu vật tư.</p></div></article>
        @else
            <div class="emw7-issue-grid">
                <article class="pt-card emw7-issue-card">
                    <header class="emw7-section-head"><div><h2><i class="bi bi-box-arrow-up-right"></i> XUẤT KHO CÔNG TRÌNH</h2><p>Chỉ mở sau khi Quản lý phê duyệt.</p></div></header>

                    @if($materialRequest->status === 'issued')
                        <div class="emw7-success-state"><i class="bi bi-check-circle-fill"></i><div><strong>Phiếu đã xuất kho</strong><span>Xuất lúc {{ optional($materialRequest->issued_at)->format('d/m/Y H:i') ?: '—' }} · Người nhận {{ $materialRequest->receiver?->name ?: '—' }}</span></div></div>
                    @elseif(! in_array($materialRequest->status, ['approved', 'preparing'], true))
                        <div class="emw7-locked-state"><i class="bi bi-lock-fill"></i><div><strong>Chưa được phép xuất kho</strong><span>Kho phải kiểm tra tồn, gửi Quản lý và chờ phê duyệt.</span></div></div>
                    @elseif($materialRequest->warehouse_status !== 'reserved')
                        <div class="emw7-issue-step"><span>1</span><div><strong>Giữ hàng trước khi xuất</strong><small>Hệ thống kiểm tra lại tồn; sản phẩm quản lý serial sẽ được tự động chọn serial khả dụng.</small></div></div>
                        @if($isWarehouseUser)
                            <form method="POST" action="{{ route('project-test.warehouse.reserve', $materialRequest) }}" data-confirm="Xác nhận giữ đủ hàng cho công trình?">@csrf<button class="pt-btn pt-btn--brand"><i class="bi bi-lock-fill"></i> Giữ hàng đã duyệt</button></form>
                        @endif
                    @else
                        <div class="emw7-success-state"><i class="bi bi-lock-fill"></i><div><strong>Đã giữ đủ hàng</strong><span>Sẵn sàng xác nhận xuất kho và trừ tồn thực tế.</span></div></div>
                        @if($isWarehouseUser)
                            <div class="emw7-issue-actions">
                                <form method="POST" action="{{ route('project-test.warehouse.release', $materialRequest) }}" data-confirm="Bỏ giữ toàn bộ hàng của phiếu này?">@csrf<button class="pt-btn pt-btn--danger"><i class="bi bi-unlock"></i> Bỏ giữ hàng</button></form>
                                <form method="POST" action="{{ route('project-test.warehouse.issue', $materialRequest) }}" class="emw7-issue-form" data-confirm="Xác nhận xuất kho chính thức và trừ tồn?">
                                    @csrf
                                    <label><span>Người nhận vật tư</span><select class="pt-select" name="receiver_id" required><option value="">-- Chọn Kỹ thuật nhận hàng --</option>@foreach($technicians as $receiver)<option value="{{ $receiver->id }}">{{ $receiver->name }}</option>@endforeach</select></label>
                                    <label><span>Ghi chú xuất kho</span><textarea class="pt-textarea" name="issue_note" placeholder="Biên bản bàn giao, phương tiện, thời gian nhận..."></textarea></label>
                                    <button class="pt-btn pt-btn--brand"><i class="bi bi-box-arrow-up-right"></i> Xác nhận xuất kho</button>
                                </form>
                            </div>
                        @endif
                    @endif
                </article>

                <aside class="pt-card emw7-issue-summary">
                    <h3>TÓM TẮT PHIẾU</h3>
                    <div><small>Mã phiếu</small><strong>{{ $materialRequest->code }}</strong></div>
                    <div><small>Tổng dòng</small><strong>{{ $totalRows }}</strong></div>
                    <div><small>Trạng thái</small><strong>{{ $requestStatusLabel }}</strong></div>
                    @if($canViewCost)<div><small>Giá vốn tạm tính</small><strong>{{ number_format($totalCost, 0, ',', '.') }} đ</strong></div>@endif
                </aside>
            </div>
        @endif
    </section>

    {{-- RETURN --}}
    <section class="emw7-view" data-emw7-view="return">
        <article class="pt-card emw7-empty"><i class="bi bi-arrow-return-left"></i><div><h2>Thu hồi vật tư</h2><p>Nghiệp vụ thu hồi chỉ mở sau khi có phát sinh xuất kho. Mọi điều chỉnh phải ghi lịch sử và hoàn tồn đúng lô/serial.</p></div></article>
    </section>
</div>

{{-- Danh mục sản phẩm chỉ xuất hiện một lần trong HTML. --}}
<template data-emw7-product-options>
    @foreach($products as $productOption)
        @php
            $templateProductId = (int) data_get($productOption, 'id', 0);
            $templateProductName = (string) data_get($productOption, 'name', 'Sản phẩm');
            $templateProductSku = (string) data_get($productOption, 'sku', '');
            $templateProductStock = (float) data_get($productOption, 'total_stock_qty', 0);
        @endphp
        <option value="{{ $templateProductId }}" data-unit="{{ $materialUnit($productOption) }}" data-stock="{{ $templateProductStock }}" data-description="{{ data_get($productOption, 'description') }}">{{ $templateProductName }}{{ $templateProductSku !== '' ? ' · SKU '.$templateProductSku : '' }} · Tồn tham khảo {{ rtrim(rtrim(number_format($templateProductStock, 3, '.', ''), '0'), '.') }}</option>
    @endforeach
</template>

<template data-emw7-tech-template>
    <tr data-emw7-tech-row>
        <td data-emw7-tech-index></td>
        <td>
            <input type="hidden" name="item_id[]" value="">
            <select class="pt-select emw7-product-select" name="product_id[]" required data-emw7-product-select data-emw7-hydrated="0">
                <option value="">-- Tìm và chọn đúng sản phẩm / SKU --</option>
            </select>
            <small class="emw7-product-help" data-emw7-product-help>Chọn theo đúng hợp đồng/phương án kỹ thuật.</small>
        </td>
        <td><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="1" required></td>
        <td><input class="pt-input" name="unit[]" value="cái" readonly data-emw7-unit></td>
        <td><input class="pt-input" name="item_note[]" placeholder="Thông số bắt buộc, vị trí lắp, lưu ý thi công..."></td>
        <td><button type="button" class="pt-btn pt-btn--danger pt-btn--sm" data-emw7-remove-tech-row><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
