@extends('layouts.app')
@section('title', 'Danh sách loại giá')
@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary mb-0">DANH SÁCH LOẠI GIÁ</h1>
            <a href="{{ route('price-tiers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm loại giá
            </a>
        </div>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control"
                               value="{{ $search ?? '' }}"
                               placeholder="Tìm theo code hoặc tên...">
                    </div>
                    <div class="col-md-auto">
                        <button class="btn btn-outline-primary">
                            <i class="bi bi-search"></i> Tìm
                        </button>
                    </div>
                    <div class="col-md-auto">
                        <a href="{{ route('price-tiers.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:80px">#</th>
                            <th style="width:220px">Code</th>
                            <th>Tên</th>
                            <th style="width:120px" class="text-center">Priority</th>
                            <th style="width:130px" class="text-center">Trạng thái</th>
                            <th style="width:200px" class="text-center">Thao tác</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($tiers as $tier)
                            <tr>
                                <td>{{ $tier->id }}</td>
                                <td class="fw-semibold">{{ $tier->code }}</td>
                                <td>{{ $tier->name }}</td>
                                <td class="text-center">{{ $tier->priority }}</td>
                                <td class="text-center">
                                    @if($tier->is_active)
                                        <span class="badge bg-success">Đang dùng</span>
                                    @else
                                        <span class="badge bg-secondary">Tắt</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('price-tiers.edit', $tier) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </a>
                                    <form method="POST" action="{{ route('price-tiers.destroy', $tier) }}"
                                          class="d-inline" onsubmit="return confirm('Xoá loại giá này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Xoá
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có loại giá.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $tiers->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
