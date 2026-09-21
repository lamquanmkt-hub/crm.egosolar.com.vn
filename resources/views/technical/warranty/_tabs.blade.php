@php
    $wxU = auth()->user();
    $wxKho = \App\Support\SolarMaintenanceAccess::isWarehouse($wxU) || \App\Support\SolarMaintenanceAccess::isTechnicalLead($wxU);
    $wxKhoCount = $wxKho && \Illuminate\Support\Facades\Schema::hasTable('warranty_claim_notifications')
        ? \Illuminate\Support\Facades\DB::table('warranty_claim_notifications')->where('audience', 'warehouse')->where('is_read', false)->count() : 0;
@endphp
<nav class="wx2-tabs" aria-label="Bảo hành và sửa chữa">
    <a href="{{ route('ky-thuat.warranty-exchange.index') }}" class="{{ request()->routeIs('ky-thuat.warranty-exchange.index', 'ky-thuat.warranty-exchange.show') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i>Đổi hàng bảo hành</a>
    <a href="{{ route('ky-thuat.repair.index') }}" class="{{ request()->routeIs('ky-thuat.repair.*') ? 'active' : '' }}"><i class="bi bi-tools"></i>Sửa chữa tính phí</a>
    @if($wxKho)
        <a href="{{ route('ky-thuat.warranty-exchange.warehouse-queue') }}" class="{{ request()->routeIs('ky-thuat.warranty-exchange.warehouse-queue') ? 'active' : '' }}"><i class="bi bi-box-seam"></i>Việc của Kho @if($wxKhoCount)<span class="wx2-badge">{{ $wxKhoCount }}</span>@endif</a>
    @endif
    <a href="{{ route('projects-unified.maintenance.index', ['view' => 'claims']) }}"><i class="bi bi-shield-check"></i>Bảo trì / Bảo hành (chung)</a>
</nav>
