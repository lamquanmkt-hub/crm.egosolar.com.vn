<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>ĐƠN MUA HÀNG</title>

    <style>
        @page { margin: 16mm 14mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            color: #000;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            margin: 12px 0 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid #000;
        }

        th, td {
            padding: 6px;
            vertical-align: middle;
        }

        .no-border,
        .no-border td {
            border: none !important;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: 700; }

        .wrap {
            word-wrap: break-word;
            white-space: normal;
        }

        .mono {
            font-family: "DejaVu Sans", sans-serif;
        }

        .small {
            font-size: 11px;
        }
    </style>
</head>
<body>

@php

    $egoNoAccentCompany = function ($value) {
        $value = (string) $value;
        $map = [
            'VIỆT NAM' => 'VIET NAM',
            'Việt Nam' => 'Viet Nam',
            'việt nam' => 'viet nam',
            'VIỆT' => 'VIET',
            'Việt' => 'Viet',
            'việt' => 'viet',
        ];
        return str_replace(array_keys($map), array_values($map), $value);
    };

$customer = $order->lead->customer ?? null;

    $companyName    = $company->name ?? '................................';
    $companyTaxCode = $company->tax_code ?? '';
    $companyAddress = $company->address ?? '';
    $companyEmail   = $company->email ?? '';

    $bankAccountsRaw = $company->bank_accounts ?? [];

    if (is_string($bankAccountsRaw)) {
        $bankAccountsDecoded = json_decode($bankAccountsRaw, true);
        $bankAccountsRaw = is_array($bankAccountsDecoded) ? $bankAccountsDecoded : [];
    }

    if (!is_array($bankAccountsRaw)) {
        $bankAccountsRaw = [];
    }

    $bankAccounts = collect($bankAccountsRaw)
        ->map(function ($item) use ($companyName) {
            return [
                'bank_account' => trim((string)($item['bank_account'] ?? '')),
                'bank_name' => trim((string)($item['bank_name'] ?? '')),
                'bank_holder' => trim((string)($item['bank_holder'] ?? $companyName)),
                'is_default' => !empty($item['is_default']) ? 1 : 0,
            ];
        })
        ->filter(function ($item) {
            return $item['bank_account'] !== '' || $item['bank_name'] !== '' || $item['bank_holder'] !== '';
        })
        ->sortByDesc('is_default')
        ->values()->take(2);

    if ($bankAccounts->isEmpty() && (($company->bank_account ?? '') || ($company->bank_name ?? '') || ($company->bank_holder ?? ''))) {
        $bankAccounts = collect([[
            'bank_account' => $company->bank_account ?? '',
            'bank_name' => $company->bank_name ?? '',
            'bank_holder' => $company->bank_holder ?? $companyName,
            'is_default' => 1,
        ]])->take(2);
    }

    $customerName  = $customer->name ?? '................................';
    // Địa chỉ khách hàng/công ty: luôn lấy từ hồ sơ khách hàng, không lấy từ vận chuyển
    $customerAddr  = $customer->address ?? '................................';

    // Địa điểm giao hàng: chỉ lấy từ thông tin vận chuyển của đơn hàng
    $deliveryAddress = trim((string)($order->shipping_address ?? ''));

    $customerPhone = $customer->phone ?? '................................';
    $customerEmail = $customer->email ?? '';
    $customerTax   = $customer->tax_code ?? '';

    $deliveryDateText = '';
    if (!empty($order->estimated_delivery)) {
        try {
            $deliveryDateText = \Carbon\Carbon::parse($order->estimated_delivery)->format('d/m/Y');
        } catch (\Throwable $e) {
            $deliveryDateText = (string) $order->estimated_delivery;
        }
    }

    $sumBeforeVat = 0;
    $sumVatAmount = 0;
    $sumAfterVat  = 0;
    $vatGroups = [];

    $hasDiscountAmountColumn = collect($order->items ?? [])->contains(function ($item) {
        return (float)($item->discount_amount ?? 0) > 0;
    });

    $summaryColspan = 4;

    if (!function_exists('pdf_read_number_vn')) {
        function pdf_read_number_vn($number)
        {
            $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

            $readThree = function($num, $full = false) use ($digits) {
                $num = (int)$num;
                $hundreds = intdiv($num, 100);
                $tens = intdiv($num % 100, 10);
                $ones = $num % 10;
                $result = '';

                if ($full || $hundreds > 0) {
                    $result .= $digits[$hundreds] . ' trăm';
                    if ($tens == 0 && $ones > 0) {
                        $result .= ' lẻ';
                    }
                }

                if ($tens > 1) {
                    $result .= ($result ? ' ' : '') . $digits[$tens] . ' mươi';
                    if ($ones == 1) {
                        $result .= ' mốt';
                    } elseif ($ones == 5) {
                        $result .= ' lăm';
                    } elseif ($ones > 0) {
                        $result .= ' ' . $digits[$ones];
                    }
                } elseif ($tens == 1) {
                    $result .= ($result ? ' ' : '') . 'mười';
                    if ($ones == 5) {
                        $result .= ' lăm';
                    } elseif ($ones > 0) {
                        $result .= ' ' . $digits[$ones];
                    }
                } elseif ($ones > 0 && $tens == 0) {
                    $result .= ($result ? ' ' : '') . $digits[$ones];
                }

                return trim($result);
            };

            $number = (int) round($number);
            if ($number === 0) {
                return 'Không đồng';
            }

            $units = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];
            $parts = [];
            $i = 0;

            while ($number > 0) {
                $parts[] = $number % 1000;
                $number = intdiv($number, 1000);
                $i++;
            }

            $result = '';
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $part = $parts[$i];
                if ($part == 0) {
                    continue;
                }

                $full = ($i < count($parts) - 1);
                $result .= $readThree($part, $full) . $units[$i] . ' ';
            }

            $result = trim(preg_replace('/\s+/', ' ', $result));
            $result = ucfirst($result) . ' đồng';

            return $result;
        }
    }

    $amountInWords = pdf_read_number_vn($sumAfterVat);
