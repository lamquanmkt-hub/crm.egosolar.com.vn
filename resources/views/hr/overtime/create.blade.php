@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">Đăng ký tăng ca</h3>
            <div class="text-muted">Tạo đơn tăng ca để HR / quản lý duyệt và ghi nhận vào bảng chấm công</div>
        </div>

        <a href="{{ route('hr.overtime.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-list-ul me-1"></i> Danh sách tăng ca
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
            <form method="POST" action="{{ route('hr.overtime.store') }}" class="row g-4">
                @csrf

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Ngày tăng ca</label>
                    <input type="date" name="overtime_date" value="{{ old('overtime_date', now()->toDateString()) }}" class="form-control rounded-4" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Từ giờ</label>
                    <input type="time" name="start_time" value="{{ old('start_time', '18:00') }}" class="form-control rounded-4" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Đến giờ</label>
                    <input type="time" name="end_time" value="{{ old('end_time', '20:00') }}" class="form-control rounded-4" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Người duyệt</label>
                    <select name="approver_id" class="form-select rounded-4">
                        <option value="">-- HR / Admin duyệt --</option>
                        @foreach($approvers as $approver)
                            <option value="{{ $approver->id }}" {{ old('approver_id') == $approver->id ? 'selected' : '' }}>
                                {{ $approver->name }} @if($approver->email) - {{ $approver->email }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Lý do tăng ca</label>
                    <textarea name="reason" rows="4" class="form-control rounded-4" placeholder="VD: xử lý đơn hàng gấp, hỗ trợ dự án, trực kho...">{{ old('reason') }}</textarea>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="{{ route('hr.overtime.index') }}" class="btn btn-light rounded-4 px-4">Huỷ</a>
                    <button type="submit" class="btn btn-primary rounded-4 px-4">
                        <i class="bi bi-send me-1"></i> Gửi đơn tăng ca
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
