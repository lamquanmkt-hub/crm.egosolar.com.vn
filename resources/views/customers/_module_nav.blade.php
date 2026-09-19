@php
    $egoCustomerNavPipelineActive = request()->routeIs('customers.pipeline', 'sales.work-reports.*');
    $egoCustomerNavOverviewActive = request()->routeIs('customers.overview');
    $egoCustomerNavListActive = request()->routeIs('customers.index', 'customers.show', 'customers.edit');
@endphp

<style>
    .ego-customer-module-nav{
        display:flex;
        align-items:center;
        gap:6px;
        padding:6px;
        margin:0 0 12px;
        border:1px solid #e3ebf3;
        border-radius:14px;
        background:#fff;
        box-shadow:0 8px 22px rgba(15,23,42,.04);
        overflow-x:auto;
        scrollbar-width:none;
    }
    .ego-customer-module-nav::-webkit-scrollbar{display:none}
    .ego-customer-module-nav__item{
        display:inline-flex;
        align-items:center;
        gap:7px;
        min-height:36px;
        padding:0 13px;
        border-radius:10px;
        color:#516174;
        font-size:12px;
        font-weight:800;
        text-decoration:none;
        white-space:nowrap;
        transition:.18s ease;
    }
    .ego-customer-module-nav__item:hover{
        color:#0f766e;
        background:#f0fdfa;
    }
    .ego-customer-module-nav__item.is-active{
        color:#087f6d;
        background:linear-gradient(135deg,#dffbf4,#ecfeff);
        box-shadow:inset 0 0 0 1px #a7f3d0;
    }
    .ego-customer-module-nav__item i{font-size:14px}
    @media(max-width:767.98px){
        .ego-customer-module-nav{margin-bottom:9px;border-radius:12px}
        .ego-customer-module-nav__item{min-height:34px;padding:0 10px;font-size:11.5px}
    }
</style>

<nav class="ego-customer-module-nav" aria-label="Điều hướng Khách hàng">
    <a href="{{ route('customers.index') }}"
       class="ego-customer-module-nav__item {{ $egoCustomerNavListActive ? 'is-active' : '' }}">
        <i class="bi bi-people"></i>
        Danh sách khách hàng
    </a>

    <a href="{{ (Route::has('customers.pipeline') ? route('customers.pipeline') : route('customers.index')) }}"
       class="ego-customer-module-nav__item {{ $egoCustomerNavPipelineActive ? 'is-active' : '' }}">
        <i class="bi bi-kanban"></i>
        Chăm sóc &amp; Pipeline
    </a>

    <a href="{{ (Route::has('customers.overview') ? route('customers.overview') : route('customers.index')) }}"
       class="ego-customer-module-nav__item {{ $egoCustomerNavOverviewActive ? 'is-active' : '' }}">
        <i class="bi bi-bar-chart-line"></i>
        Tổng quan
    </a>
</nav>
