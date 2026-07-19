
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')

@section('content')
@php
    $selectedSiteId = old('site_id', $selectedSiteId ?? request('site_id'));

    $stripPrefix = function ($note) {
        $note = (string) $note;
        $note = preg_replace('/^\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u', '', $note);
        return trim($note);
    };

    $oldItems = old('items', []);
    $oldExtra = old('extra_items', []);

    $mainStockRows = [];
    $subStockRows = [];
    $mainExternalRows = [];
    $subExternalRows = [];

    foreach ((array) $oldItems as $row) {
        $kind = $row['kind'] ?? '';
        $note = (string)($row['note'] ?? '');

        $row['note'] = $stripPrefix($note);

        if ($kind === 'sub_material' || strpos($note, '[Vật tư phụ - Trong kho]') !== false) {
            $subStockRows[] = $row;
        } else {
            $mainStockRows[] = $row;
        }
    }

    foreach ((array) $oldExtra as $row) {
        $kind = $row['kind'] ?? '';
        $note = (string)($row['note'] ?? '');

        $row['note'] = $stripPrefix($note);

        if ($kind === 'main_device' || strpos($note, '[Thiết bị chính - Ngoài kho]') !== false) {
            $mainExternalRows[] = $row;
        } else {
            $subExternalRows[] = $row;
        }
    }

    if (count($mainStockRows) === 0) {
        $mainStockRows = [[
            'warehouse_id' => '',
            'product_id' => '',
            'qty' => 1,
            'note' => '',
            'kind' => 'main_device',
        ]];
    }

    if (count($mainExternalRows) === 0) {
        $mainExternalRows = [[
            'name' => '',
            'qty' => 1,
            'unit' => '',
            'note' => '',
            'kind' => 'main_device',
        ]];
    }

    if (count($subStockRows) === 0) {
        $subStockRows = [[
            'warehouse_id' => '',
            'product_id' => '',
            'qty' => 1,
            'note' => '',
            'kind' => 'sub_material',
        ]];
    }

    if (count($subExternalRows) === 0) {
        $subExternalRows = [[
            'name' => '',
            'qty' => 1,
            'unit' => '',
            'note' => '',
            'kind' => 'sub_material',
        ]];
    }

    $sections = [
        [
            'type' => 'stock',
            'id' => 'mainStockTbody',
            'kind' => 'main_device',
            'title' => 'Thiết bị chính - Trong kho',
            'desc' => 'Chọn inverter, pin lưu trữ, tấm pin, smart meter... có trong kho. Giá vốn tự lấy từ kho/catalog.',
            'button' => 'Thêm thiết bị',
            'label' => 'Thiết bị',
            'placeholder' => '-- Chọn thiết bị --',
            'icon' => 'bi-cpu',
            'icon_class' => 'main-icon',
            'rows' => $mainStockRows,
        ],
        [
            'type' => 'external',
            'id' => 'mainExternalTbody',
            'kind' => 'main_device',
            'title' => 'Thiết bị chính - Ngoài kho',
            'desc' => 'Kỹ thuật nhập tên thiết bị và số lượng. Warehouse nhập giá vốn khi kho duyệt/xuất.',
            'button' => 'Thêm thiết bị ngoài kho',
            'label' => 'Tên thiết bị ngoài kho',
            'placeholder' => 'VD: Inverter phát sinh, pin lưu trữ ngoài kho...',
            'icon' => 'bi-cpu-fill',
            'icon_class' => 'outside-main-icon',
            'rows' => $mainExternalRows,
        ],
        [
            'type' => 'stock',
            'id' => 'subStockTbody',
            'kind' => 'sub_material',
            'title' => 'Vật tư phụ - Trong kho',
            'desc' => 'Dây, CB, rail, ốc vít, tủ điện, phụ kiện... có trong kho. Giá vốn tự lấy từ kho/catalog.',
            'button' => 'Thêm vật tư phụ',
            'label' => 'Vật tư phụ',
            'placeholder' => '-- Chọn vật tư --',
            'icon' => 'bi-hdd-stack',
            'icon_class' => 'sub-icon',
            'rows' => $subStockRows,
        ],
        [
            'type' => 'external',
            'id' => 'subExternalTbody',
            'kind' => 'sub_material',
            'title' => 'Vật tư phụ - Ngoài kho',
            'desc' => 'Vật tư phát sinh ngoài kho. Warehouse nhập giá vốn khi kho duyệt/xuất.',
            'button' => 'Thêm vật tư ngoài kho',
            'label' => 'Tên vật tư ngoài kho',
            'placeholder' => 'VD: Ống gen, phụ kiện phát sinh...',
            'icon' => 'bi-tools',
            'icon_class' => 'outside-sub-icon',
            'rows' => $subExternalRows,
        ],
    ];
