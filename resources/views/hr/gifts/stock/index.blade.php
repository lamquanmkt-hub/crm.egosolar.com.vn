@extends('layouts.app')
@section('title', 'Kho quà tặng')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="gift-page">
    <div class="gift-page-head">
        <div>
            <div class="gift-eyebrow">QUẢN LÝ QUÀ TẶNG</div>
            <h1>Kho quà tặng</h1>
            <p>Quản lý tồn kho quà tặng gọn hơn. Khi nhập kho chỉ cần gõ tên quà, hệ thống tự tạo nếu chưa có.</p>
        </div>
        <div class="gift-hero-actions">
            <a href="{{ route('hr.gifts.receipts.index') }}#tao-phieu" class="gift-btn gift-btn--primary"><i class="bi bi-box-arrow-in-down"></i>Tạo phiếu nhập</a>
            <a href="{{ route('hr.gifts.reports.index') }}" class="gift-btn gift-btn--light"><i class="bi bi-bar-chart-line"></i>Báo cáo tồn</a>
        </div>
    </div>

    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')

    <div class="gift-kpi-grid gift-kpi-grid--4">
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-gift"></i></span><div><small>Tổng loại quà</small><strong>{{ number_format($summary['gift_count']) }}</strong></div></div>
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-box-seam"></i></span><div><small>Tồn kho hiện tại</small><strong>{{ number_format($summary['stock_quantity'], 3, ',', '.') }}</strong></div></div>
        <div class="gift-kpi"><span class="gift-kpi-icon"><i class="bi bi-hourglass-split"></i></span><div><small>Phiếu nhập chờ duyệt</small><strong>{{ number_format($summary['pending_receipts']) }}</strong></div></div>
        <div class="gift-kpi gift-kpi--danger"><span class="gift-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span><div><small>Cảnh báo tồn thấp</small><strong>{{ number_format($summary['low_stock_count']) }}</strong></div></div>
    </div>

    <div class="gift-dashboard-grid">
        <section class="gift-card">
            <div class="gift-card-head">
                <div>
                    <h2>Quà trong kho</h2>
                    <p>Danh sách quà đang có trong hệ thống và số lượng tồn hiện tại.</p>
                </div>
            </div>

            <form class="gift-filter" method="get">
                <input class="gift-input" name="q" value="{{ request('q') }}" placeholder="Tìm tên quà, SKU, loại quà...">
                <button class="gift-btn gift-btn--secondary"><i class="bi bi-search"></i>Tìm</button>
            </form>

            <div class="gift-table-wrap">
                <table class="gift-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Tên quà</th>
                            <th>Loại</th>
                            <th>ĐVT</th>
                            @if($canSeeCost)
                                <th class="text-end">Giá vốn</th>
                            @endif
                            <th class="text-end">Tồn hiện tại</th>
                            <th class="text-end">Tồn tối thiểu</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($gifts as $gift)
                        <tr class="{{ (float)$gift->current_stock < (float)$gift->minimum_stock ? 'gift-row-low' : '' }}">
                            <td><span class="gift-code">{{ $gift->sku }}</span></td>
                            <td><strong>{{ $gift->name }}</strong><small>{{ $gift->is_active ? 'Đang sử dụng' : 'Ngừng sử dụng' }}</small></td>
                            <td>{{ $gift->gift_type ?: '—' }}</td>
                            <td>{{ $gift->unit }}</td>
                            @if($canSeeCost)
                                <td class="text-end">{{ number_format((float)$gift->cost_price, 0, ',', '.') }}</td>
                            @endif
                            <td class="text-end {{ (float)$gift->current_stock < (float)$gift->minimum_stock ? 'gift-text-danger' : '' }}">
                                {{ number_format((float)$gift->current_stock, 3, ',', '.') }}
                            </td>
                            <td class="text-end">{{ number_format((float)$gift->minimum_stock, 3, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canSeeCost ? 7 : 6 }}" class="gift-empty">Chưa có quà tặng trong kho.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="gift-pagination">{{ $gifts->links() }}</div>
        </section>

        <aside class="gift-card gift-card--alert">
            <div class="gift-card-head">
                <div>
                    <h2>Cảnh báo tồn thấp</h2>
                    <p>Những quà đang thấp hơn định mức tồn tối thiểu.</p>
                </div>
                <a href="{{ route('hr.gifts.reports.index', ['low_stock' => '1']) }}" class="gift-link">Xem tất cả <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="gift-low-list">
                @forelse($lowStockGifts as $gift)
                    <a href="{{ route('hr.gifts.stock.index', ['q' => $gift->sku]) }}" class="gift-low-item">
                        <span>
                            <strong>{{ $gift->name }}</strong>
                            <small>{{ $gift->sku }} · {{ $gift->gift_type ?: 'Chưa phân loại' }}</small>
                        </span>
                        <em>{{ number_format((float)$gift->current_stock, 3, ',', '.') }}/{{ number_format((float)$gift->minimum_stock, 3, ',', '.') }} {{ $gift->unit }}</em>
                    </a>
                @empty
                    <div class="gift-empty">Tồn kho đang an toàn.</div>
                @endforelse
            </div>
        </aside>
    </div>

    <section class="gift-card mt-3">
        <div class="gift-card-head">
            <div>
                <h2>Phiếu nhập gần đây</h2>
                <p>Theo dõi tiến độ tạo phiếu, gửi duyệt và nhập kho.</p>
            </div>
            <a href="{{ route('hr.gifts.receipts.index') }}" class="gift-link">Xem tất cả <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="gift-table-wrap">
            <table class="gift-table">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Ngày nhập</th>
                        <th>Nhà cung cấp</th>
                        <th>Số dòng</th>
                        <th>Người tạo</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td><a class="gift-code" href="{{ route('hr.gifts.receipts.show', $receipt) }}">{{ $receipt->code }}</a><small>{{ optional($receipt->created_at)->format('d/m/Y H:i') }}</small></td>
                        <td>{{ optional($receipt->receipt_date)->format('d/m/Y') }}</td>
                        <td>{{ $receipt->supplier_name ?: '—' }}</td>
                        <td>{{ $receipt->items_count }}</td>
                        <td>{{ $receipt->creator->name ?? '—' }}</td>
                        <td>@include('hr.gifts.partials.status', ['status' => $receipt->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="gift-empty">Chưa có phiếu nhập quà tặng.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
