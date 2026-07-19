@php
    $action    = $action ?? route('warehouses.store');
    $method    = $method ?? 'POST';
    $warehouse = $warehouse ?? null;
    $companies = $companies ?? collect();

    $selectedCompanyIds = old(
        'company_ids',
        $warehouse?->companies?->pluck('id')->toArray() ?? []
    );
@endphp

<form action="{{ $action }}" method="POST" class="mt-3">
    @csrf
    @if(strtoupper($method) === 'PUT')
        @method('PUT')
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Có lỗi xảy ra:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label">Công ty <span class="text-danger">*</span></label>
        <select name="company_ids[]" class="form-select" multiple required size="3">
            @foreach($companies as $c)
                <option value="{{ $c->id }}" {{ in_array($c->id, $selectedCompanyIds) ? 'selected' : '' }}>
                    {{ $c->name }} @if(!empty($c->code)) ({{ $c->code }}) @endif
                </option>
            @endforeach
        </select>
        <small class="text-muted">Giữ Ctrl (Windows) / Cmd (Mac) để chọn nhiều.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Tên kho</label>
        <input class="form-control" type="text" name="name"
               value="{{ old('name', $warehouse->name ?? '') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Địa điểm</label>
        <input class="form-control" type="text" name="location"
               value="{{ old('location', $warehouse->location ?? '') }}">
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">
            {{ strtoupper($method) === 'PUT' ? 'Cập nhật' : 'Tạo mới' }}
        </button>

        <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">
            Quay lại
        </a>
    </div>
</form>
