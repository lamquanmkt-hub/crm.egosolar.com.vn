@php
    $sectionOrder = [
        'main' => ['code' => 'I', 'title' => 'Vật tư / thiết bị chính'],
        'sub' => ['code' => 'II', 'title' => 'Vật tư / thiết bị phụ'],
        'ac_cabinet' => ['code' => 'III', 'title' => 'Tủ AC'],
        'grounding' => ['code' => 'IV', 'title' => 'Hệ thống tiếp địa'],
        'other' => ['code' => 'V', 'title' => 'Các hạng mục khác'],
        'om' => ['code' => 'VI', 'title' => 'Bảo hành & O&M'],
    ];

    $grouped = $quotation->items->groupBy(fn($item) => $item->section_key ?: 'main');
@endphp

<meta charset="utf-8">

<table style="font-family:'Times New Roman', Times, serif; border-collapse:collapse;">
    

    @foreach($sectionOrder as $key => $section)
        @php
            $rows = $grouped->get($key, collect());
        @endphp

        @if($rows->count())
            <tr>
                <td colspan="9" style="background:#dbeafe; color:#1e3a8a; font-weight:bold;">
                    {{ $section['code'] }}. {{ $section['title'] }}
                </td>
            </tr>

            @foreach($rows as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:bold;">{{ $item->product_name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->unit_price }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $item->line_total }}</td>
                    <td>{{ $item->specs_text }}</td>
                </tr>
            @endforeach
        @endif
    @endforeach

    <tr>
        <td colspan="5" style="font-weight:bold; text-align:right;">Tạm tính</td>
        <td style="font-weight:bold;">{{ $quotation->subtotal }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="6" style="font-weight:bold; text-align:right;">Chiết khấu</td>
        <td style="font-weight:bold;">{{ $quotation->discount_amount }}</td>
        <td></td>
    </tr>
    
    <tr>
        <td colspan="6" style="font-weight:bold; text-align:right; background:#e0f2fe; color:#075985;">TỔNG CHI PHÍ</td>
        <td style="font-weight:bold; background:#e0f2fe; color:#075985;">{{ $quotation->grand_total }}</td>
        <td></td>
    </tr>
</table>

<br>

<table border="1" style="font-family:'Times New Roman', Times, serif; border-collapse:collapse;">
    <tr style="background:#dbeafe; font-weight:bold; color:#1e3a8a;">
        <td>Điều kiện thanh toán</td>
        <td>Điều kiện thương mại</td>
    </tr>
    <tr>
        <td>{{ $quotation->payment_terms }}</td>
        <td>{{ $quotation->commercial_terms }}</td>
    </tr>
    <tr style="background:#dbeafe; font-weight:bold; color:#1e3a8a;">
        <td>Bảo hành</td>
        <td>Vận hành & bảo trì O&M</td>
    </tr>
    <tr>
        <td>{{ $quotation->warranty_terms }}</td>
        <td>{{ $quotation->om_terms }}</td>
    </tr>
</table>