@endphp

<div class="bold">{{ $egoNoAccentCompany( $companyName ) }}</div>

@if(!empty($companyTaxCode))
    <div>MST: {{ $companyTaxCode }}</div>
@endif

@if(!empty($companyAddress))
    <div>{{ $companyAddress }}</div>
@endif

@if(!empty($companyEmail))
    <div>Email: {{ $companyEmail }}</div>
@endif

<div class="title">ĐƠN MUA HÀNG</div>

<table class="no-border">
    <tr>
        <td style="width:65%; border:none;">
            <div><b>Tên khách hàng:</b> {{ $customerName }}</div>
            <div><b>Địa chỉ:</b> {{ $customerAddr }}</div>
            <div><b>Điện thoại:</b> {{ $customerPhone }}</div>

            @if(!empty($customerEmail))
                <div><b>Email:</b> {{ $customerEmail }}</div>
            @endif

            @if(!empty($customerTax))
                <div><b>MST:</b> {{ $customerTax }}</div>
            @endif

            <div><b>Diễn giải:</b> Mua hàng</div>
        </td>

        <td style="width:35%; border:none; text-align:right;">
            <div><b>Ngày:</b> {{ optional($order->created_at)->format('d/m/Y') }}</div>
            <div><b>Số:</b> {{ $order->order_code ?? '' }}</div>
            <div><b>Loại tiền:</b> VND</div>
        </td>
    </tr>
</table>

