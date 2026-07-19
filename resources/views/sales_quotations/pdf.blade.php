@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', '.');
    $qtyFmt = function ($n) {
        $value = rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        return $value === '' ? '0' : $value;
    };

    $sectionOrder = [
        'main' => ['code' => 'I', 'title' => 'Vật tư / thiết bị chính'],
        'sub' => ['code' => 'II', 'title' => 'Vật tư / thiết bị phụ'],
        'ac_cabinet' => ['code' => 'III', 'title' => 'Tủ AC'],
        'grounding' => ['code' => 'IV', 'title' => 'Hệ thống tiếp địa'],
        'other' => ['code' => 'V', 'title' => 'Các hạng mục khác'],
        'om' => ['code' => 'VI', 'title' => 'Bảo hành & O&M'],
    ];

    $grouped = $quotation->items->groupBy(fn($item) => $item->section_key ?: 'main');

    $logoFile = null;
    foreach (['images/ego-logo.png', 'images/ego-logo.jpg', 'images/ego-logo.jpeg', 'images/ego-logo.webp'] as $file) {
        if (file_exists(public_path($file))) {
            $logoFile = $file;
            break;
        }
    }

    $logoSrc = null;

    if ($logoFile) {
        $path = public_path($logoFile);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        if (!empty($downloadMode) && file_exists($path)) {
            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        } else {
            $logoSrc = asset($logoFile);
        }
    }

    $isEmbed = !empty($embed);
@endphp

@if(empty($embed))
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->quote_code ?: 'bao-gia' }}</title>
@endif