@endphp

<div class="container-fluid px-4 py-3 ego-mr-form">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 ego-header">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="page-icon">
                    <i class="bi bi-box-seam"></i>
                </span>
                <h4 class="fw-bold mb-0">Tạo đơn vật tư</h4>
            </div>

            <div class="text-muted small">
                Chia rõ thiết bị chính và vật tư phụ, trong kho và ngoài kho.
            </div>
        </div>

        <a href="{{ route('material-requests.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>

            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('material-requests.store') }}" id="materialRequestForm">
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">

                {{-- CÔNG TRÌNH --}}
                <div class="card border-0 shadow-ego ego-card" style="border-radius:18px;">
                    <div class="card-header bg-white border-0 py-3" style="border-radius:18px 18px 0 0;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-pill">
                                <i class="bi bi-buildings"></i>
                            </span>

                            <div>
                                <div class="fw-bold">Công trình</div>
                                <div class="text-muted small">
                                    Đơn vật tư sẽ link vào công trình để tính chi phí thực tế.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">
                                    Chọn công trình <span class="text-danger">*</span>
                                </label>

                                <select name="site_id" id="site_id" class="form-select" required>
                                    <option value="">-- Chọn công trình --</option>

                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}"
                                                data-name="{{ $site->name }}"
                                                data-address="{{ $site->address ?? '' }}"
                                                data-contact="{{ $site->contact_name ?? '' }}"
                                                data-phone="{{ $site->contact_phone ?? '' }}"
                                                data-contract="{{ $site->contract_amount ?? 0 }}"
                                                {{ (string)$selectedSiteId === (string)$site->id ? 'selected' : '' }}>
                                            #{{ $site->id }} - {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Ghi chú đơn</label>

                                <input name="note"
                                       class="form-control"
                                       value="{{ old('note') }}"
                                       placeholder="VD: Đợt 1, bổ sung vật tư...">
                            </div>
                        </div>

                        <div class="site-preview mt-3" id="sitePreview">
                            <div class="d-flex align-items-start gap-2">
                                <div class="preview-icon">
                                    <i class="bi bi-info-circle"></i>
                                </div>

                                <div>
                                    <div class="fw-bold" id="sitePreviewName">Chưa chọn công trình</div>
                                    <div class="small text-muted" id="sitePreviewAddress">
                                        Vui lòng chọn công trình để xem thông tin nhanh.
                                    </div>
                                    <div class="small text-muted mt-1" id="sitePreviewContact"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4 PHẦN VẬT TƯ --}}
                @foreach($sections as $section)
                    <div class="card border-0 shadow-ego ego-card mt-3" style="border-radius:18px;">
                        <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2"
                             style="border-radius:18px 18px 0 0;">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="icon-pill {{ $section['icon_class'] }}">
                                        <i class="bi {{ $section['icon'] }}"></i>
                                    </span>

                                    <div class="fw-bold">{{ $section['title'] }}</div>
                                </div>

                                <div class="text-muted small mt-1">
                                    {{ $section['desc'] }}
                                </div>
                            </div>

                            @if($section['type'] === 'stock')
                                <button type="button"
                                        class="btn btn-outline-ego btnAddStock"
                                        data-target="{{ $section['id'] }}">
                                    <i class="bi bi-plus-lg"></i> {{ $section['button'] }}
                                </button>
                            @else
                                <button type="button"
                                        class="btn btn-outline-ego btnAddExternal"
                                        data-target="{{ $section['id'] }}">
                                    <i class="bi bi-plus-lg"></i> {{ $section['button'] }}
                                </button>
                            @endif
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">

                                @if($section['type'] === 'stock')
                                    <table class="table table-hover align-middle mb-0 ego-table">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width:54px" class="text-center">#</th>
                                            <th style="min-width:180px">Kho</th>
                                            <th style="min-width:300px">{{ $section['label'] }}</th>
                                            <th style="width:110px" class="text-end">Tồn</th>
                                            <th style="width:110px">ĐVT</th>
                                            <th style="width:150px" class="text-end">Giá vốn</th>
                                            <th style="width:130px" class="text-end">Số lượng</th>
                                            <th style="width:150px" class="text-end">Tạm tính</th>
                                            <th style="min-width:190px">Ghi chú</th>
                                            <th style="width:75px" class="text-end">Xóa</th>
                                        </tr>
                                        </thead>

                                        <tbody id="{{ $section['id'] }}"
                                               class="stock-tbody"
                                               data-kind="{{ $section['kind'] }}"
                                               data-placeholder="{{ $section['placeholder'] }}">
                                        @foreach($section['rows'] as $row)
                                            <tr class="stock-row">
                                                <td class="text-center stock-idx">1</td>

                                                <td>
                                                    <select class="form-select stock-warehouse"
                                                            data-old="{{ $row['warehouse_id'] ?? '' }}">
                                                        <option value="">-- Chọn kho --</option>

                                                        @foreach($warehouses as $warehouse)
                                                            <option value="{{ $warehouse->id }}"
                                                                    {{ (string)($row['warehouse_id'] ?? '') === (string)$warehouse->id ? 'selected' : '' }}>
                                                                {{ $warehouse->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                <td>
                                                    <select class="form-select stock-product"
                                                            data-old="{{ $row['product_id'] ?? '' }}">
                                                        <option value="">{{ $section['placeholder'] }}</option>
                                                    </select>
                                                </td>

                                                <td class="text-end">
                                                    <span class="stock-available badge bg-light text-dark border">—</span>
                                                </td>

                                                <td>
                                                    <span class="stock-unit pill-soft pill-muted">—</span>
                                                </td>

                                                <td class="text-end">
                                                    <span class="stock-cost fw-bold text-success">0 đ</span>
                                                </td>

                                                <td>
                                                    <input type="number"
                                                           min="0"
                                                           step="any"
                                                           class="form-control text-end stock-qty"
                                                           value="{{ $row['qty'] ?? 1 }}">
                                                </td>

                                                <td class="text-end">
                                                    <span class="stock-line-total fw-bold">0 đ</span>
                                                </td>

                                                <td>
                                                    <input class="form-control stock-note"
                                                           value="{{ $row['note'] ?? '' }}"
                                                           placeholder="Ghi chú...">
                                                </td>

                                                <td class="text-end">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger btnRemoveRow">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>

                                                <input type="hidden" class="stock-kind" value="{{ $section['kind'] }}">
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <table class="table table-hover align-middle mb-0 ego-table">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width:54px" class="text-center">#</th>
                                            <th style="min-width:300px">{{ $section['label'] }}</th>
                                            <th style="width:140px" class="text-end">Số lượng</th>
                                            <th style="width:140px">Đơn vị</th>
                                            <th style="width:160px" class="text-end">Giá vốn</th>
                                            <th style="min-width:240px">Ghi chú</th>
                                            <th style="width:75px" class="text-end">Xóa</th>
                                        </tr>
                                        </thead>

                                        <tbody id="{{ $section['id'] }}"
                                               class="external-tbody"
                                               data-kind="{{ $section['kind'] }}">
                                        @foreach($section['rows'] as $row)
                                            <tr class="external-row">
                                                <td class="text-center external-idx">1</td>

                                                <td>
                                                    <input class="form-control external-name"
                                                           value="{{ $row['name'] ?? '' }}"
                                                           placeholder="{{ $section['placeholder'] }}">
                                                </td>

                                                <td>
                                                    <input type="number"
                                                           min="0"
                                                           step="any"
                                                           class="form-control text-end external-qty"
                                                           value="{{ $row['qty'] ?? 1 }}">
                                                </td>

                                                <td>
                                                    <input class="form-control external-unit"
                                                           value="{{ $row['unit'] ?? '' }}"
                                                           placeholder="m/cái/bộ">
                                                </td>

                                                <td class="text-end">
                                                    <span class="text-muted small">Kho nhập khi duyệt</span>
                                                </td>

                                                <td>
                                                    <input class="form-control external-note"
                                                           value="{{ $row['note'] ?? '' }}"
                                                           placeholder="Ghi chú...">
                                                </td>

                                                <td class="text-end">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger btnRemoveRow">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>

                                                <input type="hidden" class="external-kind" value="{{ $section['kind'] }}">
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>

                            @if($section['type'] === 'external')
                                <div class="p-3 border-top">
                                    <div class="rule-box warning">
                                        <div class="d-flex gap-2 align-items-start">
                                            <div class="rule-icon">
                                                <i class="bi bi-exclamation-circle"></i>
                                            </div>

                                            <div>
                                                <div class="fw-semibold">Lưu ý cho warehouse</div>
                                                <div class="text-muted small">
                                                    Hàng ngoài kho chưa có giá vốn ở bước tạo đơn.
                                                    Khi kho duyệt/xuất, warehouse phải nhập giá vốn để cộng chi phí về công trình.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div id="payloadFields"></div>
            </div>

            {{-- RIGHT --}}
            <div class="col-lg-4">
                <div class="sticky-top" style="top:90px;">
                    <div class="card border-0 shadow-ego ego-card mb-3" style="border-radius:18px;">
                        <div class="card-header bg-white border-0 py-3" style="border-radius:18px 18px 0 0;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="icon-pill">
                                    <i class="bi bi-card-checklist"></i>
                                </span>

                                <div>
                                    <div class="fw-bold">Tóm tắt đơn vật tư</div>
                                    <div class="text-muted small">Kiểm tra nhanh trước khi lưu.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="text-muted small">Thiết bị trong kho</div>
                                    <div class="fw-bold" id="mainStockCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="text-muted small">Thiết bị ngoài kho</div>
                                    <div class="fw-bold" id="mainExternalCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="text-muted small">Vật tư phụ trong kho</div>
                                    <div class="fw-bold" id="subStockCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="text-muted small">Vật tư phụ ngoài kho</div>
                                    <div class="fw-bold" id="subExternalCount">0</div>
                                </div>
                            </div>

                            <div class="finance-summary mt-3">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="text-muted">Giá vốn trong kho tạm tính</span>
                                    <strong id="stockCostTotal">0 đ</strong>
                                </div>

                                <div class="small text-muted mt-1">
                                    Vật tư ngoài kho chưa tính giá vốn ở bước này.
                                </div>
                            </div>

                            <div class="summary-hint mt-3">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="hint-ic">
                                        <i class="bi bi-diagram-3"></i>
                                    </div>

                                    <div>
                                        <div class="fw-semibold">Luồng xử lý</div>
                                        <div class="text-muted small">
                                            Lưu nháp → gửi admin duyệt → admin duyệt → kho duyệt/xuất
                                            → cộng chi phí thực tế về công trình.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0 p-3" style="border-radius:0 0 18px 18px;">
                            <button class="btn btn-ego w-100" id="btnSubmitForm">
                                <i class="bi bi-save"></i> Lưu đơn
                            </button>

                            <a href="{{ route('material-requests.index') }}" class="btn btn-outline-secondary w-100 mt-2">
                                Quay lại
                            </a>
                        </div>
                    </div>

                    <div class="card border-0 shadow-ego ego-card" style="border-radius:18px;">
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start">
                                <div class="mini-icon">
                                    <i class="bi bi-cash-coin"></i>
                                </div>

                                <div>
                                    <div class="fw-bold">Quy tắc giá vốn</div>
                                    <div class="text-muted small">
                                        Hàng trong kho tự lấy giá vốn từ kho/catalog.
                                        Hàng ngoài kho warehouse nhập giá vốn lúc duyệt/xuất.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
(function(){
    const inventories = @json($inventories ?? []);
    const form = document.getElementById('materialRequestForm');

    function qsAll(sel, root = document){
        return Array.from(root.querySelectorAll(sel));
    }

    function num(v){
        const n = parseFloat(v || '0');
        return Number.isFinite(n) ? n : 0;
    }

    function money(v){
        return Number(v || 0).toLocaleString('vi-VN') + ' đ';
    }

    function qty(v){
        return Number(v || 0).toLocaleString('vi-VN');
    }

    function rowHasStockData(row){
        return !!(row.querySelector('.stock-product')?.value || '');
    }

    function rowHasExternalData(row){
        return !!((row.querySelector('.external-name')?.value || '').trim() !== '');
    }

    function selectedInventory(row){
        const productSelect = row.querySelector('.stock-product');
        const selected = productSelect?.options[productSelect.selectedIndex];

        if (!selected || !selected.dataset.inventory) {
            return null;
        }

        try {
            return JSON.parse(selected.dataset.inventory);
        } catch (e) {
            return null;
        }
    }

    function fillProducts(row){
        const warehouseSelect = row.querySelector('.stock-warehouse');
        const productSelect = row.querySelector('.stock-product');
        const tbody = row.closest('.stock-tbody');
        const placeholder = tbody?.dataset.placeholder || '-- Chọn vật tư --';

        if (!warehouseSelect || !productSelect) return;

        const warehouseId = String(warehouseSelect.value || '');
        const oldProductId = String(productSelect.dataset.old || productSelect.value || '');

        productSelect.innerHTML = '<option value="">' + placeholder + '</option>';

        inventories
            .filter(item => String(item.warehouse_id) === warehouseId)
            .forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.product_id;
                opt.textContent = item.product_name
                    + ' — tồn: ' + qty(item.quantity)
                    + ' — giá vốn: ' + money(item.unit_cost || 0);

                opt.dataset.inventory = JSON.stringify(item);

                if (String(item.product_id) === oldProductId) {
                    opt.selected = true;
                }

                productSelect.appendChild(opt);
            });

        productSelect.dataset.old = '';
        updateStockRow(row);
    }

    function updateStockRow(row){
        const inv = selectedInventory(row);
        const availableEl = row.querySelector('.stock-available');
        const unitEl = row.querySelector('.stock-unit');
        const costEl = row.querySelector('.stock-cost');
        const totalEl = row.querySelector('.stock-line-total');
        const qtyValue = num(row.querySelector('.stock-qty')?.value);

        if (!inv) {
            if (availableEl) availableEl.textContent = '—';
            if (unitEl) unitEl.textContent = '—';
            if (costEl) costEl.textContent = '0 đ';
            if (totalEl) totalEl.textContent = '0 đ';
            return;
        }

        const unitCost = num(inv.unit_cost);
        const lineTotal = unitCost * qtyValue;

        if (availableEl) availableEl.textContent = qty(inv.quantity || 0);
        if (unitEl) unitEl.textContent = inv.unit || '—';
        if (costEl) costEl.textContent = money(unitCost);
        if (totalEl) totalEl.textContent = money(lineTotal);
    }

    function renumberStock(tbody){
        const rows = qsAll('.stock-row', tbody);

        rows.forEach((row, index) => {
            const idx = row.querySelector('.stock-idx');
            if (idx) idx.textContent = index + 1;

            const removeBtn = row.querySelector('.btnRemoveRow');
            if (removeBtn) removeBtn.disabled = rows.length === 1;
        });
    }

    function renumberExternal(tbody){
        const rows = qsAll('.external-row', tbody);

        rows.forEach((row, index) => {
            const idx = row.querySelector('.external-idx');
            if (idx) idx.textContent = index + 1;

            const removeBtn = row.querySelector('.btnRemoveRow');
            if (removeBtn) removeBtn.disabled = rows.length === 1;
        });
    }

    function cloneStockRow(tbody){
        const first = tbody.querySelector('.stock-row');
        const clone = first.cloneNode(true);

        qsAll('select', clone).forEach(select => {
            select.value = '';
            select.dataset.old = '';
        });

        qsAll('input', clone).forEach(input => {
            if (input.classList.contains('stock-qty')) {
                input.value = 1;
            } else if (input.classList.contains('stock-kind')) {
                input.value = tbody.dataset.kind || '';
            } else {
                input.value = '';
            }
        });

        clone.querySelector('.stock-available').textContent = '—';
        clone.querySelector('.stock-unit').textContent = '—';
        clone.querySelector('.stock-cost').textContent = '0 đ';
        clone.querySelector('.stock-line-total').textContent = '0 đ';

        return clone;
    }

    function cloneExternalRow(tbody){
        const first = tbody.querySelector('.external-row');
        const clone = first.cloneNode(true);

        qsAll('input', clone).forEach(input => {
            if (input.classList.contains('external-qty')) {
                input.value = 1;
            } else if (input.classList.contains('external-kind')) {
                input.value = tbody.dataset.kind || '';
            } else {
                input.value = '';
            }
        });

        return clone;
    }

    function calcSummary(){
        let mainStockCount = 0;
        let subStockCount = 0;
        let mainExternalCount = 0;
        let subExternalCount = 0;
        let stockCostTotal = 0;

        qsAll('.stock-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.stock-row', tbody).forEach(row => {
                if (!rowHasStockData(row)) return;

                if (kind === 'main_device') {
                    mainStockCount++;
                } else {
                    subStockCount++;
                }

                const inv = selectedInventory(row);
                const unitCost = inv ? num(inv.unit_cost) : 0;
                const q = num(row.querySelector('.stock-qty')?.value);

                stockCostTotal += unitCost * q;
            });
        });

        qsAll('.external-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.external-row', tbody).forEach(row => {
                if (!rowHasExternalData(row)) return;

                if (kind === 'main_device') {
                    mainExternalCount++;
                } else {
                    subExternalCount++;
                }
            });
        });

        document.getElementById('mainStockCount').textContent = mainStockCount;
        document.getElementById('subStockCount').textContent = subStockCount;
        document.getElementById('mainExternalCount').textContent = mainExternalCount;
        document.getElementById('subExternalCount').textContent = subExternalCount;
        document.getElementById('stockCostTotal').textContent = money(stockCostTotal);
    }

    function syncSitePreview(){
        const siteSelect = document.getElementById('site_id');
        const selected = siteSelect?.options[siteSelect.selectedIndex];

        const nameEl = document.getElementById('sitePreviewName');
        const addressEl = document.getElementById('sitePreviewAddress');
        const contactEl = document.getElementById('sitePreviewContact');

        if (!selected || !selected.value) {
            nameEl.textContent = 'Chưa chọn công trình';
            addressEl.textContent = 'Vui lòng chọn công trình để xem thông tin nhanh.';
            contactEl.textContent = '';
            return;
        }

        nameEl.textContent = selected.dataset.name || selected.textContent || 'Công trình';
        addressEl.textContent = selected.dataset.address || 'Chưa có địa chỉ';

        const contact = selected.dataset.contact || '';
        const phone = selected.dataset.phone || '';

        contactEl.textContent = (contact || phone)
            ? 'Liên hệ: ' + [contact, phone].filter(Boolean).join(' - ')
            : '';
    }

    function buildPayload(){
        const payload = document.getElementById('payloadFields');
        payload.innerHTML = '';

        let itemIndex = 0;
        let extraIndex = 0;

        function addHidden(name, value){
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            payload.appendChild(input);
        }

        qsAll('.stock-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.stock-row', tbody).forEach(row => {
                const warehouseId = row.querySelector('.stock-warehouse')?.value || '';
                const productId = row.querySelector('.stock-product')?.value || '';
                const quantityRaw = row.querySelector('.stock-qty')?.value || '';
                const noteRaw = row.querySelector('.stock-note')?.value || '';

                if (!productId) return;

                const quantity = num(quantityRaw) > 0 ? quantityRaw : 1;

                const prefix = kind === 'main_device'
                    ? '[Thiết bị chính - Trong kho]'
                    : '[Vật tư phụ - Trong kho]';

                const note = (prefix + ' ' + noteRaw).trim();

                addHidden(`items[${itemIndex}][warehouse_id]`, warehouseId);
                addHidden(`items[${itemIndex}][product_id]`, productId);
                addHidden(`items[${itemIndex}][qty]`, quantity);
                addHidden(`items[${itemIndex}][note]`, note);
                addHidden(`items[${itemIndex}][kind]`, kind);

                itemIndex++;
            });
        });

        qsAll('.external-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.external-row', tbody).forEach(row => {
                const name = row.querySelector('.external-name')?.value || '';
                const quantityRaw = row.querySelector('.external-qty')?.value || '';
                const unit = row.querySelector('.external-unit')?.value || '';
                const noteRaw = row.querySelector('.external-note')?.value || '';

                if (name.trim() === '') return;

                const quantity = num(quantityRaw) > 0 ? quantityRaw : 1;

                const prefix = kind === 'main_device'
                    ? '[Thiết bị chính - Ngoài kho]'
                    : '[Vật tư phụ - Ngoài kho]';

                const note = (prefix + ' ' + noteRaw).trim();

                addHidden(`extra_items[${extraIndex}][name]`, name);
                addHidden(`extra_items[${extraIndex}][qty]`, quantity);
                addHidden(`extra_items[${extraIndex}][unit]`, unit);
                addHidden(`extra_items[${extraIndex}][note]`, note);
                addHidden(`extra_items[${extraIndex}][kind]`, kind);

                extraIndex++;
            });
        });
    }

    document.getElementById('site_id')?.addEventListener('change', syncSitePreview);

    qsAll('.btnAddStock').forEach(btn => {
        btn.addEventListener('click', () => {
            const tbody = document.getElementById(btn.dataset.target);
            tbody.appendChild(cloneStockRow(tbody));
            renumberStock(tbody);
            calcSummary();
        });
    });

    qsAll('.btnAddExternal').forEach(btn => {
        btn.addEventListener('click', () => {
            const tbody = document.getElementById(btn.dataset.target);
            tbody.appendChild(cloneExternalRow(tbody));
            renumberExternal(tbody);
            calcSummary();
        });
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveRow');
        if (!btn) return;

        const stockTbody = btn.closest('.stock-tbody');
        const externalTbody = btn.closest('.external-tbody');

        if (stockTbody) {
            const rows = qsAll('.stock-row', stockTbody);
            if (rows.length <= 1) return;

            btn.closest('.stock-row')?.remove();
            renumberStock(stockTbody);
            calcSummary();
            return;
        }

        if (externalTbody) {
            const rows = qsAll('.external-row', externalTbody);
            if (rows.length <= 1) return;

            btn.closest('.external-row')?.remove();
            renumberExternal(externalTbody);
            calcSummary();
        }
    });

    document.addEventListener('change', e => {
        const stockRow = e.target.closest('.stock-row');

        if (stockRow && e.target.matches('.stock-warehouse')) {
            const productSelect = stockRow.querySelector('.stock-product');
            if (productSelect) productSelect.dataset.old = '';
            fillProducts(stockRow);
            calcSummary();
        }

        if (stockRow && e.target.matches('.stock-product')) {
            updateStockRow(stockRow);
            calcSummary();
        }
    });

    document.addEventListener('input', e => {
        const stockRow = e.target.closest('.stock-row');
        const externalRow = e.target.closest('.external-row');

        if (stockRow) {
            updateStockRow(stockRow);
            calcSummary();
        }

        if (externalRow) {
            calcSummary();
        }
    });

    form?.addEventListener('submit', () => {
        buildPayload();
    });

    qsAll('.stock-tbody').forEach(tbody => {
        qsAll('.stock-row', tbody).forEach(row => fillProducts(row));
        renumberStock(tbody);
    });

    qsAll('.external-tbody').forEach(tbody => {
        renumberExternal(tbody);
    });

    syncSitePreview();
    calcSummary();
    buildPayload();
})();
</script>

<style>
    .ego-mr-form{
        background:
            radial-gradient(circle at top left, rgba(11,201,170,.13), transparent 26%),
            linear-gradient(180deg, rgba(11,201,170,.10), rgba(11,201,170,.05) 28%, rgba(255,255,255,0) 75%);
        border-radius: 22px;
        padding-top: 18px;
        padding-bottom: 18px;
    }

    .ego-header{
        margin-top: 6px;
    }

    .page-icon{
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background: linear-gradient(135deg, rgba(11,201,170,.20), rgba(59,130,246,.13));
        color:#0f766e;
        border:1px solid rgba(11,201,170,.22);
        box-shadow:0 12px 26px rgba(2,44,34,.08);
    }

    .ego-card{
        background: rgba(255,255,255,.96);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(15,118,110,.07) !important;
    }

    .shadow-ego{
        box-shadow: 0 14px 38px rgba(2,44,34,.08) !important;
    }

    .btn-ego{
        background: linear-gradient(135deg, #0BC9AA, #08b79b);
        border:0;
        color:#fff;
        border-radius:14px;
        padding:11px 14px;
        font-weight:800;
        box-shadow:0 10px 22px rgba(11,201,170,.26);
    }

    .btn-ego:hover{
        color:#fff;
        transform: translateY(-1px);
        box-shadow:0 14px 28px rgba(11,201,170,.32);
    }

    .btn-outline-ego{
        border-color: rgba(11,201,170,.55);
        color:#0f766e;
        background: rgba(11,201,170,.10);
        border-radius:13px;
        font-weight:700;
    }

    .btn-outline-ego:hover{
        border-color: rgba(11,201,170,.75);
        background: rgba(11,201,170,.16);
        color:#0f766e;
    }

    .icon-pill{
        width:36px;
        height:36px;
        border-radius:13px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(11,201,170,.12);
        color:#0f766e;
        border:1px solid rgba(0,0,0,.06);
        flex:0 0 auto;
    }

    .main-icon{
        background: rgba(59,130,246,.12);
        color:#1d4ed8;
    }

    .sub-icon{
        background: rgba(11,201,170,.12);
        color:#0f766e;
    }

    .outside-main-icon,
    .outside-sub-icon{
        background: rgba(245,158,11,.14);
        color:#b45309;
    }

    .form-control,
    .form-select{
        border-radius:13px;
        padding-top:.58rem;
        padding-bottom:.58rem;
        border-color: rgba(15,23,42,.12);
    }

    .form-control:focus,
    .form-select:focus{
        border-color: rgba(11,201,170,.7);
        box-shadow: 0 0 0 .2rem rgba(11,201,170,.12);
    }

    .site-preview{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:16px;
        padding:13px;
    }

    .preview-icon,
    .rule-icon,
    .hint-ic,
    .mini-icon{
        width:34px;
        height:34px;
        border-radius:12px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(11,201,170,.12);
        color:#0f766e;
        border:1px solid rgba(0,0,0,.06);
        flex:0 0 auto;
    }

    .rule-box{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:16px;
        padding:13px;
    }

    .rule-box.warning{
        border-color: rgba(245,158,11,.22);
        background: rgba(245,158,11,.08);
    }

    .rule-box.warning .rule-icon{
        background: rgba(245,158,11,.14);
        color:#b45309;
    }

    .ego-table thead th{
        background:#f8fafc;
        color:#334155;
        font-size:.85rem;
        white-space:nowrap;
        vertical-align:middle;
        border-bottom:1px solid rgba(0,0,0,.06) !important;
    }

    .ego-table tbody td{
        padding-top:.8rem;
        padding-bottom:.8rem;
        vertical-align:middle;
        border-top:1px solid rgba(0,0,0,.04) !important;
    }

    .ego-table tbody tr:hover{
        background: rgba(11,201,170,.055);
    }

    .ego-table .form-control,
    .ego-table .form-select{
        min-height:42px;
        font-size:14px;
    }

    .btnRemoveRow{
        width:42px;
        height:42px;
        border-radius:12px;
    }

    .pill-soft{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        border:1px solid rgba(0,0,0,.06);
        font-size:12px;
        line-height:1;
        white-space:nowrap;
        font-weight:700;
    }

    .pill-muted{
        background:rgba(148,163,184,.12);
        color:#64748b;
        border-color:rgba(148,163,184,.22);
    }

    .summary-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .summary-item{
        border:1px solid rgba(0,0,0,.06);
        background: rgba(11,201,170,.06);
        border-radius:15px;
        padding:12px;
    }

    .finance-summary{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:15px;
        padding:12px;
    }

    .summary-hint{
        border:1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.88);
        border-radius:15px;
        padding:12px;
    }

    @media (max-width: 991.98px){
        .sticky-top{
            position: static !important;
        }
    }

    @media (max-width: 575.98px){
        .summary-grid{
            grid-template-columns:1fr;
        }
    }
</style>

@endsection