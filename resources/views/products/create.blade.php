@extends('layouts.app')

@section('title', 'Nhập sản phẩm')

@php
    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $companies = $companies ?? collect();
    $companyWarehouses = $companyWarehouses ?? [];
    $priceTiers = $priceTiers ?? collect();

    $companyOptions = $companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values();

    $warehouseOptions = collect($companyWarehouses)->flatMap(function ($list, $companyId) {
        return collect($list)->map(fn($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'company_id' => (string) $companyId,
        ]);
    })->values();
@endphp

@section('content')
<style>
    :root{
        --ego-bg:#f3f7fb;
        --ego-card:#ffffff;
        --ego-text:#0f172a;
        --ego-muted:#64748b;
        --ego-line:#dbe7ef;
        --ego-line2:#eef4f8;
        --ego-main:#12aaa6;
        --ego-main2:#087f7b;
        --ego-danger:#ef4444;
        --ego-soft:#ecfeff;
        --ego-shadow:0 12px 34px rgba(15,23,42,.07);
    }
    .ego-page{padding:14px;background:var(--ego-bg);min-height:calc(100vh - 80px)}
    .ego-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
    .ego-head h1{font-size:22px;font-weight:950;margin:0;color:var(--ego-text);letter-spacing:-.02em}
    .ego-head p{font-size:12px;margin:3px 0 0;color:var(--ego-muted)}
    .ego-actions{display:flex;gap:8px;flex-wrap:wrap}
    .ego-btn{border:0;border-radius:12px;padding:9px 13px;font-size:13px;font-weight:950;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:.15s}
    .ego-btn:hover{transform:translateY(-1px)}
    .ego-btn-main{background:linear-gradient(135deg,var(--ego-main),#15c7c1);color:#fff;box-shadow:0 10px 22px rgba(18,170,166,.22)}
    .ego-btn-light{background:#fff;color:var(--ego-text);border:1px solid var(--ego-line)}
    .ego-btn-danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
    .ego-layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:12px;align-items:start}
    .ego-card{background:#fff;border:1px solid var(--ego-line);border-radius:20px;box-shadow:var(--ego-shadow);overflow:hidden}
    .ego-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid var(--ego-line);background:linear-gradient(135deg,#f8ffff,#effafa)}
    .ego-card-head h2{font-size:15px;font-weight:950;margin:0;color:var(--ego-text)}
    .ego-card-head p{font-size:11px;color:var(--ego-muted);margin:2px 0 0}
    .ego-body{padding:12px}
    .ego-label{font-size:10px;font-weight:950;text-transform:uppercase;color:#334155;margin-bottom:4px;display:block;letter-spacing:.025em}
    .ego-label b{color:var(--ego-danger)}
    .ego-input,.ego-select,.ego-textarea{
        width:100%;height:38px;border:1px solid var(--ego-line);border-radius:12px;background:#fff;
        padding:8px 10px;color:var(--ego-text);outline:none;font-size:13px;font-weight:650;
        transition:.15s;
    }
    .ego-textarea{height:74px;resize:vertical}
    .ego-input:focus,.ego-select:focus,.ego-textarea:focus{border-color:#67e8f9;box-shadow:0 0 0 4px rgba(103,232,249,.16)}
    .top-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:10px}
    .line-card{border:1px solid var(--ego-line);border-radius:18px;background:#fff;margin-top:11px;overflow:hidden;box-shadow:0 8px 22px rgba(15,23,42,.045)}
    .line-top{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 11px;background:linear-gradient(135deg,#f8fafc,#f0fdfa);border-bottom:1px solid var(--ego-line)}
    .line-title{font-size:14px;font-weight:950;color:var(--ego-text);display:flex;align-items:center;gap:8px}
    .line-no{width:26px;height:26px;border-radius:999px;background:#ccfbf1;color:#0f766e;display:inline-flex;align-items:center;justify-content:center;font-weight:950}
    .line-body{padding:11px}
    .line-grid-1{display:grid;grid-template-columns:1.3fr 1fr .58fr 1fr .7fr;gap:8px;align-items:end}
    .line-grid-2{display:grid;grid-template-columns:.95fr .8fr 1.25fr;gap:8px;align-items:end;margin-top:8px}
    .line-grid-3{display:grid;grid-template-columns:1fr 1fr;gap:8px;align-items:end;margin-top:8px;padding-top:8px;border-top:1px dashed #cbd5e1}
    .serial-box{margin-top:8px;background:#f8ffff;border:1px dashed #99f6e4;border-radius:14px;padding:9px}
    .serial-box .ego-textarea{height:86px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px}
    .serial-help{font-size:11px;color:#0f766e;font-weight:750;margin-top:5px}
    .readonly-box{height:38px;background:var(--ego-soft);border:1px solid #99f6e4;color:#0f766e;border-radius:12px;padding:8px 10px;text-align:right;font-weight:950;font-size:13px}
    .line-foot{text-align:right;font-size:11px;color:var(--ego-muted);margin-top:6px}
    .money{font-weight:950;color:var(--ego-main2)}
    .summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:11px}
    .summary-item{background:#fff;border:1px solid var(--ego-line);border-radius:16px;padding:10px;text-align:center;box-shadow:0 6px 18px rgba(15,23,42,.04)}
    .summary-item span{display:block;font-size:10px;font-weight:950;text-transform:uppercase;color:var(--ego-muted)}
    .summary-item b{display:block;margin-top:3px;font-size:18px;color:var(--ego-text)}
    .price-card{position:sticky;top:86px}
    .price-row{border:1px solid var(--ego-line);border-radius:15px;padding:9px;background:#fff;margin-bottom:8px}
    .price-title{font-weight:950;color:var(--ego-text);font-size:12px;margin-bottom:6px}
    .price-grid{display:grid;grid-template-columns:1fr .55fr 1fr;gap:6px}
    .price-grid small{font-size:9px;color:var(--ego-muted);font-weight:950;text-transform:uppercase}
    .history-card{margin-top:12px}
    .table-wrap{overflow:auto}
    .history-table{width:100%;min-width:1080px;border-collapse:separate;border-spacing:0}
    .history-table th{background:#f1f5f9;color:#334155;font-size:10px;font-weight:950;text-transform:uppercase;padding:8px;border-bottom:1px solid var(--ego-line)}
    .history-table td{padding:8px;border-bottom:1px solid #e2e8f0;vertical-align:middle;font-size:12px}
    .tag{display:inline-flex;border-radius:999px;padding:4px 8px;background:#ecfeff;border:1px solid #99f6e4;color:#0f766e;font-weight:950;font-size:11px}
    .bottom-actions{position:sticky;bottom:0;z-index:5;display:flex;justify-content:flex-end;gap:8px;padding:10px 0 0;margin-top:8px;background:rgba(243,247,251,.92);backdrop-filter:blur(10px)}
    .text-end{text-align:right}
    @media(max-width:1200px){.ego-layout{grid-template-columns:1fr}.price-card{position:relative;top:auto}}
    @media(max-width:992px){.top-grid,.line-grid-1,.line-grid-2,.line-grid-3,.summary-grid,.price-grid{grid-template-columns:1fr}}
</style>

<div class="container-fluid ego-page">
    <div class="ego-head">
        <div>
            <h1>Nhập sản phẩm</h1>
            <p>Tên sản phẩm + SKU + Công ty + Kho + Giá vốn + Số lượng + Chi phí riêng.</p>
        </div>
        <div class="ego-actions">
            <a href="{{ route('products.input') }}" class="ego-btn ego-btn-light">Quay lại</a>
            <button type="submit" form="productCreateForm" class="ego-btn ego-btn-main">Lưu sản phẩm</button>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <b>Có lỗi:</b>
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form id="productCreateForm" action="{{ route('products.store') }}" method="POST">
        @csrf
        <input type="hidden" name="sku" id="mainSku" value="">
        <input type="hidden" name="price_agent" id="mainCost" value="0">
        <input type="hidden" name="cost_vat_percent" id="mainVat" value="0">

        <div class="ego-layout">
            <div>
                <div class="ego-card">
                    <div class="ego-card-head">
                        <div>
                            <h2>Thông tin sản phẩm</h2>
                            <p>Mỗi SKU bên dưới là một dòng tồn riêng.</p>
                        </div>
                        <button type="button" id="addLineBtn" class="ego-btn ego-btn-main">+ Thêm ô</button>
                    </div>

                    <div class="ego-body">
                        <div class="top-grid">
                            <div>
                                <label class="ego-label">Tên sản phẩm <b>*</b></label>
                                <input class="ego-input" name="name" id="productName" value="{{ old('name') }}" required placeholder="Ví dụ: Pin lưu trữ PowerBrick SC">
                            </div>
                            <div>
                                <label class="ego-label">Danh mục</label>
                                <select class="ego-select" name="category_id">
                                    <option value="">Chọn danh mục</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ (string)old('category_id') === (string)$cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="ego-label">Thương hiệu</label>
                                <select class="ego-select" name="brand_id">
                                    <option value="">Chọn thương hiệu</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ (string)old('brand_id') === (string)$brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="linesContainer"></div>

                        <div class="summary-grid">
                            <div class="summary-item"><span>Tổng số dòng</span><b id="sumLines">0</b></div>
                            <div class="summary-item"><span>Tổng tồn kho</span><b id="sumQty">0</b></div>
                            <div class="summary-item"><span>Tổng vốn</span><b id="sumAmount">0 đ</b></div>
                            <div class="summary-item"><span>SKU dòng đầu</span><b id="sumSku">-</b></div>
                        </div>
                    </div>
                </div>

                <div class="ego-card history-card">
                    <div class="ego-card-head">
                        <div>
                            <h2>Lịch sử / Dashboard dòng nhập</h2>
                            <p>Kiểm tra nhanh trước khi lưu.</p>
                        </div>
                    </div>
                    <div class="ego-body">
                        <div class="table-wrap">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>STT</th><th>Tên SP</th><th>SKU</th><th>Công ty</th><th>Kho</th><th>Ngày</th>
                                        <th class="text-end">Giá vốn</th><th class="text-end">VAT</th><th class="text-end">Sau VAT</th>
                                        <th class="text-end">SL</th><th class="text-end">Chi phí</th><th class="text-end">Vốn TT</th>
                                        <th class="text-end">Tổng</th><th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody id="historyBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bottom-actions">
                    <a href="{{ route('products.input') }}" class="ego-btn ego-btn-light">Hủy</a>
                    <button type="submit" class="ego-btn ego-btn-main">Lưu sản phẩm</button>
                </div>
            </div>

            <div>
                <div class="ego-card price-card">
                    <div class="ego-card-head">
                        <div>
                            <h2>Giá bán / Giá đại lý</h2>
                            <p>Trước VAT | VAT | Sau VAT</p>
                        </div>
                    </div>
                    <div class="ego-body">
                        <div class="price-row sale-row">
                            <div class="price-title">Giá bán mặc định</div>
                            <div class="price-grid">
                                <div><small>Trước VAT</small><input class="ego-input text-end sale-before" type="number" min="0" step="0.01" name="price_retail" value="{{ old('price_retail', 0) }}"></div>
                                <div><small>VAT</small><input class="ego-input text-end sale-vat" type="number" min="0" max="100" step="0.01" name="vat_percent" value="{{ old('vat_percent', 0) }}"></div>
                                <div><small>Sau VAT</small><input class="ego-input text-end sale-after" type="text" readonly value="0 đ"></div>
                            </div>
                        </div>

                        @foreach($priceTiers as $tier)
                            <div class="price-row sale-row">
                                <div class="price-title">{{ $tier->name }}</div>
                                <div class="price-grid">
                                    <div><small>Trước VAT</small><input class="ego-input text-end sale-before" type="number" min="0" step="0.01" name="prices[{{ $tier->id }}][before_vat]" value="{{ old('prices.' . $tier->id . '.before_vat') }}"></div>
                                    <div><small>VAT</small><input class="ego-input text-end sale-vat" type="number" min="0" max="100" step="0.01" name="prices[{{ $tier->id }}][vat_percent]" value="{{ old('prices.' . $tier->id . '.vat_percent', 0) }}"></div>
                                    <div><small>Sau VAT</small><input class="ego-input text-end sale-after" type="text" readonly value="0 đ"></div>
                                </div>
                            </div>
                        @endforeach

                        <div class="price-row">
                            <div class="price-title">Ghi chú chung</div>
                            <textarea class="ego-textarea" name="note" placeholder="Ghi chú sản phẩm...">{{ old('note') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    const companies = @json($companyOptions);
    const warehouses = @json($warehouseOptions);
    const linesContainer = document.getElementById('linesContainer');
    const addLineBtn = document.getElementById('addLineBtn');
    const historyBody = document.getElementById('historyBody');

    function n(v){ return Number(String(v || '').replace(/,/g,'')) || 0; }
    function money(v){ return Math.round(Number(v || 0)).toLocaleString('vi-VN') + ' đ'; }
    function esc(v){ return String(v || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
    function serialCount(v){ return String(v || '').split(/[\r\n,;]+/).map(x => x.trim()).filter(Boolean).length; }

    function calcSaleRows(){
        document.querySelectorAll('.sale-row').forEach(row => {
            const before = n(row.querySelector('.sale-before')?.value);
            const vat = n(row.querySelector('.sale-vat')?.value);
            const out = row.querySelector('.sale-after');
            if (out) out.value = money(before * (1 + vat / 100));
        });
    }

    function companyOptionsHtml(){
        return '<option value="">Chọn công ty</option>' + companies.map(c => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
    }

    function warehouseOptionsHtml(companyId){
        let html = '<option value="">Chọn kho</option>';
        warehouses.forEach(w => {
            if (!companyId || String(w.company_id) === String(companyId)) {
                html += `<option value="${esc(w.id)}">${esc(w.name)}</option>`;
            }
        });
        return html;
    }

    function rowHtml(index){
        const today = '{{ date('Y-m-d') }}';
        return `
            <div class="line-card" data-index="${index}">
                <div class="line-top">
                    <div class="line-title"><span class="line-no">${index + 1}</span><span>Dòng tồn / SKU ${index + 1}</span></div>
                    <button type="button" class="ego-btn ego-btn-danger btn-remove-line">Xóa</button>
                </div>
                <div class="line-body">
                    <div class="line-grid-1">
                        <div><label class="ego-label">Mã SKU <b>*</b></label><input class="ego-input line-sku" name="v2_lines[${index}][sku]" required placeholder="VD: PBSC-001-A"></div>
                        <div><label class="ego-label">Giá trước VAT</label><input class="ego-input text-end line-cost" type="number" min="0" step="0.01" name="v2_lines[${index}][cost_before_vat]" value="0"></div>
                        <div><label class="ego-label">VAT</label><input class="ego-input text-end line-vat" type="number" min="0" max="100" step="0.01" name="v2_lines[${index}][cost_vat_percent]" value="0"></div>
                        <div><label class="ego-label">Giá sau VAT</label><div class="readonly-box line-after">0 đ</div></div>
                        <div><label class="ego-label">Số lượng</label><input class="ego-input text-end line-qty" type="number" min="0" step="1" name="v2_lines[${index}][qty_in]" value="1"></div>
                    </div>

                    <div class="line-grid-2">
                        <div><label class="ego-label">Ngày nhập</label><input class="ego-input line-date" type="date" name="v2_lines[${index}][received_at]" value="${today}"></div>
                        <div><label class="ego-label">Chi phí +/-</label><input class="ego-input text-end line-extra" type="number" step="0.01" name="v2_lines[${index}][extra_cost]" value="0"></div>
                        <div><label class="ego-label">Ghi chú</label><input class="ego-input line-note" type="text" name="v2_lines[${index}][note]" placeholder="VD: vận chuyển, bốc xếp..."></div>
                    </div>

                    <div class="line-grid-3">
                        <div><label class="ego-label">Chọn công ty <b>*</b></label><select class="ego-select line-company" name="v2_lines[${index}][company_id]" required>${companyOptionsHtml()}</select></div>
                        <div><label class="ego-label">Chọn kho <b>*</b></label><select class="ego-select line-warehouse" name="v2_lines[${index}][warehouse_id]" required>${warehouseOptionsHtml('')}</select></div>
                    </div>

                    <div class="serial-box">
                        <label class="ego-label">Serial/IMEI nếu sản phẩm cần quản lý serial</label>
                        <textarea class="ego-textarea line-serials" name="v2_lines[${index}][serials]" placeholder="Mỗi dòng 1 serial. VD:&#10;SN001&#10;SN002"></textarea>
                        <div class="serial-help">Có nhập serial thì hệ thống tự đánh dấu sản phẩm quản lý serial và tự lấy số lượng theo số serial.</div>
                    </div>

                    <div class="line-foot">
                        Giá vốn thực tế / cái: <span class="money line-actual">0 đ</span> · Tổng dòng: <span class="money line-total">0 đ</span>
                    </div>
                </div>
            </div>
        `;
    }

    function refreshIndexes(){
        document.querySelectorAll('.line-card').forEach((row, idx) => {
            row.dataset.index = idx;
            row.querySelector('.line-no').textContent = idx + 1;
            row.querySelector('.line-title span:last-child').textContent = 'Dòng tồn / SKU ' + (idx + 1);
            row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/v2_lines\[\d+\]/, 'v2_lines[' + idx + ']'));
        });
    }

    function recalcLine(row){
        const cost = n(row.querySelector('.line-cost').value);
        const vat = n(row.querySelector('.line-vat').value);
        const serialQty = serialCount(row.querySelector('.line-serials')?.value || '');
        if (serialQty > 0 && row.querySelector('.line-qty')) row.querySelector('.line-qty').value = serialQty;
        const qty = Math.max(0, n(row.querySelector('.line-qty').value));
        const extra = n(row.querySelector('.line-extra').value);
        const after = cost * (1 + vat / 100);
        const actual = after + extra / qty;
        const total = actual * qty;

        row.dataset.after = after;
        row.dataset.actual = actual;
        row.dataset.total = total;
        row.querySelector('.line-after').textContent = money(after);
        row.querySelector('.line-actual').textContent = money(actual);
        row.querySelector('.line-total').textContent = money(total);
    }

    function syncMain(){
        const first = document.querySelector('.line-card');
        if (!first) return;
        document.getElementById('mainSku').value = first.querySelector('.line-sku').value || '';
        document.getElementById('mainCost').value = first.querySelector('.line-cost').value || 0;
        document.getElementById('mainVat').value = first.querySelector('.line-vat').value || 0;
    }

    function updateHistory(){
        const productName = document.getElementById('productName').value || '';
        const rows = document.querySelectorAll('.line-card');
        historyBody.innerHTML = '';
        let totalQty = 0, totalAmount = 0, firstSku = '-';

        rows.forEach((row, idx) => {
            recalcLine(row);
            const sku = row.querySelector('.line-sku').value || '';
            const companySelect = row.querySelector('.line-company');
            const warehouseSelect = row.querySelector('.line-warehouse');
            const companyName = companySelect.options[companySelect.selectedIndex]?.text || '-';
            const warehouseName = warehouseSelect.options[warehouseSelect.selectedIndex]?.text || '-';
            const date = row.querySelector('.line-date').value || '-';
            const cost = n(row.querySelector('.line-cost').value);
            const vat = n(row.querySelector('.line-vat').value);
            const qty = Math.max(0, n(row.querySelector('.line-qty').value));
            const extra = n(row.querySelector('.line-extra').value);
            const after = Number(row.dataset.after || 0);
            const actual = Number(row.dataset.actual || 0);
            const total = Number(row.dataset.total || 0);
            const note = row.querySelector('.line-note').value || '';

            if (idx === 0 && sku) firstSku = sku;
            totalQty += qty;
            totalAmount += total;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="tag">${idx + 1}</span></td>
                <td>${esc(productName || '-')}</td>
                <td><b>${esc(sku || '-')}</b></td>
                <td>${esc(companyName)}</td>
                <td>${esc(warehouseName)}</td>
                <td>${esc(date)}</td>
                <td class="text-end">${cost.toLocaleString('vi-VN')}</td>
                <td class="text-end">${vat}%</td>
                <td class="text-end"><span class="money">${money(after)}</span></td>
                <td class="text-end"><b>${qty.toLocaleString('vi-VN')}</b>${serialQty > 0 ? `<div class="serial-help">Serial: ${serialQty}</div>` : ''}</td>
                <td class="text-end">${extra.toLocaleString('vi-VN')}</td>
                <td class="text-end"><span class="money">${money(actual)}</span></td>
                <td class="text-end"><span class="money">${money(total)}</span></td>
                <td>${esc(note || '-')}</td>
            `;
            historyBody.appendChild(tr);
        });

        document.getElementById('sumLines').textContent = rows.length;
        document.getElementById('sumQty').textContent = totalQty.toLocaleString('vi-VN');
        document.getElementById('sumAmount').textContent = money(totalAmount);
        document.getElementById('sumSku').textContent = firstSku;
        syncMain();
        calcSaleRows();
    }

    function bindLine(row){
        row.querySelectorAll('input,select').forEach(el => {
            el.addEventListener('input', updateHistory);
            el.addEventListener('change', updateHistory);
        });

        row.querySelector('.line-company').addEventListener('change', function(){
            row.querySelector('.line-warehouse').innerHTML = warehouseOptionsHtml(this.value || '');
            updateHistory();
        });

        row.querySelector('.btn-remove-line').addEventListener('click', function(){
            if (document.querySelectorAll('.line-card').length <= 1) {
                alert('Phải có ít nhất 1 dòng tồn.');
                return;
            }
            row.remove();
            refreshIndexes();
            updateHistory();
        });
    }

    function addLine(){
        const index = document.querySelectorAll('.line-card').length;
        linesContainer.insertAdjacentHTML('beforeend', rowHtml(index));
        bindLine(linesContainer.querySelectorAll('.line-card')[index]);
        updateHistory();
    }

    addLineBtn.addEventListener('click', addLine);
    document.getElementById('productName').addEventListener('input', updateHistory);
    document.querySelectorAll('.sale-before,.sale-vat').forEach(el => el.addEventListener('input', calcSaleRows));

    document.getElementById('productCreateForm').addEventListener('submit', function(e){
        for (const row of document.querySelectorAll('.line-card')) {
            if (!row.querySelector('.line-sku').value.trim() || !row.querySelector('.line-company').value || !row.querySelector('.line-warehouse').value) {
                e.preventDefault();
                alert('Mỗi dòng SKU phải nhập đủ Mã SKU, Công ty và Kho.');
                return false;
            }
        }
        syncMain();
    });

    addLine();
    calcSaleRows();
})();
</script>
@endsection
