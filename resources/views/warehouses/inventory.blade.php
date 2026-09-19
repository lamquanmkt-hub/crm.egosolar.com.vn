@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Tồn kho: {{ $warehouse->name }}</h3>
            <div class="text-muted small">Hiện toàn bộ sản phẩm đang hoạt động, kể cả tồn kho = 0 hoặc chưa từng phát sinh tồn tại kho này.</div>
        </div>
        <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">← Danh sách kho</a>
    </div>

    <form method="GET" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:320px;max-width:520px" class="flex-grow-1">
                <label class="form-label fw-semibold">Tìm sản phẩm</label>
                <input type="text" name="search" class="form-control" value="{{ $keyword ?? request('search') }}" placeholder="Tên sản phẩm hoặc SKU...">
            </div>
            <button class="btn btn-primary" type="submit">Tìm</button>
            @if(request()->filled('search'))
                <a href="{{ route('warehouses.inventory', $warehouse) }}" class="btn btn-outline-secondary">Xóa lọc</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:70px">STT</th>
                        <th>Sản phẩm</th>
                        <th style="width:180px">SKU</th>
                        <th class="text-center" style="width:150px">Tồn kho</th>
                        <th style="width:220px">Cập nhật tồn lần cuối</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($inventory as $product)
                    @php $qty = (int) ($product->warehouse_qty ?? 0); @endphp
                    <tr class="{{ $qty <= 0 ? 'table-light' : '' }}">
                        <td>{{ ($inventory->currentPage() - 1) * $inventory->perPage() + $loop->iteration }}</td>
                        <td>
                            <div class="fw-semibold">{{ $product->name }}</div>
                            @if($qty <= 0)
                                <span class="badge text-bg-secondary mt-1">Hết hàng</span>
                            @endif
                        </td>
                        <td>{{ $product->sku ?: '—' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $qty > 0 ? 'text-bg-success' : 'text-bg-danger' }} fs-6">{{ number_format($qty) }}</span>
                        </td>
                        <td>{{ $product->last_stock_updated ?: 'Chưa phát sinh tồn' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Không tìm thấy sản phẩm.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2 d-flex justify-content-end">
            {{ $inventory->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
