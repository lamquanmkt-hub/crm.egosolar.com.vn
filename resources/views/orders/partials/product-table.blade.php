@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit' && isset($order) && !empty($order->id);
    $items = old('items');

    if (($items === null || $items === []) && $isEdit && isset($order) && $order->items) {
        $items = $order->items->map(function ($item) {
            return [
                'id' => $item->id,
                'warehouse_id' => $item->warehouse_id ?? '',
                'product_id' => $item->product_id ?? '',
                'price_tier_id' => $item->price_tier_id ?? '',
                'unit_price' => (float) ($item->unit_price ?? 0),
                'vat_percent' => (float) ($item->vat_percent ?? $item->product?->vat_percent ?? 0),
                'discount_percent' => (float) ($item->discount_percent ?? 0),
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'quantity' => max(1, (int) ($item->quantity ?? 1)),
                'line_total' => (float) ($item->line_total ?? 0),
                'product_name' => $item->product_name ?? $item->product?->name ?? '',
                'product_sku' => $item->product?->sku ?? '',
                'warehouse_name' => $item->warehouse?->name ?? '',
            ];
        })->toArray();
    }

    if (!is_array($items) || count($items) === 0) {
        $items = [[]];
    }
@endphp

<section
    class="oc-section oc-products-section"
    id="orderProductBuilder"
    data-mode="{{ $mode }}"
    data-products-url="{{ route('orders.product-catalog') }}"
    data-warehouses-url="{{ url('/orders/product-warehouses') }}"
>
    <header class="oc-section-head oc-products-head">
        <div>
            <h2>Sản phẩm</h2>
            <p>Chọn sản phẩm trước, hệ thống sẽ hiển thị kho còn hàng.</p>
        </div>

        <div class="oc-products-actions">
            <div class="oc-company-lock" id="orderCompanyLock" hidden>
                <span>Kho thuộc</span>
                <strong id="orderCompanyLockName">—</strong>
                <button type="button" id="resetOrderCompany">Đổi công ty</button>
            </div>

            <button type="button" id="addProductBtn" class="oc-btn oc-btn-primary oc-btn-sm">
                <i class="bi bi-plus-lg"></i>
                Thêm sản phẩm
            </button>
        </div>
    </header>

    <div class="oc-product-list" id="productTableBody">
        @foreach($items as $index => $itemData)
            @php
                $productId = (int) ($itemData['product_id'] ?? 0);
                $warehouseId = (int) ($itemData['warehouse_id'] ?? 0);
                $productName = trim((string) ($itemData['product_name'] ?? data_get($itemData, 'product.name', '')));
                $productSku = trim((string) ($itemData['product_sku'] ?? data_get($itemData, 'product.sku', '')));
                $warehouseName = trim((string) ($itemData['warehouse_name'] ?? ''));
                $quantity = max(1, (int) ($itemData['quantity'] ?? 1));
                $unitPrice = (float) ($itemData['unit_price'] ?? 0);
                $lineTotal = (float) ($itemData['line_total'] ?? 0);
            @endphp

            <article
                class="product-row oc-product-row"
                data-index="{{ $index }}"
                data-product-id="{{ $productId ?: '' }}"
                data-warehouse-id="{{ $warehouseId ?: '' }}"
            >
                @if(!empty($itemData['id']))
                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $itemData['id'] }}">
                @endif

                <input
                    type="hidden"
                    name="items[{{ $index }}][vat_percent]"
                    class="item-vat-percent"
                    value="{{ (float) ($itemData['vat_percent'] ?? 0) }}"
                >

                <div class="oc-product-row-top">
                    <div class="oc-row-index ego-product-row__number">
                        <span>{{ $index + 1 }}</span>
                        <strong class="visually-hidden">Sản phẩm {{ $index + 1 }}</strong>
                    </div>

                    <div class="oc-product-cell oc-product-picker">
                        <label>Sản phẩm <span>*</span></label>

                        <select
                            name="items[{{ $index }}][product_id]"
                            class="product-select @error("items.$index.product_id") is-invalid @enderror"
                            data-placeholder="Tìm theo tên hoặc SKU"
                            required
                        >
                            <option value="">Tìm theo tên hoặc SKU</option>

                            @if($productId)
                                <option value="{{ $productId }}" selected>
                                    {{ $productName !== '' ? $productName : ('Sản phẩm #'.$productId) }}
                                    {{ $productSku !== '' ? ' · '.$productSku : '' }}
                                </option>
                            @endif
                        </select>

                        <div class="oc-product-meta" data-product-summary hidden></div>
                        @error("items.$index.product_id")
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-product-cell oc-warehouse-picker">
                        <label>Kho xuất <span>*</span></label>

                        <select
                            name="items[{{ $index }}][warehouse_id]"
                            class="form-select warehouse-select @error("items.$index.warehouse_id") is-invalid @enderror"
                            required
                            {{ $productId ? '' : 'disabled' }}
                        >
                            <option value="">Chọn sản phẩm trước</option>

                            @if($warehouseId)
                                <option value="{{ $warehouseId }}" selected>
                                    {{ $warehouseName !== '' ? $warehouseName : ('Kho #'.$warehouseId) }}
                                </option>
                            @endif
                        </select>

                        <div class="oc-stock-line" data-stock-line>
                            <span class="oc-stock-status" data-stock-status>Chưa chọn sản phẩm</span>
                            <span data-stock-values hidden>
                                Tồn: <strong data-stock-qty>—</strong>
                                <b>·</b>
                                Đã giữ: <strong data-reserved-qty>—</strong>
                                <b>·</b>
                                Khả dụng: <strong data-available-value>—</strong>
                            </span>
                        </div>

                        @error("items.$index.warehouse_id")
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="button" class="oc-remove-btn remove-product-btn" title="Xóa sản phẩm">
                        <i class="bi bi-trash3"></i>
                        <span class="visually-hidden">Xóa sản phẩm</span>
                    </button>
                </div>

                <div class="oc-product-row-bottom">
                    <div class="oc-product-field">
                        <label>Loại giá</label>
                        <select
                            name="items[{{ $index }}][price_tier_id]"
                            class="form-select price-tier-select @error("items.$index.price_tier_id") is-invalid @enderror"
                        >
                            <option value="">Theo loại khách hàng</option>
                            @foreach(($priceTiers ?? []) as $tier)
                                <option
                                    value="{{ $tier->id }}"
                                    data-tier-code="{{ $tier->code }}"
                                    {{ (string) ($itemData['price_tier_id'] ?? '') === (string) $tier->id ? 'selected' : '' }}
                                >
                                    {{ $tier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="oc-product-field">
                        <label>Đơn giá sau VAT</label>
                        <input
                            type="text"
                            name="items[{{ $index }}][unit_price]"
                            class="form-control unit-price text-end @error("items.$index.unit_price") is-invalid @enderror"
                            value="{{ $unitPrice > 0 ? (int) $unitPrice : '' }}"
                            readonly
                        >
                        @error("items.$index.unit_price")
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-product-field oc-quantity-field">
                        <label>Số lượng <span>*</span></label>
                        <div class="oc-qty-control">
                            <button type="button" class="oc-qty-btn" data-qty-action="decrease" aria-label="Giảm số lượng">−</button>
                            <input
                                type="number"
                                name="items[{{ $index }}][quantity]"
                                class="form-control quantity text-center @error("items.$index.quantity") is-invalid @enderror"
                                value="{{ $quantity }}"
                                min="1"
                                required
                            >
                            <button type="button" class="oc-qty-btn" data-qty-action="increase" aria-label="Tăng số lượng">+</button>
                        </div>
                        <small data-qty-help>Chọn kho để kiểm tra tồn.</small>
                        @error("items.$index.quantity")
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-product-field">
                        <label>Giảm %</label>
                        <div class="oc-input-suffix">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                name="items[{{ $index }}][discount_percent]"
                                class="form-control discount-percent text-end @error("items.$index.discount_percent") is-invalid @enderror"
                                value="{{ (float) ($itemData['discount_percent'] ?? 0) }}"
                            >
                            <span>%</span>
                        </div>
                    </div>

                    <div class="oc-product-field">
                        <label>Giảm tiền / SP</label>
                        <div class="oc-input-suffix">
                            <input
                                type="number"
                                step="1"
                                min="0"
                                name="items[{{ $index }}][discount_amount]"
                                class="form-control discount-per-unit text-end @error("items.$index.discount_amount") is-invalid @enderror"
                                value="{{ (float) ($itemData['discount_amount'] ?? 0) }}"
                            >
                            <span>đ</span>
                        </div>
                    </div>

                    <div class="oc-product-field oc-total-field">
                        <label>Thành tiền</label>
                        <input
                            type="text"
                            name="items[{{ $index }}][line_total]"
                            class="form-control line-total text-end"
                            value="{{ $lineTotal > 0 ? number_format($lineTotal, 0, ',', '.') : '' }}"
                            readonly
                        >
                    </div>
                </div>

                <div class="oc-row-validation" data-row-validation hidden></div>
            </article>
        @endforeach
    </div>
</section>
