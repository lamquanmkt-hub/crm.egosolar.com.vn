@php
    $mobile = (bool) ($mobile ?? false);
    $hoverItems = $order->relationLoaded('items')
        ? $order->items
        : collect();

    $hoverRows = [];
    $subtotalBeforeVat = 0;
    $calculatedVat = 0;
    $totalQuantity = 0;

    foreach ($hoverItems as $item) {
        $product = $item->relationLoaded('product')
            ? $item->product
            : null;

        $productName = trim((string) ($item->product_name ?? ''));

        if ($productName === '' && $product) {
            $productName = trim((string) ($product->name ?? ''));
        }

        if ($productName === '') {
            $productName = 'Sản phẩm #' . (int) ($item->product_id ?? 0);
        }

        $sku = $product
            ? trim((string) ($product->sku ?? ''))
            : '';

        $quantity = max(0, (int) ($item->quantity ?? 0));
        $unitPrice = max(0, (float) ($item->unit_price ?? 0));
        $discountPercent = max(0, (float) ($item->discount_percent ?? 0));
        $discountAmount = max(0, (float) ($item->discount_amount ?? 0));
        $vatPercent = max(0, (float) ($item->vat_percent ?? 0));

        /*
         * line_total hiện tại của hệ thống là tiền dòng hàng
         * sau giảm giá và trước VAT.
         */
        $beforeVat = is_numeric($item->line_total ?? null)
            ? max(0, (float) $item->line_total)
            : max(
                0,
                round(
                    ($quantity * $unitPrice * (1 - $discountPercent / 100))
                    - $discountAmount,
                    2
                )
            );

        $vatAmount = round($beforeVat * $vatPercent / 100, 2);
        $afterVat = $beforeVat + $vatAmount;

        $subtotalBeforeVat += $beforeVat;
        $calculatedVat += $vatAmount;
        $totalQuantity += $quantity;

        $hoverRows[] = [
            'name' => $productName,
            'sku' => $sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'vat_percent' => $vatPercent,
            'before_vat' => $beforeVat,
            'vat_amount' => $vatAmount,
            'after_vat' => $afterVat,
        ];
    }

    $orderTax = max(0, (float) ($order->tax_amount ?? 0));

    $displayTax = $orderTax > 0
        ? $orderTax
        : $calculatedVat;

    $shippingFee = max(0, (float) ($order->shipping_fee ?? 0));
    $orderDiscount = max(0, (float) ($order->discount_amount ?? 0));
    $storedOrderTotal = max(0, (float) ($order->total_amount ?? 0));

    /*
     * Tổng thanh toán sau VAT:
     * Tiền hàng trước VAT
     * + Thuế VAT
     * + Phí vận chuyển
     * - Giảm giá toàn đơn
     */
    $totalAfterVat = max(
        0,
        $subtotalBeforeVat
        + $displayTax
        + $shippingFee
        - $orderDiscount
    );

    /*
     * Chỉ dùng tổng lưu trong đơn khi không tính được từ sản phẩm.
     */
    if ($totalAfterVat <= 0 && $storedOrderTotal > 0) {
        $totalAfterVat = $storedOrderTotal;
    }
@endphp

<span class="ego-order-hover-wrap">
    <a
        href="{{ route('orders.show', $order->id) }}"
        class="{{ $mobile ? 'm-code' : 'order-code' }} ego-order-hover-trigger"
        aria-expanded="false"
        title="Rê chuột để xem sản phẩm trong đơn"
    >{{ $mobile ? '#' : '' }}{{ $order->order_code }}</a>

    <template class="ego-order-hover-template">
        <div class="ego-oh-card">
            <div class="ego-oh-header">
                <div>
                    <div class="ego-oh-code">
                        {{ $order->order_code }}
                    </div>

                    <div class="ego-oh-count">
                        {{ count($hoverRows) }} mặt hàng ·
                        {{ $totalQuantity }} sản phẩm
                    </div>
                </div>

                <div class="ego-oh-header-total">
                    <span>Tổng sau VAT</span>
                    <strong>
                        {{ number_format($totalAfterVat, 0, ',', '.') }} đ
                    </strong>
                </div>
            </div>

            <div class="ego-oh-products">
                @forelse($hoverRows as $row)
                    <div class="ego-oh-product-row">
                        <div class="ego-oh-product-top">
                            <div class="ego-oh-product-name-wrap">
                                <div class="ego-oh-product-name">
                                    {{ $row['name'] }}
                                </div>

                                @if($row['sku'] !== '')
                                    <div class="ego-oh-sku">
                                        SKU: {{ $row['sku'] }}
                                    </div>
                                @endif
                            </div>

                            <div class="ego-oh-after-vat">
                                <span>Sau VAT</span>
                                <strong>
                                    {{ number_format($row['after_vat'], 0, ',', '.') }} đ
                                </strong>
                            </div>
                        </div>

                        <div class="ego-oh-info">
                            <span>
                                SL:
                                <b>{{ $row['quantity'] }}</b>
                            </span>

                            <span>
                                Đơn giá:
                                <b>
                                    {{ number_format($row['unit_price'], 0, ',', '.') }} đ
                                </b>
                            </span>

                            @if($row['discount_percent'] > 0)
                                <span>
                                    CK:
                                    <b>
                                        {{ rtrim(rtrim(number_format($row['discount_percent'], 2, ',', '.'), '0'), ',') }}%
                                    </b>
                                </span>
                            @endif

                            @if($row['discount_amount'] > 0)
                                <span>
                                    Giảm tiền:
                                    <b>
                                        {{ number_format($row['discount_amount'], 0, ',', '.') }} đ
                                    </b>
                                </span>
                            @endif
                        </div>

                        <div class="ego-oh-tax-info">
                            <span>
                                Trước VAT:
                                <b>
                                    {{ number_format($row['before_vat'], 0, ',', '.') }} đ
                                </b>
                            </span>

                            <span>
                                VAT
                                {{ rtrim(rtrim(number_format($row['vat_percent'], 2, ',', '.'), '0'), ',') }}%:
                                <b>
                                    {{ number_format($row['vat_amount'], 0, ',', '.') }} đ
                                </b>
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="ego-oh-empty">
                        Đơn hàng chưa có sản phẩm.
                    </div>
                @endforelse
            </div>

            <div class="ego-oh-summary">
                <div>
                    <span>Tiền hàng trước VAT</span>
                    <b>
                        {{ number_format($subtotalBeforeVat, 0, ',', '.') }} đ
                    </b>
                </div>

                <div>
                    <span>Thuế VAT</span>
                    <b>
                        {{ number_format($displayTax, 0, ',', '.') }} đ
                    </b>
                </div>

                @if($orderDiscount > 0)
                    <div>
                        <span>Giảm giá đơn</span>
                        <b>
                            -{{ number_format($orderDiscount, 0, ',', '.') }} đ
                        </b>
                    </div>
                @endif

                @if($shippingFee > 0)
                    <div>
                        <span>Phí vận chuyển</span>
                        <b>
                            {{ number_format($shippingFee, 0, ',', '.') }} đ
                        </b>
                    </div>
                @endif

                <div class="ego-oh-grand-total">
                    <span>Tổng thanh toán sau VAT</span>
                    <b>
                        {{ number_format($totalAfterVat, 0, ',', '.') }} đ
                    </b>
                </div>
            </div>
        </div>
    </template>
</span>