<table style="margin-top: 14px;">
    <thead>
        <tr class="text-center bold">
            <th style="width:38px;">STT</th>
            <th>Tên hàng hóa, dịch vụ</th>
            <th style="width:70px;">Đơn vị<br>tính</th>
            <th style="width:55px;">Số<br>lượng</th>
            <th style="width:95px;">Đơn giá<br>(VND)</th>
            <th style="width:105px;">Thành tiền<br>(VND)</th>
        </tr>
    </thead>

    <tbody>
        @foreach($order->items as $item)
            @php
                $qty = (int)($item->quantity ?? 1);
                if ($qty < 1) $qty = 1;

                $p = $item->product ?? null;

                $vat = (float)($item->vat_percent ?? $p->vat_percent ?? 0);
                $vat = max(0, min(100, $vat));

                $unitAfter = (float)($item->unit_price ?? 0);
                $unitBefore = $vat > 0 ? ($unitAfter / (1 + $vat / 100)) : $unitAfter;

                $subAfter = $unitAfter * $qty;

                $discPercent = max(0, min(100, (float)($item->discount_percent ?? 0)));
                $discPerUnit = max(0, (float)($item->discount_amount ?? 0));

                $discountAfter = 0;
                if ($discPerUnit > 0) {
                    $discountAfter = $discPerUnit * $qty;
                } elseif ($discPercent > 0) {
                    $discountAfter = $subAfter * $discPercent / 100;
                }

                if ($discountAfter > $subAfter) $discountAfter = $subAfter;

                $lineAfter = max(0, $subAfter - $discountAfter);
                $lineBefore = $vat > 0 ? ($lineAfter / (1 + $vat / 100)) : $lineAfter;
                $vatAmountLine = max(0, $lineAfter - $lineBefore);

                $displayUnitBefore = $qty > 0 ? ($lineBefore / $qty) : $unitBefore;

                $sumBeforeVat += $lineBefore;
                $sumVatAmount += $vatAmountLine;
                $sumAfterVat  += $lineAfter;

                $vatKey = number_format($vat, 2, '.', '');
                if (!isset($vatGroups[$vatKey])) {
                    $vatGroups[$vatKey] = [
                        'percent' => $vat,
                        'before' => 0,
                        'amount' => 0,
                        'after' => 0,
                    ];
                }

                $vatGroups[$vatKey]['before'] += $lineBefore;
                $vatGroups[$vatKey]['amount'] += $vatAmountLine;
                $vatGroups[$vatKey]['after'] += $lineAfter;

                $productName = $p->name ?? 'N/A';

                $unitName = $p->unit ?? $p->unit_name ?? $p->dvt ?? $p->don_vi_tinh ?? 'Bộ';

                $vatText = rtrim(rtrim(number_format($vat, 2), '0'), '.');
            @endphp

            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>

                <td class="wrap">
                    <div>{{ $productName }}</div>
                </td>

                <td class="text-center">{{ $unitName }}</td>

                <td class="text-center">{{ $qty }}</td>

                <td class="text-right mono">{{ number_format($displayUnitBefore, 0, ',', '.') }}</td>

                <td class="text-right mono">{{ number_format($lineBefore, 0, ',', '.') }}</td>
            </tr>
        @endforeach

        @php
            $amountInWords = pdf_read_number_vn($sumAfterVat);

            ksort($vatGroups, SORT_NUMERIC);

            $formatVatText = function ($percent) {
                return rtrim(rtrim(number_format((float) $percent, 2), '0'), '.');
            };
        @endphp

        <tr>
            <td colspan="5" class="text-center bold">Tổng cộng</td>
            <td class="text-right mono">{{ number_format($sumBeforeVat, 0, ',', '.') }}</td>
        </tr>

        @foreach($vatGroups as $vatGroup)
            @continue((float) $vatGroup['percent'] <= 0)
            <tr>
                <td colspan="5" class="text-center bold">
                    Thuế VAT {{ $formatVatText($vatGroup['percent']) }}%
                </td>
                <td class="text-right mono">{{ number_format($vatGroup['amount'], 0, ',', '.') }}</td>
            </tr>
        @endforeach

        <tr>
            <td colspan="5" class="text-center bold">Tổng cộng sau thuế</td>
            <td class="text-right bold mono">{{ number_format($sumAfterVat, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td style="width:170px;">Số tiền viết bằng chữ:</td>
        <td>{{ $amountInWords }}</td>
    </tr>
</table>

<div style="margin-top: 12px;">
    <div>Ngày giao hàng: {{ $deliveryDateText !== '' ? $deliveryDateText : '________________________________________________' }}</div>
    <div style="margin-top: 10px;">
        Địa điểm giao hàng:
        @if(!empty($deliveryAddress))
            {{ $deliveryAddress }}
        @else
            ___________________________________________
        @endif
    </div>
    <div style="margin-top: 10px;">Điều khoản thanh toán: _________________________________________</div>
</div>

<div style="margin-top: 16px;">
    <div class="bold">Thông tin thanh toán:</div>

    @if($bankAccounts->count())
        @foreach($bankAccounts as $bankIndex => $bank)
            <div style="margin-top: {{ $bankIndex === 0 ? '4px' : '8px' }};">
                @if($bankAccounts->count() > 1)
                    <div class="bold">
                        Tài khoản {{ $bankIndex + 1 }}{{ !empty($bank['is_default']) ? ' - mặc định' : '' }}:
                    </div>
                @endif

                @if(!empty($bank['bank_account']))
                    <div>Số tài khoản: {{ $bank['bank_account'] }}</div>
                @endif

                @if(!empty($bank['bank_name']))
                    <div class="wrap">Ngân hàng: {{ $bank['bank_name'] }}</div>
                @endif

                @if(!empty($bank['bank_holder']))
                    <div>Tên tài khoản: {{ $bank['bank_holder'] }}</div>
                @endif
            </div>
        @endforeach
    @endif
</div>

<table class="no-border" style="margin-top: 18px;">
    <tr>
        <td class="text-center" style="border:none;">
            <div class="bold">Người lập</div>
            <div><i>(Ký, họ tên)</i></div>
        </td>
        <td class="text-center" style="border:none;">
            <div class="bold">Kế toán trưởng</div>
            <div><i>(Ký, họ tên)</i></div>
        </td>
        <td class="text-center" style="border:none;">
            <div class="bold">Giám đốc KD</div>
            <div><i>(Ký, họ tên, đóng dấu)</i></div>
        </td>
    </tr>
</table>

</body>
</html>