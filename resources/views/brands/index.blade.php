@extends('layouts.app')
@section('title', 'Danh sách Brand')
@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary mb-0">DANH SÁCH BRAND</h1>
            <a href="{{ route('brands.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm brand
            </a>
        </div>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control"
                               value="{{ $search ?? '' }}"
                               placeholder="Tìm theo tên hoặc slug...">
                    </div>
                    <div class="col-md-auto">
                        <button class="btn btn-outline-primary">
                            <i class="bi bi-search"></i> Tìm
                        </button>
                    </div>
                    <div class="col-md-auto">
                        <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th>Tên brand</th>
                            <th>Slug</th>
                            <th style="width:130px" class="text-center">Trạng thái</th>
                            <th style="width:200px" class="text-center">Thao tác</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td>{{ $brand->id }}</td>
                                <td class="fw-semibold">{{ $brand->name }}</td>
                                <td class="text-muted">{{ $brand->slug }}</td>
                                <td class="text-center">
                                    @if($brand->is_active)
                                        <span class="badge bg-success">Đang dùng</span>
                                    @else
                                        <span class="badge bg-secondary">Tắt</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </a>
                                    <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                          class="d-inline" onsubmit="return confirm('Xoá brand này?');">
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
                                <td colspan="5" class="text-center text-muted py-4">
                                    Không có brand nào.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $brands->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
