@extends('layouts.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-uppercase text-secondary">Chỉnh sửa danh mục sản phẩm</h1>
            <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('categories.update', $category->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    Tên danh mục <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name', $category->name) }}"
                                       placeholder="Nhập tên danh mục"
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Mô tả</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description"
                                          name="description"
                                          rows="4"
                                          placeholder="Nhập mô tả danh mục">{{ old('description', $category->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="parent_id" class="form-label">Danh mục cha</label>
                                <select class="form-select @error('parent_id') is-invalid @enderror"
                                        id="parent_id"
                                        name="parent_id">
                                    <option value="">— Không có —</option>
                                    @foreach($categories as $cat)
                                        @if($cat->id != $category->id)
                                            <option value="{{ $cat->id }}" 
                                                    {{ old('parent_id', $category->parent_id) == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->name }}
                                                @if($cat->parent)
                                                    ({{ $cat->parent->name }})
                                                @endif
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Chọn danh mục cha nếu muốn tạo danh mục con
                                </small>
                            </div>

                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Thông tin</h6>
                                    <p class="card-text small mb-1">
                                        <strong>ID:</strong> {{ $category->id }}
                                    </p>
                                    <p class="card-text small mb-1">
                                        <strong>Số sản phẩm:</strong> {{ $category->products()->count() }}
                                    </p>
                                    <p class="card-text small mb-0">
                                        <strong>Số danh mục con:</strong> {{ $category->children()->count() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('categories.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Hủy
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Cập nhật
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

