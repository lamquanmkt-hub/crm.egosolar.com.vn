@extends('layouts.app')

@section('title', 'Chi tiết phiếu nhập '.$receipt->code)

@php
    $paymentLabels = [
        'unpaid' => ['Chưa thanh toán', 'warning'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
    ];
    $statusLabels = [
        'draft' => ['Phiếu nháp', 'warning'],
        'posted' => ['Đã nhập kho', 'success'],
    ];
    $pay = $paymentLabels[$receipt->payment_status] ?? [$receipt->payment_status ?: 'Chưa rõ', 'warning'];
    $status = $statusLabels[$receipt->status] ?? [$receipt->status ?: 'Chưa rõ', 'warning'];
@endphp

@section('content')
<style>
    :root{--rd-navy:#0b1f38;--rd-teal:#079b96;--rd-bg:#f3f7fb;--rd-card:#fff;--rd-text:#17304d;--rd-muted:#6c7e91;--rd-line:#dce7f0;--rd-soft:#ebfaf9;--rd-red:#e54b61;--rd-shadow:0 12px 32px rgba(19,42,67,.07)}
    .rd-page{padding:16px 18px 36px;background:var(--rd-bg);min-height:calc(100vh - 72px)}
    .rd-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px}.rd-eyebrow{font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.1em;color:var(--rd-teal);margin-bottom:5px}.rd-title{font-size:25px;line-height:1.15;font-weight:950;letter-spacing:-.035em;color:var(--rd-navy);margin:0}.rd-sub{font-size:12px;color:var(--rd-muted);font-weight:650;margin-top:5px}.rd-actions{display:flex;gap:8px;flex-wrap:wrap}
    .rd-btn{height:38px;border:1px solid var(--rd-line);border-radius:11px;background:#fff;color:var(--rd-text);font-size:12px;font-weight:900;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;white-space:nowrap}.rd-btn:hover{color:var(--rd-text);transform:translateY(-1px);box-shadow:0 8px 18px rgba(15,23,42,.08)}.rd-btn-primary{background:linear-gradient(135deg,#0bb4ae,var(--rd-teal));border-color:transparent;color:#fff}.rd-btn-primary:hover{color:#fff}
    .rd-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px}.rd-kpi{background:#fff;border:1px solid var(--rd-line);border-radius:15px;padding:13px 14px;box-shadow:var(--rd-shadow)}.rd-kpi span{display:block;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.05em;color:var(--rd-muted)}.rd-kpi b{display:block;font-size:20px;color:var(--rd-navy);margin-top:5px}.rd-kpi.red b{color:var(--rd-red)}
    .rd-card{background:#fff;border:1px solid var(--rd-line);border-radius:16px;box-shadow:var(--rd-shadow);overflow:hidden;margin-bottom:12px}.rd-card-head{padding:12px 14px;border-bottom:1px solid var(--rd-line);background:linear-gradient(135deg,#fbfefe,#f2fbfa);display:flex;justify-content:space-between;gap:10px;align-items:center}.rd-card-title{font-size:15px;font-weight:950;color:var(--rd-navy);margin:0}.rd-card-body{padding:14px}
    .rd-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.rd-field{border:1px solid var(--rd-line);border-radius:12px;padding:10px;background:#fff;min-height:66px}.rd-field.wide{grid-column:span 2}.rd-field label{display:block;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.05em;color:var(--rd-muted);margin-bottom:5px}.rd-field div{font-size:12px;font-weight:850;color:var(--rd-text);word-break:break-word}
    .rd-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 8px;font-size:10px;font-weight:950}.rd-badge-success{background:#e5f8ec;color:#16824a}.rd-badge-warning{background:#fff4d9;color:#9d6a00}
    .rd-table-wrap{overflow:auto}.rd-table{width:100%;min-width:980px;border-collapse:collapse}.rd-table th{background:#f4f8fb;padding:10px;font-size:9px;font-weight:950;text-transform:uppercase;color:#607489;border-bottom:1px solid var(--rd-line);text-align:left}.rd-table td{padding:11px 10px;border-bottom:1px solid #edf2f6;font-size:11px;color:var(--rd-text);vertical-align:middle}.rd-table tr:last-child td{border-bottom:0}.rd-product{font-weight:950;color:var(--rd-navy);text-decoration:none}.rd-product:hover{color:var(--rd-teal);text-decoration:underline}.rd-sku{display:inline-flex;margin-top:4px;padding:3px 7px;border-radius:999px;background:var(--rd-soft);color:#087a76;font-size:9px;font-weight:950}.rd-money{font-weight:950;white-space:nowrap}.rd-empty{text-align:center;color:var(--rd-muted);padding:30px!important}
    @media(max-width:1050px){.rd-summary{grid-template-columns:repeat(2,1fr)}.rd-info{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:680px){.rd-page{padding:12px}.rd-head{display:block}.rd-actions{margin-top:10px}.rd-summary,.rd-info{grid-template-columns:1fr}.rd-field.wide{grid-column:auto}.rd-title{font-size:21px}}
</style>

<div class="rd-page">
    <div class="rd-head">
        <div>
            <div class="rd-eyebrow">Kho &amp; Sản phẩm</div>
            <h1 class="rd-title">Phiếu nhập {{ $receipt->code }}</h1>
            <div class="rd-sub">Xem thông tin nhà cung cấp và mở trực tiếp từng sản phẩm trong phiếu.</div>
        </div>
        <div class="rd-actions">
            <a class="rd-btn" href="{{ route('product-goods-receipts.index') }}"><i class="bi bi-arrow-left"></i> Danh sách phiếu</a>
            <a class="rd-btn rd-btn-primary" href="{{ route('products.input') }}"><i class="bi bi-box-seam"></i> Danh sách sản phẩm</a>
        </div>
    </div>

    <div class="rd-summary">
        <div class="rd-kpi"><span>Tổng dòng hàng</span><b>{{ number_format($items->count()) }}</b></div>
        <div class="rd-kpi"><span>Tổng giá trị phiếu</span><b>{{ number_format((float) $receipt->total_amount) }} đ</b></div>
        <div class="rd-kpi"><span>Đã thanh toán</span><b>{{ number_format((float) $receipt->paid_amount) }} đ</b></div>
        <div class="rd-kpi red"><span>Công nợ còn lại</span><b>{{ number_format((float) $receipt->debt_amount) }} đ</b></div>
    </div>

    <section class="rd-card">
        <div class="rd-card-head">
            <h2 class="rd-card-title">Thông tin phiếu nhập</h2>
            <div style="display:flex;gap:7px;flex-wrap:wrap">
                <span class="rd-badge rd-badge-{{ $pay[1] }}">{{ $pay[0] }}</span>
                <span class="rd-badge rd-badge-{{ $status[1] }}">{{ $status[0] }}</span>
            </div>
        </div>
        <div class="rd-card-body">
            <div class="rd-info">
                <div class="rd-field"><label>Kho nhập</label><div>{{ $receipt->warehouse_name ?: 'Chưa xác định' }}</div></div>
                <div class="rd-field"><label>Nhà cung cấp</label><div>{{ $receipt->supplier_name ?: 'Chưa nhập' }}</div></div>
                <div class="rd-field"><label>Số điện thoại</label><div>{{ $receipt->supplier_phone ?: '—' }}</div></div>
                <div class="rd-field"><label>Mã số thuế</label><div>{{ $receipt->supplier_tax_code ?: '—' }}</div></div>
                <div class="rd-field"><label>Số hóa đơn</label><div>{{ $receipt->invoice_no ?: '—' }}</div></div>
                <div class="rd-field"><label>Ngày hóa đơn</label><div>{{ $receipt->invoice_date ? \Illuminate\Support\Carbon::parse($receipt->invoice_date)->format('d/m/Y') : '—' }}</div></div>
                <div class="rd-field"><label>Ngày thanh toán dự kiến</label><div>{{ $receipt->payment_due_date ? \Illuminate\Support\Carbon::parse($receipt->payment_due_date)->format('d/m/Y') : '—' }}</div></div>
                <div class="rd-field"><label>Ngày nhập kho</label><div>{{ $receipt->posted_at ? \Illuminate\Support\Carbon::parse($receipt->posted_at)->format('d/m/Y H:i') : 'Chưa nhập kho' }}</div></div>
                <div class="rd-field wide"><label>Địa chỉ nhà cung cấp</label><div>{{ $receipt->supplier_address ?: '—' }}</div></div>
                <div class="rd-field wide"><label>Ghi chú</label><div>{{ $receipt->note ?: 'Không có ghi chú' }}</div></div>
            </div>
        </div>
    </section>

    <section class="rd-card">
        <div class="rd-card-head">
            <div>
                <h2 class="rd-card-title">Sản phẩm trong phiếu</h2>
                <div class="rd-sub">Bấm tên hoặc SKU để mở hồ sơ sản phẩm.</div>
            </div>
        </div>
        <div class="rd-table-wrap">
            <table class="rd-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>VAT</th>
                        <th>Thành tiền</th>
                        <th>Ghi chú</th>
                        <th>Mở</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                @if($item->product_id)
                                    <a class="rd-product" href="{{ route('products.show', $item->product_id) }}">{{ $item->product_name ?: 'Sản phẩm #'.$item->product_id }}</a>
                                    <div><a class="rd-sku" href="{{ route('products.show', $item->product_id) }}">{{ $item->product_sku ?: 'Chưa có SKU' }}</a></div>
                                @else
                                    <strong>{{ $item->product_name ?: 'Sản phẩm không xác định' }}</strong>
                                @endif
                            </td>
                            <td><strong>{{ number_format((float) $item->qty, 0, ',', '.') }} {{ $item->product_unit }}</strong></td>
                            <td class="rd-money">{{ number_format((float) $item->unit_price) }} đ</td>
                            <td>{{ number_format((float) $item->vat_percent, 0, ',', '.') }}%</td>
                            <td class="rd-money">{{ number_format((float) $item->amount) }} đ</td>
                            <td>{{ $item->note ?: '—' }}</td>
                            <td>
                                @if($item->product_id)
                                    <a class="rd-btn" href="{{ route('products.show', $item->product_id) }}"><i class="bi bi-box-arrow-up-right"></i> Xem sản phẩm</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="rd-empty">Phiếu này chưa có dòng sản phẩm.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
