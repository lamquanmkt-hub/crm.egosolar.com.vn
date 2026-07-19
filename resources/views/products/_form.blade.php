@php
    use Illuminate\Support\Facades\Schema;

    $productInstance = $product ?? null;

    $categories = $categories ?? collect();
    $brands     = $brands ?? collect();
    $companies  = $companies ?? collect();
    $companyWarehouses = $companyWarehouses ?? [];
    $priceTiers = $priceTiers ?? collect();

    $formData = $formData ?? [];
    $warehouseQty = $formData['warehouseQty'] ?? [];
    $serialsByWarehouse = $formData['serialsByWarehouse'] ?? [];
    $totalQty = $formData['totalQty'] ?? 0;
    $tierPrices = $formData['tierPrices'] ?? [];

    $isSerialized = (bool) old('is_serialized', $productInstance?->is_serialized ?? false);

    $buttonText = $buttonText ?? ($productInstance ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm');

    $serialCol = Schema::hasColumn('crm_product_stock','serials')
        ? 'serials'
        : (Schema::hasColumn('crm_product_stock','serials_json') ? 'serials_json' : null);

    // Giá vốn
    $costBeforeVat = old('price_agent', $productInstance?->price_agent ?? 0);
    $costVatPercent = old('cost_vat_percent', $productInstance?->cost_vat_percent ?? ($productInstance?->vat_percent ?? 0));

    // Giá bán mặc định
    $defaultRetailBeforeVat = old('price_retail', $productInstance?->price_retail ?? '');
    $defaultRetailVat = old('vat_percent', $productInstance?->vat_percent ?? 0);
@endphp

@csrf

<style>
    :root{
        --ego:#0ea5a4;
        --ego06: rgba(14,165,164,.06);
        --ego10: rgba(14,165,164,.10);
        --border: rgba(2,6,23,.08);
        --text: #0f172a;
        --muted: #64748b;
    }
    .ego-card{
        border:1px solid var(--border);
        border-radius:16px;
        background:#fff;
        box-shadow:0 8px 20px rgba(2,6,23,.05);
        overflow:hidden;
    }
    .ego-card .card-header{
        background: var(--ego06);
        border-bottom:1px solid rgba(2,6,23,.06);
    }
    .btn-ego{
        background: var(--ego);
        border-color: var(--ego);
        color:#fff;
        font-weight:800;
        border-radius:12px;
    }
    .btn-ego:hover{ filter:brightness(.96); color:#fff; }
    .ego-label{ font-weight:800; color:var(--text); }
    .ego-hint{ font-size:.875rem; color:var(--muted); }
    .form-control:focus,.form-select:focus{
        border-color:rgba(14,165,164,.45);
        box-shadow:0 0 0 .2rem var(--ego10);
    }
    .ego-company-box{
        border:1px solid rgba(2,6,23,.08);
        border-radius:14px;
        overflow:hidden;
        background:#fff;
    }
    .ego-company-title{
        padding:10px 12px;
        font-weight:900;
        background: rgba(2,6,23,.03);
        border-bottom:1px solid rgba(2,6,23,.06);
        text-transform:uppercase;
        font-size:.85rem;
    }
    .ego-stock-table th{
        font-size:.78rem;
        text-transform:uppercase;
        letter-spacing:.35px;
        color:var(--muted);
        background: rgba(2,6,23,.03);
        white-space:nowrap;
    }
    .ego-serial-btn{
        border-radius:10px;
        font-weight:800;
        padding:6px 10px;
        white-space:nowrap;
    }
    .table td,
    .table th{
        vertical-align: middle;
    }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="small text-muted">Nhập thông tin sản phẩm và lưu để cập nhật hệ thống.</div>
    <div class="d-flex gap-2">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Quay lại</a>
        <button type="submit" class="btn btn-ego">{{ $buttonText }}</button>
    </div>
</div>

<div class="row g-3">
    {{-- LEFT --}}
    <div class="col-lg-8">
        <div class="card ego-card">
            <div class="card-header px-3 py-3">
                <div class="fw-bold">Thông tin cơ bản</div>
                <div class="small text-muted">Tên, mô tả, SKU, danh mục và thương hiệu.</div>
            </div>

            <div class="card-body p-3 p-md-4">
                <div class="mb-3">
                    <label class="form-label ego-label">Tên sản phẩm <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $productInstance?->name ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label ego-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="3">{{ old('note', $productInstance?->note ?? '') }}</textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label ego-label">Mã SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control"
                               value="{{ old('sku', $productInstance?->sku ?? '') }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label ego-label">Danh mục</label>
                        <select name="category_id" class="form-select">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}"
                                    {{ (string)old('category_id', $productInstance?->category_id ?? '') === (string)$cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label ego-label">Brand</label>
                        <select name="brand_id" class="form-select">
                            <option value="">-- Không chọn --</option>
                            @foreach($brands as $b)
                                <option value="{{ $b->id }}"
                                    {{ (string)old('brand_id', $productInstance?->brand_id ?? '') === (string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name }} @if(!empty($b->code)) ({{ $b->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="ego-hint mt-1">Giúp chuẩn hoá thương hiệu, dễ tìm kiếm.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label ego-label">Serial/IMEI</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="is_serialized" name="is_serialized" value="1"
                                   {{ $isSerialized ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_serialized">
                                Hiện nút nhập Serial/IMEI theo kho
                            </label>
                        </div>
                        <div class="ego-hint">Bật để nhập serial (qty sẽ tự tính theo serial).</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- STOCK --}}
        <div class="card ego-card mt-3">
            <div class="card-header px-3 py-3">
                <div class="fw-bold">Tồn kho</div>
                <div class="small text-muted">Theo công ty, theo kho, tự tạo lô khi tăng số lượng.</div>
            </div>

            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold">Tổng tồn kho</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted">Tổng:</span>
                        <input type="text" id="total_qty_display" class="form-control form-control-sm text-center fw-bold"
                               style="width:110px" value="{{ (int)$totalQty }}" disabled>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach($companies as $c)
                        @php
                            $cid = (int)$c->id;
                            $ws = $companyWarehouses[$cid] ?? collect();
                        @endphp

                        <div class="col-md-6">
                            <div class="ego-company-box">
                                <div class="ego-company-title">{{ $c->name }}</div>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0 ego-stock-table">
                                        <thead>
                                        <tr>
                                            <th>Kho</th>
                                            <th style="width:140px" class="text-center">Tồn sau nhập</th>
                                            <th style="width:160px" class="text-center serial-col">Serial/IMEI</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($ws as $w)
                                            @php
                                                $wid = (int)$w->id;

                                                $currentQty = (int) old("stocks.{$cid}.{$wid}.qty", $warehouseQty[$cid][$wid] ?? 0);

                                                $existingSerials = old("stocks.{$cid}.{$wid}.serials", null);
                                                if ($existingSerials === null) {
                                                    $existingSerials = $serialsByWarehouse[$cid][$wid] ?? [];
                                                } else {
                                                    $tmp = json_decode($existingSerials, true);
                                                    if (is_array($tmp)) $existingSerials = $tmp;
                                                }
                                                $existingSerials = is_array($existingSerials) ? $existingSerials : [];
                                                $serialCount = count($existingSerials);
                                            @endphp

                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $w->name }}</div>
                                                    @if(!empty($w->location))
                                                        <div class="text-muted small">{{ $w->location }}</div>
                                                    @endif
                                                </td>

                                                <td class="text-center">
                                                    <input type="number"
                                                           min="0"
                                                           class="form-control form-control-sm text-center js-warehouse-qty"
                                                           data-company-id="{{ $cid }}"
                                                           data-warehouse-id="{{ $wid }}"
                                                           name="stocks[{{ $cid }}][{{ $wid }}][qty]"
                                                           value="{{ $currentQty }}">
                                                </td>

                                                <td class="text-center serial-col">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary ego-serial-btn"
                                                            onclick="openSerialModal({{ $cid }}, {{ $wid }}, '{{ addslashes($w->name) }}')">
                                                        Nhập (<span id="serial-count-{{ $cid }}-{{ $wid }}">{{ $serialCount }}</span>)
                                                    </button>

                                                    <input type="hidden"
                                                           id="serials-{{ $cid }}-{{ $wid }}"
                                                           name="stocks[{{ $cid }}][{{ $wid }}][serials]"
                                                           value='{{ json_encode($existingSerials) }}'>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có kho</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-info mt-3 mb-0" style="border-radius:14px;">
                    <i class="bi bi-info-circle"></i>
                    <b>Lưu ý lô hàng:</b> Khi bạn tăng <b>Tồn sau nhập</b>, hệ thống sẽ tự tạo <b>lô nhập mới</b>
                    theo <b>Giá vốn trước VAT</b> và <b>VAT giá vốn</b> hiện tại. Khi xuất đơn hàng, hệ thống tự trừ
                    lô còn tồn lâu nhất trước.
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT --}}
    <div class="col-lg-4">
        {{-- BẢNG GIÁ VỐN --}}
        <div class="card ego-card">
            <div class="card-header px-3 py-3">
                <div class="fw-bold">Bảng giá vốn</div>
                <div class="small text-muted">Giá vốn trước VAT / VAT / Giá vốn sau VAT.</div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Giá vốn trước VAT</th>
                            <th style="width:110px">VAT (%)</th>
                            <th>Giá vốn sau VAT</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number"
                                           step="0.01"
                                           min="0"
                                           name="price_agent"
                                           id="cost_before_vat"
                                           class="form-control"
                                           value="{{ $costBeforeVat }}"
                                           required>
                                    <span class="input-group-text">VNĐ</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number"
                                           step="0.01"
                                           min="0"
                                           max="100"
                                           name="cost_vat_percent"
                                           id="cost_vat_percent"
                                           class="form-control"
                                           value="{{ $costVatPercent }}"
                                           placeholder="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           id="cost_after_vat"
                                           class="form-control fw-bold bg-light"
                                           value="0"
                                           readonly>
                                    <span class="input-group-text">VNĐ</span>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- BẢNG GIÁ BÁN --}}
        <div class="card ego-card mt-3">
            <div class="card-header px-3 py-3">
                <div class="fw-bold">Bảng giá bán</div>
                <div class="small text-muted">Giá bán mặc định / loại giá / Giá trước VAT / VAT / Giá sau VAT.</div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Loại giá</th>
                            <th style="width:170px">Giá trước VAT</th>
                            <th style="width:110px">VAT (%)</th>
                            <th style="width:170px">Giá sau VAT</th>
                        </tr>
                        </thead>
                        <tbody>
                            {{-- GIÁ BÁN MẶC ĐỊNH --}}
                            <tr>
                                <td class="fw-semibold">Giá bán mặc định</td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               class="form-control js-default-retail-price"
                                               name="price_retail"
                                               value="{{ $defaultRetailBeforeVat }}"
                                               placeholder="Nhập...">
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               max="100"
                                               class="form-control js-default-retail-vat"
                                               name="vat_percent"
                                               value="{{ $defaultRetailVat }}"
                                               placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text"
                                               id="default_retail_after_vat"
                                               class="form-control fw-bold bg-light"
                                               value="0"
                                               readonly>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </td>
                            </tr>

                        @forelse($priceTiers as $tier)
                            @php
                                $tid = (int) $tier->id;

                                $tierRow = $tierPrices[$tid] ?? [];

                                $beforeVat = old("prices.{$tid}.before_vat", is_array($tierRow) ? ($tierRow['before_vat'] ?? '') : '');
                                $vatPercent = old("prices.{$tid}.vat_percent", is_array($tierRow) ? ($tierRow['vat_percent'] ?? 0) : 0);
                                $afterVat = is_array($tierRow) ? ($tierRow['after_vat'] ?? 0) : 0;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $tier->name }}</td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               class="form-control js-tier-price"
                                               name="prices[{{ $tid }}][before_vat]"
                                               data-tier-id="{{ $tid }}"
                                               value="{{ $beforeVat }}"
                                               placeholder="Nhập...">
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               max="100"
                                               class="form-control js-tier-vat"
                                               name="prices[{{ $tid }}][vat_percent]"
                                               data-tier-id="{{ $tid }}"
                                               value="{{ $vatPercent }}"
                                               placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text"
                                               id="tier_after_vat_{{ $tid }}"
                                               class="form-control fw-bold bg-light"
                                               value="{{ number_format((float)$afterVat, 0, ',', '.') }}"
                                               readonly>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Chưa có loại giá</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 ego-hint">Tip: để trống giá nếu loại giá đó không sử dụng.</div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card ego-card mt-3">
            <div class="card-body p-3 p-md-4">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-ego btn-lg">{{ $buttonText }}</button>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Hủy / Quay lại</a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- SERIAL MODAL --}}
