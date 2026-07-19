@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">Chức vụ</h2>
            <div class="text-muted">Quản lý danh mục chức vụ trong hệ thống</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('hr.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <a href="{{ route('hr.positions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Thêm chức vụ
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Tên chức vụ</th>
                            <th>Mã</th>
                            <th>Mô tả</th>
                            <th>Ngày tạo</th>
                            <th class="text-end pe-3">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $position)
                            <tr>
                                <td class="ps-3">{{ $position->id }}</td>
                                <td class="fw-semibold">{{ $position->name }}</td>
                                <td>{{ $position->code ?? '—' }}</td>
                                <td>{{ $position->description ?? '—' }}</td>
                                <td>{{ optional($position->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('hr.positions.edit', $position->id) }}" class="btn btn-sm btn-warning text-white">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form action="{{ route('hr.positions.destroy', $position->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xoá chức vụ này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    Chưa có chức vụ nào.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($positions->hasPages())
            <div class="card-footer bg-white border-0">
                {{ $positions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection