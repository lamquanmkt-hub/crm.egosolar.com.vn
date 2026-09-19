@extends('layouts.app')

@section('title', 'Quản lý quà tặng')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="gift-page">
    <div class="gift-hero">
        <div>
            <div class="gift-eyebrow">NHÂN SỰ · QUÀ TẶNG</div>
            <h1>Quản lý quà tặng</h1>
            <p>Rút gọn còn 3 khu vực chính: tổng quan, kho và xuất quà để thao tác nhanh, dễ nhìn hơn.</p>
        </div>
        <div class="gift-hero-actions">
            <a href="{{ route('hr.gifts.requests.create') }}" class="gift-btn gift-btn--primary"><i class="bi bi-plus-lg"></i>Tạo yêu cầu tặng</a>
            @if($canHandleStock)
                <a href="{{ route('hr.gifts.stock.index') }}" class="gift-btn gift-btn--light"><i class="bi bi-box-seam"></i>Vào kho quà</a>
            @endif
        </div>
    </div>

    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')

    <div class="gift-kpi-grid gift-kpi-grid--4">
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-gift"></i></span><div><small>Tổng loại quà</small><strong>{{ number_format($summary['gift_count']) }}</strong></div></div>
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-boxes"></i></span><div><small>Tồn kho hiện tại</small><strong>{{ number_format($summary['stock_quantity'], 3, ',', '.') }}</strong></div></div>
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-hourglass-split"></i></span><div><small>Yêu cầu chờ duyệt</small><strong>{{ number_format($summary['pending_requests']) }}</strong></div></div>
        <div class="gift-kpi gift-kpi--danger"><span class="gift-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span><div><small>Cảnh báo tồn thấp</small><strong>{{ number_format($summary['low_stock_count']) }}</strong></div></div>
    </div>

    <div class="gift-dashboard-grid">
        <section class="gift-card">
            <div class="gift-card-head">
                <div><h2>Giao dịch gần đây</h2><p>Hiển thị chung phiếu nhập kho và yêu cầu xuất quà.</p></div>
            </div>
            <div class="gift-table-wrap">
                <table class="gift-table">
                    <thead><tr><th>Mã giao dịch</th><th>Loại giao dịch</th><th>Khách hàng / Đối tượng</th><th>Số lượng</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
                    <tbody>
                    @forelse($recentTransactions as $row)
                        <tr>
                            <td><a class="gift-code" href="{{ $row['url'] }}">{{ $row['code'] }}</a></td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['target'] }}</td>
                            <td>{{ number_format((float) $row['quantity'], 3, ',', '.') }}</td>
                            <td>@include('hr.gifts.partials.status', ['status' => $row['status']])</td>
                            <td>{{ optional($row['created_at'])->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="gift-empty">Chưa có giao dịch quà tặng.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="gift-side-stack">
            @if($canHandleStock)
            <aside class="gift-card gift-card--alert">
                <div class="gift-card-head"><div><h2>Cảnh báo tồn thấp</h2><p>Quà cần nhập thêm sớm.</p></div><a href="{{ route('hr.gifts.stock.index') }}" class="gift-link">Mở kho <i class="bi bi-arrow-right"></i></a></div>
                <div class="gift-low-list">
                    @forelse($lowStockGifts as $gift)
                        <a href="{{ route('hr.gifts.catalog.index', ['q' => $gift->sku]) }}" class="gift-low-item">
                            <span><strong>{{ $gift->name }}</strong><small>{{ $gift->sku }} · {{ $gift->gift_type ?: 'Chưa phân loại' }}</small></span>
                            <em>{{ number_format((float)$gift->current_stock, 3, ',', '.') }}/{{ number_format((float)$gift->minimum_stock, 3, ',', '.') }}</em>
                        </a>
                    @empty
                        <div class="gift-empty">Tồn kho đang an toàn.</div>
                    @endforelse
                </div>
            </aside>
            @endif

            <aside class="gift-card">
                <div class="gift-card-head"><div><h2>Hoạt động gần đây</h2><p>Các cập nhật mới nhất của module quà tặng.</p></div></div>
                <div class="gift-low-list">
                    @forelse($recentActivities as $row)
                        <a href="{{ $row['url'] }}" class="gift-low-item">
                            <span><strong>{{ $row['type'] }}</strong><small>{{ $row['code'] }} · {{ $row['target'] }}</small></span>
                            <em>{{ optional($row['created_at'])->format('d/m H:i') }}</em>
                        </a>
                    @empty
                        <div class="gift-empty">Chưa có hoạt động gần đây.</div>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