<div class="modal fade" id="serialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#0ea5a4;color:#fff;">
                <h5 class="modal-title">
                    Nhập Serial/IMEI - <span id="serial-modal-warehouse-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    <b>Hướng dẫn:</b> mỗi serial 1 dòng. Tự IN HOA. Chỉ A-Z, 0-9, -, _, / (6-50 ký tự).
                </div>

                <div id="serial-errors" style="display:none;"></div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <b>Đã nhập: <span id="serial-entered-count" class="text-primary">0</span></b>
                </div>

                <textarea id="serial-textarea" class="form-control font-monospace" rows="10"
                          placeholder="56000NAW258L1292&#10;56000NAW258L1293"></textarea>

                <div id="serial-list-preview" class="mt-3"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveSerials()">Lưu Serial</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentCompanyId = null;
let currentWarehouseId = null;

function recalcTotalQty() {
    let total = 0;
    document.querySelectorAll('.js-warehouse-qty').forEach(el => {
        const v = parseInt(el.value || '0', 10);
        if (!isNaN(v)) total += v;
    });
    const totalEl = document.getElementById('total_qty_display');
    if (totalEl) totalEl.value = total;
}

document.addEventListener('input', function(e){
    if (e.target.classList.contains('js-warehouse-qty')) recalcTotalQty();
});

