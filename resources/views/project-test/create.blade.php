@extends('layouts.app')

@section('title', 'Tạo yêu cầu Kỹ thuật')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
@php
    $selectedSource = old('request_source', $defaultRequestSource ?? 'technical');
    $selectedType = old('project_type', $selectedSource === 'sales' ? 'commercial' : 'survey');
@endphp
<div class="pt-page">
<div class="pt-shell" data-pt-wizard>
    <section class="pt-hero">
        <div class="pt-hero__row">
            <div>
                <div class="pt-kicker"><i class="bi bi-tools"></i> PHÒNG KỸ THUẬT <span class="pt-new">TIẾP NHẬN</span></div>
                <h1>Tạo yêu cầu Kỹ thuật</h1>
                <p>Yêu cầu có thể đến từ Kinh doanh, CSKH, nội bộ hoặc do Phòng Kỹ thuật tự tạo. Sales không còn là bước bắt buộc của mọi hồ sơ.</p>
            </div>
            <div class="pt-actions"><a href="{{ route('project-test.index') }}" class="pt-btn pt-btn--light"><i class="bi bi-arrow-left"></i> Quay lại</a></div>
        </div>
    </section>

    @if($errors->any())
        <div class="pt-alert" style="margin-top:14px"><strong>Chưa thể lưu:</strong> {{ $errors->first() }}</div>
    @endif

    <div class="pt-wizard">
        <div class="pt-wizard__step active" data-wizard-step="1">01 · Nguồn yêu cầu</div>
        <div class="pt-wizard__step" data-wizard-step="2">02 · Công trình & nhu cầu</div>
        <div class="pt-wizard__step" data-wizard-step="3">03 · Điều phối ban đầu</div>
    </div>

    <form method="POST" action="{{ route('project-test.store') }}" class="pt-card pt-section">
        @csrf

        <section class="pt-wizard-panel active" data-wizard-panel="1">
            <div class="pt-section__head"><div><h2>Nguồn yêu cầu và loại nghiệp vụ</h2><p>Xác định hồ sơ đến từ đâu để hệ thống phân quyền và bỏ qua các bước không cần thiết.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div>
                    <label class="pt-label">Nguồn yêu cầu <span class="pt-required">*</span></label>
                    <select class="pt-select" name="request_source" required>
                        @foreach($requestSources as $value => $label)
                            <option value="{{ $value }}" @selected($selectedSource === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="pt-label">Loại nghiệp vụ <span class="pt-required">*</span></label>
                    <select class="pt-select" name="project_type" required>
                        @foreach($projectTypes as $value => $label)
                            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="pt-label">Mức ưu tiên</label>
                    <select class="pt-select" name="priority" required>
                        <option value="normal" @selected(old('priority','normal')==='normal')>Bình thường</option>
                        <option value="high" @selected(old('priority')==='high')>Cao</option>
                        <option value="urgent" @selected(old('priority')==='urgent')>Khẩn</option>
                        <option value="low" @selected(old('priority')==='low')>Thấp</option>
                    </select>
                </div>
                <div>
                    <label class="pt-label">Công ty</label>
                    <select class="pt-select" name="company_id"><option value="">-- Công ty hiện tại --</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)old('company_id',$activeCompanyId)===(int)$company->id)>{{ $company->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="pt-label">Người phụ trách khách hàng</label>
                    <select class="pt-select" name="sales_user_id"><option value="">-- Không bắt buộc --</option>@foreach($salesUsers as $user)<option value="{{ $user->id }}" @selected((int)old('sales_user_id',$defaultSalesId)===(int)$user->id)>{{ $user->name }}</option>@endforeach</select>
                    <div class="pt-help">Chỉ dùng khi hồ sơ thương mại hoặc cần phối hợp khách hàng.</div>
                </div>
                <div>
                    <label class="pt-label">Xác nhận khách hàng trước triển khai</label>
                    <label class="pt-switch-row"><input type="checkbox" name="customer_confirmation_required" value="1" @checked(old('customer_confirmation_required', $selectedType === 'commercial'))><span>Có yêu cầu xác nhận phương án / phạm vi</span></label>
                </div>
            </div>
            <div class="pt-alert pt-alert--info" style="margin-top:16px">Công trình nội bộ, bảo trì, kiểm tra hoặc sửa chữa có thể bỏ bước xác nhận khách hàng. Trưởng phòng Kỹ thuật vẫn là đầu mối tiếp nhận và điều phối.</div>
        </section>

        <section class="pt-wizard-panel" data-wizard-panel="2">
            <div class="pt-section__head"><div><h2>Thông tin công trình và yêu cầu</h2><p>Chọn khách CRM khi có; yêu cầu nội bộ có thể nhập trực tiếp tên đơn vị hoặc người đề nghị.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div>
                    <label class="pt-label">Khách hàng CRM</label>
                    <select class="pt-select" name="customer_id" data-customer-select><option value="">-- Chọn khách hoặc nhập tay --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->address }}" @selected((int)old('customer_id')===(int)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select>
                </div>
                <div><label class="pt-label">Người liên hệ / Người đề nghị</label><input class="pt-input" name="contact_name" value="{{ old('contact_name') }}" placeholder="Tên khách hàng hoặc người yêu cầu"></div>
                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="09xxxxxxxx"></div>
                <div class="pt-field--full"><label class="pt-label">Tên yêu cầu / công trình <span class="pt-required">*</span></label><input class="pt-input" name="name" value="{{ old('name') }}" required placeholder="VD: Khảo sát hệ thống điện mặt trời nhà Anh Nam"></div>
                <div class="pt-field--full"><label class="pt-label">Địa điểm thực hiện <span class="pt-required">*</span></label><input class="pt-input" name="address" value="{{ old('address') }}" required placeholder="Số nhà, đường, phường/xã, tỉnh/thành hoặc khu vực nội bộ"></div>
                <div><label class="pt-label">Loại hệ thống</label><select class="pt-select" name="system_type"><option value="">-- Chưa xác định --</option><option value="hybrid" @selected(old('system_type')==='hybrid')>Hybrid có lưu trữ</option><option value="on-grid" @selected(old('system_type')==='on-grid')>Hòa lưới bám tải</option><option value="off-grid" @selected(old('system_type')==='off-grid')>Độc lập</option><option value="other" @selected(old('system_type')==='other')>Khác</option></select></div>
                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" min="0" name="estimated_kwp" value="{{ old('estimated_kwp') }}" placeholder="VD: 10.50"></div>
                <div><label class="pt-label">Mục tiêu hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ old('target_completion_at') }}"></div>
                <div class="pt-field--full"><label class="pt-label">Mô tả yêu cầu Kỹ thuật <span class="pt-required">*</span></label><textarea class="pt-textarea" name="customer_need" required placeholder="Nội dung cần khảo sát, kiểm tra, lắp đặt, sửa chữa, bảo trì hoặc hỗ trợ...">{{ old('customer_need') }}</textarea></div>
                <div class="pt-field--full"><label class="pt-label">Ghi chú nội bộ</label><textarea class="pt-textarea" name="note" placeholder="Rủi ro, tài liệu đã có, người cần phối hợp hoặc lưu ý khi tiếp nhận...">{{ old('note') }}</textarea></div>
            </div>
        </section>

        <section class="pt-wizard-panel" data-wizard-panel="3">
            <div class="pt-section__head"><div><h2>Điều phối ban đầu</h2><p>Trưởng phòng có thể tạo và phân công ngay; các nguồn khác chỉ cần gửi yêu cầu để Kỹ thuật tiếp nhận.</p></div></div>
            <div class="pt-form-grid pt-form-grid--3">
                <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id"><option value="">-- Tiếp nhận sau --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected((int)old('technical_manager_id',$defaultTechnicalManagerId)===(int)$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div><label class="pt-label">Kỹ thuật viên khảo sát</label><select class="pt-select" name="lead_technician_id"><option value="">-- Phân công sau --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected((int)old('lead_technician_id')===(int)$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div><label class="pt-label">Lịch khảo sát dự kiến</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" value="{{ old('proposed_survey_at') }}"></div>
            </div>

            <div class="pt-review" style="margin-top:18px">
                <div><small>Tên yêu cầu</small><strong data-review-field="name">—</strong></div>
                <div><small>Địa điểm</small><strong data-review-field="address">—</strong></div>
                <div><small>Người liên hệ</small><strong data-review-field="contact_name">—</strong></div>
                <div><small>Số điện thoại</small><strong data-review-field="contact_phone">—</strong></div>
                <div><small>Lịch khảo sát</small><strong data-review-field="proposed_survey_at">Chưa đặt</strong></div>
                <div><small>Loại hệ thống</small><strong data-review-field="system_type">—</strong></div>
            </div>
        </section>

        <div class="pt-actionbar" style="margin-top:18px">
            <span class="pt-help"><i class="bi bi-shield-check"></i> Không xóa dữ liệu cũ; hồ sơ được ghi vào module Công trình chính thức.</span>
            <div class="pt-actions">
                <button type="button" class="pt-btn pt-btn--soft" data-wizard-prev><i class="bi bi-arrow-left"></i> Quay lại</button>
                <button type="button" class="pt-btn pt-btn--brand" data-wizard-next>Tiếp tục <i class="bi bi-arrow-right"></i></button>
                <button type="submit" class="pt-btn pt-btn--brand" data-wizard-submit style="display:none"><i class="bi bi-send-check"></i> Lưu yêu cầu Kỹ thuật</button>
            </div>
        </div>
    </form>
</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
@endpush
