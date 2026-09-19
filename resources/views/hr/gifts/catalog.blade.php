@extends('layouts.app')
@section('title', 'Danh mục quà tặng')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">@endpush
@section('content')
<div class="gift-page">
    <div class="gift-page-head"><div><div class="gift-eyebrow">QUẢN LÝ QUÀ TẶNG</div><h1>Danh mục quà</h1><p>Quản lý SKU, loại quà, đơn vị tính, giá vốn và định mức tồn tối thiểu.</p></div><button class="gift-btn gift-btn--primary" data-bs-toggle="modal" data-bs-target="#giftCreateModal"><i class="bi bi-plus-lg"></i>Thêm quà</button></div>
    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')

    <section class="gift-card">
        <form class="gift-filter" method="get">
            <input class="gift-input" name="q" value="{{ request('q') }}" placeholder="Tìm SKU, tên quà, loại quà...">
            <select class="gift-select" name="status"><option value="">Tất cả trạng thái</option><option value="active" @selected(request('status')==='active')>Đang sử dụng</option><option value="inactive" @selected(request('status')==='inactive')>Ngừng sử dụng</option></select>
            <button class="gift-btn gift-btn--secondary"><i class="bi bi-search"></i>Lọc</button>
        </form>
        <div class="gift-table-wrap">
            <table class="gift-table">
                <thead><tr><th>SKU</th><th>Tên quà</th><th>Loại quà</th><th>ĐVT</th>@if($canSeeCost)<th class="text-end">Giá vốn</th>@endif<th class="text-end">Tồn hiện tại</th><th class="text-end">Tồn tối thiểu</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                @forelse($gifts as $gift)
                    @php($isLow = (float)$gift->current_stock < (float)$gift->minimum_stock)
                    <tr class="{{ $isLow ? 'gift-row-low' : '' }}">
                        <td><span class="gift-code">{{ $gift->sku }}</span></td>
                        <td><strong>{{ $gift->name }}</strong>@if($gift->notes)<small>{{ $gift->notes }}</small>@endif</td>
                        <td>{{ $gift->gift_type ?: '—' }}</td><td>{{ $gift->unit }}</td>
                        @if($canSeeCost)<td class="text-end">{{ number_format((float)$gift->cost_price, 0, ',', '.') }} đ</td>@endif
                        <td class="text-end"><strong class="{{ $isLow ? 'gift-text-danger' : '' }}">{{ number_format((float)$gift->current_stock, 3, ',', '.') }}</strong></td>
                        <td class="text-end">{{ number_format((float)$gift->minimum_stock, 3, ',', '.') }}</td>
                        <td><span class="gift-status gift-status--{{ $gift->is_active ? 'success' : 'muted' }}">{{ $gift->is_active ? 'Đang dùng' : 'Ngừng dùng' }}</span></td>
                        <td class="text-end"><button class="gift-icon-btn" type="button" data-bs-toggle="modal" data-bs-target="#giftEdit{{ $gift->id }}"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                @empty<tr><td colspan="9" class="gift-empty">Chưa có quà tặng trong danh mục.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="gift-pagination">{{ $gifts->links() }}</div>
    </section>
</div>

<div class="modal fade" id="giftCreateModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content gift-modal"><form method="post" action="{{ route('hr.gifts.catalog.store') }}">@csrf<div class="modal-header"><div><h5 class="modal-title">Thêm quà tặng</h5><small>Tồn đầu mặc định bằng 0, nhập kho bằng phiếu nhập.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="gift-form-grid"><label>Mã quà (SKU)<input class="gift-input" name="sku" required></label><label>Tên quà<input class="gift-input" name="name" required></label><label>Loại quà<input class="gift-input" name="gift_type" placeholder="Sinh nhật, tri ân..."></label><label>Đơn vị tính<input class="gift-input" name="unit" value="Cái" required></label><label>Giá vốn<input class="gift-input" type="number" min="0" step="0.01" name="cost_price" value="0"></label><label>Tồn tối thiểu<input class="gift-input" type="number" min="0" step="0.001" name="minimum_stock" value="0"></label><label class="gift-col-2">Ghi chú<textarea class="gift-textarea" name="notes"></textarea></label></div></div><div class="modal-footer"><button type="button" class="gift-btn gift-btn--light" data-bs-dismiss="modal">Đóng</button><button class="gift-btn gift-btn--primary">Lưu danh mục</button></div></form></div></div></div>

@foreach($gifts as $gift)
<div class="modal fade" id="giftEdit{{ $gift->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content gift-modal"><form method="post" action="{{ route('hr.gifts.catalog.update', $gift) }}">@csrf @method('put')<div class="modal-header"><h5 class="modal-title">Cập nhật {{ $gift->sku }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="gift-form-grid"><label>Mã quà<input class="gift-input" name="sku" value="{{ $gift->sku }}" required></label><label>Tên quà<input class="gift-input" name="name" value="{{ $gift->name }}" required></label><label>Loại quà<input class="gift-input" name="gift_type" value="{{ $gift->gift_type }}"></label><label>Đơn vị tính<input class="gift-input" name="unit" value="{{ $gift->unit }}" required></label><label>Giá vốn<input class="gift-input" type="number" min="0" step="0.01" name="cost_price" value="{{ $gift->cost_price }}"></label><label>Tồn tối thiểu<input class="gift-input" type="number" min="0" step="0.001" name="minimum_stock" value="{{ $gift->minimum_stock }}"></label><label class="gift-col-2">Ghi chú<textarea class="gift-textarea" name="notes">{{ $gift->notes }}</textarea></label><label class="gift-check gift-col-2"><input type="checkbox" name="is_active" value="1" @checked($gift->is_active)> Đang sử dụng</label></div></div><div class="modal-footer"><button type="button" class="gift-btn gift-btn--light" data-bs-dismiss="modal">Đóng</button><button class="gift-btn gift-btn--primary">Cập nhật</button></div></form></div></div></div>
@endforeach
@endsection
