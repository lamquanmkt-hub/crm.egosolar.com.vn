@php
    $action    = $action ?? route('warehouses.store');
    $method    = $method ?? 'POST';
    $warehouse = $warehouse ?? null;
    $companies = $companies ?? collect();


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
