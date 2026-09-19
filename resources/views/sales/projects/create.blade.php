@extends('layouts.app')

@section('title', 'Tạo công trình Sales')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
<div class="pt-page">
    <div class="pt-shell pt-intake-page">
        <section class="pt-hero pt-hero--sales">
            <div class="pt-hero__row">
                <div>
                    <div class="pt-kicker"><i class="bi bi-graph-up-arrow"></i> MODULE SALES <span class="pt-new">BÀN GIAO KỸ THUẬT</span></div>
                    <h1>Tạo công trình từ Sales</h1>
                    <p>Sales nhập thông tin khách hàng và nhu cầu ban đầu. Sau khi lưu, hồ sơ được chuyển sang Trưởng phòng Kỹ thuật tiếp nhận và điều phối.</p>
                </div>
                <div class="pt-actions">
                    <a href="{{ route('sales-projects.index') }}" class="pt-btn pt-btn--light"><i class="bi bi-arrow-left"></i> Công trình Sales</a>
                </div>
            </div>
        </section>

        <?php if ($errors->any()): ?>
            <div class="pt-alert"><strong>Chưa thể lưu:</strong> {{ $errors->first() }}</div>
        <?php endif; ?>

        <form method="POST" action="{{ route('sales-projects.store') }}" class="pt-card pt-section pt-intake-form">
            @csrf
            <input type="hidden" name="request_source" value="sales">
            <input type="hidden" name="sales_user_id" value="{{ auth()->id() }}">
            <input type="hidden" name="customer_confirmation_required" value="1">

            <div class="pt-section__head">
                <div><h2>1. Liên kết hồ sơ bán hàng</h2><p>Đơn hàng và hợp đồng không bắt buộc, nhưng nên chọn khi công trình phát sinh từ giao dịch.</p></div>
                <span class="pt-source-chip pt-source-chip--sales">SALES TẠO</span>
            </div>

            <div class="pt-form-grid pt-form-grid--3">
                <div>
                    <label class="pt-label">Đơn hàng liên quan</label>
                    <select class="pt-select" name="sales_order_id" data-sales-order>
                        <option value="">-- Không liên kết đơn hàng --</option>
                        <?php foreach ($salesOrders as $order): ?>
                            <option value="{{ $order->id }}"
                                    data-customer-id="{{ $order->customer_id }}"
                                    data-customer-name="{{ $order->customer_name }}"
                                    data-phone="{{ $order->customer_phone ?: $order->receiver_phone }}"
                                    data-address="{{ $order->shipping_address ?: $order->customer_address }}" data-total="{{ (float) $order->total_amount }}"
                                    {{ (int) old('sales_order_id') === (int) $order->id ? 'selected' : '' }}>
                                {{ $order->order_code }} · {{ $order->customer_name ?: $order->receiver_name ?: 'Chưa có tên khách' }}
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="pt-label">Mã hợp đồng / báo giá</label>
                    <input class="pt-input" name="contract_reference" value="{{ old('contract_reference') }}" placeholder="VD: HĐ-2026-015">
                </div>
                <div>
                    <label class="pt-label">Loại yêu cầu</label>
                    <select class="pt-select" name="project_type" required>
                        <?php foreach ($salesProjectTypes as $value => $label): ?>
                            <option value="{{ $value }}" {{ old('project_type', 'commercial') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="pt-label">Khách hàng CRM</label>
                    <select class="pt-select" name="customer_id" data-customer-select>
                        <option value="">-- Chọn khách hàng --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->address }}" {{ (int) old('customer_id') === (int) $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="pt-label">Người liên hệ</label><input class="pt-input" name="contact_name" value="{{ old('contact_name') }}" data-contact-name></div>
                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ old('contact_phone') }}" data-contact-phone></div>
            </div>

            {{-- EGO_SALES_PROJECT_FINANCE_CREATE_V1 --}}
            <div class="pt-divider"></div>
            <div class="pt-section__head">
                <div>
                    <h2>2. Doanh thu công trình</h2>
                    <p>Chỉ Sales, Kế toán và Ban Giám đốc nhìn thấy. Phòng Kỹ thuật không hiển thị khối tài chính này.</p>
                </div>
                <span class="pt-source-chip pt-source-chip--sales">NỘI BỘ SALES</span>
            </div>
            <div class="pt-form-grid pt-form-grid--3">
                <div>
                    <label class="pt-label">Giá trị công trình / hợp đồng</label>
                    <input class="pt-input" type="number" step="1000" min="0" name="contract_amount" value="{{ old('contract_amount', 0) }}" data-contract-amount>
                </div>
                <div>
                    <label class="pt-label">Doanh thu phát sinh khác</label>
                    <input class="pt-input" type="number" step="1000" min="0" name="extra_revenue" value="{{ old('extra_revenue', 0) }}">
                </div>
                <div class="pt-field--full">
                    <label class="pt-label">Ghi chú doanh thu / điều khoản thanh toán</label>
                    <textarea class="pt-textarea" name="financial_note" placeholder="Điều khoản thu tiền; sau khi tạo công trình, lập từng đợt và ghi nhận chứng từ tại tab Doanh thu...">{{ old('financial_note') }}</textarea>
                </div>
            </div>

            <div class="pt-divider"></div>
            <div class="pt-section__head"><div><h2>3. Thông tin bàn giao cho Kỹ thuật</h2><p>Chỉ nhập dữ liệu đầu vào. Sales không phân công kỹ thuật viên và không can thiệp quy trình nội bộ.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div class="pt-field--full"><label class="pt-label">Tên công trình <span class="pt-required">*</span></label><input class="pt-input" name="name" value="{{ old('name') }}" required placeholder="VD: Điện mặt trời nhà Anh Nam – 10 kWp"></div>
                <div class="pt-field--full"><label class="pt-label">Địa chỉ công trình <span class="pt-required">*</span></label><input class="pt-input" name="address" value="{{ old('address') }}" data-address required></div>
                <div><label class="pt-label">Loại hệ thống</label><input class="pt-input" name="system_type" value="{{ old('system_type') }}" placeholder="Hybrid / On-grid / Khác"></div>
                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" min="0" name="estimated_kwp" value="{{ old('estimated_kwp') }}"></div>
                <div><label class="pt-label">Ngày khách mong muốn khảo sát</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" value="{{ old('proposed_survey_at') }}"></div>
                <div><label class="pt-label">Mức ưu tiên</label><select class="pt-select" name="priority"><option value="normal">Bình thường</option><option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Cao</option><option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Khẩn</option><option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Thấp</option></select></div>
                <div><label class="pt-label">Mục tiêu hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ old('target_completion_at') }}"></div>
                <div class="pt-field--full"><label class="pt-label">Nhu cầu khách hàng <span class="pt-required">*</span></label><textarea class="pt-textarea" name="customer_need" required placeholder="Mức tiêu thụ điện, nhu cầu lưu trữ, thiết bị cần lắp, yêu cầu khảo sát...">{{ old('customer_need') }}</textarea></div>
                <div class="pt-field--full"><label class="pt-label">Ghi chú bàn giao</label><textarea class="pt-textarea" name="handover_note" placeholder="Lịch khách rảnh, yêu cầu liên hệ, hồ sơ còn thiếu, lưu ý khi khảo sát...">{{ old('handover_note') }}</textarea></div>
                <div class="pt-field--full"><label class="pt-label">Ghi chú nội bộ Sales</label><textarea class="pt-textarea" name="note">{{ old('note') }}</textarea></div>
            </div>

            <div class="pt-intake-footer">
                <div><strong>Sau khi lưu</strong><span>Hồ sơ chuyển sang trạng thái “Yêu cầu mới · Chờ Kỹ thuật tiếp nhận”.</span></div>
                <button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-send"></i> Tạo & bàn giao Kỹ thuật</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const order = document.querySelector('[data-sales-order]');
    const customer = document.querySelector('[data-customer-select]');
    const name = document.querySelector('[data-contact-name]');
    const phone = document.querySelector('[data-contact-phone]');
    const address = document.querySelector('[data-address]');

    function fill(option) {
        if (!option) return;
        if (customer && option.dataset.customerId) customer.value = option.dataset.customerId;
        if (name && !name.value) name.value = option.dataset.customerName || '';
        if (phone && !phone.value) phone.value = option.dataset.phone || '';
        if (address && !address.value) address.value = option.dataset.address || '';
        const contractAmount = document.querySelector('[data-contract-amount]');
        if (contractAmount && Number(contractAmount.value || 0) <= 0 && Number(option.dataset.total || 0) > 0) contractAmount.value = option.dataset.total;
    }

    order?.addEventListener('change', () => fill(order.options[order.selectedIndex]));
    customer?.addEventListener('change', () => {
        const option = customer.options[customer.selectedIndex];
        if (!option) return;
        if (name) name.value = option.dataset.name || name.value;
        if (phone) phone.value = option.dataset.phone || phone.value;
        if (address) address.value = option.dataset.address || address.value;
    });
})();
</script>
@endpush
