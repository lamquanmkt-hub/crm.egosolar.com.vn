<div class="emw8-table-wrap">
    <table class="emw8-table">
        <thead><tr><th>STT</th><th>Sản phẩm / SKU</th><th>SL</th><th>ĐVT</th><th>Thông số / lưu ý</th></tr></thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $item->product?->name ?: $item->item_name }}</strong><small>{{ $item->product?->sku ? 'SKU '.$item->product->sku : 'Chưa có SKU' }}</small></td>
                    <td><strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</strong></td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->note ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
