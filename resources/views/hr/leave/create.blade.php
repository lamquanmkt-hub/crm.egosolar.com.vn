@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">Tạo đơn nhân sự</h3>
            <div class="text-muted">Đăng ký nghỉ phép hoặc làm online và chọn người duyệt</div>
        </div>
        <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-list-ul me-1"></i> Danh sách đơn
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-bold mb-2">Có lỗi xảy ra:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('hr.leave.store') }}" class="row g-4">
                @csrf

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Loại đăng ký</label>
                    <select name="request_type" id="request_type" class="form-select rounded-4">
                        <option value="leave" {{ old('request_type') === 'leave' ? 'selected' : '' }}>Nghỉ phép</option>
                        <option value="wfh" {{ old('request_type') === 'wfh' ? 'selected' : '' }}>Làm online</option>
                    </select>
                </div>

                <div class="col-md-6" id="leave_type_wrap">
                    <label class="form-label fw-semibold">Loại nghỉ</label>
                    <select name="leave_type" id="leave_type" class="form-select rounded-4">
                        <option value="annual" {{ old('leave_type') === 'annual' ? 'selected' : '' }}>Nghỉ phép năm</option>
                        <option value="sick" {{ old('leave_type') === 'sick' ? 'selected' : '' }}>Nghỉ ốm</option>
                        <option value="personal" {{ old('leave_type') === 'personal' ? 'selected' : '' }}>Nghỉ việc riêng</option>
                        <option value="unpaid" {{ old('leave_type') === 'unpaid' ? 'selected' : '' }}>Nghỉ không lương</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Từ ngày</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control rounded-4" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Đến ngày</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control rounded-4" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Người duyệt</label>
                    <select name="approver_id" class="form-select rounded-4" required>
                        <option value="">-- Chọn người duyệt --</option>
                        @foreach($approvers as $approver)
                            <option value="{{ $approver->id }}" {{ old('approver_id') == $approver->id ? 'selected' : '' }}>
                                {{ $approver->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="alert alert-light border rounded-4 mb-0 w-100">
                        <div class="fw-semibold mb-1">Gợi ý</div>
                        <div class="small text-muted">
                            Nghỉ phép: chọn loại nghỉ phù hợp. Làm online: hệ thống tự hiểu là WFH.
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Lý do</label>
                    <textarea name="reason" rows="4" class="form-control rounded-4" placeholder="Nhập lý do đăng ký...">{{ old('reason') }}</textarea>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="{{ route('hr.leave.index') }}" class="btn btn-light rounded-4 px-4">Huỷ</a>
                    <button type="submit" class="btn btn-primary rounded-4 px-4">
                        <i class="bi bi-send me-1"></i> Gửi đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const requestType = document.getElementById('request_type');
    const leaveTypeWrap = document.getElementById('leave_type_wrap');
    const leaveType = document.getElementById('leave_type');

    function syncForm() {
        if (requestType.value === 'wfh') {
            leaveTypeWrap.style.display = 'none';
            leaveType.value = 'wfh';
        } else {
            leaveTypeWrap.style.display = '';
            if (leaveType.value === 'wfh') {
                leaveType.value = 'annual';
            }
        }
    }

    requestType.addEventListener('change', syncForm);
    syncForm();
});
</script>
@endsection