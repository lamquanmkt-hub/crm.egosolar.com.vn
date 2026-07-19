@extends('layouts.app')

@section('title', 'Quản lý phương thức thanh toán')

@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
            <h2 class="fw-bold text-secondary">PHƯƠNG THỨC THANH TOÁN</h2>
            <a href="{{ route('payment-methods.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm mới
            </a>
        </div>

        {{-- Thông báo thành công --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <form action="{{ route('payment-methods.index') }}" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" name="keyword" class="form-control" placeholder="Tìm kiếm tên..." value="{{ request('keyword') }}">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th class="ps-4">Tên phương thức</th>
                            <th>Mã Code</th>
                            <th>Mô tả</th>
                            <th>Trạng thái</th>
                            <th class="text-end pe-4">Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($methods as $method)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $method->method_name }}</td>
                                <td><span class="badge bg-secondary font-monospace">{{ $method->code }}</span></td>
                                <td class="text-muted small">{{ Str::limit($method->description, 50) }}</td>
                                <td>
                                    @if($method->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success">Hoạt động</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger">Tạm khóa</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('payment-methods.edit', $method->id) }}" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('payment-methods.destroy', $method->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa phương thức này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Chưa có phương thức thanh toán nào.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                {{ $methods->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection