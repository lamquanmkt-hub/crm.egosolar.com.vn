<div class="emw8-table-wrap">
    <table class="emw8-table">
        <thead><tr><th>STT</th><th>Vật tư đề xuất</th><th>Sản phẩm/SKU Kho đối chiếu</th><th>SL</th><th>ĐVT</th><th>Thông số / lưu ý</th></tr></thead>
        <tbody>
            @foreach($items as $index => $item)
                @php
                    $allocation = collect($item->allocations ?? [])->first();
                    $matchedProduct = $allocation?->product ?: $item->product;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $item->item_name }}</strong><small>Kỹ thuật đề xuất</small></td>
                    <td>
                        <strong>{{ $matchedProduct?->name ?: 'Kho chưa đối chiếu' }}</strong>
                        <small>{{ $matchedProduct?->sku ? 'SKU '.$matchedProduct->sku : 'Chưa có SKU' }}</small>
                    </td>
                    <td><strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</strong></td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->note ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