function toggleSerialInputs() {
    const checkbox = document.getElementById('is_serialized');
    const isChecked = checkbox && checkbox.checked;

    document.querySelectorAll('.serial-col').forEach(el => {
        el.style.display = isChecked ? '' : 'none';
    });

    document.querySelectorAll('.js-warehouse-qty').forEach(el => {
        el.removeAttribute('readonly');
        el.classList.remove('bg-light');
    });
}

function validateSerials(serials) {
    const errors = [];
    const seen = new Set();

    serials.forEach((s, i) => {
        const line = i + 1;
        if (!s) { errors.push(`Dòng ${line}: rỗng`); return; }
        if (s.length < 6) errors.push(`Dòng ${line}: "${s}" quá ngắn`);
        if (s.length > 50) errors.push(`Dòng ${line}: "${s}" quá dài`);
        if (!/^[A-Za-z0-9\-_\/]+$/.test(s)) errors.push(`Dòng ${line}: "${s}" sai ký tự`);

        const up = s.toUpperCase();
        if (seen.has(up)) errors.push(`Dòng ${line}: "${s}" bị trùng`);
        seen.add(up);
    });

    return errors;
}

function updateEnteredCount() {
    const textarea = document.getElementById('serial-textarea');
    if (!textarea) return;

    const serials = textarea.value.split('\n').map(s => s.trim()).filter(Boolean);
    document.getElementById('serial-entered-count').textContent = serials.length;

    const preview = document.getElementById('serial-list-preview');
    if (!preview) return;

    if (!serials.length) {
        preview.innerHTML = '';
        return;
    }

    preview.innerHTML =
        '<div class="small text-muted mb-1">Preview:</div>' +
        '<div class="border rounded p-2 bg-light" style="max-height:180px;overflow:auto;">' +
        serials.map((s, idx) => `<span class="badge bg-secondary me-1 mb-1">${idx+1}. ${s.toUpperCase()}</span>`).join('') +
        '</div>';
}

