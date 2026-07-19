@extends('layouts.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-uppercase text-secondary">Chi tiết danh mục sản phẩm</h1>
            <div>
                <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Chỉnh sửa
                </a>
                <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Thông tin danh mục</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">ID:</dt>
                            <dd class="col-sm-9">{{ $category->id }}</dd>

                            <dt class="col-sm-3">Tên danh mục:</dt>
                            <dd class="col-sm-9"><strong>{{ $category->name }}</strong></dd>

                            <dt class="col-sm-3">Mô tả:</dt>
                            <dd class="col-sm-9">{{ $category->description ?? '—' }}</dd>

                            <dt class="col-sm-3">Danh mục cha:</dt>
                            <dd class="col-sm-9">
                                @if($category->parent)
                                    <span class="badge bg-info">{{ $category->parent->name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </dd>

                            <dt class="col-sm-3">Ngày tạo:</dt>
                            <dd class="col-sm-9">{{ $category->created_at->format('d/m/Y H:i') }}</dd>

                            <dt class="col-sm-3">Cập nhật lần cuối:</dt>
                            <dd class="col-sm-9">{{ $category->updated_at->format('d/m/Y H:i') }}</dd>
                        </dl>
                    </div>
                </div>

                @if($category->children->count() > 0)
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Danh mục con ({{ $category->children->count() }})</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                @foreach($category->children as $child)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="{{ route('categories.show', $child->id) }}">{{ $child->name }}</a>
                                        <span class="badge bg-primary rounded-pill">{{ $child->products()->count() }} sản phẩm</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Thống kê</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Số sản phẩm</h6>
                            <p class="h3 text-primary">{{ $category->products->count() }}</p>
                        </div>
                        <div class="mb-3">
                            <h6>Số danh mục con</h6>
                            <p class="h3 text-secondary">{{ $category->children->count() }}</p>
                        </div>
                    </div>
                </div>

                @if($category->products->count() > 0)
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Sản phẩm trong danh mục</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                @foreach($category->products->take(10) as $product)
                                    <li class="list-group-item">
                                        <a href="{{ route('products.edit', $product->id) }}">{{ $product->name }}</a>
                                    </li>
                                @endforeach
                                @if($category->products->count() > 10)
                                    <li class="list-group-item text-center">
                                        <small class="text-muted">... và {{ $category->products->count() - 10 }} sản phẩm khác</small>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

