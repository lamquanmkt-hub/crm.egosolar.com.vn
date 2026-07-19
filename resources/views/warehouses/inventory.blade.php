<table class="table">
    <thead>
    <tr>
        <th>Sản phẩm</th>
        <th>Tồn kho</th>
        <th>Cập nhật lần cuối</th>
    </tr>
    </thead>

    <tbody>
    @foreach($inventory as $stock)
        <tr>
            <td>{{ $stock->product->name }}</td>
            <td>{{ $stock->qty }}</td>
            <td>{{ $stock->last_updated }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