function showSerialErrors(errors) {
    const box = document.getElementById('serial-errors');
    box.style.display = 'block';
    box.innerHTML = `<div class="alert alert-danger">
        <b>Có ${errors.length} lỗi:</b>
        <ul class="mb-0 mt-2">${errors.map(e => `<li>${e}</li>`).join('')}</ul>
    </div>`;
}

function hideSerialErrors() {
    const box = document.getElementById('serial-errors');
    box.style.display = 'none';
    box.innerHTML = '';
}

function openSerialModal(companyId, warehouseId, warehouseName) {
    currentCompanyId = companyId;
    currentWarehouseId = warehouseId;

    document.getElementById('serial-modal-warehouse-name').textContent = warehouseName;

    const hiddenId = `serials-${companyId}-${warehouseId}`;
    const hidden = document.getElementById(hiddenId);

    let existing = [];
    try {
        existing = JSON.parse(hidden?.value || '[]');
        if (!Array.isArray(existing)) existing = [];
    } catch(e) {
        existing = [];
    }

    document.getElementById('serial-textarea').value = existing.join('\n');
    updateEnteredCount();
    hideSerialErrors();

    const modal = new bootstrap.Modal(document.getElementById('serialModal'));
    modal.show();
}

function saveSerials() {
    const textarea = document.getElementById('serial-textarea');
    let serials = textarea.value.split('\n').map(s => s.trim()).filter(Boolean).map(s => s.toUpperCase());

    const errors = validateSerials(serials);
    if (errors.length) {
        showSerialErrors(errors);
        return;
    }

    const hiddenId = `serials-${currentCompanyId}-${currentWarehouseId}`;
    const hidden = document.getElementById(hiddenId);
    if (hidden) hidden.value = JSON.stringify(serials);

    const qtyInput = document.querySelector(`.js-warehouse-qty[data-company-id="${currentCompanyId}"][data-warehouse-id="${currentWarehouseId}"]`);
    if (qtyInput) qtyInput.value = serials.length;

    const countEl = document.getElementById(`serial-count-${currentCompanyId}-${currentWarehouseId}`);
    if (countEl) countEl.textContent = serials.length;

    recalcTotalQty();

    const modal = bootstrap.Modal.getInstance(document.getElementById('serialModal'));
    if (modal) modal.hide();
}

