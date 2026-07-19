@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">Thêm nhân viên</h2>
            <div class="text-muted">Tạo hồ sơ nhân sự mới</div>
        </div>

        <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('hr.employees.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror"
                               value="{{ old('phone_number') }}">
                        @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror">
    <option value="">-- Chọn vai trò --</option>
    @foreach($roles as $role)
        <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
            {{ $role->name }}
        </option>
    @endforeach
</select>
@error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phòng ban</label>
                        <select name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                            <option value="">-- Chọn phòng ban --</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Chức vụ</label>
                        <select name="position_id" class="form-select @error('position_id') is-invalid @enderror">
                            <option value="">-- Chọn chức vụ --</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                    {{ $position->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('position_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <select name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Đang hoạt động</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Ngưng hoạt động</option>
                        </select>
                        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <div class="border rounded-4 p-3 bg-light">
                            <div class="fw-bold mb-2"><i class="bi bi-cash-coin me-1"></i> Mức lương</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Lương chính thức</label>
                                    <input type="text" name="official_salary" class="form-control @error('official_salary') is-invalid @enderror" value="{{ old('official_salary') }}" placeholder="VD: 15000000 hoặc 15.000.000">
                                    @error('official_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Lương thử việc</label>
                                    <input type="text" name="probation_salary" class="form-control @error('probation_salary') is-invalid @enderror" value="{{ old('probation_salary') }}" placeholder="VD: 12000000">
                                    @error('probation_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Lương thực tập</label>
                                    <input type="text" name="internship_salary" class="form-control @error('internship_salary') is-invalid @enderror" value="{{ old('internship_salary') }}" placeholder="VD: 4000000">
                                    @error('internship_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Lưu nhân viên
                    </button>

                    <a href="{{ route('hr.employees.index') }}" class="btn btn-light border">
                        Huỷ
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection