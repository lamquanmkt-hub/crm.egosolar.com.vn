@php
    $giftCanStock = \App\Support\GiftAccess::canHandleStock(auth()->user());
    $giftStockActive = request()->routeIs('hr.gifts.stock.*') || request()->routeIs('hr.gifts.catalog.*') || request()->routeIs('hr.gifts.receipts.*') || request()->routeIs('hr.gifts.reports.*');
@endphp
<nav class="gift-tabs" aria-label="Điều hướng quản lý quà tặng">
    <a href="{{ route('hr.gifts.index') }}" class="gift-tab {{ request()->routeIs('hr.gifts.index') ? 'active' : '' }}">
        <i class="bi bi-grid-1x2"></i><span>Tổng quan</span>
    </a>
    @if($giftCanStock)
        <a href="{{ route('hr.gifts.stock.index') }}" class="gift-tab {{ $giftStockActive ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i><span>Kho</span>
        </a>
    @endif
    <a href="{{ route('hr.gifts.requests.index') }}" class="gift-tab {{ request()->routeIs('hr.gifts.requests.*') ? 'active' : '' }}">
        <i class="bi bi-send-check"></i><span>Xuất quà</span>
    </a>
</nav>
