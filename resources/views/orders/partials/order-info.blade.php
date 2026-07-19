@php
    $mode = $mode ?? 'create';
    $isEdit = ($mode === 'edit') && isset($order) && !empty($order->id);
    $selectedCustomer = $selectedCustomer ?? null;

    $selectedLeadId = old('lead_id');
    if (empty($selectedLeadId)) {
        $selectedLeadId = $isEdit
            ? ($order->lead_id ?? '')
            : ($selectedCustomer['lead_id'] ?? '');
    }

    $companyHidden = old('company_id', $isEdit ? ($order->company_id ?? '') : '');

    $orderDateValue = old('order_date');
    if (!$orderDateValue) {
        $raw = $isEdit ? ($order->order_date ?? null) : now()->toDateString();

        try {
            $orderDateValue = $raw
                ? \Carbon\Carbon::parse($raw)->toDateString()
                : now()->toDateString();
        } catch (\Throwable $e) {
            $orderDateValue = now()->toDateString();
        }
    }

    $invoiceCompanyNameValue = old(
        'invoice_company_name',
        $isEdit ? ($order->invoice_company_name ?? '') : ''
    );

    $invoiceTaxCodeValue = old(
        'invoice_tax_code',
        $isEdit ? ($order->invoice_tax_code ?? '') : ''
    );

    $invoiceAddressValue = old(
        'invoice_address',
        $isEdit ? ($order->invoice_address ?? '') : ''
    );

    $invoiceEmailValue = old(
        'invoice_email',
        $isEdit ? ($order->invoice_email ?? '') : ''
    );

    $invoiceOpen = filled($invoiceCompanyNameValue)
        || filled($invoiceTaxCodeValue)
        || filled($invoiceAddressValue)
        || filled($invoiceEmailValue)
        || $errors->hasAny([
            'invoice_company_name',
            'invoice_tax_code',
            'invoice_address',
            'invoice_email',
        ]);
@endphp

<section class="oc-section oc-order-section">
    <header class="oc-section-head">
        <div>
            <h2>Thông tin đơn hàng</h2>
        </div>
    </header>

    <div class="oc-section-body">
        <div class="oc-order-grid">
            <div class="oc-field oc-field-customer">
                <label for="customerSelect">Khách hàng <span>*</span></label>

                <select
                    id="customerSelect"
                    name="customer_id"
                    class="form-select @error('customer_id') is-invalid @enderror"
                    required
                    data-search-url="{{ route('orders.customers.search') }}"
                    data-customer-info-url="{{ url('/orders/customer-info') }}"
                    data-invoice-info-url-template="{{ url('/customers/__ID__/invoice-info') }}"
                >
                    <option value="">Tìm theo tên hoặc số điện thoại</option>

                    @if(!empty($selectedCustomer))
                        <option
                            value="{{ $selectedCustomer['id'] }}"
                            data-lead-id="{{ $selectedCustomer['lead_id'] ?? '' }}"
                            selected
                        >
                            {{ $selectedCustomer['text'] }}
                        </option>
                    @endif
                </select>

                <input type="hidden" id="leadIdInput" name="lead_id" value="{{ $selectedLeadId }}">
                <input type="hidden" id="orderCompanyId" name="company_id" value="{{ $companyHidden }}">

                <div class="oc-field-feedback" id="customerSearchStatus">Gõ tên hoặc số điện thoại để tìm khách hàng.</div>
                @error('customer_id')
                    <div class="oc-invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="oc-field">
                <label for="orderDate">Ngày đặt hàng <span>*</span></label>
                <input
                    id="orderDate"
                    type="date"
                    name="order_date"
                    class="form-control @error('order_date') is-invalid @enderror"
                    value="{{ $orderDateValue }}"
                    required
                >
                @error('order_date')
                    <div class="oc-invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="oc-field">
                <label for="orderNote">Ghi chú</label>
                <input
                    id="orderNote"
                    type="text"
                    name="note"
                    class="form-control @error('note') is-invalid @enderror"
                    value="{{ old('note', $isEdit ? ($order->note ?? '') : '') }}"
                    placeholder="Giao gấp, yêu cầu đặc biệt..."
                >
                @error('note')
                    <div class="oc-invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div id="customerInfo" class="oc-customer-meta" hidden>
            <span>Loại khách: <strong id="customerType">—</strong></span>
            <span>Khu vực: <strong id="customerRegion">—</strong></span>
            <span>Đơn gần đây: <strong id="customerOrders">0</strong></span>
            <span>Công nợ: <strong id="customerDebt">0 đ</strong></span>
        </div>

        <details class="oc-invoice" id="invoiceDetails" {{ $invoiceOpen ? 'open' : '' }}>
            <summary>
                <span class="oc-invoice-title">
                    <i class="bi bi-receipt"></i>
                    Thông tin xuất hóa đơn
                </span>

                <span class="oc-invoice-state" id="invoiceStateText">
                    {{ $invoiceOpen ? 'Đã có thông tin hóa đơn' : 'Không xuất hóa đơn' }}
                </span>
            </summary>

            <div class="oc-invoice-content">
                <div class="oc-invoice-grid">
                    <div class="oc-field">
                        <label for="invoice_company_name">Tên công ty / cá nhân</label>
                        <input
                            type="text"
                            id="invoice_company_name"
                            name="invoice_company_name"
                            class="form-control @error('invoice_company_name') is-invalid @enderror"
                            value="{{ $invoiceCompanyNameValue }}"
                            placeholder="CÔNG TY ABC"
                        >
                        @error('invoice_company_name')
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-field">
                        <label for="invoice_tax_code">Mã số thuế</label>
                        <input
                            type="text"
                            id="invoice_tax_code"
                            name="invoice_tax_code"
                            class="form-control @error('invoice_tax_code') is-invalid @enderror"
                            value="{{ $invoiceTaxCodeValue }}"
                            placeholder="Nhập mã số thuế"
                        >
                        @error('invoice_tax_code')
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-field">
                        <label for="invoice_email">Email nhận hóa đơn</label>
                        <input
                            type="email"
                            id="invoice_email"
                            name="invoice_email"
                            class="form-control @error('invoice_email') is-invalid @enderror"
                            value="{{ $invoiceEmailValue }}"
                            placeholder="example@email.com"
                        >
                        @error('invoice_email')
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="oc-field oc-field-full">
                        <label for="invoice_address">Địa chỉ xuất hóa đơn</label>
                        <input
                            type="text"
                            id="invoice_address"
                            name="invoice_address"
                            class="form-control @error('invoice_address') is-invalid @enderror"
                            value="{{ $invoiceAddressValue }}"
                            placeholder="Nhập địa chỉ xuất hóa đơn"
                        >
                        @error('invoice_address')
                            <div class="oc-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="oc-invoice-footer">
                    <button
                        type="button"
                        class="oc-btn oc-btn-outline oc-btn-sm"
                        id="btnSaveCustomerBilling"
                        data-update-url-template="{{ url('/customers/__ID__/billing-info') }}"
                    >
                        <i class="bi bi-save"></i>
                        Lưu vào khách hàng
                    </button>

                    <span id="billingInfoStatus">Chọn khách hàng để tự động lấy thông tin hóa đơn.</span>
                </div>
            </div>
        </details>
    </div>
</section>
