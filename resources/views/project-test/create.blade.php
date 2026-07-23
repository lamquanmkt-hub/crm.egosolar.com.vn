@extends('layouts.app')

@section('title', 'Tạo Công Trình Test new')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
<div class="pt-page">
<div class="pt-shell" data-pt-wizard>
    <section class="pt-hero">
        <div class="pt-hero__row">
            <div><div class="pt-kicker"><i class="bi bi-person-plus"></i> Bước khởi tạo từ Sales <span class="pt-new">TEST NEW</span></div><h1>Tạo hồ sơ công trình</h1><p>Chỉ nhập thông tin khách hàng, nhu cầu và lịch khảo sát đề xuất. Kỹ thuật sẽ duyệt hoặc gửi lại lịch khác.</p></div>
            <div class="pt-actions"><a href="{{ route('project-test.index') }}" class="pt-btn pt-btn--light"><i class="bi bi-arrow-left"></i> Quay lại</a></div>
        </div>
    </section>

    @if($errors->any())
        <div class="pt-alert" style="margin-top:14px"><strong>Chưa thể lưu:</strong> {{ $errors->first() }}</div>
    @endif

    <div class="pt-wizard">
        <div class="pt-wizard__step active" data-wizard-step="1">01 · Khách hàng & công trình</div>
        <div class="pt-wizard__step" data-wizard-step="2">02 · Nhu cầu & lịch khảo sát</div>
        <div class="pt-wizard__step" data-wizard-step="3">03 · Kiểm tra & gửi Kỹ thuật</div>
    </div>

    <form method="POST" action="{{ route('project-test.store') }}" class="pt-card pt-section">
        @csrf
        <section class="pt-wizard-panel active" data-wizard-panel="1">
            <div class="pt-section__head"><div><h2>Thông tin cơ bản</h2><p>Chọn khách CRM để tự điền người liên hệ, số điện thoại và địa chỉ.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div><label class="pt-label">Công ty</label><select class="pt-select" name="company_id"><option value="">-- Chọn công ty --</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)old('company_id',$activeCompanyId)===(int)$company->id)>{{ $company->name }}</option>@endforeach</select></div>
                <div><label class="pt-label">Khách hàng CRM</label><select class="pt-select" name="customer_id" data-customer-select><option value="">-- Chọn khách hoặc nhập tay --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->address }}" @selected((int)old('customer_id')===(int)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select></div>
                <div><label class="pt-label">Sales phụ trách</label><select class="pt-select" name="sales_user_id"><option value="">-- Người đang tạo --</option>@foreach($activeUsers as $user)<option value="{{ $user->id }}" @selected((int)old('sales_user_id',$defaultSalesId)===(int)$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div class="pt-field--full"><label class="pt-label">Tên công trình <span class="pt-required">*</span></label><input class="pt-input" name="name" value="{{ old('name') }}" required placeholder="VD: Hệ thống điện mặt trời nhà Anh Nam"></div>
                <div class="pt-field--full"><label class="pt-label">Địa chỉ lắp đặt <span class="pt-required">*</span></label><input class="pt-input" name="address" value="{{ old('address') }}" required placeholder="Số nhà, đường, phường/xã, tỉnh/thành"></div>
                <div><label class="pt-label">Người liên hệ</label><input class="pt-input" name="contact_name" value="{{ old('contact_name') }}" placeholder="Tên khách hàng"></div>
                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="09xxxxxxxx"></div>
                <div><label class="pt-label">Mức ưu tiên</label><select class="pt-select" name="priority" required><option value="normal">Bình thường</option><option value="high" @selected(old('priority')==='high')>Cao</option><option value="urgent" @selected(old('priority')==='urgent')>Khẩn</option><option value="low" @selected(old('priority')==='low')>Thấp</option></select></div>
            </div>
        </section>

        <section class="pt-wizard-panel" data-wizard-panel="2">
            <div class="pt-section__head"><div><h2>Nhu cầu và lịch khảo sát</h2><p>Sales đề xuất lịch; Kỹ thuật có quyền xác nhận hoặc gửi lại thời gian khác.</p></div></div>
            <div class="pt-form-grid">
                <div><label class="pt-label">Loại hệ thống</label><select class="pt-select" name="system_type"><option value="">-- Chưa xác định --</option><option value="hybrid" @selected(old('system_type')==='hybrid')>Hybrid có lưu trữ</option><option value="on-grid" @selected(old('system_type')==='on-grid')>Hòa lưới bám tải</option><option value="off-grid" @selected(old('system_type')==='off-grid')>Độc lập</option><option value="other" @selected(old('system_type')==='other')>Khác</option></select></div>
                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" min="0" name="estimated_kwp" value="{{ old('estimated_kwp') }}" placeholder="VD: 10.50"></div>
                <div><label class="pt-label">Sales đề xuất lịch khảo sát <span class="pt-required">*</span></label><input class="pt-input" type="datetime-local" name="proposed_survey_at" value="{{ old('proposed_survey_at') }}" required></div>
                <div><label class="pt-label">Mục tiêu hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ old('target_completion_at') }}"></div>
                <div class="pt-field--full"><label class="pt-label">Nhu cầu khách hàng <span class="pt-required">*</span></label><textarea class="pt-textarea" name="customer_need" required placeholder="Tiền điện, thiết bị sử dụng, nhu cầu ban ngày/ban đêm, yêu cầu lưu trữ, mong muốn của khách...">{{ old('customer_need') }}</textarea></div>
                <div class="pt-field--full"><label class="pt-label">Ghi chú bàn giao nội bộ</label><textarea class="pt-textarea" name="note" placeholder="Điểm cần lưu ý khi Kỹ thuật liên hệ khảo sát...">{{ old('note') }}</textarea></div>
            </div>
        </section>

        <section class="pt-wizard-panel" data-wizard-panel="3">
            <div class="pt-section__head"><div><h2>Xác nhận trước khi gửi</h2><p>Sau khi lưu, hồ sơ chuyển thẳng sang trạng thái chờ Kỹ thuật duyệt lịch khảo sát.</p></div></div>
            <div class="pt-alert pt-alert--info">Module Test sử dụng bảng dữ liệu riêng, không tạo hoặc thay đổi công trình cũ.</div>
            <div class="pt-review" style="margin-top:12px">
                <div><small>Tên công trình</small><strong data-review-field="name">—</strong></div>
                <div><small>Địa chỉ</small><strong data-review-field="address">—</strong></div>
                <div><small>Người liên hệ</small><strong data-review-field="contact_name">—</strong></div>
                <div><small>Số điện thoại</small><strong data-review-field="contact_phone">—</strong></div>
                <div><small>Lịch khảo sát đề xuất</small><strong data-review-field="proposed_survey_at">—</strong></div>
                <div><small>Loại hệ thống</small><strong data-review-field="system_type">—</strong></div>
            </div>
        </section>

        <div class="pt-actionbar" style="margin-top:18px">
            <span class="pt-help"><i class="bi bi-shield-check"></i> Dữ liệu lưu riêng trong project_test_*, không đụng bảng sites cũ.</span>
            <div class="pt-actions">
                <button type="button" class="pt-btn pt-btn--soft" data-wizard-prev><i class="bi bi-arrow-left"></i> Quay lại</button>
                <button type="button" class="pt-btn pt-btn--brand" data-wizard-next>Tiếp tục <i class="bi bi-arrow-right"></i></button>
                <button type="submit" class="pt-btn pt-btn--brand" data-wizard-submit style="display:none"><i class="bi bi-send-check"></i> Lưu & gửi Kỹ thuật</button>
            </div>
        </div>
    </form>
</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
@endpush
