@extends('layouts.app')

@section('title', 'Tạo đơn nhân sự')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-leave-promax.css') }}?v={{ filemtime(public_path('css/ego-leave-promax.css')) }}">
@endpush

@section('content')
<div id="egoLeaveCreatePromax">
    <div class="lv-shell">
        @if(session('error'))
            <div class="lv-alert lv-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="lv-alert lv-alert--danger">
                <i class="bi bi-exclamation-circle"></i>
                <div>
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            </div>
        @endif

        <header class="lv-hero lv-panel">
            <div class="lv-heading">
                <div class="lv-heading__icon"><i class="bi bi-file-earmark-plus"></i></div>
                <div>
                    <span>ĐỀ XUẤT NHÂN SỰ</span>
                    <h1>Tạo đơn mới</h1>
                    <p>Điền thông tin, chọn người duyệt và tải file chứng minh khi cần.</p>
                </div>
            </div>
            <div class="lv-actions">
                <a class="lv-btn lv-btn--glass" href="{{ route('hr.leave.index', ['tab' => 'mine']) }}"><i class="bi bi-arrow-left"></i>Danh sách đơn</a>
            </div>
        </header>

        <form method="POST" action="{{ route('hr.leave.store') }}" enctype="multipart/form-data" class="lv-create-layout" data-loading-form>
            @csrf

            <main class="lv-panel lv-form-card">
                <section class="lv-form-section">
                    <div class="lv-section-head">
                        <div class="lv-section-no">01</div>
                        <div><h2>Loại đề xuất</h2><p>Chọn đúng loại để hệ thống xử lý và ghi nhận bảng công.</p></div>
                    </div>

                    <div class="lv-grid">
                        <div class="lv-field">
                            <label>Loại đơn <em>*</em></label>
                            <select class="lv-select" name="request_type" required>
                                <option value="leave" @selected(old('request_type', $presetType) === 'leave')>Nghỉ phép</option>
                                <option value="wfh" @selected(old('request_type', $presetType) === 'wfh')>Làm online</option>
                                <option value="business_trip" @selected(old('request_type', $presetType) === 'business_trip')>Công tác</option>
                                <option value="late" @selected(old('request_type', $presetType) === 'late')>Xin đi trễ</option>
                                <option value="early_leave" @selected(old('request_type', $presetType) === 'early_leave')>Xin về sớm</option>
                            </select>
                        </div>

                        <div class="lv-field" data-leave-type-wrap>
                            <label>Loại nghỉ <em>*</em></label>
                            <select class="lv-select" name="leave_type">
                                <option value="annual" @selected(old('leave_type', 'annual') === 'annual')>Nghỉ phép năm</option>
                                <option value="sick" @selected(old('leave_type') === 'sick')>Nghỉ ốm</option>
                                <option value="personal" @selected(old('leave_type') === 'personal')>Nghỉ việc riêng</option>
                                <option value="unpaid" @selected(old('leave_type') === 'unpaid')>Nghỉ không lương</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="lv-form-section">
                    <div class="lv-section-head">
                        <div class="lv-section-no">02</div>
                        <div><h2>Thời gian & người duyệt</h2><p>Trưởng phòng cùng ban được ưu tiên trong danh sách người duyệt.</p></div>
                    </div>

                    <div class="lv-grid">
                        <div class="lv-field">
                            <label>Từ ngày <em>*</em></label>
                            <input type="date" class="lv-input" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="lv-field">
                            <label>Giờ bắt đầu</label>
                            <input type="time" class="lv-input" name="start_time" value="{{ old('start_time') }}">
                        </div>
                        <div class="lv-field">
                            <label>Đến ngày <em>*</em></label>
                            <input type="date" class="lv-input" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="lv-field">
                            <label>Giờ kết thúc</label>
                            <input type="time" class="lv-input" name="end_time" value="{{ old('end_time') }}">
                        </div>
                        <div class="lv-field is-full">
                            <label>Người duyệt <em>*</em></label>
                            <select class="lv-select" name="approver_id" required>
                                <option value="">Chọn người duyệt</option>
                                @foreach($approvers as $approver)
                                    <option value="{{ $approver->id }}" @selected((int) old('approver_id', $defaultApproverId) === (int) $approver->id)>
                                        {{ $approver->name }}{{ $approver->department?->name ? ' · '.$approver->department->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lv-days-preview is-full">
                            <span>Số ngày hệ thống dự kiến ghi nhận</span>
                            <strong data-days-preview>1 ngày</strong>
                        </div>
                    </div>
                </section>

                <section class="lv-form-section">
                    <div class="lv-section-head">
                        <div class="lv-section-no">03</div>
                        <div><h2>Lý do & file chứng minh</h2><p>Đính kèm tối đa 8 file ảnh, PDF, Word hoặc Excel; mỗi file tối đa 10 MB.</p></div>
                    </div>

                    <div class="lv-grid">
                        <div class="lv-field is-full">
                            <label>Lý do đề xuất <em>*</em></label>
                            <textarea class="lv-textarea" name="reason" rows="6" required placeholder="Mô tả rõ lý do, kế hoạch bàn giao công việc hoặc thông tin cần người duyệt biết...">{{ old('reason') }}</textarea>
                        </div>

                        <div class="is-full">
                            <label class="lv-dropzone" data-proof-dropzone>
                                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" data-proof-input>
                                <i class="bi bi-cloud-arrow-up"></i>
                                <strong>Kéo thả file vào đây hoặc bấm để chọn</strong>
                                <span>JPG, PNG, WEBP, PDF, Word, Excel · tối đa 8 file</span>
                            </label>
                            <div class="lv-file-preview" data-proof-preview></div>
                        </div>
                    </div>
                </section>
            </main>

            <aside class="lv-panel lv-summary">
                <div class="lv-summary__hero">
                    <span>TÓM TẮT ĐƠN</span>
                    <h2>Kiểm tra trước khi gửi</h2>
                </div>
                <div class="lv-summary__body">
                    <div class="lv-summary-row"><span>Loại đơn</span><strong data-summary-type>—</strong></div>
                    <div class="lv-summary-row"><span>Khoảng thời gian</span><strong data-summary-period>—</strong></div>
                    <div class="lv-summary-row"><span>Người duyệt</span><strong data-summary-approver>—</strong></div>
                    <div class="lv-summary-note"><i class="bi bi-shield-check"></i><span>File chứng minh được lưu riêng tư. Chỉ người tạo, người duyệt và nhóm quản trị HR được tải xuống.</span></div>
                    <button type="submit" class="lv-btn lv-btn--primary lv-submit"><i class="bi bi-send"></i>Gửi đơn chờ duyệt</button>
                    <a class="lv-btn lv-btn--light lv-submit" href="{{ route('hr.leave.index', ['tab' => 'mine']) }}">Hủy và quay lại</a>
                </div>
            </aside>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-leave-promax.js') }}?v={{ filemtime(public_path('js/ego-leave-promax.js')) }}" defer></script>
@endpush