document.addEventListener('DOMContentLoaded', function(){
    recalcTotalQty();
    toggleSerialInputs();

    const checkbox = document.getElementById('is_serialized');
    if (checkbox) checkbox.addEventListener('change', toggleSerialInputs);

    const textarea = document.getElementById('serial-textarea');
    if (textarea) textarea.addEventListener('input', updateEnteredCount);
});
</script>

<script>
(function () {
    function numFrom(el) {
        if (!el) return 0;

        if (typeof el.valueAsNumber === 'number' && !isNaN(el.valueAsNumber)) {
            return el.valueAsNumber;
        }

        let v = (el.value ?? '').toString().trim();
        if (!v) return 0;

        v = v.replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function fmtVND(n) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(n || 0));
    }

    function calcCostTable() {
        const beforeEl = document.getElementById('cost_before_vat');
        const vatEl = document.getElementById('cost_vat_percent');
        const afterEl = document.getElementById('cost_after_vat');

        if (!beforeEl || !vatEl || !afterEl) return;

        const before = numFrom(beforeEl);
        const vat = numFrom(vatEl);
        const after = before * (1 + vat / 100);

        afterEl.value = fmtVND(after);
    }

    function calcDefaultRetail() {
        const beforeEl = document.querySelector('.js-default-retail-price');
        const vatEl = document.querySelector('.js-default-retail-vat');
        const afterEl = document.getElementById('default_retail_after_vat');

        if (!beforeEl || !vatEl || !afterEl) return;

        const before = numFrom(beforeEl);
        const vat = numFrom(vatEl);
        const after = before * (1 + vat / 100);

        afterEl.value = fmtVND(after);
    }

    function calcTierTable() {
        document.querySelectorAll('.js-tier-price').forEach(priceInput => {
            const tid = priceInput.dataset.tierId;
            if (!tid) return;

            const vatInput = document.querySelector('.js-tier-vat[data-tier-id="' + tid + '"]');
            const out = document.getElementById('tier_after_vat_' + tid);

            if (!out) return;

            const before = numFrom(priceInput);
            const vat = numFrom(vatInput);
            const after = before * (1 + vat / 100);

            out.value = fmtVND(after);
        });
    }

    function bindEvents() {
        calcCostTable();
        calcDefaultRetail();
        calcTierTable();

        const costBeforeEl = document.getElementById('cost_before_vat');
        const costVatEl = document.getElementById('cost_vat_percent');
        const defaultRetailPriceEl = document.querySelector('.js-default-retail-price');
        const defaultRetailVatEl = document.querySelector('.js-default-retail-vat');

        ['input', 'change', 'keyup'].forEach(evt => {
            if (costBeforeEl) costBeforeEl.addEventListener(evt, calcCostTable);
            if (costVatEl) costVatEl.addEventListener(evt, calcCostTable);

            if (defaultRetailPriceEl) defaultRetailPriceEl.addEventListener(evt, calcDefaultRetail);
            if (defaultRetailVatEl) defaultRetailVatEl.addEventListener(evt, calcDefaultRetail);
        });

        document.querySelectorAll('.js-tier-price, .js-tier-vat').forEach(el => {
            ['input', 'change', 'keyup'].forEach(evt => {
                el.addEventListener(evt, calcTierTable);
            });
        });

        setTimeout(() => {
            calcCostTable();
            calcDefaultRetail();
            calcTierTable();
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
</script>

<!-- EGO_CUSTOMER_MODAL_STYLE_START -->
<style id="ego-customer-modal-style">
.ego-customer-modal{
    border:0 !important;
    border-radius:22px !important;
    overflow:hidden !important;
    box-shadow:0 30px 80px rgba(15,23,42,.28) !important;
    background:#f8fafc !important;
}

.ego-customer-modal .modal-header{
    background:linear-gradient(135deg,#0ea5e9 0%, #2563eb 100%) !important;
    color:#fff !important;
    border-bottom:0 !important;
    padding:18px 24px !important;
}

.ego-customer-modal .modal-title,
.ego-customer-modal h5,
.ego-customer-modal h4{
    color:#fff !important;
    font-weight:800 !important;
    font-size:30px !important;
    margin:0 !important;
}

.ego-customer-modal .btn-close,
.ego-customer-modal .close{
    filter:brightness(0) invert(1) !important;
    opacity:1 !important;
}

.ego-customer-modal .modal-body{
    background:#f8fafc !important;
    padding:18px !important;
    max-height:78vh !important;
    overflow-y:auto !important;
}

.ego-customer-modal .ego-customer-panel{
    background:#fff !important;
    border:1px solid #e2e8f0 !important;
    border-radius:18px !important;
    padding:16px 16px 12px !important;
    margin-bottom:16px !important;
    box-shadow:0 10px 26px rgba(15,23,42,.05) !important;
}

.ego-customer-modal .ego-customer-panel-title{
    display:flex !important;
    align-items:center !important;
    gap:8px !important;
    font-size:20px !important;
    font-weight:800 !important;
    color:#0f172a !important;
    margin-bottom:14px !important;
    padding-bottom:10px !important;
    border-bottom:1px solid #eef2f7 !important;
}

.ego-customer-modal label,
.ego-customer-modal .form-label{
    font-size:14px !important;
    font-weight:700 !important;
    color:#334155 !important;
    margin-bottom:8px !important;
}

.ego-customer-modal .form-control,
.ego-customer-modal .form-select,
.ego-customer-modal input,
.ego-customer-modal select,
.ego-customer-modal textarea{
    border-radius:14px !important;
    border:1px solid #dbe4ee !important;
    background:#fff !important;
    box-shadow:none !important;
    min-height:44px !important;
    padding:10px 14px !important;
    font-size:14px !important;
    color:#0f172a !important;
}

.ego-customer-modal textarea{
    min-height:88px !important;
    resize:vertical !important;
}

.ego-customer-modal .form-control:focus,
.ego-customer-modal .form-select:focus,
.ego-customer-modal input:focus,
.ego-customer-modal select:focus,
.ego-customer-modal textarea:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.14) !important;
    outline:none !important;
}

.ego-customer-modal ::placeholder{
    color:#94a3b8 !important;
}

.ego-customer-modal .text-muted,
.ego-customer-modal small,
.ego-customer-modal .form-text{
    color:#64748b !important;
    font-size:12px !important;
}

.ego-customer-modal .modal-footer{
    background:#fff !important;
    border-top:1px solid #e2e8f0 !important;
    padding:14px 18px !important;
}

.ego-customer-modal .btn{
    border-radius:14px !important;
    min-height:42px !important;
    padding:10px 16px !important;
    font-weight:700 !important;
}

.ego-customer-modal .btn-primary,
.ego-customer-modal .btn-success{
    background:linear-gradient(135deg,#06b6d4 0%, #2563eb 100%) !important;
    border:0 !important;
    box-shadow:0 12px 28px rgba(37,99,235,.22) !important;
}

.ego-customer-modal .btn-secondary,
.ego-customer-modal .btn-light{
    background:#f8fafc !important;
    border:1px solid #dbe4ee !important;
    color:#0f172a !important;
}
</style>

<script id="ego-customer-modal-style-js">
(function(){
    function beautifyCustomerModal(){
        document.querySelectorAll('.modal').forEach(function(modal){
            const text = (modal.innerText || '').trim();

            if(
                text.includes('Thêm mới khách hàng') ||
                text.includes('Thông tin cơ bản')
            ){
                const content = modal.querySelector('.modal-content');
                if(content) content.classList.add('ego-customer-modal');

                const body = modal.querySelector('.modal-body');
                if(body){
                    body.querySelectorAll(':scope > div').forEach(function(el){
                        if(el.querySelector('input, select, textarea')){
                            el.classList.add('ego-customer-panel');
                        }
                    });

                    body.querySelectorAll('.ego-customer-panel').forEach(function(panel){
                        const firstHeading = panel.querySelector('h1,h2,h3,h4,h5,h6,.fw-bold,strong,legend');
                        if(firstHeading && !firstHeading.classList.contains('ego-customer-panel-title')){
                            firstHeading.classList.add('ego-customer-panel-title');
                        }
                    });
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', beautifyCustomerModal);
    beautifyCustomerModal();

    const obs = new MutationObserver(function(){
        beautifyCustomerModal();
    });

    obs.observe(document.body, {childList:true, subtree:true});
})();
</script>
<!-- EGO_CUSTOMER_MODAL_STYLE_END -->

