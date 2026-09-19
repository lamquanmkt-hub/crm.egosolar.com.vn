{{-- EGO_ORDER_STOCK_SHORTAGE_WARNING --}}

@if(($shortageSummary['has_shortage'] ?? false))

<style>
.ego-stock-warning{
    background:linear-gradient(135deg,#fff8e6 0%,#fff 100%);
    border:1px solid #f4c95d;
    border-left:5px solid #f59e0b;
    border-radius:14px;
    padding:16px 18px;
    margin-bottom:16px;
    box-shadow:0 3px 12px rgba(15,23,42,.05);
}

.ego-stock-warning-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
}

.ego-stock-warning-title{
    font-size:16px;
    font-weight:800;
    color:#b45309;
    display:flex;
    align-items:center;
    gap:8px;
}

.ego-stock-warning-desc{
    font-size:13px;
    color:#64748b;
    margin-top:5px;
    line-height:1.6;
}

.ego-stock-warning-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#fee2e2;
    color:#b91c1c;
    font-size:12px;
    font-weight:800;
    padding:7px 11px;
    border-radius:999px;
    white-space:nowrap;
}

.ego-stock-warning-stats{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:14px;
}

.ego-stock-stat{
    background:#fff;
    border:1px solid #fde7ad;
    border-radius:10px;
    padding:8px 12px;
    min-width:130px;
}

.ego-stock-stat small{
    display:block;
    color:#94a3b8;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
}

.ego-stock-stat strong{
    display:block;
    font-size:15px;
    margin-top:2px;
    color:#0f172a;
}

.ego-stock-table-wrap{
    margin-top:14px;
    overflow-x:auto;
    border-radius:10px;
    border:1px solid #f4e5bd;
    background:#fff;
}

.ego-stock-table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
    min-width:720px;
}

.ego-stock-table th{
    padding:9px 10px;
    background:#fffaf0;
    color:#64748b;
    font-size:10px;
    text-transform:uppercase;
    font-weight:800;
    border-bottom:1px solid #f4e5bd;
}

.ego-stock-table td{
    padding:10px;
    border-bottom:1px solid #f1f5f9;
    vertical-align:middle;
}

.ego-stock-table tbody tr:last-child td{
    border-bottom:0;
}

.ego-stock-product{
    font-weight:700;
    color:#0f172a;
}

.ego-stock-sku{
    color:#94a3b8;
    font-size:11px;
    margin-top:2px;
}

.ego-stock-number{
    text-align:center;
    font-weight:700;
}

.ego-stock-missing{
    color:#dc2626;
    font-weight:800;
}

.ego-stock-status{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:11px;
    font-weight:800;
}

.ego-stock-status.out{
    background:#fee2e2;
    color:#b91c1c;
}

.ego-stock-status.low{
    background:#fef3c7;
    color:#b45309;
}

.ego-stock-status.none{
    background:#e2e8f0;
    color:#475569;
}
</style>

<div class="ego-stock-warning">

    <div class="ego-stock-warning-head">

        <div>
            <div class="ego-stock-warning-title">
                <span>⚠</span>
                <span>Đơn hàng đang thiếu hàng</span>
            </div>

            <div class="ego-stock-warning-desc">
                Đơn vẫn được phép duyệt theo quy trình,
                nhưng <strong>Kho chưa thể xuất hàng</strong>
                cho đến khi đã nhập kho hoặc điều chuyển đủ số lượng.
            </div>
        </div>

        <div class="ego-stock-warning-badge">
            ⚠ CẦN BỔ SUNG HÀNG
        </div>

    </div>

    <div class="ego-stock-warning-stats">

        <div class="ego-stock-stat">
            <small>Dòng đang thiếu</small>
            <strong>
                {{ number_format(
                    $shortageSummary['shortage_lines'] ?? 0,
                    0,
                    ',',
                    '.'
                ) }}
            </strong>
        </div>

        <div class="ego-stock-stat">
            <small>Tổng SL thiếu</small>
            <strong class="ego-stock-missing">
                {{ number_format(
                    $shortageSummary['missing_qty'] ?? 0,
                    0,
                    ',',
                    '.'
                ) }}
            </strong>
        </div>

        <div class="ego-stock-stat">
            <small>Hết hàng hoàn toàn</small>
            <strong>
                {{ number_format(
                    $shortageSummary['out_of_stock_lines'] ?? 0,
                    0,
                    ',',
                    '.'
                ) }}
            </strong>
        </div>

    </div>

    @if(isset($shortageItems) && $shortageItems->count())

        <div class="ego-stock-table-wrap">

            <table class="ego-stock-table">

                <thead>
                    <tr>
                        <th style="text-align:left">
                            Sản phẩm
                        </th>

                        <th style="text-align:left">
                            Kho xuất
                        </th>

                        <th style="text-align:center">
                            Cần xuất
                        </th>

                        <th style="text-align:center">
                            Tồn hiện tại
                        </th>

                        <th style="text-align:center">
                            Thiếu
                        </th>

                        <th style="text-align:center">
                            Trạng thái
                        </th>
                    </tr>
                </thead>

                <tbody>

                @foreach($shortageItems as $line)

                    <tr>

                        <td>
                            <div class="ego-stock-product">
                                {{ $line->product_name }}
                            </div>

                            @if(!empty($line->sku))
                                <div class="ego-stock-sku">
                                    {{ $line->sku }}
                                </div>
                            @endif
                        </td>

                        <td>
                            {{ $line->warehouse_name ?: 'Chưa chọn kho' }}
                        </td>

                        <td class="ego-stock-number">
                            {{ number_format(
                                $line->required_qty,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td class="ego-stock-number">
                            {{ number_format(
                                $line->available_qty,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td class="ego-stock-number ego-stock-missing">
                            {{ number_format(
                                $line->missing_qty,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="text-align:center">

                            @if($line->status === 'out_of_stock')

                                <span class="ego-stock-status out">
                                    Hết hàng
                                </span>

                            @elseif($line->status === 'insufficient')

                                <span class="ego-stock-status low">
                                    Thiếu hàng
                                </span>

                            @else

                                <span class="ego-stock-status none">
                                    Chưa chọn kho
                                </span>

                            @endif

                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    @endif

</div>

@endif