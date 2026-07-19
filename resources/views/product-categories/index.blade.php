@extends('layouts.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary">Danh sách danh mục sản phẩm</h1>
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Thêm danh mục
            </a>
        </div>

        {{-- Hiển thị message --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">ID</th>
                                <th>Tên danh mục</th>
                                <th>Mô tả</th>
                                <th>Danh mục cha</th>
                                <th>Số sản phẩm</th>
                                <th>Số danh mục con</th>
                                <th width="180" class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td>
                                        <strong>{{ $category->name }}</strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            {{ Str::limit($category->description ?? 'Không có mô tả', 50) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($category->parent)
                                            <span class="badge bg-info">{{ $category->parent->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">{{ $category->products_count ?? $category->products()->count() }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $category->children_count ?? $category->children()->count() }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('categories.edit', $category->id) }}"
                                               class="btn btn-sm btn-outline-warning"
                                               title="Sửa">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </a>
                                            <form action="{{ route('categories.destroy', $category->id) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Xóa">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox"></i> Chưa có danh mục nào
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($categories->hasPages())
                    <div class="mt-3">
                        {{ $categories->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

