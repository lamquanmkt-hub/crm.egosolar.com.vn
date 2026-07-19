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
    $tierInstance = $tier ?? null;
@endphp
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control"
               value="{{ old('code', $tierInstance?->code ?? '') }}"
               placeholder="retail, agent_1..."
               required>
        <small class="text-muted">Duy nhất, không dấu, dùng _ nếu cần.</small>
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label">Tên</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $tierInstance?->name ?? '') }}"
               required>
    </div>
</div>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Priority</label>
        <input type="number" min="0" name="priority" class="form-control"
               value="{{ old('priority', $tierInstance?->priority ?? 0) }}">
        <small class="text-muted">Số nhỏ hơn ưu tiên hơn (tuỳ bạn quy ước).</small>
    </div>
    <div class="col-md-8 mb-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input"
                   type="checkbox"
                   id="is_active"
                   name="is_active"
                   value="1"
                    {{ old('is_active', $tierInstance?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Đang sử dụng</label>
        </div>
    </div>
</div>
<div class="d-flex gap-2">
    <button class="btn btn-primary">
        <i class="bi bi-save"></i> {{ $buttonText ?? 'Lưu' }}
    </button>
    <a href="{{ route('price-tiers.index') }}" class="btn btn-outline-secondary">Huỷ</a>
</div>
