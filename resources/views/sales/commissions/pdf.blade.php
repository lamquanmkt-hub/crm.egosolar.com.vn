<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        body{ font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2{ margin:0 0 8px 0; }
        p{ margin:2px 0; }
        table{ width:100%; border-collapse: collapse; }
        th,td{ border:1px solid #ddd; padding:6px; vertical-align: top; }
        th{ background:#f3f4f6; }
        .text-right{ text-align:right; }
        .muted{ color:#6b7280; font-size: 11px; }
        .nowrap{ white-space: nowrap; }
    </style>
</head>
<body>

@php
    $fmtMoney = fn($v) => number_format((float)$v, 0, ',', '.') . ' đ';

    $fromText = !empty($from) ? \Carbon\Carbon::parse($from)->format('d/m/Y') : '—';
    $toText   = !empty($to)   ? \Carbon\Carbon::parse($to)->format('d/m/Y')   : '—';

    $periodText = match($period ?? 'month'){
        'year' => 'Năm',
        'quarter' => 'Quý',
        default => 'Tháng',
    };
@endphp

<h2>Báo cáo hoa hồng ({{ $periodText }})</h2>
<p>Kỳ: <strong>{{ $fromText }}</strong> — <strong>{{ $toText }}</strong></p>
<p>
    Tổng đơn: <strong>{{ (int)($totalOrders ?? 0) }}</strong> |
    Tổng hoa hồng: <strong>{{ $fmtMoney($totalCommission ?? 0) }}</strong>
</p>

<br>

<table>
    <thead>
    <tr>
        <th style="width:34px;">#</th>
        <th style="width:120px;">Mã đơn</th>
        <th style="width:150px;">Công ty bán</th>
        <th style="width:220px;">Khách hàng</th>
        <th style="width:95px;">Sales</th>
        <th style="width:120px;">Mã hàng</th>
        <th style="width:220px;">Tên sản phẩm</th>
        <th class="text-right" style="width:105px;">Tổng tiền</th>
        <th style="width:55px;">%</th>
        <th class="text-right" style="width:105px;">Hoa hồng</th>
        <th style="width:80px;">Ngày</th>
        <th style="width:70px;">Nguồn</th>
    </tr>
    </thead>

    <tbody>
    @foreach($rows as $i => $r)
        @php
            // ✅ an toàn cho cả Eloquent & stdClass
            $orderCode = data_get($r, 'order.order_code') ?? data_get($r, 'order_code') ?? '-';
            $companyName = data_get($r, 'order.company.name') ?? data_get($r, 'company_name') ?? '-';
            $customerName = data_get($r, 'customer.name') ?? data_get($r, 'customer_name') ?? '-';
            $customerPhone = data_get($r, 'customer.phone') ?? data_get($r, 'customer_phone') ?? null;
            $salesName = data_get($r, 'salesUser.name') ?? data_get($r, 'sales_name') ?? '-';

            // ✅ Mã hàng / Tên sản phẩm:
            // - Nếu stdClass export đã join -> dùng product_codes/product_names
            // - Nếu Eloquent -> build từ order.items.product
            $productCodes = data_get($r, 'product_codes');
            $productNames = data_get($r, 'product_names');

            if (empty($productCodes) || empty($productNames)) {
                $items = collect(data_get($r, 'order.items', []));
                if ($items->count() > 0) {
                    $productCodes = $items->pluck('product.product_code')->filter()->unique()->values()->implode(', ');
                    $productNames = $items->pluck('product.name')->filter()->unique()->values()->implode(', ');
                }
            }

            $productCodes = $productCodes ?: '-';
            $productNames = $productNames ?: '-';

            $base = (float)(data_get($r, 'base_amount') ?? 0);
            $rate = (float)(data_get($r, 'rate') ?? 0);
            $comm = (float)(data_get($r, 'commission_amount') ?? 0);

            $dateRaw = data_get($r, 'order.completed_date')
                ?? data_get($r, 'completed_date')
                ?? data_get($r, 'created_at');

            $dateText = $dateRaw ? \Carbon\Carbon::parse($dateRaw)->format('d/m/Y') : '-';

            $source = data_get($r, 'status') ?? data_get($r, 'source') ?? 'auto';
        @endphp

        <tr>
            <td class="nowrap">{{ $i+1 }}</td>
            <td class="nowrap">{{ $orderCode }}</td>
            <td>{{ $companyName }}</td>
            <td>
                <div><strong>{{ $customerName }}</strong></div>
                @if(!empty($customerPhone))
                    <div class="muted">{{ $customerPhone }}</div>
                @endif
            </td>
            <td class="nowrap">{{ $salesName }}</td>
            <td>{{ $productCodes }}</td>
            <td>{{ $productNames }}</td>
            <td class="text-right nowrap">{{ $fmtMoney($base) }}</td>
            <td class="nowrap">{{ rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') }}%</td>
            <td class="text-right nowrap">{{ $fmtMoney($comm) }}</td>
            <td class="nowrap">{{ $dateText }}</td>
            <td class="nowrap">{{ $source }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>