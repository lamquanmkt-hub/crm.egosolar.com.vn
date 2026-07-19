@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">Cập nhật chức vụ</h2>
            <div class="text-muted">Chỉnh sửa thông tin chức vụ</div>
        </div>

        <a href="{{ route('hr.positions.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('hr.positions.update', $position->id) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên chức vụ</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $position->name) }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mã chức vụ</label>
                        <input type="text" name="code" class="form-control"
                               value="{{ old('code', $position->code) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" rows="4" class="form-control">{{ old('description', $position->description) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Cập nhật
                    </button>
                    <a href="{{ route('hr.positions.index') }}" class="btn btn-light border">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection