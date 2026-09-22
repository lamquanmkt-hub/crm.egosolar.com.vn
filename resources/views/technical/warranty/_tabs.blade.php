{{-- Tab "Việc của Kho" đã bỏ khỏi module Kỹ thuật — nghiệp vụ Kho nay ở trang riêng Kho → Xuất hàng BH/SC. --}}
<nav class="wx2-tabs" aria-label="Bảo hành và sửa chữa">
    <a href="{{ route('ky-thuat.warranty-exchange.index') }}" class="{{ request()->routeIs('ky-thuat.warranty-exchange.index', 'ky-thuat.warranty-exchange.show') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i>Đổi hàng bảo hành</a>
    <a href="{{ route('ky-thuat.repair.index') }}" class="{{ request()->routeIs('ky-thuat.repair.*') ? 'active' : '' }}"><i class="bi bi-tools"></i>Sửa chữa tính phí</a>
    <a href="{{ route('projects-unified.maintenance.index', ['view' => 'claims']) }}"><i class="bi bi-shield-check"></i>Bảo trì / Bảo hành (chung)</a>
</nav>
