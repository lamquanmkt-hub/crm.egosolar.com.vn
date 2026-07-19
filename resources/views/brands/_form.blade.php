@csrf
@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Dữ liệu chưa hợp lệ:</div>
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
@php
    $brandInstance = $brand ?? null;
@endphp
<div class="mb-3">
    <label class="form-label">Tên brand</label>
    <input type="text" name="name" class="form-control"
           value="{{ old('name', $brandInstance?->name ?? '') }}"
           required>
</div>
<div class="mb-3">
    <label class="form-label">Slug</label>
    <input type="text" name="slug" class="form-control"
           value="{{ old('slug', $brandInstance?->slug ?? '') }}"
           placeholder="Tự sinh theo tên nếu để trống">
    <small class="text-muted">Unique. Không dấu, viết thường, dùng dấu gạch ngang.</small>
</div>
<div class="mb-3">
    <label class="form-label">Mô tả</label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Mô tả brand...">{{ old('description', $brandInstance?->description ?? '') }}</textarea>
</div>
<div class="mb-3">
    <div class="form-check form-switch">
        <input class="form-check-input"
               type="checkbox"
               id="is_active"
               name="is_active"
               value="1"
                {{ old('is_active', $brandInstance?->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Đang sử dụng</label>
    </div>
</div>
<div class="d-flex gap-2">
    <button class="btn btn-primary">
        <i class="bi bi-save"></i> {{ $buttonText ?? 'Lưu' }}
    </button>
    <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">Huỷ</a>
</div>
