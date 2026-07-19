@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit' && isset($order) && !empty($order->id);
    $initialTotal = old('total_amount', $isEdit ? ($order->total_amount ?? 0) : 0);
@endphp

<div class="oc-summary-card">
    <header class="oc-summary-head">
        <span>Tổng đơn</span>
        <h2>Thanh toán</h2>
    </header>

    <div class="oc-summary-body">
        <div class="oc-summary-row oc-summary-count">
            <span>Số mặt hàng</span>
            <strong id="totalItems">0</strong>
        </div>

        <div class="oc-summary-row oc-summary-count">
            <span>Tổng số lượng</span>
            <strong id="totalQuantity">0</strong>
        </div>

        <div class="oc-summary-separator"></div>

        <div class="oc-summary-row">
            <span>Tạm tính trước VAT</span>
            <strong id="totalBeforeVat">0 đ</strong>
        </div>

        <div class="oc-summary-row">
            <span>Thuế VAT</span>
            <strong id="totalVatAmount">0 đ</strong>
        </div>

        <div class="oc-summary-row" id="discountSummaryRow" hidden>
            <span>Tổng giảm giá</span>
            <strong id="totalDiscount">0 đ</strong>
        </div>

        <div class="oc-summary-total">
            <span>Tổng thanh toán</span>
            <strong id="totalAmount">{{ number_format((float) $initialTotal, 0, ',', '.') }} đ</strong>
        </div>

        <div class="oc-summary-note">
            <i class="bi bi-shield-check"></i>
            <span>Hệ thống sẽ kiểm tra lại tồn kho trước khi tạo đơn.</span>
        </div>

        <div class="oc-summary-actions">
            <button
                type="submit"
                form="orderForm"
                name="action"
                value="save_draft"
                class="oc-btn oc-btn-outline oc-btn-block"
                data-submit-button
            >
                <i class="bi bi-save"></i>
                <span>Lưu nháp</span>
            </button>

            <button
                type="submit"
                form="orderForm"
                name="action"
                value="submit"
                class="oc-btn oc-btn-primary oc-btn-block"
                data-primary-submit
                data-submit-button
                disabled
            >
                @if($isEdit)
                    <i class="bi bi-save2"></i>
                    <span>Cập nhật đơn hàng</span>
                @else
                    <i class="bi bi-send"></i>
                    <span>Tạo đơn hàng</span>
                @endif
            </button>
        </div>
    </div>

    <div id="invoiceItems" hidden></div>
</div>
