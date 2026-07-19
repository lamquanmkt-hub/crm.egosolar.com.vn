@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">Cập nhật phòng ban</h2>
            <div class="text-muted">Chỉnh sửa thông tin phòng ban</div>
        </div>

        <a href="{{ route('hr.departments.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('hr.departments.update', $department->id) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên phòng ban</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $department->name) }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mã phòng ban</label>
                        <input type="text" name="code" class="form-control"
                               value="{{ old('code', $department->code) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" rows="4" class="form-control">{{ old('description', $department->description) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Cập nhật
                    </button>
                    <a href="{{ route('hr.departments.index') }}" class="btn btn-light border">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection