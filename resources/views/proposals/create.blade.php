@extends('layouts.app')

@section('title', 'Tạo đề xuất')

@section('content')
<link rel="stylesheet"
      href="{{ asset('css/ego-proposals-v2.css') }}?v={{ @filemtime(public_path('css/ego-proposals-v2.css')) ?: time() }}">

<div class="ep-page">
    <div class="ep-wrap">
        <div class="ep-header">
            <div class="ep-heading">
                <div class="ep-heading-icon">
                    <i class="bi bi-file-earmark-plus"></i>
                </div>
                <div>
                    <div class="ep-eyebrow">Đề xuất nội bộ</div>
                    <h1 class="ep-title">Tạo đề xuất mới</h1>
                    <div class="ep-sub">
                        Điền rõ nhu cầu, ngân sách và thời hạn để cấp duyệt xử lý nhanh.
                    </div>
                </div>
            </div>

            <div class="ep-actions">
                <a href="{{ route('de-xuat.index') }}" class="ep-btn">
                    <i class="bi bi-arrow-left"></i> Danh sách
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                <strong>Chưa thể gửi đề xuất.</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('de-xuat.store') }}"
              enctype="multipart/form-data">
            @csrf

            <div class="ep-form-layout">
                <div class="ep-card">
                    <div class="ep-section">
                        <h2 class="ep-section-title">
                            <span class="ep-section-icon">
                                <i class="bi bi-card-heading"></i>
                            </span>
                            Thông tin chung
                        </h2>

                        <div class="ep-field">
                            <label class="ep-label">
                                Tiêu đề đề xuất <span class="ep-required">*</span>
                            </label>
                            <input type="text"
                                   name="title"
                                   class="ep-input"
                                   value="{{ old('title') }}"
                                   required
                                   placeholder="Ví dụ: Đề xuất mua thiết bị phục vụ thi công">
                        </div>

                        <div class="ep-grid-3">
                            <div class="ep-field">
                                <label class="ep-label">Loại đề xuất</label>
                                <select name="proposal_type" class="ep-select">
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ old('proposal_type', 'mua_sam') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ep-field">
                                <label class="ep-label">Mức ưu tiên</label>
                                <select name="priority" class="ep-select">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ old('priority', 'normal') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ep-field">
                                <label class="ep-label">Ngày cần xử lý</label>
                                <input type="date"
                                       name="needed_date"
                                       class="ep-input"
                                       value="{{ old('needed_date') }}">
                            </div>
                        </div>

                        <div class="ep-grid-2">
                            <div class="ep-field">
                                <label class="ep-label">Phòng ban</label>
                                <input type="text"
                                       name="department_name"
                                       class="ep-input"
                                       value="{{ old('department_name', $departmentName) }}"
                                       placeholder="Phòng ban đề xuất">
                            </div>

                            <div class="ep-field">
                                <label class="ep-label">Ngân sách dự kiến</label>
                                <input type="text"
                                       id="proposalAmount"
                                       name="amount"
                                       class="ep-input"
                                       inputmode="numeric"
                                       value="{{ old('amount', 0) }}"
                                       placeholder="0">
                                <div class="ep-hint">
                                    Đề xuất có số tiền lớn hơn 0 sẽ tự tạo ĐNTT nháp ngay khi được duyệt.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ep-section">
                        <h2 class="ep-section-title">
                            <span class="ep-section-icon">
                                <i class="bi bi-file-text"></i>
                            </span>
                            Nội dung trình duyệt
                        </h2>

                        <div class="ep-field">
                            <label class="ep-label">Nội dung đề xuất</label>
                            <textarea name="content"
                                      class="ep-textarea"
                                      placeholder="Mô tả cụ thể hạng mục, số lượng, đơn vị thực hiện hoặc phạm vi công việc...">{{ old('content') }}</textarea>
                        </div>

                        <div class="ep-field">
                            <label class="ep-label">Lý do đề xuất</label>
                            <textarea name="reason"
                                      class="ep-textarea"
                                      placeholder="Nêu rõ sự cần thiết, vấn đề đang gặp và căn cứ đề xuất...">{{ old('reason') }}</textarea>
                        </div>

                        <div class="ep-field mb-0">
                            <label class="ep-label">Kết quả kỳ vọng</label>
                            <textarea name="expected_result"
                                      class="ep-textarea"
                                      placeholder="Kết quả mong muốn sau khi đề xuất được phê duyệt...">{{ old('expected_result') }}</textarea>
                        </div>
                    </div>

                    <div class="ep-section">
                        <h2 class="ep-section-title">
                            <span class="ep-section-icon">
                                <i class="bi bi-paperclip"></i>
                            </span>
                            Hồ sơ đính kèm
                        </h2>

                        <div class="ep-upload">
                            <div class="mb-2 fs-3 text-info">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>
                            <div class="fw-bold mb-1">Chọn file minh chứng</div>
                            <div class="ep-hint mb-3">
                                Hỗ trợ hình ảnh, PDF, Word, Excel; tối đa 20MB mỗi file.
                            </div>
                            <input type="file"
                                   name="attachments[]"
                                   multiple
                                   class="form-control">
                        </div>
                    </div>

                    <div class="ep-form-actions">
                        <a href="{{ route('de-xuat.index') }}" class="ep-btn">
                            Hủy
                        </a>
                        <button class="ep-btn ep-btn-primary">
                            <i class="bi bi-send"></i> Gửi đề xuất
                        </button>
                    </div>
                </div>

                <div class="ep-card ep-side-card">
                    <div class="ep-card-head">
                        <div>
                            <h2 class="ep-card-title">Luồng xử lý</h2>
                            <div class="ep-card-sub">Tự động, không nhập lại dữ liệu</div>
                        </div>
                    </div>

                    <div class="ep-flow">
                        <div class="ep-flow-item">
                            <div class="ep-flow-num">1</div>
                            <div>
                                <div class="ep-flow-title">Nhân viên tạo đề xuất</div>
                                <div class="ep-flow-desc">
                                    Nhập nhu cầu, số tiền, thời hạn và hồ sơ liên quan.
                                </div>
                            </div>
                        </div>

                        <div class="ep-flow-item">
                            <div class="ep-flow-num">2</div>
                            <div>
                                <div class="ep-flow-title">Cấp có thẩm quyền duyệt</div>
                                <div class="ep-flow-desc">
                                    Phê duyệt hoặc từ chối trực tiếp tại chi tiết đề xuất.
                                </div>
                            </div>
                        </div>

                        <div class="ep-flow-item">
                            <div class="ep-flow-num">3</div>
                            <div>
                                <div class="ep-flow-title">Tự động tạo ĐNTT</div>
                                <div class="ep-flow-desc">
                                    Nếu số tiền lớn hơn 0, hệ thống tạo đúng một ĐNTT ở trạng thái nháp.
                                </div>
                            </div>
                        </div>

                        <div class="ep-flow-item">
                            <div class="ep-flow-num">4</div>
                            <div>
                                <div class="ep-flow-title">Người đề xuất bổ sung</div>
                                <div class="ep-flow-desc">
                                    Mở ĐNTT đã tạo để bổ sung người nhận, ngân hàng và chứng từ trước khi gửi duyệt.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ep-note">
                        <strong>Lưu ý:</strong> Module ĐNTT giữ nguyên giao diện, route và quy trình hiện tại. Nâng cấp này chỉ tạo phiếu nháp từ phía Đề xuất.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('proposalAmount');

    if (!input) return;

    const format = function () {
        const digits = String(input.value || '').replace(/\D/g, '');
        input.value = digits ? Number(digits).toLocaleString('vi-VN') : '0';
    };

    input.addEventListener('input', format);
    input.closest('form').addEventListener('submit', function () {
        input.value = String(input.value || '').replace(/\D/g, '');
    });

    format();
});
</script>
@endsection