<style>
    @page {
        size: A4 portrait;
        margin: 7mm 7mm 9mm 7mm;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        background: #ffffff;
    }

    body,
    .ego-pdf,
    .ego-pdf * {
        font-family: "DejaVu Serif", "Times New Roman", Times, serif;
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    .ego-pdf {
        width: 100%;
        max-width: {{ $isEmbed ? '1080px' : '196mm' }};
        margin: 0 auto;
        background: #ffffff;
        color: #111827;
        font-size: {{ $isEmbed ? '14px' : '11.8px' }};
        line-height: 1.35;
    }

    .pdf-card {
        border: 1px solid #9db4cc;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
        background: #ffffff;
    }

    .soft-card {
        border: 1px solid #c6d7e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
        background: #ffffff;
    }

    .pdf-header {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-header td {
        border: 0;
        vertical-align: top;
        padding: 10px 12px 8px;
    }

    .logo-img {
        height: {{ $isEmbed ? '72px' : '58px' }};
        max-width: {{ $isEmbed ? '280px' : '230px' }};
        object-fit: contain;
        display: block;
        margin-bottom: 4px;
    }

    .logo-text {
        font-size: 24px;
        font-weight: bold;
        color: #0f766e;
        margin-bottom: 4px;
    }

    .left-info,
    .company-info {
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
        line-height: 1.35;
    }

    .company-info {
        text-align: right;
    }

    .green-line {
        height: 2px;
        background: #0f766e !important;
        margin: 0 12px 10px;
    }

    .title-box {
        text-align: center;
        padding: 8px 10px 9px;
    }

    .title-box h1 {
        margin: 0;
        font-size: {{ $isEmbed ? '23px' : '18px' }};
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: .2px;
    }

    .title-box p {
        margin: 3px 0 0;
        font-size: {{ $isEmbed ? '13px' : '11px' }};
        color: #374151;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .quote-meta {
        table-layout: fixed;
    }

    .quote-meta td {
        border: 1px solid #9db4cc;
        background: #f3f8ff !important;
        padding: 7px 8px;
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
    }

    .info-table {
        table-layout: fixed;
    }

    .info-table th {
        background: #cfe2ff !important;
        color: #111827;
        border: 1px solid #9db4cc;
        padding: 7px 8px;
        text-align: left;
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
        font-weight: bold;
    }

    .info-table td {
        border: 1px solid #9db4cc;
        padding: 7px 8px;
        vertical-align: top;
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
        word-break: break-word;
    }

    .items-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .items-table th {
        background: #cfe2ff !important;
        color: #111827;
        border: 1px solid #8fa8c4;
        padding: 7px 6px;
        text-align: center;
        font-size: {{ $isEmbed ? '12.8px' : '10.4px' }};
        font-weight: bold;
        line-height: 1.15;
    }

    .items-table td {
        border: 1px solid #9db4cc;
        padding: 7px 6px;
        vertical-align: top;
        font-size: {{ $isEmbed ? '12.8px' : '10.4px' }};
        line-height: 1.28;
        word-break: break-word;
    }

    .section-row td {
        background: #dbeafe !important;
        color: #111827;
        font-weight: bold;
        padding: 8px 9px;
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
    }

    .product-name {
        font-weight: bold;
        color: #111827;
        line-height: 1.28;
    }

    .spec {
        margin-top: 2px;
        color: #374151;
        font-size: {{ $isEmbed ? '11.2px' : '9.1px' }};
        white-space: pre-line;
        line-height: 1.24;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .total-label {
        background: #f8fafc !important;
        font-weight: bold;
        text-align: right;
        padding: 6px 7px !important;
    }

    .total-value {
        background: #f8fafc !important;
        font-weight: bold;
        text-align: right;
        padding: 6px 7px !important;
    }

    .grand-label,
    .grand-value {
        background: #cfe2ff !important;
        color: #111827;
        font-size: {{ $isEmbed ? '14px' : '12px' }};
        font-weight: bold;
        padding: 9px !important;
    }

    .grand-label {
        text-align: right;
    }

    .grand-value {
        text-align: right;
    }

    .terms-table {
        table-layout: fixed;
    }

    .terms-table td {
        width: 50%;
        border: 1px solid #9db4cc;
        padding: 0;
        vertical-align: top;
    }

    .terms-title {
        background: #dbeafe !important;
        color: #111827;
        font-weight: bold;
        padding: 7px 8px;
        border-bottom: 1px solid #9db4cc;
        font-size: {{ $isEmbed ? '10.5px' : '9.2px' }};
    }

    .terms-content {
        padding: 7px;
        white-space: pre-line;
        color: #374151;
        font-size: {{ $isEmbed ? '12px' : '9.8px' }};
        line-height: 1.35;
        min-height: 48px;
    }

    .signature-table {
        table-layout: fixed;
        margin-top: 10px;
    }

    .signature-table td {
        width: 50%;
        border: 0;
        text-align: center;
        height: 56px;
        vertical-align: top;
        font-weight: bold;
        font-size: {{ $isEmbed ? '13px' : '10.8px' }};
    }

    .signature-table span {
        font-weight: 400;
        color: #4b5563;
    }

    .footer-note {
        text-align: center;
        margin-top: 4px;
        color: #4b5563;
        font-size: {{ $isEmbed ? '11px' : '9px' }};
    }

    tr,
    td,
    th {
        page-break-inside: avoid !important;
    }

    thead {
        display: table-header-group;
    }


    /* EGO_QUOTE_PDF_BIGGER_POLISH_START */
    .pdf-card,
    .soft-card {
        border-color: #8fb0d2;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .pdf-header td {
        padding: {{ $isEmbed ? '15px 16px 11px' : '11px 13px 9px' }};
    }

    .green-line {
        height: 2.5px;
        background: linear-gradient(90deg, #0891b2, #0f766e) !important;
        margin: 0 14px 12px;
    }

    .title-box {
        padding: {{ $isEmbed ? '13px 12px 14px' : '10px 10px 11px' }};
    }

    .title-box h1 {
        color: #0f172a;
        letter-spacing: .35px;
    }

    .quote-meta td,
    .info-table th,
    .info-table td,
    .items-table th,
    .items-table td {
        border-color: #8fb0d2;
    }

    .quote-meta td {
        background: #eaf3ff !important;
        font-weight: 650;
    }

    .info-table th,
    .items-table th {
        background: #cfe3fb !important;
        color: #0f172a;
    }

    .section-row td {
        background: #d7e8fb !important;
        color: #0f172a;
        text-transform: none;
    }

    .product-name {
        font-weight: 800;
        color: #0f172a;
    }

    .spec {
        color: #334155;
    }

    .right,
    .total-value,
    .grand-value {
        font-variant-numeric: tabular-nums;
    }

    .grand-label,
    .grand-value {
        background: #d7e8fb !important;
        color: #0f172a;
        font-weight: 900;
    }

    .terms-title {
        background: #d7e8fb !important;
    }
    /* EGO_QUOTE_PDF_BIGGER_POLISH_END */

    @media print {
        html,
        body {
            width: 210mm;
            background: #ffffff !important;
        }

        .ego-pdf {
            max-width: 194mm;
            width: 194mm;
        }

        .no-print {
            display: none !important;
        }
    }
</style>

@if(empty($embed))
</head>
<body>
@endif

<div class="ego-pdf">
    <div class="pdf-card">
        <table class="pdf-header">
            <tr>
                <td style="width:50%;">
                    @if($logoSrc)
                        <img class="logo-img" src="{{ $logoSrc }}" alt="EGO SOLAR">
                    @else
                        <div class="logo-text">EGO SOLAR</div>
                    @endif

                    <div class="left-info">
                        Web: egosolar.vn - egosolar.com.vn<br>
                        Hotline: 0937610858
                    </div>
                </td>

                <td style="width:50%;" class="company-info">
                    <b>CÔNG TY TNHH TM KỸ THUẬT QUỐC TẾ EGO</b><br>
                    26 Đường 24B, Phường Bình Trưng, TPHCM<br>
                    MST: 0315672171
                </td>
            </tr>
        </table>

        <div class="green-line"></div>

        <div class="title-box">
            <h1>BÁO GIÁ / DỰ TOÁN VẬT TƯ CHI TIẾT</h1>
            <p>{{ $quotation->project_name ?: 'Dự án điện mặt trời' }}</p>
        </div>
    </div>

    <div class="soft-card">
        <table class="quote-meta">
            <tr>
                <td style="width:33.33%;"><b>Báo giá số:</b> {{ $quotation->quote_code }}</td>
                <td style="width:33.33%;"><b>Lập ngày:</b> {{ optional($quotation->quote_date)->format('d/m/Y') }}</td>
                <td style="width:33.33%;"><b>Có hiệu lực đến:</b> {{ optional($quotation->valid_until)->format('d/m/Y') }}</td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <th colspan="2">Thông tin khách hàng</th>
                <th colspan="2">Mô tả hệ thống / dự án</th>
            </tr>
            <tr>
                <td style="width:15%;"><b>Tên khách hàng</b></td>
                <td style="width:35%;">{{ $quotation->customer_name ?: '—' }}</td>
                <td style="width:15%;"><b>Dự án</b></td>
                <td style="width:35%;">{{ $quotation->project_name ?: '—' }}</td>
            </tr>
            <tr>
                <td><b>Điện thoại</b></td>
                <td>{{ $quotation->customer_phone ?: '—' }}</td>
                <td><b>Địa chỉ dự án</b></td>
                <td>{{ $quotation->project_address ?: '—' }}</td>
            </tr>
            <tr>
                <td><b>Email</b></td>
                <td>{{ $quotation->customer_email ?: '—' }}</td>
                <td><b>Công suất</b></td>
                <td>{{ $quotation->system_kwp ? rtrim(rtrim(number_format((float) $quotation->system_kwp, 2, ',', '.'), '0'), ',') . ' kWp' : '—' }}</td>
            </tr>
            <tr>
                <td><b>MST</b></td>
                <td>{{ $quotation->billing_tax_code ?: '—' }}</td>
                <td><b>Cấu hình</b></td>
                <td>{{ $quotation->config_summary ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="soft-card">
        <table class="items-table">
            <colgroup>
                <col style="width:5%;">
                <col style="width:42%;">
                <col style="width:13%;">
                <col style="width:7%;">
                <col style="width:12%;">
                <col style="width:8%;">
                <col style="width:13%;">
            </colgroup>

            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên VT/HH</th>
                    <th>SKU</th>
                    <th>ĐVT</th>
                    <th>Đơn giá</th>
                    <th>Số lượng</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>

            <tbody>
                @foreach($sectionOrder as $key => $section)
                    @php
                        $rows = $grouped->get($key, collect());
                    @endphp

                    @if($rows->count())
                        <tr class="section-row">
                            <td colspan="7">{{ $section['code'] }}. {{ $section['title'] }}</td>
                        </tr>

                        @foreach($rows as $i => $item)
                            <tr>
                                <td class="center">{{ $i + 1 }}</td>
                                <td>
                                    <div class="product-name">{{ $item->product_name }}</div>

                                    @if($item->specs_text)
                                        <div class="spec">{{ $item->specs_text }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->sku }}</td>
                                <td class="center">{{ $item->unit }}</td>
                                <td class="right">{{ $fmt($item->unit_price) }}</td>
                                <td class="right">{{ $qtyFmt($item->qty) }}</td>
                                <td class="right"><b>{{ $fmt($item->line_total) }}</b></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach

                <tr>
                    <td colspan="6" class="total-label">Tạm tính</td>
                    <td class="total-value">{{ $fmt($quotation->subtotal) }} đ</td>
                </tr>
                <tr>
                    <td colspan="6" class="total-label">Chiết khấu</td>
                    <td class="total-value">{{ $fmt($quotation->discount_amount) }} đ</td>
                </tr>
                <tr>
                    <td colspan="6" class="grand-label">TỔNG CHI PHÍ</td>
                    <td class="grand-value">{{ $fmt($quotation->grand_total) }} đ</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="soft-card">
        <table class="terms-table">
            <tr>
                <td>
                    <div class="terms-title">Điều kiện thanh toán</div>
                    <div class="terms-content">{{ $quotation->payment_terms }}</div>
                </td>
                <td>
                    <div class="terms-title">Điều kiện thương mại</div>
                    <div class="terms-content">{{ $quotation->commercial_terms }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="terms-title">Bảo hành</div>
                    <div class="terms-content">{{ $quotation->warranty_terms }}</div>
                </td>
                <td>
                    <div class="terms-title">Vận hành & bảo trì O&M</div>
                    <div class="terms-content">{{ $quotation->om_terms }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                ĐẠI DIỆN KHÁCH HÀNG<br>
                <span>Ký, ghi rõ họ tên</span>
            </td>
            <td>
                ĐẠI DIỆN EGO SOLAR<br>
                <span>Ký, ghi rõ họ tên</span>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        EGO SOLAR chân thành cảm ơn Quý khách hàng đã tin tưởng và lựa chọn giải pháp của chúng tôi.
    </div>
</div>

@if(!empty($printMode))
<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 500);
    });
</script>
@endif

@if(empty($embed))
</body>
</html>
@endif
