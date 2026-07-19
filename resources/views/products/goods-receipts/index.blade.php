@extends('layouts.app')

@section('content')
@php
    $payMap = [
        'unpaid' => ['Chưa thanh toán', 'danger'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
    ];

    $statusMap = [
        'draft' => ['Nháp', 'warning'],
        'posted' => ['Đã nhập kho', 'success'],
    ];
@endphp

<style>
    .gr-wrap{max-width:1450px;margin:0 auto;padding:14px 12px 36px}
    .gr-hero{background:linear-gradient(135deg,#06172f,#0f766e);border-radius:18px;color:#fff;padding:16px 20px;margin-bottom:12px;box-shadow:0 12px 34px rgba(15,23,42,.14)}
    .gr-title{font-size:23px;font-weight:950;margin:0}.gr-sub{font-size:13px;font-weight:750;opacity:.9;margin-top:4px}
    .gr-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:10px 0 12px}
    .gr-stat,.gr-card{background:#fff;border:1px solid #e5edf5;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.055)}
    .gr-stat{padding:11px 14px;min-height:66px}.gr-stat b{font-size:22px;color:#0f172a}.gr-stat span{display:block;color:#64748b;font-size:13px;font-weight:850;margin-top:4px}
    .gr-card{padding:14px 16px;margin-bottom:12px}.gr-card h3{font-size:17px;font-weight:950;color:#0f172a;margin:0 0 10px}
    .gr-label{font-size:11px;font-weight:950;text-transform:uppercase;color:#475569;margin-bottom:4px}
    .gr-control{border:1px solid #dbe7f0;border-radius:11px;padding:7px 10px;height:38px;width:100%;outline:none;background:#fff;font-size:13px;font-weight:800}
    textarea.gr-control{height:auto;min-height:58px}
    .gr-control:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.10)}
    .gr-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:start}
    .gr-row-3{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:10px}
    .gr-item{display:grid;grid-template-columns:minmax(360px,1fr) 90px 120px 90px 130px 1fr 36px;gap:8px;align-items:end;margin-bottom:8px;padding:9px;border:1px dashed #dbe7f0;border-radius:14px;background:#fbfdff}
    .gr-search{margin-bottom:5px;height:36px;background:#fbfdff}
    .gr-btn{border:0;border-radius:11px;padding:8px 11px;font-size:13px;line-height:1;font-weight:950;text-decoration:none;display:inline-flex;gap:6px;align-items:center;justify-content:center;cursor:pointer;white-space:nowrap}
    .gr-btn-primary{background:#10b981;color:#fff}.gr-btn-dark{background:#0f172a;color:#fff}.gr-btn-light{background:#ecfeff;color:#0f766e}.gr-btn-danger{background:#fee2e2;color:#b91c1c}
    .gr-table{width:100%;border-collapse:separate;border-spacing:0 7px;font-size:13px}
    .gr-table th{font-size:11px;text-transform:uppercase;color:#64748b;text-align:left;padding:0 10px}
    .gr-table td{background:#f8fafc;border-top:1px solid #e5edf5;border-bottom:1px solid #e5edf5;padding:9px 10px;font-weight:800;color:#0f172a;vertical-align:middle}
    .gr-table td:first-child{border-left:1px solid #e5edf5;border-radius:11px 0 0 11px}.gr-table td:last-child{border-right:1px solid #e5edf5;border-radius:0 11px 11px 0}
    .gr-muted{color:#64748b;font-size:12px;font-weight:800}.gr-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
    .gr-badge{border-radius:999px;padding:5px 9px;font-size:11px;font-weight:950;white-space:nowrap}
    .gr-badge-success{background:#dcfce7;color:#15803d}.gr-badge-warning{background:#fef3c7;color:#b45309}.gr-badge-danger{background:#fee2e2;color:#b91c1c}
    .alert{border-radius:12px;font-weight:800;padding:9px 12px;margin-bottom:10px;font-size:13px}
    @media(max-width:1200px){.gr-row,.gr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gr-item{grid-template-columns:1fr 90px 120px 90px}}
    @media(max-width:760px){.gr-row,.gr-row-3,.gr-grid,.gr-item{grid-template-columns:1fr}.gr-wrap{padding:10px}.gr-table{font-size:12px}}
</style>

<div class="gr-wrap">
    <div class="gr-hero">
        <div class="gr-title">Nhập hàng hóa / Công nợ NCC</div>
        <div class="gr-sub">Ghi nhận NCC, hóa đơn, công nợ phải trả, ngày thanh toán dự kiến và nhập hàng vào kho.</div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="gr-grid">
        <div class="gr-stat"><b>{{ number_format($stats['total'] ?? 0) }}</b><span>Tổng phiếu</span></div>
        <div class="gr-stat"><b>{{ number_format($stats['posted'] ?? 0) }}</b><span>Đã nhập kho</span></div>
        <div class="gr-stat"><b>{{ number_format($stats['unpaid'] ?? 0) }}</b><span>Công nợ còn phải trả</span></div>
        <div class="gr-stat"><b>{{ number_format($stats['paid'] ?? 0) }}</b><span>Đã thanh toán</span></div>
    </div>

    <div class="gr-card">
        <h3>Tạo phiếu nhập hàng</h3>

        <form method="POST" action="{{ route('product-goods-receipts.store') }}">
            @csrf

            <div class="gr-row">
                <div>
                    <div class="gr-label">Công ty</div>
                    <select class="gr-control" name="company_id" id="grCompany" required>
                        <option value="">-- Chọn công ty --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code }} - {{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="gr-label">Kho nhập hàng</div>
                    <select class="gr-control" name="warehouse_id" data-company-filter="1" required>
                        <option value="">-- Chọn kho --</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="gr-label">Tên NCC</div>
                    <input class="gr-control" name="supplier_name" placeholder="Nhập tên nhà cung cấp" required>
                </div>

                <div>
                    <div class="gr-label">SĐT NCC</div>
                    <input class="gr-control" name="supplier_phone" placeholder="Không bắt buộc">
                </div>
            </div>

            <div class="gr-row" style="margin-top:9px">
                <div>
                    <div class="gr-label">MST NCC</div>
                    <input class="gr-control" name="supplier_tax_code" placeholder="Không bắt buộc">
                </div>

                <div>
                    <div class="gr-label">Số hóa đơn</div>
                    <input class="gr-control" name="invoice_no" placeholder="VD: HD001 / VAT001">
                </div>

                <div>
                    <div class="gr-label">Ngày hóa đơn</div>
                    <input class="gr-control" type="date" name="invoice_date" value="{{ now()->toDateString() }}">
                </div>

                <div>
                    <div class="gr-label">Ngày TT dự kiến</div>
                    <input class="gr-control" type="date" name="payment_due_date">
                </div>
            </div>

            <div class="gr-row-3" style="margin-top:9px">
                <div>
                    <div class="gr-label">Địa chỉ NCC</div>
                    <input class="gr-control" name="supplier_address" placeholder="Không bắt buộc">
                </div>

                <div>
                    <div class="gr-label">Trạng thái thanh toán</div>
                    <select class="gr-control" name="payment_status" required>
                        <option value="unpaid">Chưa thanh toán</option>
                        <option value="partial">Thanh toán một phần</option>
                        <option value="paid">Đã thanh toán</option>
                    </select>
                </div>

                <div>
                    <div class="gr-label">Số tiền đã TT</div>
                    <input class="gr-control" type="number" name="paid_amount" min="0" step="1000" value="0">
                </div>
            </div>

            <div style="margin-top:12px">
                <div class="gr-label">Hàng hóa nhập kho</div>
                <div id="grItems"></div>
                <button class="gr-btn gr-btn-light" type="button" onclick="grAddItem()">+ Thêm hàng hóa</button>
            </div>

            <div style="margin-top:10px">
                <div class="gr-label">Ghi chú</div>
                <textarea class="gr-control" name="note" rows="2" placeholder="Ví dụ: Nhập hàng từ NCC, chưa thanh toán, hẹn thanh toán ngày..."></textarea>
            </div>

            <div class="gr-actions" style="margin-top:12px">
                <button class="gr-btn gr-btn-dark" name="action" value="draft">Lưu nháp</button>
                <button class="gr-btn gr-btn-primary" name="action" value="post" onclick="return confirm('Xác nhận nhập hàng vào kho?')">Lưu & nhập kho</button>
            </div>
        </form>
    </div>

    <div class="gr-card">
        <h3>Danh sách phiếu nhập hàng</h3>

        <form method="GET" class="gr-row-3" style="margin-bottom:10px">
            <input class="gr-control" name="q" value="{{ $q }}" placeholder="Tìm mã phiếu, NCC, số hóa đơn...">
            <select class="gr-control" name="payment_status">
                <option value="">Tất cả thanh toán</option>
                <option value="unpaid" @selected($paymentStatus === 'unpaid')>Chưa thanh toán</option>
                <option value="partial" @selected($paymentStatus === 'partial')>Thanh toán một phần</option>
                <option value="paid" @selected($paymentStatus === 'paid')>Đã thanh toán</option>
            </select>
            <button class="gr-btn gr-btn-dark">Lọc</button>
        </form>

        <div style="overflow:auto">
            <table class="gr-table">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>NCC / Hóa đơn</th>
                        <th>Công ty / kho</th>
                        <th>Công nợ</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $row)
                        @php
                            $pay = $payMap[$row->payment_status] ?? [$row->payment_status, 'warning'];
                            $st = $statusMap[$row->status] ?? [$row->status, 'warning'];
                        @endphp
                        <tr>
                            <td>
                                <b>{{ $row->code }}</b><br>
                                <span class="gr-muted">{{ $row->invoice_date ? \Illuminate\Support\Carbon::parse($row->invoice_date)->format('d/m/Y') : '' }}</span>
                            </td>
                            <td>
                                {{ $row->supplier_name }}<br>
                                <span class="gr-muted">HĐ: {{ $row->invoice_no ?: 'Chưa nhập' }}</span>
                            </td>
                            <td>
                                {{ $row->company_name }}<br>
                                <span class="gr-muted">{{ $row->warehouse_name }}</span>
                            </td>
                            <td>
                                <b>{{ number_format($row->debt_amount) }}</b><br>
                                <span class="gr-badge gr-badge-{{ $pay[1] }}">{{ $pay[0] }}</span>
                                <div class="gr-muted">TT dự kiến: {{ $row->payment_due_date ? \Illuminate\Support\Carbon::parse($row->payment_due_date)->format('d/m/Y') : 'Chưa có' }}</div>
                            </td>
                            <td><span class="gr-badge gr-badge-{{ $st[1] }}">{{ $st[0] }}</span></td>
                            <td>
                                <div class="gr-actions">
                                    @if($row->status !== 'posted')
                                        <form method="POST" action="{{ route('product-goods-receipts.post', $row->id) }}">
                                            @csrf
                                            <button class="gr-btn gr-btn-primary" onclick="return confirm('Nhập kho phiếu này?')">Nhập kho</button>
                                        </form>

                                        <form method="POST" action="{{ route('product-goods-receipts.destroy', $row->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="gr-btn gr-btn-danger" onclick="return confirm('Xóa phiếu nháp này?')">Xóa</button>
                                        </form>
                                    @else
                                        <span class="gr-muted">Đã khóa</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center">Chưa có phiếu nhập hàng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $receipts->links() }}
    </div>
</div>

<template id="grItemTemplate">
    <div class="gr-item">
        <div>
            <div class="gr-label">Sản phẩm</div>
            <input class="gr-control gr-search" type="text" placeholder="Gõ tìm sản phẩm..." oninput="grFilterSelect(this)">
            <select class="gr-control" data-name="product_id" data-company-filter="1" required>
                <option value="">-- Chọn sản phẩm --</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-company="{{ $product->company_id }}">
                        {{ $product->sku }} - {{ $product->name }} {{ $product->unit ? '('.$product->unit.')' : '' }} - Tồn {{ number_format($product->stock_qty) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="gr-label">SL</div>
            <input class="gr-control" data-name="qty" type="number" min="0.001" step="0.001" value="1" required oninput="grCalcRow(this)">
        </div>

        <div>
            <div class="gr-label">Đơn giá</div>
            <input class="gr-control" data-name="unit_price" type="number" min="0" step="1000" value="0" oninput="grCalcRow(this)">
        </div>

        <div>
            <div class="gr-label">VAT %</div>
            <input class="gr-control" data-name="vat_percent" type="number" min="0" step="1" value="0" oninput="grCalcRow(this)">
        </div>

        <div>
            <div class="gr-label">Thành tiền</div>
            <input class="gr-control" data-total readonly value="0">
        </div>

        <div>
            <div class="gr-label">Ghi chú</div>
            <input class="gr-control" data-name="note" placeholder="Không bắt buộc">
        </div>

        <button type="button" class="gr-btn gr-btn-danger" onclick="this.closest('.gr-item').remove(); grReindexItems();">×</button>
    </div>
</template>

<script>
function grNormalize(v){
    return (v || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

function grFilterSelect(input){
    var select = input.parentElement.querySelector('select');
    if(!select){ return; }

    var q = grNormalize(input.value);

    Array.prototype.forEach.call(select.options, function(opt, idx){
        if(idx === 0){ opt.hidden = false; return; }
        var text = grNormalize(opt.textContent || '');
        opt.hidden = q && text.indexOf(q) === -1;
    });
}

function grCalcRow(el){
    var row = el.closest('.gr-item');
    if(!row){ return; }

    var qty = parseFloat(row.querySelector('[data-name="qty"]').value || 0);
    var price = parseFloat(row.querySelector('[data-name="unit_price"]').value || 0);
    var vat = parseFloat(row.querySelector('[data-name="vat_percent"]').value || 0);
    var amount = qty * price * (1 + vat / 100);

    row.querySelector('[data-total]').value = Math.round(amount).toLocaleString('vi-VN');
}

function grReindexItems(){
    document.querySelectorAll('#grItems .gr-item').forEach(function(row, i){
        row.querySelectorAll('[data-name]').forEach(function(el){
            el.name = 'items[' + i + '][' + el.dataset.name + ']';
        });
    });
}

function grAddItem(){
    var tpl = document.getElementById('grItemTemplate');
    var node = tpl.content.cloneNode(true);
    document.getElementById('grItems').appendChild(node);
    grReindexItems();
    grApplyCompanyFilter();
}

function grApplyCompanyFilter(){
    var companyId = document.getElementById('grCompany') ? document.getElementById('grCompany').value : '';

    document.querySelectorAll('select[data-company-filter="1"]').forEach(function(select){
        Array.prototype.forEach.call(select.options, function(opt, idx){
            if(idx === 0){ opt.hidden = false; return; }

            var c = opt.getAttribute('data-company') || '';
            opt.hidden = !!companyId && !!c && c !== companyId;
        });

        if(select.selectedOptions[0] && select.selectedOptions[0].hidden){
            select.value = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', function(){
    grAddItem();
    grApplyCompanyFilter();

    var company = document.getElementById('grCompany');
    if(company){
        company.addEventListener('change', grApplyCompanyFilter);
    }
});
</script>
@endsection
