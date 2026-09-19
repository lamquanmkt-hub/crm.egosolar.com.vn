<style>
/* EGO_HR_MENU_HIERARCHY_V4_CSS */
#menuNhanSu.ego-hr-v4-root{gap:3px!important}
#menuNhanSu .ego-hr-v4-group{list-style:none!important;margin:0!important;padding:0!important}
#menuNhanSu .ego-hr-v4-details{margin:0!important;padding:0!important}
#menuNhanSu .ego-hr-v4-summary{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important;cursor:pointer!important;list-style:none!important}
#menuNhanSu .ego-hr-v4-summary::-webkit-details-marker{display:none!important}
#menuNhanSu .ego-hr-v4-summary::marker{display:none!important;content:""!important}
#menuNhanSu .ego-hr-v4-chevron{font-size:11px!important;transition:transform .16s ease!important}
#menuNhanSu details[open]>.ego-hr-v4-summary .ego-hr-v4-chevron{transform:rotate(180deg)!important}
#menuNhanSu .ego-hr-v4-children{display:none!important;list-style:none!important;margin:3px 0 5px 10px!important;padding:2px 0 2px 8px!important;border-left:1px solid rgba(148,163,184,.20)!important}
#menuNhanSu details[open]>.ego-hr-v4-children{display:flex!important;flex-direction:column!important;gap:2px!important}
#menuNhanSu .ego-hr-v4-children .ego-sublink{font-size:12px!important;line-height:1.25!important;padding:7px 9px!important;min-height:30px!important}
/* Quan trọng: flyout/panel là DOM clone, details native vẫn tự đóng/mở, không phụ thuộc ID Bootstrap. */
.ego-flyout .ego-hr-v4-children{display:none!important}
.ego-flyout details[open]>.ego-hr-v4-children{display:flex!important;flex-direction:column!important;gap:2px!important}
.ego-flyout .ego-hr-v4-summary{display:flex!important;align-items:center!important;justify-content:space-between!important;cursor:pointer!important;list-style:none!important}
.ego-flyout .ego-hr-v4-summary::-webkit-details-marker{display:none!important}
.ego-flyout details[open]>.ego-hr-v4-summary .ego-hr-v4-chevron{transform:rotate(180deg)!important}
</style>
<style>
/* EGO_REAL_VN_MENU_COMPAT_CSS_V2 */
#sidebar .ego-item--nested>.ego-sublink--toggle{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important}
#sidebar .ego-sub--nested{margin-left:10px!important;padding-left:8px!important;border-left:1px solid rgba(148,163,184,.18)!important}
#sidebar .ego-menu-group-label{display:block;padding:8px 12px 4px;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;opacity:.65}
#sidebar .ego-menu-soon{margin-left:auto;font-size:9px;font-style:normal;opacity:.6}
</style>
<style>
/* EGO_MENU_SYNC_VN_STYLE_CSS_20260907 */
#sidebar .ego-item--nested > .ego-sublink--toggle{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important}
#sidebar .ego-sub--nested{margin-left:10px!important;padding-left:8px!important;border-left:1px solid rgba(148,163,184,.18)!important}
#sidebar .ego-sub--nested .ego-sublink{font-size:12px!important}
</style>

<style>
/* EGO_BOOKING_NEW_BADGE_START */
.ego-booking-new-badge{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    min-width:34px !important;
    height:18px !important;
    margin-left:auto !important;
    padding:0 8px !important;
    border-radius:999px !important;
    background:linear-gradient(135deg,#ff4d4f 0%,#ff1f1f 42%,#b00000 100%) !important;
    color:#ffffff !important;
    font-size:9px !important;
    font-weight:950 !important;
    letter-spacing:.65px !important;
    line-height:18px !important;
    text-transform:uppercase !important;
    border:1px solid rgba(255,255,255,.35) !important;
    box-shadow:
        0 0 0 1px rgba(255,255,255,.12) inset,
        0 6px 14px rgba(255,31,31,.38),
        0 0 18px rgba(255,31,31,.65) !important;
    animation:egoBookingNewPulse 1.25s ease-in-out infinite !important;
}

.ego-link:hover .ego-booking-new-badge,
.ego-link.active .ego-booking-new-badge{
    background:linear-gradient(135deg,#ff6b6b 0%,#ff2020 45%,#c40000 100%) !important;
    box-shadow:
        0 0 0 1px rgba(255,255,255,.18) inset,
        0 8px 18px rgba(255,31,31,.52),
        0 0 24px rgba(255,31,31,.88) !important;
}

.ego-sidebar.ego-collapsed .ego-booking-new-badge{
    display:none !important;
}

@keyframes egoBookingNewPulse{
    0%,100%{
        transform:scale(1);
        filter:brightness(1);
    }
    50%{
        transform:scale(1.08);
        filter:brightness(1.18);
    }
}
/* EGO_BOOKING_NEW_BADGE_END */
</style>


<style>
/* EGO_SALES_REPORT_MENU_STYLE_START */
.ego-sales-child-link{
    position: relative;
    display: flex !important;
    align-items: center;
    gap: 9px;
    margin: 4px 10px 4px 44px;
    padding: 9px 12px;
    border-radius: 12px;
    color: rgba(255,255,255,.82) !important;
    text-decoration: none !important;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.2;
    transition: all .18s ease;
}
.ego-sales-child-link:hover{
    color: #ffffff !important;
    background: rgba(20,184,166,.12);
    transform: translateX(2px);
}
.ego-sales-child-link.active{
    color: #ffffff !important;
    background: linear-gradient(135deg, rgba(20,184,166,.22), rgba(14,116,144,.16));
    box-shadow: inset 0 0 0 1px rgba(34,211,238,.18);
}
.ego-sales-child-dot{
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: rgba(148,163,184,.75);
    box-shadow: 0 0 0 3px rgba(148,163,184,.08);
    flex: 0 0 auto;
}
.ego-sales-child-link.active .ego-sales-child-dot,
.ego-sales-child-link:hover .ego-sales-child-dot{
    background: #22d3ee;
    box-shadow: 0 0 0 4px rgba(34,211,238,.13), 0 0 16px rgba(34,211,238,.45);
}
/* EGO_SALES_REPORT_MENU_STYLE_END */
</style>


<style>
    .top-nav-link {
        font-size: 14px;
    }

    .top-nav-link.active {
        color: #0ea5e9 !important;
    }
</style>



<style>
/* EGO_TECHNICAL_WORKSPACE_V2_MENU_START */
.ego-tech-menu-index{
    display:inline-grid;place-items:center;width:22px;height:22px;flex:0 0 22px;
    border-radius:8px;background:rgba(249,115,22,.14);color:#fdba74;
    font-size:10px;font-weight:950;border:1px solid rgba(251,146,60,.16)
}
.ego-link.active .ego-tech-menu-index,.ego-link:hover .ego-tech-menu-index{
    background:rgba(255,255,255,.16);color:#fff;border-color:rgba(255,255,255,.18)
}
.ego-tech-group-label{font-weight:900!important;letter-spacing:-.01em}
.ego-tech-menu .ego-sub{padding-top:4px;padding-bottom:8px}
.ego-tech-menu .ego-sublink{position:relative;padding-left:48px!important;font-size:12.5px!important}
.ego-tech-menu .ego-sublink:before{
    content:"";position:absolute;left:31px;top:50%;width:6px;height:6px;border-radius:999px;
    transform:translateY(-50%);background:rgba(148,163,184,.55)
}
.ego-tech-menu .ego-sublink.active:before,.ego-tech-menu .ego-sublink:hover:before{
    background:#fb923c;box-shadow:0 0 0 4px rgba(251,146,60,.12)
}
.ego-tech-menu .ego-count-badge{margin-left:auto}
.ego-sidebar.ego-collapsed .ego-tech-menu-index{display:none}
/* EGO_TECHNICAL_WORKSPACE_V2_MENU_END */
</style>

@php
    /* EGO_SIDEBAR_CACHED_SUMMARY_V3 */
    $egoStatusNow = now();
    $egoSidebarSummary = \App\Support\EgoSidebarSummary::data(auth()->user());

    $egoOnlineCount = (int) ($egoSidebarSummary['online'] ?? 0);
    $egoWorkingToday = (int) ($egoSidebarSummary['working'] ?? 0);
    $egoActiveEmployees = (int) ($egoSidebarSummary['employees'] ?? 0);
    $egoPendingOrdersCount = (int) ($egoSidebarSummary['pending_orders'] ?? 0);
    $egoPendingMaterialRequestsCount = (int) ($egoSidebarSummary['pending_material_requests'] ?? 0);
    $egoPendingPaymentRequestsCount = (int) ($egoSidebarSummary['pending_payment_requests'] ?? 0);
    $egoPendingProposalsCount = (int) ($egoSidebarSummary['pending_proposals'] ?? 0);
    $egoMyUnfinishedTasksCount = (int) ($egoSidebarSummary['unfinished_tasks'] ?? 0);

    $egoStatusItems = [
        [
            'key' => 'online',
            'label' => 'Đang online',
            'value' => $egoOnlineCount,
            'icon' => 'bi-wifi',
            'tone' => 'online',
        ],
        [
            'key' => 'working',
            'label' => 'Đang làm việc',
            'value' => $egoWorkingToday,
            'icon' => 'bi-person-check',
            'tone' => 'work',
        ],
        [
            'key' => 'employees',
            'label' => 'Nhân viên hoạt động',
            'value' => $egoActiveEmployees,
            'icon' => 'bi-people',
            'tone' => 'people',
        ],
    ];
@endphp

{{-- EGO_ROLE_MENU_MATRIX_V2 --}}
@php
    $egoSidebarUser = auth()->user();
    $egoMenuAccessService = app(\App\Services\RolePermission\PageAccessService::class);
    $egoCanSeeMenu = static fn (string $permission): bool =>
        $egoSidebarUser
        && $egoMenuAccessService->canSeeMenu($egoSidebarUser, $permission);

    $egoSidebarIsAdmin = $egoSidebarUser && $egoMenuAccessService->isAdmin($egoSidebarUser);
    $egoSidebarIsManagement = $egoSidebarUser && $egoSidebarUser->hasRole('management');
    $egoSidebarIsExecutive = $egoSidebarIsAdmin || $egoSidebarIsManagement;
    $egoActiveWorkspace = $egoSidebarUser
        ? app(\App\Services\Workspace\WorkspaceContextService::class)->current($egoSidebarUser)
        : 'general';
    $egoTechnicalWorkspace = $egoActiveWorkspace === 'technical';
    $egoWorkspaceLevelService = app(\App\Services\Workspace\WorkspaceLevelService::class);
    $egoAccessLevel = $egoSidebarUser ? $egoWorkspaceLevelService->resolve($egoSidebarUser) : 'staff';
    $egoDataScope = $egoSidebarUser ? $egoWorkspaceLevelService->scope($egoSidebarUser) : 'own';
    $egoSidebarIsDepartmentHead = $egoSidebarUser
        ? $egoWorkspaceLevelService->isDepartmentManager($egoSidebarUser)
        : false;

    $egoCanDashboardMenu = $egoCanSeeMenu('menu.dashboard');
    $egoCanBookingMenu = $egoCanSeeMenu('menu.booking');
    $egoCanCustomerMenu = $egoCanSeeMenu('menu.customers');
    $egoCanConsignmentsMenu = $egoCanSeeMenu('menu.consignments');
    $egoCanOrdersMenu = $egoCanSeeMenu('menu.orders');
    $egoCanProjectTestMenu = $egoCanSeeMenu('menu.project_test');
    $egoCanConstructionMenu = $egoCanSeeMenu('menu.sites')
        || ($egoSidebarUser && method_exists($egoSidebarUser, 'hasAnyRole')
            && $egoSidebarUser->hasAnyRole(['sales', 'sales_manager', 'sales_staff']));
    $egoCanPaymentRequestsMenu = true; // DNTT là ứng dụng dùng chung cho mọi role
    $egoCanProposalsMenu = $egoCanSeeMenu('menu.proposals');
    $egoCanTechnicalMenu = $egoCanSeeMenu('menu.technical');
    $egoCanSalesMenu = $egoCanSeeMenu('menu.sales');
    $egoCanMarketingMenu = $egoCanSeeMenu('menu.marketing');
    $egoCanTasksMenu = $egoCanSeeMenu('menu.tasks');
    $egoCanProductMenuByRole = $egoCanSeeMenu('menu.products');
    $egoCanFinanceMenu = $egoCanSeeMenu('menu.finance');
    $egoCanCompanyMenu = $egoCanSeeMenu('menu.company');
    $egoCanHrMenu = $egoCanSeeMenu('menu.hr');
    $egoCanSettingsMenu = $egoCanSeeMenu('menu.settings');

    /* Biến tương thích cho các submenu cũ. */
    $egoCanSiteWorkspaceMenu = $egoCanConstructionMenu;
    $egoCanOrderSettingsMenu = $egoCanOrdersMenu && (
        $egoSidebarIsExecutive || $egoSidebarUser->hasRole('accounting')
    );
    $egoCanWarrantyLookupMenu = $egoSidebarUser && (
        $egoSidebarIsExecutive
        || $egoSidebarUser->hasAnyRole([
            'sales', 'sales_manager', 'accounting', 'warehouse',
            'ky_thuat', 'technical_manager',
        ])
    );
@endphp
{{-- EGO_ROLE_MENU_MATRIX_V2_END --}}

{{-- ============================================================================
|  FILE: resources/views/layouts/partials/sidebar.blade.php
|  EGO SOLAR CRM - Modern Sidebar (Desktop collapsed + Flyout submenu + Mobile offcanvas)
|  ✅ Synced with TOPBAR variable: --ego-topbar-h
|  ✅ Works with FLEX layout (sidebar + content)
============================================================================ --}}

@includeIf('admin.settings.partials.menu-guard')

<nav id="sidebar" class="ego-sidebar" aria-label="Main sidebar" data-ego-workspace="{{ $egoActiveWorkspace }}" data-ego-access-level="{{ $egoAccessLevel }}" data-ego-data-scope="{{ $egoDataScope }}">
    {{-- ===== HEADER / BRAND ===== --}}

    <div class="ego-sidebar__header">
        {{-- LOGO BIG (replace text) --}}
        <a href="{{ route('dashboard') }}" class="ego-brand ego-brand--logoonly" aria-label="EGO SOLAR">
            <img src="{{ asset('logo/ego-solar-white.png') }}"
                 alt="EGO SOLAR"
                 class="ego-brand__logo-big">
        </a>

        {{-- Toggle: Desktop => collapsed/expand | Mobile => open/close offcanvas --}}
        <button id="toggleSidebar"
                class="ego-toggle"
                type="button"
                aria-label="Toggle sidebar"
                title="Thu gọn / Mở rộng">
            {{-- icon will be injected by JS --}}
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    {{-- ===== NAV ===== --}}
    <div class="ego-sidebar__scroll">
        <ul class="ego-nav" role="list">

            @auth
            {{-- EGO_WORKSPACE_EXCEL_MENU_V3_START --}}
            @php
                $egoExcelSidebarUser = auth()->user();
                $egoExcelSidebarWorkspace = $egoExcelSidebarUser
                    ? app(\App\Services\Workspace\WorkspaceContextService::class)->current($egoExcelSidebarUser)
                    : 'general';
                $egoExcelSidebarEnabled = in_array(
                    $egoExcelSidebarWorkspace,
                    (array) config('ego_menu_v4.supported_workspaces', config('ego_menu_excel.supported_workspaces', [])),
                    true
                );
            @endphp
            @if($egoExcelSidebarEnabled)
                <script>document.addEventListener('DOMContentLoaded',function(){var n=document.querySelector('#sidebar .ego-nav');if(n){n.classList.add('ego-nav--excel-v3')}});</script>
                @include('partials.workspace-menu-excel-v3')
            @endif
            {{-- EGO_WORKSPACE_EXCEL_MENU_V3_END --}}
{{-- DASHBOARD --}}
            <li class="ego-item" data-title="Trang chủ" data-ego-menu-permission="menu.dashboard">
                <a href="{{ route('dashboard') }}"
                   class="ego-link {{ active_route('dashboard') }}"
                   data-ego-type="nav">
                    <span class="ego-ic"><i class="bi bi-house-door"></i></span>
                    <span class="ego-txt">Trang chủ</span>
                </a>
            </li>
{{-- EGO_AI_COPILOT_MENU_START --}}
@can('ai.use')
<li class="ego-item" data-title="EGO AI Copilot">
    <a href="{{ route('ai.index') }}"
       class="ego-link {{ request()->routeIs('ai.*') ? 'active' : '' }}"
       data-ego-type="nav">
        <span class="ego-ic"><i class="bi bi-stars"></i></span>
        <span class="ego-txt">EGO AI Copilot</span>
        <span class="ego-booking-new-badge">AI</span>
    </a>
</li>
@endcan
{{-- EGO_AI_COPILOT_MENU_END --}}
{{-- EGO_BOOKING_ROOM_MENU_START --}}
<li class="ego-item" data-title="Booking phòng họp" data-ego-menu-permission="menu.booking">
                <a href="{{ route('meeting-room-bookings.index') }}"
                   class="ego-link {{ request()->routeIs('meeting-room-bookings.*') || request()->is('booking-phong-hop*') ? 'active' : '' }}"
                   data-ego-type="nav">
                    <span class="ego-ic"><i class="bi bi-calendar2-check"></i></span>
                    <span class="ego-txt">Booking phòng họp</span><span class="ego-booking-new-badge">NEW</span>
                </a>
            </li>
{{-- EGO_BOOKING_ROOM_MENU_END --}}


            {{-- KHÁCH HÀNG --}}
            @php
                $egoCustomerMenuUser = auth()->user();
                $egoCustomerMenuRoleText = strtolower(implode(' ', array_filter([
                    $egoCustomerMenuUser->role ?? null,
                    $egoCustomerMenuUser->role_name ?? null,
                    $egoCustomerMenuUser->department ?? null,
                    $egoCustomerMenuUser->position ?? null,
                    $egoCustomerMenuUser->type ?? null,
                ])));

                $egoCustomerMenuIsKho = $egoCustomerMenuUser && (
                    (method_exists($egoCustomerMenuUser, 'hasAnyRole') && $egoCustomerMenuUser->hasAnyRole(['kho', 'warehouse', 'admin']))
                    || (method_exists($egoCustomerMenuUser, 'hasRole') && (
                        $egoCustomerMenuUser->hasRole('kho')
                        || $egoCustomerMenuUser->hasRole('warehouse')
                        || $egoCustomerMenuUser->hasRole('admin')
                    ))
                    || str_contains($egoCustomerMenuRoleText, 'kho')
                    || str_contains($egoCustomerMenuRoleText, 'warehouse')
                    || str_contains($egoCustomerMenuRoleText, 'admin')
                );
                $canCustomerMenu = $egoCanCustomerMenu;
            @endphp

            @if($canCustomerMenu)
                <li class="ego-item ego-item--has-sub" data-title="Khách hàng" data-ego-sub="true" data-ego-menu-permission="menu.customers">
                    <a href="#menuKhachHang"
                       class="ego-link {{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') || request()->routeIs('sales.work-reports.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') || request()->routeIs('sales.work-reports.*') ? 'true' : 'false' }}"
                       aria-controls="menuKhachHang">
                        <span class="ego-ic"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="ego-txt">Khách hàng</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuKhachHang"
                        class="ego-sub collapse {{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') || request()->routeIs('sales.work-reports.*') ? 'show' : '' }}"
                        data-ego-submenu>
                        <li>
                            <a href="{{ route('customers.index') }}"
                               class="ego-sublink {{ request()->routeIs('customers.index', 'customers.show', 'customers.edit') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Danh sách KH
                            </a>
                        </li>

                        @if(\Illuminate\Support\Facades\Route::has('customers.pipeline'))
                            <li>
                                <a href="{{ route('customers.pipeline') }}"
                                   class="ego-sublink {{ request()->routeIs('customers.pipeline', 'sales.work-reports.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Chăm sóc &amp; Pipeline
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('customers.overview'))
                            <li>
                                <a href="{{ route('customers.overview') }}"
                                   class="ego-sublink {{ request()->routeIs('customers.overview') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Tổng quan khách hàng
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('customer-profiles.index'))
                            <li>
                                <a href="{{ route('customer-profiles.index') }}"
                                   class="ego-sublink {{ request()->routeIs('customer-profiles.index') || request()->is('customer-profiles') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Danh sách đại lý
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('customer-profiles.shipping.index'))
                            <li>
                                <a href="{{ route('customer-profiles.shipping.index') }}"
                                   class="ego-sublink {{ request()->routeIs('customer-profiles.shipping.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Danh sách vận chuyển
                                </a>
                            </li>
                        @endif

                        @can('customer.create')
                            <li>
                                <a href="#"
                                   class="ego-sublink"
                                   onclick="openCustomerForm(); return false;"
                                   data-ego-type="action">
                                    Thêm mới
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endif


            {{-- EGO_CUSTOMER_CONSIGNMENT_MENU_START --}}
            @if($egoCanConsignmentsMenu)
                <li class="ego-item" data-title="Ký gửi hàng hóa" data-ego-menu-permission="menu.consignments">
                    <a href="{{ route('customer-consignments.index') }}"
                       class="ego-link {{ request()->routeIs('customer-consignments.*') ? 'active' : '' }}"
                       data-ego-type="nav">
                        <span class="ego-ic"><i class="bi bi-box-seam"></i></span>
                        <span class="ego-txt">Ký gửi hàng hóa</span>
                    </a>
                </li>
            @endif
            {{-- EGO_CUSTOMER_CONSIGNMENT_MENU_END --}}
            {{-- ĐƠN HÀNG --}}
            @if($egoCanOrdersMenu)
            <li class="ego-item ego-item--has-sub" data-title="Đơn hàng" data-ego-sub="true" data-ego-menu-permission="menu.orders">
                <a href="#menuOrders"
                   class="ego-link {{ request()->routeIs('orders.*') || request()->routeIs('serial-warranty.*') ? 'active' : '' }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ request()->routeIs('orders.*') || request()->routeIs('serial-warranty.*') ? 'true' : 'false' }}"
                   aria-controls="menuOrders">
                    <span class="ego-ic"><i class="bi bi-receipt-cutoff"></i></span>
                    <span class="ego-txt">Đơn hàng</span>
                    <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                </a>

                <ul id="menuOrders"
                    class="ego-sub collapse {{ (request()->routeIs('orders.*') || request()->routeIs('serial-warranty.*')) ? 'show' : '' }}"
                    data-ego-submenu>
                    <li>
                        <a href="{{ route('orders.index') }}"
                           class="ego-sublink {{ active_route('orders.index') }}"
                           data-ego-type="nav">
                            Danh sách
                            @if(($egoPendingOrdersCount ?? 0) > 0)<span class="ego-count-badge" title="Đơn hàng chờ duyệt">{{ $egoPendingOrdersCount }}</span>@endif
                        </a>
                    </li>

                    @can('create', \App\Models\CRM\Order::class)
                        <li>
                            <a href="{{ route('orders.create') }}"
                               class="ego-sublink {{ active_route('orders.create') }}"
                               data-ego-type="nav">
                                Thêm mới
                            </a>
                        </li>
                    @endcan

                    


                    


                    @php
                            /* EGO_SERIAL_WARRANTY_MENU_ACCESS_START */
                            $egoSerialWarrantyUser = auth()->user();
                            $egoSerialWarrantyWarehouseMenu = false;

                            if ($egoSerialWarrantyUser) {
                                if (method_exists($egoSerialWarrantyUser, 'hasAnyRole')) {
                                    $egoSerialWarrantyWarehouseMenu = $egoSerialWarrantyUser->hasAnyRole(['admin', 'warehouse', 'kho']);
                                } elseif (method_exists($egoSerialWarrantyUser, 'hasRole')) {
                                    $egoSerialWarrantyWarehouseMenu =
                                        $egoSerialWarrantyUser->hasRole('admin') ||
                                        $egoSerialWarrantyUser->hasRole('warehouse') ||
                                        $egoSerialWarrantyUser->hasRole('kho');
                                } else {
                                    $egoSerialWarrantyWarehouseMenu = in_array(
                                        strtolower((string) ($egoSerialWarrantyUser->role ?? '')),
                                        ['admin', 'warehouse', 'kho'],
                                        true
                                    );
                                }
                            }
                            /* EGO_SERIAL_WARRANTY_MENU_ACCESS_END */
                        @endphp
                        @if((($egoCanWarrantyLookupMenu ?? false) || $egoSerialWarrantyWarehouseMenu) && \Illuminate\Support\Facades\Route::has('serial-warranty.index'))
                        <li>
                            <a href="{{ route('serial-warranty.index') }}"
                               class="ego-sublink {{ request()->routeIs('serial-warranty.*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Tra cứu Seri bảo hành
                            </a>
                        </li>
                    @endif
                </ul>
            </li>
            @endif
            {{-- EGO_PRIMARY_PROJECT_MENU_START --}}
{{-- CÔNG TRÌNH --}}
@if($egoCanConstructionMenu)
    @php
        $egoProjectMenuOpen = (request()->routeIs('projects-unified.*')
            && !request()->routeIs('projects-unified.maintenance.*'))
            || request()->routeIs('material-requests.*');
        $egoMyProjects = request()->routeIs('projects-unified.index')
            && request()->boolean('mine');
    @endphp
    <li class="ego-item ego-item--has-sub" data-title="Công trình" data-ego-sub="true" data-ego-menu-permission="menu.sites">
        <a href="#menuConstruction" class="ego-link {{ $egoProjectMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoProjectMenuOpen ? 'true' : 'false' }}" aria-controls="menuConstruction">
            <span class="ego-ic"><i class="bi bi-kanban"></i></span>
            <span class="ego-txt">Công trình</span>
            <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
        </a>
        <ul id="menuConstruction" class="ego-sub collapse {{ $egoProjectMenuOpen ? 'show' : '' }}" data-ego-submenu>
            <li><a href="{{ route('projects-unified.index') }}" class="ego-sublink {{ request()->routeIs('projects-unified.index') && !request()->query() ? 'active' : '' }}" data-ego-type="nav">Tổng quan</a></li>
            <li><a href="{{ route('projects-unified.index', ['view'=>'list']) }}" class="ego-sublink {{ request()->routeIs('projects-unified.index') && request('view')==='list' && !$egoMyProjects ? 'active' : '' }}" data-ego-type="nav">Danh sách công trình</a></li>
            <li><a href="{{ route('projects-unified.index', ['view'=>'list','mine'=>1]) }}" class="ego-sublink {{ $egoMyProjects ? 'active' : '' }}" data-ego-type="nav">Công trình của tôi</a></li>
            @if(auth()->check() && (app(\App\Services\Projects\UnifiedProjectAccess::class)->isSalesScoped(auth()->user()) || auth()->user()->hasAnyRole(['admin','super_admin','technical_manager','technical_leader','technical','technician','engineer','engineering'])))
                <li><a href="{{ route('projects-unified.create') }}" class="ego-sublink {{ request()->routeIs('projects-unified.create') ? 'active' : '' }}" data-ego-type="nav">Tạo công trình</a></li>
            @endif
            {{-- EGO_CONSTRUCTION_MATERIAL_MENU_START --}}
            @if(\Illuminate\Support\Facades\Route::has('material-requests.index') && auth()->user()->hasAnyRole(['admin', 'warehouse', 'kho', 'accounting', 'technical', 'sales']))
                <li><a href="{{ route('material-requests.index') }}" class="ego-sublink {{ request()->routeIs('material-requests.*') ? 'active' : '' }}" data-ego-type="nav">Vật tư công trình</a></li>
            @endif
            {{-- EGO_CONSTRUCTION_MATERIAL_MENU_END --}}
        </ul>
    </li>
@endif


{{-- KỸ THUẬT --}}
@if($egoCanTechnicalMenu)
@php
    $egoTechnicalMenuOpen = request()->routeIs(
        'ky-thuat.tong-quan',
        'ky-thuat.ke-hoach*',
        'ky-thuat.warranty-exchange.*',
        'ky-thuat.bao-cao*',
        'ky-thuat.kpis.*',
        'ky-thuat.luong.*'
    );
@endphp
<li class="ego-item ego-item--has-sub" data-title="Kỹ thuật" data-ego-sub="true" data-ego-menu-permission="menu.technical">
    <a href="#menuKyThuat" class="ego-link {{ $egoTechnicalMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoTechnicalMenuOpen ? 'true' : 'false' }}" aria-controls="menuKyThuat">
        <span class="ego-ic"><i class="bi bi-tools"></i></span>
        <span class="ego-txt">Kỹ thuật</span>
        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>
    <ul id="menuKyThuat" class="ego-sub collapse {{ $egoTechnicalMenuOpen ? 'show' : '' }}" data-ego-submenu>
        <li><a href="{{ route('ky-thuat.tong-quan') }}" class="ego-sublink {{ request()->routeIs('ky-thuat.tong-quan') ? 'active' : '' }}" data-ego-type="nav">Tổng quan</a></li>
        <li><a href="{{ route('ky-thuat.ke-hoach') }}" class="ego-sublink {{ request()->routeIs('ky-thuat.ke-hoach*') ? 'active' : '' }}" data-ego-type="nav">Kế hoạch</a></li>
        <li><a href="{{ route('ky-thuat.warranty-exchange.index') }}" class="ego-sublink {{ request()->routeIs('ky-thuat.warranty-exchange.*') ? 'active' : '' }}" data-ego-type="nav">Đề xuất đổi hàng BH</a></li>
        <li><a href="{{ route('ky-thuat.bao-cao') }}" class="ego-sublink {{ request()->routeIs('ky-thuat.bao-cao*') ? 'active' : '' }}" data-ego-type="nav">Báo cáo</a></li>
        <li><a href="{{ route('ky-thuat.kpis.index') }}" class="ego-sublink {{ request()->routeIs('ky-thuat.kpis.*', 'ky-thuat.luong.*') ? 'active' : '' }}" data-ego-type="nav">KPIs</a></li>
    </ul>
</li>
@endif

{{-- BẢO TRÌ / BẢO HÀNH --}}
@if($egoCanConstructionMenu || $egoCanTechnicalMenu)
<li class="ego-item" data-title="Bảo trì / Bảo hành" data-ego-menu-permission="menu.sites">
    <a href="{{ route('projects-unified.maintenance.index') }}" class="ego-link {{ request()->routeIs('projects-unified.maintenance.*', 'ky-thuat.maintenance.*') ? 'active' : '' }}" data-ego-type="nav">
        <span class="ego-ic"><i class="bi bi-shield-check"></i></span>
        <span class="ego-txt">Bảo trì / Bảo hành</span>
    </a>
</li>
@endif


{{-- EGO_PRIMARY_PROJECT_MENU_END --}}


            {{-- ĐỀ NGHỊ THANH TOÁN --}}
            {{-- EGO_REAL_VN_DNTT_V2_START --}}
@php
    $egoPaymentMenuOpen = request()->routeIs('payment_requests.*') || request()->routeIs('payment_advances.*');
@endphp
@if(($egoCanPaymentRequestsMenu ?? true) && \Illuminate\Support\Facades\Route::has('payment_requests.index'))
<li class="ego-item ego-item--has-sub" data-title="Đề nghị thanh toán" data-ego-sub="true" data-ego-shared-finance="true">
    <a href="#menuDeNghiThanhToan" class="ego-link {{ $egoPaymentMenuOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoPaymentMenuOpen ? 'true' : 'false' }}" aria-controls="menuDeNghiThanhToan">
        <span class="ego-ic"><i class="bi bi-receipt"></i></span>
        <span class="ego-txt">Đề nghị thanh toán</span>
        @if(($egoPendingPaymentRequestsCount ?? 0) > 0)<span class="ego-count-badge" title="Đề nghị thanh toán chờ duyệt">{{ $egoPendingPaymentRequestsCount }}</span>@endif
        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>
    <ul id="menuDeNghiThanhToan" class="ego-sub collapse {{ $egoPaymentMenuOpen ? 'show' : '' }}" data-ego-submenu>
        <li><a href="{{ route('payment_requests.index') }}" class="ego-sublink {{ request()->routeIs('payment_requests.*') && !request()->routeIs('payment_advances.*') ? 'active' : '' }}" data-ego-type="nav">Đề nghị thanh toán</a></li>
        @if(\Illuminate\Support\Facades\Route::has('payment_advances.index'))
            <li><a href="{{ route('payment_advances.index') }}" class="ego-sublink {{ request()->routeIs('payment_advances.*') ? 'active' : '' }}" data-ego-type="nav">Tạm ứng &amp; Hoàn ứng</a></li>
        @endif
    </ul>
</li>
@endif
{{-- EGO_REAL_VN_DNTT_V2_END --}}

{{-- ĐỀ XUẤT - DÙNG CHUNG MỌI PHÒNG BAN --}}
@auth
@if(\Illuminate\Support\Facades\Route::has('de-xuat.index'))
<li class="ego-item" data-title="Đề xuất">
    <a href="{{ route('de-xuat.index') }}"
       class="ego-link {{ request()->is('de-xuat*') ? 'active' : '' }}"
       data-ego-type="nav">
        <span class="ego-ic"><i class="bi bi-lightbulb"></i></span>
        <span class="ego-txt">Đề xuất</span>
        @if(($egoPendingProposalsCount ?? 0) > 0)
            <span class="ego-count-badge" title="Đề xuất chờ duyệt">{{ $egoPendingProposalsCount }}</span>
        @endif
    </a>
</li>
@endif
@endauth
{{-- SALES --}}
            @if($egoCanSalesMenu)
                <li class="ego-item ego-item--has-sub" data-title="Sales" data-ego-sub="true" data-ego-menu-permission="menu.sales">
                    <a href="#menuSales"
                       class="ego-link {{ request()->routeIs('sales.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('sales.*') ? 'true' : 'false' }}"
                       aria-controls="menuSales">
                        <span class="ego-ic"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="ego-txt">Sales</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuSales"
                        class="ego-sub collapse {{ request()->routeIs('sales.*') ? 'show' : '' }}"
                        data-ego-submenu>
                        <li>
                            <a href="{{ route('sales.commissions.index') }}"
                               class="ego-sublink {{ active_route('sales.commissions.*') }}"
                               data-ego-type="nav">
                                Hoa hồng
                            </a>
                        </li>
                        <li>
    @if(\Illuminate\Support\Facades\Route::has('sales.kpi.index'))
        <a href="{{ route('sales.kpi.index') }}"
           class="ego-sublink {{ active_route('sales.kpi.*') }}"
           data-ego-type="nav">
            KPI & Công việc
        </a>


@else
        <a href="#"
           class="ego-sublink"
           onclick="alert('Chưa khai báo route: sales.kpi.index'); return false;"
           data-ego-type="action">
            KPI & Công việc
        </a>
    @endif
</li>
                    </ul>
                </li>
            @endif

            {{-- MARKETING --}}
            @if($egoCanMarketingMenu)
                <li class="ego-item ego-item--has-sub ego-item--modern" data-title="Marketing" data-ego-sub="true" data-ego-menu-permission="menu.marketing">
                    <a href="#menuMarketing"
                       class="ego-link ego-link--modern {{ request()->routeIs('marketing.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('marketing.*') ? 'true' : 'false' }}"
                       aria-controls="menuMarketing">
                        <span class="ego-ic ego-ic--modern"><i class="bi bi-megaphone"></i></span>
                        <span class="ego-txt">Marketing</span>
                        <span class="ego-badge">PRO</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuMarketing"
                        class="ego-sub ego-sub--modern collapse {{ request()->routeIs('marketing.*') ? 'show' : '' }}"
                        data-ego-submenu>

                        <li>
                            <a href="{{ route('marketing.plan.overview') }}"
                               class="ego-sublink ego-sublink--modern {{ active_route('marketing.plan.*') }}"
                               data-ego-type="nav">
                                <span class="ego-sub-ic"><i class="bi bi-calendar2-check"></i></span>
                                <span>Kế hoạch</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('marketing.progress.index') }}"
                               class="ego-sublink ego-sublink--modern {{ active_route('marketing.progress.*') }}"
                               data-ego-type="nav">
                                <span class="ego-sub-ic"><i class="bi bi-kanban"></i></span>
                                <span>Tiến độ</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('marketing.report.ads') }}"
                               class="ego-sublink ego-sublink--modern {{ active_route('marketing.report.*') }}"
                               data-ego-type="nav">
                                <span class="ego-sub-ic"><i class="bi bi-bar-chart-line"></i></span>
                                <span>Báo cáo dữ liệu</span>
                            </a>
                        </li>

                        <li class="ego-sub-sep"></li>

                        <li>
                            <a href="{{ route('marketing.kpi-payroll.index') }}"
                               class="ego-sublink ego-sublink--modern {{ request()->routeIs('marketing.kpi-payroll.*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                <span class="ego-sub-ic"><i class="bi bi-cash-coin"></i></span>
                                <span>KPI &amp; Lương</span>
                            </a>
                        </li>

                        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Báo cáo công việc" data-ego-sub="true">
                            <a href="#menuMarketingReports"
                               class="ego-sublink ego-sublink--modern ego-sublink--toggle
                                      {{ request()->routeIs('marketing.reports.*') || request()->routeIs('marketing.leads.*') ? 'active' : '' }}"
                               data-bs-toggle="collapse"
                               data-ego-type="toggle"
                               aria-expanded="{{ (request()->routeIs('marketing.reports.*') || request()->routeIs('marketing.leads.*')) ? 'true' : 'false' }}"
                               aria-controls="menuMarketingReports">
                                <span class="ego-sub-ic"><i class="bi bi-clipboard-check"></i></span>
                                <span>Báo cáo công việc</span>
                                <i class="bi bi-chevron-down ego-sub-caret"></i>
                            </a>

                            <ul id="menuMarketingReports"
                                class="ego-sub ego-sub--nested collapse
                                       {{ (request()->routeIs('marketing.reports.*') || request()->routeIs('marketing.leads.*')) ? 'show' : '' }}"
                                data-ego-submenu>

                                <li>
                                    @php $hasLeadIndex = \Illuminate\Support\Facades\Route::has('marketing.leads.index'); @endphp
                                    @if($hasLeadIndex)
                                        <a href="{{ route('marketing.leads.index') }}"
                                           class="ego-sublink ego-sublink--modern {{ active_route('marketing.leads.index') }}"
                                           data-ego-type="nav">
                                            <span class="ego-sub-ic"><i class="bi bi-people"></i></span>
                                            <span>Danh sách Leads</span>
                                        </a>
                                    @else
                                        <a href="#"
                                           class="ego-sublink ego-sublink--modern"
                                           onclick="alert('Chưa khai báo route: marketing.leads.index'); return false;"
                                           data-ego-type="action">
                                            <span class="ego-sub-ic"><i class="bi bi-people"></i></span>
                                            <span>Danh sách Leads</span>
                                        </a>
                                    @endif
                                </li>

                                <li>
                                    @php $hasContentCalendar = \Illuminate\Support\Facades\Route::has('marketing.reports.content-calendar'); @endphp
                                    @if($hasContentCalendar)
                                        <a href="{{ route('marketing.reports.content-calendar') }}"
                                           class="ego-sublink ego-sublink--modern {{ active_route('marketing.reports.content-calendar') }}"
                                           data-ego-type="nav">
                                            <span class="ego-sub-ic"><i class="bi bi-journal-text"></i></span>
                                            <span>Lịch biên tập nội dung</span>
                                        </a>
                                    @else
                                        <a href="#"
                                           class="ego-sublink ego-sublink--modern"
                                           onclick="alert('Chưa khai báo route: marketing.reports.content-calendar'); return false;"
                                           data-ego-type="action">
                                            <span class="ego-sub-ic"><i class="bi bi-journal-text"></i></span>
                                            <span>Lịch biên tập nội dung</span>
                                        </a>
                                    @endif
                                </li>

                                <li>
                                    <a href="/marketing/reports/weekly-tasks"
                                       class="ego-sublink ego-sublink--modern {{ active_route('marketing.reports.weekly-tasks') }}"
                                       data-ego-type="nav">
                                        <span class="ego-sub-ic"><i class="bi bi-list-check"></i></span>
                                        <span>Công việc hàng tuần</span>
                                    </a>
                                </li>

                            </ul>
                        </li>
                    </ul>
                </li>
            @endif

            @if(!$egoTechnicalWorkspace)
            {{-- CÔNG VIỆC --}}
            <li class="ego-item ego-item--has-sub" data-title="Công việc" data-ego-sub="true" data-ego-menu-permission="menu.tasks">
                <a href="#menuTasks"
                   class="ego-link {{ active_route('tasks.*') }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ request()->routeIs('tasks.*') ? 'true' : 'false' }}"
                   aria-controls="menuTasks">
                    <span class="ego-ic"><i class="bi bi-list-check"></i></span>
                    <span class="ego-txt">Công việc</span>
                    @if(($egoMyUnfinishedTasksCount ?? 0) > 0)
                        <span class="ego-count-badge" title="Công việc chưa hoàn thành">{{ $egoMyUnfinishedTasksCount }}</span>
                    @endif
                    <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                </a>

                <ul id="menuTasks"
                    class="ego-sub collapse {{ request()->routeIs('tasks.*') ? 'show' : '' }}"
                    data-ego-submenu>
                    <li>
                        <a href="{{ route('tasks.my') }}"
                           class="ego-sublink {{ active_route('tasks.my') }}"
                           data-ego-type="nav">
                            Việc của tôi
                            @if(($egoMyUnfinishedTasksCount ?? 0) > 0)
                                <span class="ego-count-badge" title="Việc của tôi chưa hoàn thành">{{ $egoMyUnfinishedTasksCount }}</span>
                            @endif
                        </a>
                    </li>
                    @can('viewAny', \App\Models\Task::class)
                        <li>
                            <a href="{{ route('tasks.index') }}"
                               class="ego-sublink {{ active_route('tasks.index') }}"
                               data-ego-type="nav">
                                Giao việc
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>

            @endif
            {{-- KHO --}}
            @php
                $u = auth()->user();

                $canProductMenu = $u && $egoCanProductMenuByRole;

                $canSeeInputProducts = $u && (
                    $egoSidebarIsExecutive
                    || $u->hasAnyRole([
                        'warehouse',
                        'kho',
                        'accounting',
                    ])
                );

                $egoWarehouseMenuActive =
                    request()->routeIs('products.*')
                    || request()->routeIs('product-goods-receipts.*')
                    || request()->routeIs('categories.*')
                    || request()->routeIs('warehouses.*')
                    || request()->routeIs('brands.*')
                    || request()->routeIs('price-tiers.*')
                    || request()->routeIs('project-test.warehouse.*')
                    || request()->routeIs('warehouse-projects.*')
                    || request()->is('products/goods-receipts*');
            @endphp

            @if($canProductMenu)
                <li class="ego-item ego-item--has-sub"
                    data-title="Kho"
                    data-ego-sub="true"
                    data-ego-menu-permission="menu.products">
                    <a href="#menuSP"
                       class="ego-link {{ $egoWarehouseMenuActive ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ $egoWarehouseMenuActive ? 'true' : 'false' }}"
                       aria-controls="menuSP">
                        <span class="ego-ic"><i class="bi bi-box-seam"></i></span>
                        <span class="ego-txt">Kho</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuSP"
                        class="ego-sub collapse {{ $egoWarehouseMenuActive ? 'show' : '' }}"
                        data-ego-submenu>

                        {{-- 1. NGHIỆP VỤ KHO HÀNG NGÀY --}}
                        @if($canSeeInputProducts && \Illuminate\Support\Facades\Route::has('products.input'))
                            <li>
                                <a href="{{ route('products.input') }}"
                                   class="ego-sublink {{ request()->routeIs('products.input') || request()->routeIs('products.show') || request()->routeIs('products.edit') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Danh sách sản phẩm
                                </a>
                            </li>
                        {{-- EGO_SITE_ASSEMBLY_WAREHOUSE_MENU_START --}}
@if(\Illuminate\Support\Facades\Route::has('site-assemblies.index'))
    <li>
        <a href="{{ route('site-assemblies.index') }}"
           class="ego-sublink {{ request()->routeIs('site-assemblies.*') ? 'active' : '' }}"
           data-ego-type="nav">
            Lắp ráp / Sản xuất
        </a>
    </li>
@endif
{{-- EGO_SITE_ASSEMBLY_WAREHOUSE_MENU_END --}}

                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('product-goods-receipts.index'))
                            <li>
                                <a href="{{ route('product-goods-receipts.index') }}"
                                   class="ego-sublink {{ request()->routeIs('product-goods-receipts.*') || request()->is('products/goods-receipts*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Nhập sản phẩm
                                </a>
                            </li>
                        @else
                            <li>
                                <a href="{{ url('/products/goods-receipts') }}"
                                   class="ego-sublink {{ request()->is('products/goods-receipts*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Nhập sản phẩm
                                </a>
                            </li>
                        @endif

                        @if($u->hasAnyRole(['admin','warehouse']) || $u->can('products.manage'))
                            <li>
                                <a href="{{ route('products.create') }}"
                                   class="ego-sublink {{ request()->routeIs('products.create') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Tạo &amp; nhập sản phẩm
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('products.output'))
                            <li>
                                <a href="{{ route('products.output') }}"
                                   class="ego-sublink {{ request()->routeIs('products.output') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Sản phẩm đầu ra
                                </a>
                            </li>
                        @endif

                        @can('project-test.warehouse')
                            <li>
                                <a href="{{ route('warehouse-projects.index') }}"
                                   class="ego-sublink {{ request()->routeIs('warehouse-projects.*') || request()->routeIs('project-test.warehouse.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Công trình Kho
                                </a>
                            </li>
                        @endcan

                        {{-- 2. KIỂM SOÁT KHO --}}
                        @if(\Illuminate\Support\Facades\Route::has('products.serials.index'))
                            <li>
                                <a href="{{ route('products.serials.index') }}"
                                   class="ego-sublink {{ request()->routeIs('products.serials.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Quản lý serial
                                </a>
                            </li>
                        @endif

                        @if($u->hasAnyRole(['admin','warehouse']) || $u->can('warehouse.manage') || $u->can('warehouse.view'))
                            <li>
                                <a href="{{ route('warehouses.index') }}"
                                   class="ego-sublink {{ request()->routeIs('warehouses.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Danh sách kho
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('products.history'))
                            <li>
                                <a href="{{ route('products.history') }}"
                                   class="ego-sublink {{ request()->routeIs('products.history') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Lịch sử xuất/nhập kho
                                </a>
                            </li>
                        @endif

                        {{-- 3. DANH MỤC CẤU HÌNH --}}
                        @if($u->hasAnyRole(['admin','warehouse']) || $u->can('categories.manage'))
                            <li>
                                <a href="{{ route('categories.index') }}"
                                   class="ego-sublink {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Danh mục sản phẩm
                                </a>
                            </li>
                        @endif

                        @if($u->hasAnyRole(['admin','warehouse']) || $u->can('brands.manage'))
                            <li>
                                <a href="{{ route('brands.index') }}"
                                   class="ego-sublink {{ request()->routeIs('brands.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Thương hiệu
                                </a>
                            </li>
                        @endif

                        @if($u->hasAnyRole(['admin','warehouse']) || $u->can('price-tiers.manage'))
                            <li>
                                <a href="{{ route('price-tiers.index') }}"
                                   class="ego-sublink {{ request()->routeIs('price-tiers.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Bảng giá
                                </a>
                            </li>
                        @endif
                    
                        {{-- EGO_WAREHOUSE_RECEIVABLE_CLASSIC_MENU_V2 --}}
                        @if(\Illuminate\Support\Facades\Route::has('finance.customer-debts.index') && $u && $u->hasAnyRole(['admin','warehouse','kho']))
                            <li>
                                <a href="{{ route('finance.customer-debts.index') }}"
                                   class="ego-sublink {{ request()->routeIs('finance.customer-debts.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Công nợ phải thu
                                </a>
                            </li>
                        @endif
                        {{-- EGO_WAREHOUSE_RECEIVABLE_CLASSIC_MENU_V2_END --}}
</ul>
                </li>
            @endif
            {{-- DIVIDER --}}
            <li class="ego-divider" role="separator"></li>
{{-- EGO_MENU_SYNC_FROM_REAL_VN_V2_20260907_START --}}
{{-- Cấu trúc đối chiếu trực tiếp từ crm.egosolar.vn: Hành chánh + Tài chính kế toán. --}}
@if($egoCanHrMenu)
@php
    $egoHanhChanhActive = request()->routeIs('hr.operations.*')
        || request()->routeIs('hr.office-expenses.*')
        || request()->routeIs('hr.office-supply-process.*')
        || request()->routeIs('hr.gifts.*')
        || request()->routeIs('hr.document-handovers.*')
        || request()->routeIs('company-documents.*');
@endphp
<li class="ego-item ego-item--has-sub" data-title="Hành chánh" data-ego-sub="true" data-ego-menu-permission="menu.hr">
    <a href="#menuHanhChanh" class="ego-link {{ $egoHanhChanhActive ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoHanhChanhActive ? 'true' : 'false' }}" aria-controls="menuHanhChanh">
        <span class="ego-ic"><i class="bi bi-building-gear"></i></span>
        <span class="ego-txt">Hành chánh</span>
        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>
    <ul id="menuHanhChanh" class="ego-sub collapse {{ $egoHanhChanhActive ? 'show' : '' }}" data-ego-submenu>
        @if(\Illuminate\Support\Facades\Route::has('hr.operations.index'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Chi phí" data-ego-sub="true">
            <a href="#menuHanhChanhChiPhi" class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.operations.*') && request('tab') === 'expenses' ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ request()->routeIs('hr.operations.*') && request('tab') === 'expenses' ? 'true' : 'false' }}" aria-controls="menuHanhChanhChiPhi"><span>Chi phí</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuHanhChanhChiPhi" class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.operations.*') && request('tab') === 'expenses' ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('hr.operations.index', ['tab'=>'expenses','expense_type'=>'fixed']) }}" class="ego-sublink" data-ego-type="nav">Chi phí cố định</a></li>
                <li><a href="{{ route('hr.operations.index', ['tab'=>'expenses','expense_type'=>'variable']) }}" class="ego-sublink" data-ego-type="nav">Chi phí không cố định</a></li>
            </ul>
        </li>
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Tài sản" data-ego-sub="true">
            <a href="#menuHanhChanhTaiSan" class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.operations.*') && request('tab') === 'assets' ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ request()->routeIs('hr.operations.*') && request('tab') === 'assets' ? 'true' : 'false' }}" aria-controls="menuHanhChanhTaiSan"><span>Tài sản</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuHanhChanhTaiSan" class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.operations.*') && request('tab') === 'assets' ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('hr.operations.index', ['tab'=>'assets','asset_group'=>'fixed']) }}" class="ego-sublink" data-ego-type="nav">Tài sản cố định</a></li>
                <li><a href="{{ route('hr.operations.index', ['tab'=>'assets','asset_group'=>'technical']) }}" class="ego-sublink" data-ego-type="nav">Máy móc kỹ thuật</a></li>
            </ul>
        </li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('hr.office-supply-process.index'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Văn phòng phẩm" data-ego-sub="true">
            <a href="#menuHanhChanhVPP" class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.office-supply-process.*') ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ request()->routeIs('hr.office-supply-process.*') ? 'true' : 'false' }}" aria-controls="menuHanhChanhVPP"><span>Văn phòng phẩm</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuHanhChanhVPP" class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.office-supply-process.*') ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('hr.office-supply-process.index', ['view'=>'list']) }}" class="ego-sublink" data-ego-type="nav">Danh sách</a></li>
                <li><a href="{{ route('hr.office-supply-process.index', ['view'=>'import']) }}" class="ego-sublink" data-ego-type="nav">Nhập</a></li>
                <li><a href="{{ route('hr.office-supply-process.index', ['view'=>'export']) }}" class="ego-sublink" data-ego-type="nav">Xuất</a></li>
                <li><a href="{{ route('hr.office-supply-process.index', ['view'=>'stock']) }}" class="ego-sublink" data-ego-type="nav">Tồn</a></li>
            </ul>
        </li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('hr.gifts.index'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Quà tặng" data-ego-sub="true">
            <a href="#menuHanhChanhQuaTang" class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.gifts.*') ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ request()->routeIs('hr.gifts.*') ? 'true' : 'false' }}" aria-controls="menuHanhChanhQuaTang"><span>Quà tặng</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuHanhChanhQuaTang" class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.gifts.*') ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('hr.gifts.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.index') ? 'active' : '' }}" data-ego-type="nav">Tổng quan</a></li>
                @if(\Illuminate\Support\Facades\Route::has('hr.gifts.catalog.index'))<li><a href="{{ route('hr.gifts.catalog.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.catalog.*') ? 'active' : '' }}" data-ego-type="nav">Danh sách</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('hr.gifts.receipts.index'))<li><a href="{{ route('hr.gifts.receipts.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.receipts.*') ? 'active' : '' }}" data-ego-type="nav">Nhập</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('hr.gifts.requests.index'))<li><a href="{{ route('hr.gifts.requests.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.requests.*') ? 'active' : '' }}" data-ego-type="nav">Xuất</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('hr.gifts.stock.index'))<li><a href="{{ route('hr.gifts.stock.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.stock.*') || request()->routeIs('hr.gifts.reports.*') ? 'active' : '' }}" data-ego-type="nav">Tồn kho</a></li>@endif
            </ul>
        </li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('hr.operations.index'))
            <li><a href="{{ route('hr.operations.index', ['tab'=>'suppliers']) }}" class="ego-sublink {{ request()->routeIs('hr.operations.*') && request('tab') === 'suppliers' ? 'active' : '' }}" data-ego-type="nav">Nhà cung cấp</a></li>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('hr.document-handovers.index'))
            <li><a href="{{ route('hr.document-handovers.index') }}" class="ego-sublink {{ request()->routeIs('hr.document-handovers.*') ? 'active' : '' }}" data-ego-type="nav">Văn thư lưu trữ</a></li>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('company-documents.index'))
            <li><a href="{{ route('company-documents.index') }}" class="ego-sublink {{ request()->routeIs('company-documents.*') ? 'active' : '' }}" data-ego-type="nav">Tài liệu nội bộ</a></li>
        @endif
        <li><a href="#" onclick="return false;" class="ego-sublink ego-sublink--soon"><span>Mẫu văn bản</span><em class="ego-menu-soon">Bổ sung sau</em></a></li>
    </ul>
</li>
@endif

{{-- TÀI CHÍNH KẾ TOÁN - bám hierarchy thật của .vn, route nào .com.vn chưa có thì tự ẩn. --}}
@if($egoCanFinanceMenu)
@php
    $egoTaiChinhKeToanActive = request()->is('finance*');
    $egoBaoCaoTaiChinhActive = request()->routeIs('finance.reports*') || request()->routeIs('finance.settlement') || request()->routeIs('finance.audit') || request()->routeIs('finance.accounts.*');
    $egoNoPhaiThuActive = request()->routeIs('finance.project-receivables.*') || request()->routeIs('finance.customer-debts.*');
    $egoNoPhaiTraActive = request()->routeIs('finance.supplier-debts.*') || request()->routeIs('finance.ledger.*');
@endphp
<li class="ego-item ego-item--has-sub" data-title="Tài chính kế toán" data-ego-sub="true" data-ego-menu-permission="menu.finance">
    <a href="#menuTaiChinhKeToan" class="ego-link {{ $egoTaiChinhKeToanActive ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoTaiChinhKeToanActive ? 'true' : 'false' }}" aria-controls="menuTaiChinhKeToan">
        <span class="ego-ic"><i class="bi bi-calculator"></i></span><span class="ego-txt">Tài chính kế toán</span><span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>
    <ul id="menuTaiChinhKeToan" class="ego-sub collapse {{ $egoTaiChinhKeToanActive ? 'show' : '' }}" data-ego-submenu>
        @if(\Illuminate\Support\Facades\Route::has('finance.salary'))
            <li><a href="{{ route('finance.salary') }}" class="ego-sublink {{ request()->routeIs('finance.salary*') ? 'active' : '' }}" data-ego-type="nav">Bảng tiền lương</a></li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('finance.reports'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Báo cáo tài chính / Thuế" data-ego-sub="true">
            <a href="#menuBaoCaoTaiChinhThue" class="ego-sublink ego-sublink--toggle {{ $egoBaoCaoTaiChinhActive ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoBaoCaoTaiChinhActive ? 'true' : 'false' }}" aria-controls="menuBaoCaoTaiChinhThue"><span>Báo cáo tài chính / Thuế</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuBaoCaoTaiChinhThue" class="ego-sub ego-sub--nested collapse {{ $egoBaoCaoTaiChinhActive ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('finance.reports', ['period'=>'quarter']) }}" class="ego-sublink" data-ego-type="nav">Báo cáo quý</a></li>
                <li><a href="{{ route('finance.reports', ['period'=>'year']) }}" class="ego-sublink" data-ego-type="nav">Báo cáo năm</a></li>
                @if(\Illuminate\Support\Facades\Route::has('finance.settlement'))<li><a href="{{ route('finance.settlement') }}" class="ego-sublink {{ request()->routeIs('finance.settlement') ? 'active' : '' }}" data-ego-type="nav">Quyết toán</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('finance.audit'))<li><a href="{{ route('finance.audit') }}" class="ego-sublink {{ request()->routeIs('finance.audit') ? 'active' : '' }}" data-ego-type="nav">Kiểm toán</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('finance.accounts.index'))
                    <li><span class="ego-menu-group-label">Báo cáo tồn quỹ</span></li>
                    <li><a href="{{ route('finance.accounts.index', ['type'=>'bank']) }}" class="ego-sublink ego-menu-level3" data-ego-type="nav">Quỹ ngân hàng</a></li>
                    <li><a href="{{ route('finance.accounts.index', ['type'=>'cash']) }}" class="ego-sublink ego-menu-level3" data-ego-type="nav">Quỹ tiền mặt</a></li>
                @endif
            </ul>
        </li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('finance.customer-debts.index') || \Illuminate\Support\Facades\Route::has('finance.project-receivables.index'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Nợ phải thu" data-ego-sub="true">
            <a href="#menuNoPhaiThu" class="ego-sublink ego-sublink--toggle {{ $egoNoPhaiThuActive ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoNoPhaiThuActive ? 'true' : 'false' }}" aria-controls="menuNoPhaiThu"><span>Nợ phải thu</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuNoPhaiThu" class="ego-sub ego-sub--nested collapse {{ $egoNoPhaiThuActive ? 'show' : '' }}" data-ego-submenu>
                @if(\Illuminate\Support\Facades\Route::has('finance.project-receivables.index'))<li><a href="{{ route('finance.project-receivables.index') }}" class="ego-sublink {{ request()->routeIs('finance.project-receivables.*') ? 'active' : '' }}" data-ego-type="nav">Phải thu công trình</a></li>@endif
                @if(\Illuminate\Support\Facades\Route::has('finance.customer-debts.index'))
                    <li><a href="{{ route('finance.customer-debts.index', ['debt_type'=>'walk_in']) }}" class="ego-sublink ego-menu-level3" data-ego-type="nav">Phải thu khách vãng lai</a></li>
                    <li><a href="{{ route('finance.customer-debts.index', ['debt_type'=>'dealer']) }}" class="ego-sublink" data-ego-type="nav">Phải thu đại lý</a></li>
                    <li><a href="{{ route('finance.customer-debts.index', ['debt_type'=>'investment']) }}" class="ego-sublink" data-ego-type="nav">Phải thu dự án đầu tư</a></li>
                @endif
            </ul>
        </li>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('finance.supplier-debts.index'))
        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Nợ phải trả" data-ego-sub="true">
            <a href="#menuNoPhaiTra" class="ego-sublink ego-sublink--toggle {{ $egoNoPhaiTraActive ? 'active' : '' }}" data-bs-toggle="collapse" data-ego-type="toggle" aria-expanded="{{ $egoNoPhaiTraActive ? 'true' : 'false' }}" aria-controls="menuNoPhaiTra"><span>Nợ phải trả</span><i class="bi bi-chevron-down ego-sub-caret"></i></a>
            <ul id="menuNoPhaiTra" class="ego-sub ego-sub--nested collapse {{ $egoNoPhaiTraActive ? 'show' : '' }}" data-ego-submenu>
                <li><a href="{{ route('finance.supplier-debts.index') }}" class="ego-sublink" data-ego-type="nav">Phải trả nhà cung cấp</a></li>
                <li><a href="{{ route('finance.supplier-debts.index', ['scope'=>'domestic']) }}" class="ego-sublink ego-menu-level3" data-ego-type="nav">Phải trả nhà cung cấp trong nước</a></li>
                <li><a href="{{ route('finance.supplier-debts.index', ['scope'=>'import']) }}" class="ego-sublink" data-ego-type="nav">Phải trả hàng nhập khẩu</a></li>
                @if(\Illuminate\Support\Facades\Route::has('finance.ledger.index'))
                    @foreach([['bank','Phải trả ngân hàng'],['loan','Phải trả nợ vay'],['shareholder','Phải trả cổ đông']] as [$egoLedgerCategory,$egoLedgerLabel])
                        <li><a href="{{ route('finance.ledger.index', ['direction'=>'payable','category'=>$egoLedgerCategory]) }}" class="ego-sublink" data-ego-type="nav">{{ $egoLedgerLabel }}</a></li>
                    @endforeach
                @endif
            </ul>
        </li>
        @endif

        {{-- Các màn .com.vn đang có nhưng .vn không có vị trí tương đương được giữ cuối nhóm để không mất chức năng. --}}
        @if(\Illuminate\Support\Facades\Route::has('finance.index'))<li><a href="{{ route('finance.index') }}" class="ego-sublink {{ request()->routeIs('finance.index') ? 'active' : '' }}" data-ego-type="nav">Tổng quan tài chính</a></li>@endif
        @if(\Illuminate\Support\Facades\Route::has('finance.budget'))<li><a href="{{ route('finance.budget') }}" class="ego-sublink {{ request()->routeIs('finance.budget') ? 'active' : '' }}" data-ego-type="nav">Ngân sách</a></li>@endif
    </ul>
</li>
@endif
{{-- EGO_MENU_SYNC_FROM_REAL_VN_V2_20260907_END --}}
{{-- HỒ SƠ CÔNG TY --}}
@if(\Illuminate\Support\Facades\Route::has('company-documents.index'))
<li class="ego-item" data-title="Hồ sơ công ty">
    <a href="{{ route('company-documents.index') }}"
       class="ego-link {{ request()->routeIs('company-documents.*') ? 'active' : '' }}"
       data-ego-type="nav">
        <span class="ego-ic"><i class="bi bi-folder2-open"></i></span>
        <span class="ego-txt">Hồ sơ công ty</span>
    </a>
</li>
@endif

            {{-- EGO_LEAVE_APPROVAL_SIDEBAR_V1 --}}
            @php
                $egoLeaveAccess = app(\App\Services\Hr\LeaveApprovalAccessService::class);
                $egoCanReviewLeave = auth()->check() && $egoLeaveAccess->canReview(auth()->user());
                $egoCanViewCompanyAttendance = auth()->check()
                    && $egoLeaveAccess->canManageAll(auth()->user());
                $egoPendingLeaveApprovalCount = 0;

                if ($egoCanReviewLeave) {
                    try {
                        $egoPendingLeaveApprovalQuery = \App\Models\LeaveRequest::query()->where('status', 'pending');
                        $egoPendingLeaveApprovalCount = (int) $egoLeaveAccess
                            ->scopeReviewable($egoPendingLeaveApprovalQuery, auth()->user())
                            ->count();
                    } catch (\Throwable $e) {
                        $egoPendingLeaveApprovalCount = 0;
                    }
                }
            @endphp

            {{-- EGO_LEAVE_SIDEBAR_SNAPSHOT_V110_START --}}
            @php
                $egoLeaveSidebarSnapshot = auth()->check()
                    ? app(\App\Services\Hr\LeaveDashboardAlertService::class)->snapshot(auth()->user())
                    : [];
                $egoPendingLeaveApprovalCount = (int) ($egoLeaveSidebarSnapshot['pending_approval_count'] ?? 0);
                $egoCanReviewLeave = (bool) ($egoLeaveSidebarSnapshot['can_review'] ?? false);
            @endphp
            {{-- EGO_LEAVE_SIDEBAR_SNAPSHOT_V110_END --}}

            {{-- EGO_PERSONAL_HR_MENU_V110_START --}}
            @if(!$egoCanHrMenu && auth()->check())
                <li class="ego-item ego-item--has-sub" data-title="Nhân sự cá nhân" data-ego-sub="true">
                    <a href="#menuNhanSuCaNhan"
                       class="ego-link {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.gifts.requests.*') || request()->routeIs('hr.gifts.requests.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.gifts.requests.*') || request()->routeIs('hr.gifts.requests.*') ? 'true' : 'false' }}"
                       aria-controls="menuNhanSuCaNhan">
                        <span class="ego-ic"><i class="bi bi-person-workspace"></i></span>
                        <span class="ego-txt">Nhân sự</span>
                        @if($egoPendingLeaveApprovalCount > 0)
                            <em class="ego-leave-parent-badge">{{ $egoPendingLeaveApprovalCount }}</em>
                        @endif
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>
                    <ul id="menuNhanSuCaNhan"
                        class="ego-sub collapse {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.gifts.requests.*') || request()->routeIs('hr.gifts.requests.*') ? 'show' : '' }}"
                        data-ego-submenu>
                        <li><a href="{{ route('hr.attendance.my') }}" class="ego-sublink {{ active_route('hr.attendance.my') }}" data-ego-type="nav">Chấm công của tôi</a></li>
                        @if(\Illuminate\Support\Facades\Route::has('hr.gifts.requests.index'))
                            <li><a href="{{ route('hr.gifts.requests.index') }}" class="ego-sublink {{ request()->routeIs('hr.gifts.requests.*') ? 'active' : '' }}" data-ego-type="nav">Yêu cầu tặng quà</a></li>
                        @endif
                        <li><a href="{{ route('hr.leave.index', ['tab' => 'mine']) }}" class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab', 'mine') === 'mine' ? 'active' : '' }}" data-ego-type="nav">Đơn nghỉ phép / làm online</a></li>
                        @if($egoCanReviewLeave)
                            <li>
                                <a href="{{ route('hr.leave.index', ['tab' => 'approval', 'status' => 'pending']) }}" class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab') === 'approval' ? 'active' : '' }}" data-ego-type="nav" style="display:flex;align-items:center;gap:8px">
                                    <span>Duyệt đơn nhân sự</span>
                                    @if($egoPendingLeaveApprovalCount > 0)<span class="ego-booking-new-badge">{{ $egoPendingLeaveApprovalCount }}</span>@endif
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif
            {{-- EGO_PERSONAL_HR_MENU_V110_END --}}

            {{-- GIAO NHẬN HỒ SƠ - DÙNG CHUNG TOÀN CÔNG TY --}}
            @if(\Illuminate\Support\Facades\Route::has('hr.document-handovers.index'))
                <li class="ego-item" data-title="Giao nhận hồ sơ">
                    <a href="{{ route('hr.document-handovers.index') }}"
                       class="ego-link {{ request()->routeIs('hr.document-handovers.*') ? 'active' : '' }}"
                       data-ego-type="nav">
                        <span class="ego-ic"><i class="bi bi-folder-symlink"></i></span>
                        <span class="ego-txt">Giao nhận hồ sơ</span>
                    </a>
                </li>
            @endif

            {{-- EGO_HR_MENU_HIERARCHY_V4_20260907_START --}}
@if($egoCanHrMenu)
    @php
        $egoHrPeopleOpen = request()->routeIs('hr.employees.*')
            || request()->routeIs('hr.records.*')
            || request()->routeIs('hr.departments.*')
            || request()->routeIs('hr.positions.*')
            || request()->routeIs('hr.org-chart');

        $egoHrRecruitmentOpen = request()->routeIs('hr.recruitment.*')
            || request()->routeIs('hr.candidate-processes.*');

        $egoHrTimeOpen = request()->routeIs('hr.attendance.*')
            || request()->routeIs('hr.leave.*')
            || request()->routeIs('hr.overtime.*');

        $egoHrAnyOpen = request()->routeIs('hr.*')
            && !request()->routeIs('hr.document-handovers.*')
            && !request()->routeIs('hr.operations.*')
            && !request()->routeIs('hr.office-expenses.*')
            && !request()->routeIs('hr.office-supply-process.*')
            && !request()->routeIs('hr.gifts.*');
    @endphp

    <li class="ego-item ego-item--has-sub"
        data-title="Nhân sự"
        data-ego-sub="true"
        data-ego-menu-permission="menu.hr">

        <a href="#menuNhanSu"
           class="ego-link {{ $egoHrAnyOpen ? 'active' : '' }}"
           data-bs-toggle="collapse"
           data-ego-type="toggle"
           aria-expanded="{{ $egoHrAnyOpen ? 'true' : 'false' }}"
           aria-controls="menuNhanSu">
            <span class="ego-ic"><i class="bi bi-people"></i></span>
            <span class="ego-txt">Nhân sự</span>
            @if(($egoPendingLeaveApprovalCount ?? 0) > 0)
                <em class="ego-leave-parent-badge">{{ $egoPendingLeaveApprovalCount }}</em>
            @endif
            <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
        </a>

        <ul id="menuNhanSu"
            class="ego-sub collapse {{ $egoHrAnyOpen ? 'show' : '' }} ego-hr-v4-root"
            data-ego-submenu>

            @if(\Illuminate\Support\Facades\Route::has('hr.dashboard'))
                <li class="ego-hr-v4-direct">
                    <a href="{{ route('hr.dashboard') }}"
                       class="ego-sublink {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}"
                       data-ego-type="nav">Tổng quan</a>
                </li>
            @endif

            <li class="ego-hr-v4-group">
                <details class="ego-hr-v4-details" {{ $egoHrPeopleOpen ? 'open' : '' }}>
                    <summary class="ego-sublink ego-hr-v4-summary {{ $egoHrPeopleOpen ? 'active' : '' }}">
                        <span>Nhân viên &amp; cơ cấu</span>
                        <i class="bi bi-chevron-down ego-hr-v4-chevron"></i>
                    </summary>
                    <ul class="ego-hr-v4-children">
                        @if(\Illuminate\Support\Facades\Route::has('hr.employees.index'))
                            <li><a href="{{ route('hr.employees.index') }}" class="ego-sublink {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}" data-ego-type="nav">Danh sách nhân viên</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.records.index'))
                            <li><a href="{{ route('hr.records.index') }}" class="ego-sublink {{ request()->routeIs('hr.records.*') ? 'active' : '' }}" data-ego-type="nav">Hồ sơ nhân viên</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.departments.index'))
                            <li><a href="{{ route('hr.departments.index') }}" class="ego-sublink {{ request()->routeIs('hr.departments.*') ? 'active' : '' }}" data-ego-type="nav">Phòng ban</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.positions.index'))
                            <li><a href="{{ route('hr.positions.index') }}" class="ego-sublink {{ request()->routeIs('hr.positions.*') ? 'active' : '' }}" data-ego-type="nav">Chức vụ</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.org-chart'))
                            <li><a href="{{ route('hr.org-chart') }}" class="ego-sublink {{ request()->routeIs('hr.org-chart') ? 'active' : '' }}" data-ego-type="nav">Sơ đồ tổ chức</a></li>
                        @endif
                    </ul>
                </details>
            </li>

            <li class="ego-hr-v4-group">
                <details class="ego-hr-v4-details" {{ $egoHrTimeOpen ? 'open' : '' }}>
                    <summary class="ego-sublink ego-hr-v4-summary {{ $egoHrTimeOpen ? 'active' : '' }}">
                        <span>Chấm công &amp; nghỉ phép</span>
                        <i class="bi bi-chevron-down ego-hr-v4-chevron"></i>
                    </summary>
                    <ul class="ego-hr-v4-children">
                        @if($egoCanViewCompanyAttendance && \Illuminate\Support\Facades\Route::has('hr.attendance.index'))
                            <li><a href="{{ route('hr.attendance.index') }}" class="ego-sublink {{ request()->routeIs('hr.attendance.index') ? 'active' : '' }}" data-ego-type="nav">Bảng chấm công</a></li>
                        @elseif(\Illuminate\Support\Facades\Route::has('hr.attendance.my'))
                            <li><a href="{{ route('hr.attendance.my') }}" class="ego-sublink {{ request()->routeIs('hr.attendance.my') ? 'active' : '' }}" data-ego-type="nav">Chấm công</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.overtime.index'))
                            <li><a href="{{ route('hr.overtime.index') }}" class="ego-sublink {{ request()->routeIs('hr.overtime.*') ? 'active' : '' }}" data-ego-type="nav">Tăng ca</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('hr.leave.index'))
                            <li><a href="{{ route('hr.leave.index', ['tab'=>'mine']) }}" class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab','mine') === 'mine' ? 'active' : '' }}" data-ego-type="nav">Đơn nghỉ phép</a></li>
                            @if($egoCanReviewLeave)
                                <li>
                                    <a href="{{ route('hr.leave.index', ['tab'=>'approval','status'=>'pending']) }}"
                                       class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab') === 'approval' ? 'active' : '' }}"
                                       data-ego-type="nav">
                                        <span>Duyệt đơn nhân sự</span>
                                        @if(($egoPendingLeaveApprovalCount ?? 0) > 0)
                                            <span class="ego-booking-new-badge">{{ $egoPendingLeaveApprovalCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        @endif
                    </ul>
                </details>
            </li>

            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.index') || \Illuminate\Support\Facades\Route::has('hr.candidate-processes.index'))
                <li class="ego-hr-v4-group">
                    <details class="ego-hr-v4-details" {{ $egoHrRecruitmentOpen ? 'open' : '' }}>
                        <summary class="ego-sublink ego-hr-v4-summary {{ $egoHrRecruitmentOpen ? 'active' : '' }}">
                            <span>Tuyển dụng &amp; hồ sơ</span>
                            <i class="bi bi-chevron-down ego-hr-v4-chevron"></i>
                        </summary>
                        <ul class="ego-hr-v4-children">
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.index'))
                                <li><a href="{{ route('hr.recruitment.index') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.index') ? 'active' : '' }}" data-ego-type="nav">Tổng quan tuyển dụng</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.requests'))
                                <li><a href="{{ route('hr.recruitment.requests') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.requests*') ? 'active' : '' }}" data-ego-type="nav">Yêu cầu tuyển dụng</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.candidates'))
                                <li><a href="{{ route('hr.recruitment.candidates') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.candidates*') ? 'active' : '' }}" data-ego-type="nav">Kho ứng viên</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.interviews'))
                                <li><a href="{{ route('hr.recruitment.interviews') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.interviews') ? 'active' : '' }}" data-ego-type="nav">Lịch / thư mời phỏng vấn</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.offers'))
                                <li><a href="{{ route('hr.recruitment.offers') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.offers') ? 'active' : '' }}" data-ego-type="nav">Thư mời nhận việc</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.onboarding'))
                                <li><a href="{{ route('hr.recruitment.onboarding') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.onboarding') ? 'active' : '' }}" data-ego-type="nav">Tiếp nhận nhân sự</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.recruitment.archives'))
                                <li><a href="{{ route('hr.recruitment.archives') }}" class="ego-sublink {{ request()->routeIs('hr.recruitment.archives') ? 'active' : '' }}" data-ego-type="nav">Hồ sơ lưu trữ</a></li>
                            @endif
                            @if(\Illuminate\Support\Facades\Route::has('hr.candidate-processes.index'))
                                <li><a href="{{ route('hr.candidate-processes.index') }}" class="ego-sublink {{ request()->routeIs('hr.candidate-processes.*') ? 'active' : '' }}" data-ego-type="nav">Quy trình ứng viên</a></li>
                            @endif
                        </ul>
                    </details>
                </li>
            @endif
        </ul>
    </li>
@endif
{{-- EGO_HR_MENU_HIERARCHY_V4_20260907_END --}}

            {{-- EGO_SETTINGS_MENU_START --}}
            @php
                $egoSettingsRouteActive =
                    request()->routeIs('admin.settings.*')
                    || request()->routeIs('payment-methods.*')
                    || request()->is('companies*');
            @endphp
            @if($egoCanSettingsMenu)
                <li class="ego-item ego-item--has-sub"
                    data-title="Cài đặt"
                    data-ego-sub="true"
                    data-ego-menu-permission="menu.settings">
                    <a href="#menuSettings"
                       class="ego-link {{ $egoSettingsRouteActive ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ $egoSettingsRouteActive ? 'true' : 'false' }}"
                       aria-controls="menuSettings">
                        <span class="ego-ic"><i class="bi bi-gear"></i></span>
                        <span class="ego-txt">Cài đặt</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuSettings"
                        class="ego-sub collapse {{ $egoSettingsRouteActive ? 'show' : '' }}"
                        data-ego-submenu>
                        <li><a href="{{ route('admin.settings.index') }}" class="ego-sublink {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" data-ego-type="nav">Tổng quan</a></li>
                        <li><a href="{{ route('admin.settings.appearance') }}" class="ego-sublink {{ request()->routeIs('admin.settings.appearance*') ? 'active' : '' }}" data-ego-type="nav">Giao diện &amp; thương hiệu</a></li>
                        <li><a href="{{ route('admin.settings.ai.index') }}" class="ego-sublink {{ request()->routeIs('admin.settings.ai.*') ? 'active' : '' }}" data-ego-type="nav">API &amp; mô hình AI</a></li>
                        <li><a href="{{ route('admin.settings.ai.governance.index') }}" class="ego-sublink {{ request()->routeIs('admin.settings.ai.governance.*') ? 'active' : '' }}" data-ego-type="nav">Phân quyền &amp; nhật ký AI</a></li>
                        @if(\Illuminate\Support\Facades\Route::has('companies.index'))
                            <li><a href="{{ route('companies.index') }}" class="ego-sublink {{ request()->is('companies*') ? 'active' : '' }}" data-ego-type="nav">Công ty &amp; pháp nhân</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('payment-methods.index'))
                            <li><a href="{{ route('payment-methods.index') }}" class="ego-sublink {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}" data-ego-type="nav">Phương thức thanh toán</a></li>
                        @endif
                        <li><a href="{{ route('admin.settings.roles') }}" class="ego-sublink {{ request()->routeIs('admin.settings.roles') ? 'active' : '' }}" data-ego-type="nav">Vai trò &amp; nhân sự</a></li>
                        <li><a href="{{ route('admin.settings.pages') }}" class="ego-sublink {{ request()->routeIs('admin.settings.pages') ? 'active' : '' }}" data-ego-type="nav">Phân quyền trang</a></li>
                        <li><a href="{{ route('admin.settings.menus') }}" class="ego-sublink {{ request()->routeIs('admin.settings.menus') ? 'active' : '' }}" data-ego-type="nav">Phân quyền menu</a></li>
                        <li><a href="{{ route('admin.settings.actions') }}" class="ego-sublink {{ request()->routeIs('admin.settings.actions') ? 'active' : '' }}" data-ego-type="nav">Quyền thao tác</a></li>
                        <li><a href="{{ route('admin.settings.audit') }}" class="ego-sublink {{ request()->routeIs('admin.settings.audit') ? 'active' : '' }}" data-ego-type="nav">Nhật ký thay đổi</a></li>
                    </ul>
                </li>
            @endif
            {{-- EGO_SETTINGS_MENU_END --}}

            {{-- TÀI KHOẢN --}}
            <li class="ego-item ego-item--has-sub" data-title="Tài khoản" data-ego-sub="true">
                <a href="#menuTaiKhoan"
                   class="ego-link {{ active_route('users.*') }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ request()->routeIs('users.*') ? 'true' : 'false' }}"
                   aria-controls="menuTaiKhoan">
                    <span class="ego-ic"><i class="bi bi-person-circle"></i></span>
                    <span class="ego-txt">Tài khoản</span>
                    <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                </a>

                <ul id="menuTaiKhoan"
                    class="ego-sub collapse {{ request()->routeIs('users.*') ? 'show' : '' }}"
                    data-ego-submenu>
                    @role('admin')
                        <li>
                            <a href="{{ route('users.index') }}"
                               class="ego-sublink {{ active_route('users.index') }}"
                               data-ego-type="nav">
                                Danh sách User
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('users.create') }}"
                               class="ego-sublink {{ active_route('users.create') }}"
                               data-ego-type="nav">
                                Thêm User
                            </a>
                        </li>
                    @endrole

                    <li>
                        <a href="{{ route('users.profile') }}"
                           class="ego-sublink {{ active_route('users.profile') }}"
                           data-ego-type="nav">
                            Thông tin cá nhân
                        </a>
                    </li>

                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="ego-logout">
                            @csrf
                            <button type="submit" class="ego-sublink ego-sublink--button">
                                Đăng xuất
                            </button>
                        </form>
                    </li>
                </ul>
            </li>

            @endauth
        </ul>
    </div>

    
    
    @auth
        {{-- ===== MINI STATUS FOOTER - FINAL V4 ===== --}}
        @php
            $egoSidebarCompaniesV4 = collect();
            $egoCurrentCompanyIdV4 = session('ego_company_id')
                ?? session('selected_company_id')
                ?? session('company_id')
                ?? session('current_company_id')
                ?? session('active_company_id')
                ?? optional(auth()->user())->company_id
                ?? null;

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('companies')) {
                    $egoCompanyQueryV4 = \Illuminate\Support\Facades\DB::table('companies')->select('id', 'code', 'name')->where('id', \App\Support\EgoCompanyLock::id());

                    if (\Illuminate\Support\Facades\Schema::hasColumn('companies', 'is_active')) {
                        $egoCompanyQueryV4->where('is_active', 1);
                    }

                    $egoSidebarCompaniesV4 = $egoCompanyQueryV4->orderBy('id')->get();

                    if (!$egoCurrentCompanyIdV4 && $egoSidebarCompaniesV4->count()) {
                        $egoCurrentCompanyIdV4 = $egoSidebarCompaniesV4->first()->id;
                    }
                }
            } catch (\Throwable $e) {
                $egoSidebarCompaniesV4 = collect();
            }

            $egoCompanySwitchActionV4 = \Illuminate\Support\Facades\Route::has('company-context.store')
                ? route('company-context.store')
                : url('/chon-cong-ty');

            $egoCompanyLabelV4 = function ($company) {
                $code = strtoupper(trim((string)($company->code ?? '')));
                $name = mb_strtolower(trim((string)($company->name ?? '')));

                if (str_contains($code, 'VN') || str_contains($name, 'việt')) return 'VN';
                if (str_contains($code, 'QT') || str_contains($code, 'INT') || str_contains($name, 'quốc tế')) return 'QT';

                return $code !== '' ? mb_substr($code, 0, 3) : ('CT' . $company->id);
            };
        @endphp

        <div class="ego-sidebar__footer">
            <div class="ego-status-card ego-status-card-final-v4" aria-label="Trạng thái hôm nay">
                <div class="ego-status-card__glow"></div>

                <div class="ego-status-final-head">
                    <div class="ego-status-final-titlebox">
                        <div class="ego-status-card__title">
                            <span class="ego-status-pulse"></span>
                            Trạng thái
                        </div>
                        <div class="ego-status-card__hint">
                            Cập nhật <span data-ego-clock>{{ $egoStatusNow->format('H:i') }}</span>
                        </div>
                    </div>

                    <div class="ego-status-final-actions">
                        <span class="ego-status-chip">LIVE</span>
                        <button type="button" class="ego-status-final-toggle" title="Thu gọn / mở rộng">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <div class="ego-company-picker-final">
                    <div class="ego-company-picker-final__label">
                        <i class="bi bi-building"></i>
                        <span>Công ty làm việc</span>
                    </div>
                    <div class="ego-company-picker-final__switch">
                        <span class="ego-company-picker-final__btn active" title="CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO">QT</span>
                    </div>
                </div>

                <div class="ego-status-grid">
                    @foreach($egoStatusItems as $item)
                        <div class="ego-status-row ego-status-row--{{ $item['tone'] }}">
                            <span class="ego-status-row__label">
                                <i class="bi {{ $item['icon'] }}"></i>
                                {{ $item['label'] }}
                            </span>
                            <strong data-ego-status="{{ $item['key'] }}">{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>

                @if(\Illuminate\Support\Facades\Route::has('hr.attendance.index'))
                    <a href="{{ route('hr.attendance.index') }}" class="ego-status-card__bottom">
                        <i class="bi bi-box-arrow-up-right"></i>
                        Xem chấm công
                    </a>
                @else
                    <div class="ego-status-card__bottom ego-status-card__bottom--muted">
                        <i class="bi bi-shield-check"></i>
                        Hệ thống ổn định
                    </div>
                @endif

                <div class="ego-side-lang-box ego-side-lang-final" title="Chuyển ngôn ngữ">
                    <div class="ego-side-lang-switch">
                        <button type="button" class="ego-side-lang-btn active" data-ego-lang="vi">VN</button>
                        <button type="button" class="ego-side-lang-btn" data-ego-lang="en">EN</button>
                        <button type="button" class="ego-side-lang-btn" data-ego-lang="zh-CN">CN</button>
                    </div>
                    <div id="google_translate_element" style="display:none;"></div>
                </div>
            </div>
        </div>
    @endauth


</nav>

{{-- Overlay mobile --}}
<div id="sidebarOverlay" class="ego-sidebar-overlay" aria-hidden="true"></div>

{{-- Customer modal --}}
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="customerModalContent"></div>
    </div>
</div>

{{-- Flyout container (desktop collapsed) --}}
<div id="egoFlyout" class="ego-flyout" aria-hidden="true"></div>

<script>
(function () {
  const sidebar   = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleSidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  const flyout    = document.getElementById('egoFlyout');

  const KEY = 'ego_sidebar_collapsed';
  const MOBILE_MAX = 991;

  function isMobile() { return window.innerWidth <= MOBILE_MAX; }
  function isCollapsed() { return sidebar?.classList.contains('ego-collapsed'); }

  // ===== Toggle icon (arrow in/out) =====
  function setToggleIcon() {
    if (!toggleBtn) return;
    const icon = toggleBtn.querySelector('i');
    if (!icon) return;

    // Mobile: show "arrow-left" when opened (to close), else "arrow-right" to open
    if (isMobile()) {
      const opened = sidebar?.classList.contains('show');
      icon.className = opened ? 'bi bi-arrow-left' : 'bi bi-arrow-right';
      return;
    }

    // Desktop: collapsed => show arrow-right (expand), expanded => arrow-left (collapse)
    icon.className = isCollapsed() ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
  }

  // ===== MOBILE OFFCANVAS =====
  function openMobile() {
    if (!sidebar) return;
    sidebar.classList.add('show');
    overlay?.classList.add('show');
    document.body.classList.add('ego-noscroll');
    setToggleIcon();
  }
  function closeMobile() {
    sidebar?.classList.remove('show');
    overlay?.classList.remove('show');
    document.body.classList.remove('ego-noscroll');
    setToggleIcon();
  }

  // EXPOSE for topbar button
  window.EgoSidebar = {
    open: openMobile,
    close: closeMobile,
    toggle: () => (sidebar?.classList.contains('show') ? closeMobile() : openMobile())
  };

  // ===== DESKTOP COLLAPSE =====
  function setCollapsed(v) {
    if (!sidebar) return;
    sidebar.classList.toggle('ego-collapsed', !!v);
    document.body.classList.toggle('ego-sidebar-collapsed', !!v);
    localStorage.setItem(KEY, v ? '1' : '0');
    hideFlyout();
    setToggleIcon();
  }

  function initState() {
    if (!sidebar) return;

    if (isMobile()) {
      sidebar.classList.remove('ego-collapsed');
      hideFlyout();
      closeMobile();
    } else {
      const saved = localStorage.getItem(KEY) === '1';
      setCollapsed(saved);
      closeMobile();
    }
    setToggleIcon();
  }

  // ===== FLYOUT (desktop collapsed) =====
  function hideFlyout() {
    if (!flyout) return;
    flyout.classList.remove('show');
    flyout.innerHTML = '';
    flyout.setAttribute('aria-hidden', 'true');
  }

  function showFlyoutForItem(itemEl) {
    if (!flyout || !itemEl) return;
    if (isMobile() || !isCollapsed()) return;

    const submenu = itemEl.querySelector('[data-ego-submenu]');
    if (!submenu) { hideFlyout(); return; }

    const clone = submenu.cloneNode(true);
    clone.classList.remove('collapse', 'show');
    clone.classList.add('ego-flyout__menu');

    const title = itemEl.getAttribute('data-title') || '';
    flyout.innerHTML = `
      <div class="ego-flyout__panel" role="menu">
        <div class="ego-flyout__title">${title}</div>
      </div>
    `;
    flyout.querySelector('.ego-flyout__panel')?.appendChild(clone);

    const r = itemEl.getBoundingClientRect();
    flyout.style.top  = Math.max(12, r.top) + 'px';
    flyout.style.left = (r.right + 10) + 'px';

    flyout.classList.add('show');
    flyout.setAttribute('aria-hidden', 'false');
  }

  flyout?.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a) return;
    const href = a.getAttribute('href') || '';
    if (href.startsWith('#')) {
      e.preventDefault();
      return;
    }
    hideFlyout();
  });

  // ===== Toggle button behavior =====
  toggleBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    if (isMobile()) {
      sidebar?.classList.contains('show') ? closeMobile() : openMobile();
    } else {
      setCollapsed(!isCollapsed());
    }
  });

  overlay?.addEventListener('click', closeMobile);

  // ===== Click handling =====
  sidebar?.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a) return;

    const type = a.getAttribute('data-ego-type') || '';
    const isToggle = type === 'toggle' || a.getAttribute('data-bs-toggle') === 'collapse';
    const href = a.getAttribute('href') || '';
    const isHash = href.startsWith('#');

    if (isMobile()) {
      if (isToggle || isHash) return;        // mở submenu -> không đóng
      if (type === 'action') return;         // action/modal -> không đóng
      closeMobile();                          // link thật -> đóng
    } else {
      if (isCollapsed() && (isToggle || isHash)) {
        e.preventDefault();
        const item = a.closest('.ego-item');
        showFlyoutForItem(item);
      }
    }
  });

  // ===== Hover flyout (desktop collapsed) =====
  sidebar?.addEventListener('pointerenter', (e) => {
    const item = e.target.closest('.ego-item');
    if (!item) return;
    if (!isCollapsed() || isMobile()) return;
    if (item.hasAttribute('data-ego-sub')) showFlyoutForItem(item);
    else hideFlyout();
  }, true);

  let flyoutHideTimer = null;
  sidebar?.addEventListener('pointerleave', () => {
    if (!isCollapsed() || isMobile()) return;
    clearTimeout(flyoutHideTimer);
    flyoutHideTimer = setTimeout(() => hideFlyout(), 120);
  });

  flyout?.addEventListener('pointerenter', () => clearTimeout(flyoutHideTimer));
  flyout?.addEventListener('pointerleave', () => {
    if (!isCollapsed() || isMobile()) return;
    clearTimeout(flyoutHideTimer);
    flyoutHideTimer = setTimeout(() => hideFlyout(), 120);
  });

  // ===== Resize =====
  window.addEventListener('resize', initState);

  // ===== Init =====
  initState();

  // ===== Mini status clock =====
  const egoClockEl = document.querySelector('[data-ego-clock]');
  function updateEgoSidebarClock() {
    if (!egoClockEl) return;
    const d = new Date();
    egoClockEl.textContent = d.toLocaleTimeString('vi-VN', {
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  updateEgoSidebarClock();
  window.setInterval(updateEgoSidebarClock, 30000);

  // ===== FORCE LIVE STATUS FIX =====
  function egoSetStatusByLabel(labelText, value) {
    document.querySelectorAll('.ego-status-row').forEach(function (row) {
      const label = row.querySelector('.ego-status-row__label');
      const number = row.querySelector('strong');

      if (!label || !number) return;

      const text = (label.textContent || '').toLowerCase();
      if (text.includes(labelText.toLowerCase())) {
        number.textContent = value;
      }
    });
  }

  async function egoForceRefreshStatus() {
    let online = 1;
    let working = null;
    let employees = null;
    let updatedAt = null;

    try {
      const response = await fetch('/sidebar/status?t=' + Date.now(), {
        cache: 'no-store',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();

        online = Math.max(parseInt(data.online || 0, 10), 1);
        working = parseInt(data.working || 0, 10);
        employees = parseInt(data.employees || 0, 10);
        updatedAt = data.updated_at || null;
      }
    } catch (error) {
      online = 1;
    }

    egoSetStatusByLabel('Đang online', online);

    if (working !== null) {
      egoSetStatusByLabel('Đang làm việc', working);
    }

    if (employees !== null) {
      egoSetStatusByLabel('Nhân viên', employees);
      egoSetStatusByLabel('Nhân sự', employees);
    }

    document.querySelectorAll('[data-ego-status="online"]').forEach(function (el) {
      el.textContent = online;
    });

    document.querySelectorAll('[data-ego-status="working"]').forEach(function (el) {
      if (working !== null) el.textContent = working;
    });

    document.querySelectorAll('[data-ego-status="employees"]').forEach(function (el) {
      if (employees !== null) el.textContent = employees;
    });

    if (updatedAt) {
      document.querySelectorAll('[data-ego-status="updated_at"], [data-ego-clock]').forEach(function (el) {
        el.textContent = updatedAt;
      });
    }
  }

  window.setTimeout(egoForceRefreshStatus, 120000);
  window.setInterval(function () {
    if (document.visibilityState === 'visible') {
      egoForceRefreshStatus();
    }
  }, 120000);
  // ===== Customer modal AJAX =====
  window.openCustomerForm = async function (url = "{{ route('customers.popup-form') }}") {
    const modalElement = document.getElementById("customerModal");
    const modalContent = document.getElementById("customerModalContent");

    modalContent.innerHTML = `<div class="p-4 text-center">Đang tải...</div>`;
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();

    try {
      const response = await fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } });
      if (!response.ok) throw new Error("Lỗi tải dữ liệu");
      modalContent.innerHTML = await response.text();
    } catch (err) {
      modalContent.innerHTML = `<div class="p-4 text-danger text-center">Không thể tải dữ liệu!</div>`;
      console.error(err);
    }
  }
})();
</script>

<style>
/* ============================================================================
   THEME TOKENS
============================================================================ */
:root{
  --ego-bg-2: #0f172a;
  --ego-bg-3: #111c33;

  --ego-aqua: #22d3ee;
  --ego-aqua-soft: rgba(34,211,238,.10);
  --ego-aqua-soft2: rgba(34,211,238,.14);
  --ego-aqua-border: rgba(34,211,238,.18);

  --ego-white-88: rgba(255,255,255,.88);
  --ego-white-78: rgba(255,255,255,.78);

  --ego-radius-1: 14px;
  --ego-shadow-1: 0 10px 24px rgba(0,0,0,.18);
  --ego-shadow-2: 0 18px 50px rgba(0,0,0,.35);

  --ego-w: 292px;
  --ego-wc: 86px;
}

/* ============================================================================
   SIDEBAR SHELL (DESKTOP)
   ✅ Height = viewport - topbar height
============================================================================ */
.ego-sidebar{
  width: var(--ego-w);
  flex: 0 0 auto;
  background:
    radial-gradient(1200px 800px at -20% 10%, rgba(34,211,238,.08), transparent 60%),
    linear-gradient(180deg, var(--ego-bg-2), var(--ego-bg-3));
  border-right: 1px solid rgba(255,255,255,.06);
  color: var(--ego-white-88);
  position: relative;
  overflow: visible;

  height: calc(100dvh - var(--ego-topbar-h, 0px));
  min-height: calc(100dvh - var(--ego-topbar-h, 0px));
  display:flex;
  flex-direction:column;
}

/* Header */
.ego-sidebar__header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 10px;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255,255,255,.10);
  flex: 0 0 auto;
  position: relative;
  overflow: visible;
}

/* Scroll area (NO max-height) */
.ego-sidebar__scroll{
  padding: 10px 10px 14px;
  overflow:auto;
  flex: 1 1 auto;
  max-height: none !important;
}

/* ===== BRAND: LOGO ONLY (BIG) ===== */
.ego-brand--logoonly{
  display:flex;
  align-items:center;
  justify-content:center;
  min-width:0;
  text-decoration:none;
  flex: 1 1 auto;
}
.ego-brand__logo-big{
  height: 46px;           /* LOGO BỰ */
  width: auto;
  max-width: 100%;
  object-fit: contain;
  display:block;
  filter: drop-shadow(0 10px 24px rgba(0,0,0,.25));
}

/* Toggle pill */
.ego-toggle{
  width:38px;height:38px;border-radius:999px;
  border:1px solid rgba(255,255,255,.18);
  background: rgba(15,23,42,.70);
  color: rgba(255,255,255,.9);
  display:flex;align-items:center;justify-content:center;
  box-shadow: 0 12px 30px rgba(0,0,0,.28);
  backdrop-filter: blur(8px);
  cursor:pointer;
}
.ego-toggle:hover{ border-color: rgba(34,211,238,.28); }

/* ============================================================================
   NAV ITEMS
============================================================================ */
.ego-nav{ list-style:none; padding: 8px 0 0; margin:0; display:flex; flex-direction:column; gap:6px; }
.ego-item{ position:relative; }

.ego-link{
  display:flex; align-items:center; gap:10px;
  padding:10px 10px;
  border-radius: var(--ego-radius-1);
  border:1px solid transparent;
  color: var(--ego-white-88);
  text-decoration:none;
  font-weight:650;
  transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.ego-link:hover{
  background: var(--ego-aqua-soft);
  border-color: var(--ego-aqua-border);
  box-shadow: var(--ego-shadow-1);
}
.ego-link.active{
  background: var(--ego-aqua-soft2);
  border-color: rgba(34,211,238,.22);
  color: #e9fdff;
}

.ego-ic{
  width:38px;height:38px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  background: rgba(255,255,255,.06);
  transition: background .15s ease, color .15s ease;
  flex:0 0 auto;
}
.ego-link:hover .ego-ic{ background: var(--ego-aqua-soft2); color: var(--ego-aqua); }
.ego-link.active .ego-ic{ background: rgba(34,211,238,.20); color: var(--ego-aqua); }

.ego-txt{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1 1 auto; }
.ego-caret{ opacity:.75; flex:0 0 auto; }

/* EGO_PAYMENT_ADVANCE_MENU_CSS_V1 */
.ego-pr-parent-row{display:flex;align-items:stretch;gap:4px;}
.ego-pr-parent-row>.ego-link{flex:1 1 auto;min-width:0;}
.ego-pr-parent-toggle{width:38px;min-width:38px;border:1px solid transparent;border-radius:12px;background:transparent;color:rgba(255,255,255,.76);display:flex;align-items:center;justify-content:center;transition:.15s ease;}
.ego-pr-parent-toggle:hover{background:rgba(34,211,238,.12);border-color:rgba(34,211,238,.18);color:#fff;}
.ego-sidebar.ego-collapsed .ego-pr-parent-toggle{display:none;}
/* Divider */
.ego-divider{
  height:1px; margin:10px 8px;
  background: rgba(255,255,255,.10);
  border-radius:999px;
}

/* ============================================================================
   SUBMENU
============================================================================ */
.ego-sub{
  list-style:none;
  margin:6px 0 4px;
  padding:0 0 0 50px;
  display:flex;
  flex-direction:column;
  gap:4px;
}
.ego-sublink{
  display:block;
  padding:8px 10px;
  border-radius:12px;
  border:1px solid transparent;
  color: var(--ego-white-78);
  text-decoration:none;
  font-weight:520;
  transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.ego-sublink:hover{
  background: rgba(34,211,238,.09);
  border-color: rgba(34,211,238,.14);
  box-shadow: 0 10px 24px rgba(0,0,0,.12);
  color:#e9fdff;
}
.ego-sublink.active{
  background: rgba(34,211,238,.12);
  border-color: rgba(34,211,238,.18);
  color:#e9fdff;
}
.ego-logout{ margin:0; }
.ego-sublink--button{ width:100%; text-align:left; background:transparent; cursor:pointer; }

/* Modern marketing polish */
.ego-link--modern{
  background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
  border-color: rgba(255,255,255,.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.20);
}
.ego-link--modern:hover{
  background: linear-gradient(180deg, rgba(34,211,238,.14), rgba(255,255,255,.02));
  border-color: rgba(34,211,238,.22);
  box-shadow: 0 14px 40px rgba(0,0,0,.26);
}
.ego-ic--modern{
  background: rgba(34,211,238,.12);
  border: 1px solid rgba(34,211,238,.16);
}
.ego-badge{
  font-size:11px;font-weight:800;letter-spacing:.4px;
  padding:4px 10px;border-radius:999px;
  color: rgba(255,255,255,.92);
  background: rgba(34,211,238,.16);
  border: 1px solid rgba(34,211,238,.22);
  margin-left:auto;
}
.ego-sub--modern{ margin-top:6px; }
.ego-sublink--modern{ display:flex; align-items:center; gap:10px; }
.ego-sub-ic{
  width:30px;height:30px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.06);
  flex:0 0 auto;
}
.ego-sublink--modern:hover .ego-sub-ic{
  background: rgba(34,211,238,.12);
  border-color: rgba(34,211,238,.18);
  color: var(--ego-aqua);
}
.ego-sub-sep{ height:1px; background: rgba(255,255,255,.10); margin:6px 0; border-radius:999px; }
.ego-sub--nested{ padding-left:18px; margin-top:6px; border-left:1px dashed rgba(255,255,255,.10); }
.ego-sublink--toggle{ justify-content:space-between; }
.ego-sub-caret{ opacity:.75; }

/* ============================================================================
   DESKTOP COLLAPSED
============================================================================ */
@media (min-width: 992px){
  .ego-sidebar{ transition: width .20s ease; }
  .ego-sidebar.ego-collapsed{ width: var(--ego-wc) !important; }

  .ego-sidebar.ego-collapsed .ego-txt,
  .ego-sidebar.ego-collapsed .ego-caret,
  .ego-sidebar.ego-collapsed .ego-badge{ display:none !important; }

  /* logo nhỏ lại khi collapsed */
  .ego-sidebar.ego-collapsed .ego-brand__logo-big{
    height: 34px;
  }

  .ego-sidebar.ego-collapsed .ego-link{ justify-content:center; padding:10px 8px; }
  .ego-sidebar.ego-collapsed .ego-ic{ width:44px;height:44px;border-radius:14px; }
  .ego-sidebar.ego-collapsed .ego-sub{ display:none !important; }

  /* Tooltip */
  .ego-sidebar.ego-collapsed .ego-item::after{
    content: attr(data-title);
    position:absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: rgba(2,6,23,.92);
    border: 1px solid rgba(255,255,255,.14);
    color: rgba(255,255,255,.92);
    padding: 6px 10px;
    border-radius: 10px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events:none;
    transition: opacity .12s ease;
    box-shadow: 0 14px 40px rgba(0,0,0,.30);
    z-index: 4000;
  }
  .ego-sidebar.ego-collapsed .ego-item:hover::after{ opacity:1; }

  /* Pill nổi */
  .ego-toggle{
    position:absolute;
    right:-14px;
    top: 16px;
    z-index: 2500;
  }
}

/* ============================================================================
   FLYOUT
============================================================================ */
.ego-flyout{ position:fixed; z-index:5000; display:none; pointer-events:auto; }
.ego-flyout.show{ display:block; }
.ego-flyout__panel{
  width:260px;
  background:
    radial-gradient(700px 500px at 10% 10%, rgba(34,211,238,.10), transparent 55%),
    linear-gradient(180deg, rgba(15,23,42,.96), rgba(17,28,51,.96));
  border:1px solid rgba(255,255,255,.10);
  border-radius:16px;
  box-shadow: 0 22px 60px rgba(0,0,0,.42);
  overflow:hidden;
}
.ego-flyout__title{
  padding:10px 12px;
  font-weight:800;
  color: rgba(255,255,255,.92);
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.ego-flyout__menu{
  list-style:none;margin:0;padding:8px;
  display:flex;flex-direction:column;gap:6px;
}
.ego-flyout__menu .ego-sublink{ padding:10px 10px; border-radius:12px; }

/* ============================================================================
   MOBILE OFFCANVAS
============================================================================ */
@media (max-width: 991px){
  .ego-sidebar{
    position:fixed;
    top:0;
    left:-110%;
    height: 100dvh !important;
    min-height: 100dvh !important;
    width: min(86vw, 320px);
    max-width: 320px;
    z-index: 1050;
    transition: left .25s ease;
    box-shadow: var(--ego-shadow-2);
    border-radius: 0 18px 18px 0;
  }
  .ego-sidebar.show{ left:0; }

  .ego-sidebar__header{
    position: sticky;
    top: 0;
    z-index: 2;
    background: inherit;
    backdrop-filter: blur(8px);
  }

  .ego-sidebar-overlay{
    position:fixed;
    inset:0;
    background: rgba(0,0,0,.45);
    z-index: 1040;
    display:none;
  }
  .ego-sidebar-overlay.show{ display:block; }

  body.ego-noscroll{ overflow:hidden; touch-action:none; }

  /* mobile không dùng flyout */
  .ego-flyout{ display:none !important; }
}

/* ===== Desktop: sidebar sticky để không bị mất khi scroll ===== */
@media (min-width: 992px){
  #sidebar.ego-sidebar{
    position: sticky !important;
    top: 0 !important;
    height: 100vh !important;
    min-height: 100vh !important;
    align-self: flex-start;
    overflow: hidden !important;
    z-index: 1000;
  }

  #sidebar .ego-sidebar__header{
    flex: 0 0 auto;
  }

  #sidebar .ego-sidebar__scroll{
    flex: 1 1 auto;
    height: calc(100vh - 68px) !important; /* nếu header cao khác -> sửa số này */
    overflow-y: auto !important;
    overflow-x: hidden !important;
    max-height: none !important;
  }
}
/* ============================================================================
   PREMIUM COMPACT SIDEBAR + MINI STATUS FOOTER
   Dán nguyên file là chạy. Khối này override style cũ ở phía trên.
============================================================================ */
:root{
  --ego-w: 276px;
  --ego-wc: 78px;
  --ego-compact-font: 13px;
  --ego-compact-sub-font: 12.2px;
  --ego-status-bg: rgba(2, 8, 23, .54);
  --ego-status-border: rgba(148, 163, 184, .16);
}

.ego-sidebar{
  background:
    radial-gradient(900px 520px at -25% 0%, rgba(34,211,238,.15), transparent 55%),
    radial-gradient(700px 460px at 110% 18%, rgba(14,165,233,.12), transparent 52%),
    linear-gradient(180deg, #0b1427 0%, #0e1930 48%, #0a1020 100%);
  box-shadow: inset -1px 0 0 rgba(255,255,255,.06);
}

.ego-sidebar__header{
  padding: 10px 12px;
  min-height: 66px;
}

.ego-brand__logo-big{
  height: 39px;
}

.ego-sidebar__scroll{
  padding: 8px 8px 8px;
  min-height: 0;
  scrollbar-width: thin;
  scrollbar-color: rgba(34,211,238,.42) transparent;
}

.ego-sidebar__scroll::-webkit-scrollbar{ width: 5px; }
.ego-sidebar__scroll::-webkit-scrollbar-track{ background: transparent; }
.ego-sidebar__scroll::-webkit-scrollbar-thumb{
  background: linear-gradient(180deg, rgba(34,211,238,.55), rgba(14,165,233,.24));
  border-radius: 999px;
}

.ego-nav{
  gap: 4px;
  padding-top: 5px;
}

.ego-link{
  min-height: 44px;
  gap: 9px;
  padding: 7px 9px;
  border-radius: 15px;
  font-size: var(--ego-compact-font);
  line-height: 1.15;
  letter-spacing: -.012em;
  font-weight: 720;
}

.ego-link:hover{
  transform: translateX(1px);
}

.ego-link.active{
  background:
    linear-gradient(135deg, rgba(34,211,238,.22), rgba(14,165,233,.08)),
    rgba(255,255,255,.025);
  border-color: rgba(34,211,238,.30);
  box-shadow:
    0 12px 28px rgba(0,0,0,.22),
    inset 0 1px 0 rgba(255,255,255,.08);
}

.ego-ic{
  width: 34px;
  height: 34px;
  border-radius: 12px;
  font-size: 14px;
  background:
    linear-gradient(180deg, rgba(255,255,255,.085), rgba(255,255,255,.035));
  border: 1px solid rgba(255,255,255,.055);
}

.ego-link.active .ego-ic{
  background:
    radial-gradient(circle at 30% 20%, rgba(255,255,255,.20), transparent 45%),
    linear-gradient(135deg, rgba(34,211,238,.36), rgba(14,165,233,.18));
  border-color: rgba(34,211,238,.28);
}

.ego-txt{
  letter-spacing: -.018em;
}

.ego-caret{
  font-size: 11px;
}

.ego-sub{
  margin: 5px 0 4px;
  padding-left: 43px;
  gap: 3px;
}

.ego-sublink{
  min-height: 32px;
  padding: 7px 9px;
  border-radius: 12px;
  font-size: var(--ego-compact-sub-font);
  line-height: 1.15;
  font-weight: 650;
  letter-spacing: -.01em;
}

.ego-sublink.active{
  background:
    linear-gradient(135deg, rgba(34,211,238,.18), rgba(14,165,233,.06));
  border-color: rgba(34,211,238,.20);
}

.ego-badge{
  font-size: 9px;
  padding: 3px 7px;
  letter-spacing: .35px;
}

.ego-divider{
  margin: 7px 8px;
  opacity: .7;
}

.ego-sub-ic{
  width: 25px;
  height: 25px;
  border-radius: 9px;
  font-size: 12px;
}

/* Footer */
.ego-sidebar__footer{
  flex: 0 0 auto;
  padding: 8px 10px 12px;
  border-top: 1px solid rgba(255,255,255,.065);
  background:
    linear-gradient(180deg, rgba(15,23,42,0), rgba(2,8,23,.22));
}

.ego-status-card{
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 11px;
  background:
    linear-gradient(135deg, rgba(255,255,255,.075), rgba(255,255,255,.025)),
    var(--ego-status-bg);
  border: 1px solid var(--ego-status-border);
  box-shadow:
    0 18px 40px rgba(0,0,0,.30),
    inset 0 1px 0 rgba(255,255,255,.075);
  backdrop-filter: blur(14px);
}

.ego-status-card__glow{
  position: absolute;
  inset: -42px -60px auto auto;
  width: 130px;
  height: 130px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(34,211,238,.22), transparent 64%);
  pointer-events: none;
}

.ego-status-card__top{
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 9px;
}

.ego-status-card__heading{
  min-width: 0;
}

.ego-status-card__title{
  display: flex;
  align-items: center;
  gap: 7px;
  color: rgba(255,255,255,.92);
  font-size: 12px;
  line-height: 1.1;
  font-weight: 850;
  letter-spacing: -.01em;
}

.ego-status-card__hint{
  margin-top: 4px;
  color: rgba(226,232,240,.56);
  font-size: 10.5px;
  line-height: 1.1;
  font-weight: 600;
}

.ego-status-pulse{
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #22c55e;
  box-shadow: 0 0 0 4px rgba(34,197,94,.13), 0 0 18px rgba(34,197,94,.55);
  animation: egoPulse 1.6s ease-out infinite;
  flex: 0 0 auto;
}

.ego-status-chip{
  position: relative;
  padding: 4px 7px;
  border-radius: 999px;
  color: #a7f3d0;
  background: rgba(16,185,129,.12);
  border: 1px solid rgba(16,185,129,.20);
  font-size: 9.5px;
  line-height: 1;
  font-weight: 900;
  letter-spacing: .5px;
}

.ego-status-grid{
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ego-status-row{
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 31px;
  padding: 7px 8px;
  border-radius: 13px;
  background: rgba(15,23,42,.44);
  border: 1px solid rgba(255,255,255,.055);
}

.ego-status-row__label{
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  color: rgba(226,232,240,.72);
  font-size: 11.2px;
  line-height: 1.1;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ego-status-row__label i{
  width: 18px;
  height: 18px;
  border-radius: 7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10.5px;
  flex: 0 0 auto;
}

.ego-status-row strong{
  color: rgba(255,255,255,.96);
  font-size: 13px;
  line-height: 1;
  font-weight: 900;
  letter-spacing: -.02em;
}

.ego-status-row--online .ego-status-row__label i{
  color: #67e8f9;
  background: rgba(34,211,238,.12);
}

.ego-status-row--work .ego-status-row__label i{
  color: #86efac;
  background: rgba(34,197,94,.12);
}

.ego-status-row--people .ego-status-row__label i{
  color: #c4b5fd;
  background: rgba(139,92,246,.13);
}

.ego-status-card__bottom{
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 8px;
  min-height: 30px;
  padding: 7px 8px;
  border-radius: 13px;
  color: #cffafe;
  text-decoration: none;
  font-size: 11.2px;
  line-height: 1.1;
  font-weight: 800;
  background:
    linear-gradient(135deg, rgba(34,211,238,.18), rgba(14,165,233,.08));
  border: 1px solid rgba(34,211,238,.20);
}

.ego-status-card__bottom:hover{
  color: #ffffff;
  border-color: rgba(34,211,238,.34);
  box-shadow: 0 12px 28px rgba(34,211,238,.10);
}

.ego-status-card__bottom--muted{
  color: rgba(226,232,240,.70);
  background: rgba(255,255,255,.045);
  border-color: rgba(255,255,255,.07);
}

@keyframes egoPulse{
  0% { box-shadow: 0 0 0 0 rgba(34,197,94,.34), 0 0 18px rgba(34,197,94,.50); }
  70% { box-shadow: 0 0 0 7px rgba(34,197,94,0), 0 0 18px rgba(34,197,94,.50); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0), 0 0 18px rgba(34,197,94,.50); }
}

/* Desktop collapsed */
@media (min-width: 992px){
  #sidebar.ego-sidebar{
    display: flex !important;
    flex-direction: column !important;
  }

  #sidebar .ego-sidebar__scroll{
    height: auto !important;
    min-height: 0 !important;
    flex: 1 1 auto !important;
  }

  .ego-sidebar.ego-collapsed .ego-sidebar__footer{
    display: none !important;
  }

  .ego-sidebar.ego-collapsed .ego-sidebar__header{
    padding-left: 8px;
    padding-right: 8px;
  }

  .ego-sidebar.ego-collapsed .ego-brand__logo-big{
    height: 30px;
  }

  .ego-sidebar.ego-collapsed .ego-link{
    min-height: 44px;
    padding: 7px;
  }

  .ego-sidebar.ego-collapsed .ego-ic{
    width: 38px;
    height: 38px;
    border-radius: 14px;
  }

  .ego-sidebar.ego-collapsed .ego-item::after{
    font-size: 11.5px;
  }
}

/* Mobile */
@media (max-width: 991px){
  .ego-sidebar__footer{
    padding-bottom: calc(12px + env(safe-area-inset-bottom));
  }

  .ego-link{
    font-size: 13.2px;
  }

  .ego-sublink{
    font-size: 12.5px;
  }
}


/* EGO_PENDING_BADGE_CSS_START */
.ego-count-badge{
  display:inline-flex !important;
  align-items:center !important;
  justify-content:center !important;
  min-width:20px !important;
  height:20px !important;
  padding:0 6px !important;
  margin-left:8px !important;
  border-radius:8px !important;
  background:rgba(34,211,238,.16) !important;
  border:1px solid rgba(34,211,238,.30) !important;
  color:#d7fff8 !important;
  font-size:11px !important;
  font-weight:900 !important;
  line-height:1 !important;
  box-shadow:none !important;
  vertical-align:middle !important;
}

.ego-link > .ego-count-badge{
  margin-left:auto !important;
  flex:0 0 auto !important;
}

.ego-sublink > .ego-count-badge{
  float:right !important;
  margin-top:-2px !important;
}

.ego-sidebar.ego-collapsed .ego-count-badge{
  display:none !important;
}
/* EGO_PENDING_BADGE_CSS_END */

</style>

<style>

/* EGO_HR_MENU_CLEAN_BALANCE_START */
#menuNhanSu{
    padding-left:43px !important;
    gap:3px !important;
}

#menuNhanSu > li{
    list-style:none !important;
    margin:0 !important;
    padding:0 !important;
}

#menuNhanSu .ego-sublink,
#menuNhanSu .ego-hr-clean-link,
#menuNhanSu .ego-recruitment-sidebar-link{
    display:flex !important;
    align-items:center !important;
    justify-content:flex-start !important;
    width:100% !important;
    min-height:32px !important;
    margin:2px 0 !important;
    padding:7px 10px !important;
    border-radius:12px !important;
    border:1px solid transparent !important;
    background:transparent !important;
    color:rgba(255,255,255,.78) !important;
    text-decoration:none !important;
    font-size:12.2px !important;
    font-weight:650 !important;
    line-height:1.15 !important;
    letter-spacing:-.01em !important;
    transform:none !important;
    box-shadow:none !important;
}

#menuNhanSu .ego-sublink:hover,
#menuNhanSu .ego-hr-clean-link:hover,
#menuNhanSu .ego-recruitment-sidebar-link:hover{
    color:#e9fdff !important;
    background:rgba(34,211,238,.09) !important;
    border-color:rgba(34,211,238,.14) !important;
    box-shadow:0 10px 24px rgba(0,0,0,.12) !important;
}

#menuNhanSu .ego-sublink.active,
#menuNhanSu .ego-hr-clean-link.active,
#menuNhanSu .ego-recruitment-sidebar-link.active{
    color:#e9fdff !important;
    background:linear-gradient(135deg, rgba(34,211,238,.18), rgba(14,165,233,.06)) !important;
    border-color:rgba(34,211,238,.20) !important;
}

#menuNhanSu .nav-item{
    list-style:none !important;
    margin:0 !important;
    padding:0 !important;
}

#menuNhanSu .nav-link{
    display:flex !important;
    align-items:center !important;
    width:100% !important;
    min-height:32px !important;
    margin:2px 0 !important;
    padding:7px 10px !important;
    border-radius:12px !important;
    color:rgba(255,255,255,.78) !important;
    font-size:12.2px !important;
    font-weight:650 !important;
    line-height:1.15 !important;
    text-decoration:none !important;
    background:transparent !important;
    border:1px solid transparent !important;
}

#menuNhanSu .nav-link i{
    display:none !important;
}
/* EGO_HR_MENU_CLEAN_BALANCE_END */

</style>

<style>

/* EGO_HR_HANDOVER_MENU_POLISH_START */
#menuNhanSu .ego-hr-clean-link{
    display:flex !important;
    align-items:center !important;
    width:100% !important;
    min-height:32px !important;
    margin:2px 0 !important;
    padding:7px 10px !important;
    border-radius:12px !important;
    color:rgba(255,255,255,.78) !important;
    text-decoration:none !important;
    font-size:12.2px !important;
    font-weight:650 !important;
    line-height:1.15 !important;
}
#menuNhanSu .ego-hr-clean-link:hover{
    color:#e9fdff !important;
    background:rgba(34,211,238,.09) !important;
    border-color:rgba(34,211,238,.14) !important;
}
#menuNhanSu .ego-hr-clean-link.active{
    color:#e9fdff !important;
    background:linear-gradient(135deg, rgba(34,211,238,.18), rgba(14,165,233,.06)) !important;
    border-color:rgba(34,211,238,.20) !important;
}
/* EGO_HR_HANDOVER_MENU_POLISH_END */

</style>


{{-- EGO_SALES_MANAGER_DROPDOWN_START --}}
@php
    $egoSalesManagerOptions = collect();

    try {
        $egoSalesManagerOptions = \App\Models\User::query()
            ->get()
            ->filter(function ($u) {
                $roles = [];

                foreach (['role', 'type', 'position', 'department'] as $field) {
                    if (!empty($u->{$field})) {
                        $roles[] = mb_strtolower((string) $u->{$field});
                    }
                }

                if (method_exists($u, 'getRoleNames')) {
                    foreach ($u->getRoleNames() as $roleName) {
                        $roles[] = mb_strtolower((string) $roleName);
                    }
                }

                $roleText = implode('|', array_unique(array_filter($roles)));

                return str_contains($roleText, 'sales_manager')
                    || str_contains($roleText, 'sales manager')
                    || str_contains($roleText, 'trưởng phòng sales')
                    || str_contains($roleText, 'truong_phong_sales')
                    || str_contains($roleText, 'manager_sales');
            })
            ->map(function ($u) {
                return [
                    'id' => (string) $u->id,
                    'name' => (string) ($u->name ?? $u->email ?? ('User #' . $u->id)),
                ];
            })
            ->values();
    } catch (\Throwable $e) {
        $egoSalesManagerOptions = collect();
    }
@endphp

<script>
(function () {
    var managers = @json($egoSalesManagerOptions);

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function selectLooksLikeSalesOwner(select) {
        var name = cleanText(select.getAttribute('name'));
        var id = cleanText(select.getAttribute('id'));
        var text = cleanText(select.closest('form, .modal, .card, section, div') ? select.closest('form, .modal, .card, section, div').textContent : '');

        return name.indexOf('sales') !== -1
            || name.indexOf('assigned') !== -1
            || name.indexOf('owner') !== -1
            || id.indexOf('sales') !== -1
            || text.indexOf('sales phụ trách') !== -1
            || text.indexOf('sales phu trach') !== -1;
    }

    function hasOption(select, value) {
        return Array.prototype.slice.call(select.options).some(function (opt) {
            return String(opt.value) === String(value);
        });
    }

    function addManagersToSelect(select) {
        if (!select || !selectLooksLikeSalesOwner(select)) return;

        managers.forEach(function (manager) {
            if (!manager || !manager.id || hasOption(select, manager.id)) return;

            var option = document.createElement('option');
            option.value = manager.id;
            option.textContent = manager.name + ' - Sales Manager';
            option.setAttribute('data-ego-sales-manager', '1');

            select.appendChild(option);
        });
    }

    function run() {
        if (!Array.isArray(managers) || managers.length === 0) return;

        document.querySelectorAll('select').forEach(addManagersToSelect);
    }

    document.addEventListener('DOMContentLoaded', run);

    setTimeout(run, 300);
    setTimeout(run, 900);
    setTimeout(run, 1800);

    document.addEventListener('click', function () {
        setTimeout(run, 150);
        setTimeout(run, 500);
    }, true);
})();
</script>
{{-- EGO_SALES_MANAGER_DROPDOWN_END --}}


<script>
/* EGO_SIDE_LANG_JS_START */
(function(){
    const PAGE_LANG = 'vi';

    function setCookie(name, value) {
        document.cookie = name + '=' + value + ';path=/';
        document.cookie = name + '=' + value + ';path=/;domain=' + location.hostname;

        const parts = location.hostname.split('.');
        if (parts.length >= 2) {
            document.cookie = name + '=' + value + ';path=/;domain=.' + parts.slice(-2).join('.');
        }
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    function currentLang() {
        const val = getCookie('googtrans');
        const match = val.match(/\/vi\/([^/]+)/);
        return match ? match[1] : 'vi';
    }

    function markActive(lang) {
        document.querySelectorAll('.ego-side-lang-btn').forEach(function(btn){
            btn.classList.toggle('active', btn.dataset.egoLang === lang);
        });
    }

    window.googleTranslateElementInit = function(){
        new google.translate.TranslateElement({
            pageLanguage: PAGE_LANG,
            includedLanguages: 'vi,en,zh-CN',
            autoDisplay: false
        }, 'google_translate_element');

        setTimeout(function(){
            markActive(currentLang());
        }, 500);
    };

    function changeLang(lang) {
        markActive(lang);
        setCookie('googtrans', '/vi/' + lang);

        const combo = document.querySelector('.goog-te-combo');

        if (combo) {
            combo.value = lang;
            combo.dispatchEvent(new Event('change'));
            setTimeout(function(){ location.reload(); }, 300);
        } else {
            location.reload();
        }
    }

    document.addEventListener('click', function(e){
        const btn = e.target.closest('.ego-side-lang-btn');
        if (!btn) return;

        e.preventDefault();
        changeLang(btn.dataset.egoLang || 'vi');
    });

    document.addEventListener('DOMContentLoaded', function(){
        markActive(currentLang());
    });

    if (!document.querySelector('script[src*="translate.google.com/translate_a/element.js"]')) {
        const script = document.createElement('script');
        script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
        document.head.appendChild(script);
    }
})();
/* EGO_SIDE_LANG_JS_END */
</script>


<!-- EGO_STATUS_FINAL_V4_START -->
<style>
    html body #sidebar .ego-sidebar__footer{
        width:100% !important;
        padding:0 8px 10px !important;
        margin:0 !important;
        box-sizing:border-box !important;
    }

    html body #sidebar .ego-status-card-final-v4{
        position:relative !important;
        width:100% !important;
        max-width:100% !important;
        margin:0 !important;
        padding:12px 10px 11px !important;
        border-radius:18px !important;
        overflow:hidden !important;
        box-sizing:border-box !important;
        transform:none !important;
    }

    html body #sidebar .ego-status-final-head{
        display:flex !important;
        align-items:flex-start !important;
        justify-content:space-between !important;
        gap:8px !important;
        margin-bottom:9px !important;
    }

    html body #sidebar .ego-status-final-actions{
        display:flex !important;
        align-items:center !important;
        gap:6px !important;
        flex:0 0 auto !important;
    }

    html body #sidebar .ego-status-final-toggle{
        width:25px !important;
        height:25px !important;
        min-width:25px !important;
        min-height:25px !important;
        padding:0 !important;
        border:1px solid rgba(45,212,191,.45) !important;
        border-radius:999px !important;
        background:rgba(15,118,110,.28) !important;
        color:#9ff7ee !important;
        display:flex !important;
        align-items:center !important;
        justify-content:center !important;
        cursor:pointer !important;
        font-size:12px !important;
        line-height:1 !important;
        box-shadow:none !important;
        appearance:none !important;
    }

    html body #sidebar .ego-company-picker-final{
        width:100% !important;
        min-height:40px !important;
        display:grid !important;
        grid-template-columns:minmax(0,1fr) auto !important;
        align-items:center !important;
        gap:8px !important;
        padding:7px 9px !important;
        margin:0 0 8px !important;
        border-radius:15px !important;
        background:rgba(15,23,42,.28) !important;
        border:1px solid rgba(255,255,255,.07) !important;
        box-sizing:border-box !important;
    }

    html body #sidebar .ego-company-picker-final__label{
        min-width:0 !important;
        display:flex !important;
        align-items:center !important;
        gap:8px !important;
        color:#e5edf7 !important;
        font-size:12px !important;
        font-weight:900 !important;
        white-space:nowrap !important;
        overflow:hidden !important;
        text-overflow:ellipsis !important;
    }

    html body #sidebar .ego-company-picker-final__label i{
        color:#22d3ee !important;
        font-size:13px !important;
    }

    html body #sidebar .ego-company-picker-final__switch{
        justify-self:end !important;
        display:flex !important;
        align-items:center !important;
        gap:4px !important;
        padding:4px !important;
        border-radius:999px !important;
        background:rgba(15,23,42,.62) !important;
        border:1px solid rgba(148,163,184,.18) !important;
        box-sizing:border-box !important;
    }

    html body #sidebar .ego-company-picker-final__switch form{
        margin:0 !important;
        padding:0 !important;
        display:block !important;
        line-height:0 !important;
    }

    html body #sidebar .ego-company-picker-final__btn{
        appearance:none !important;
        width:35px !important;
        height:24px !important;
        min-width:35px !important;
        max-width:35px !important;
        padding:0 !important;
        margin:0 !important;
        border:0 !important;
        border-radius:999px !important;
        background:transparent !important;
        color:#cbd5e1 !important;
        font-size:10px !important;
        font-weight:950 !important;
        line-height:24px !important;
        text-align:center !important;
        cursor:pointer !important;
        box-shadow:none !important;
    }

    html body #sidebar .ego-company-picker-final__btn.active{
        background:linear-gradient(135deg,#2dd4bf,#22d3ee) !important;
        color:#052f3a !important;
        box-shadow:0 7px 16px rgba(45,212,191,.28) !important;
    }

    html body #sidebar .ego-status-grid{
        display:flex !important;
        flex-direction:column !important;
        gap:7px !important;
        width:100% !important;
    }

    html body #sidebar .ego-status-row{
        display:grid !important;
        grid-template-columns:minmax(0,1fr) auto !important;
        align-items:center !important;
        gap:8px !important;
        width:100% !important;
        min-height:37px !important;
        padding:8px 10px !important;
        border-radius:14px !important;
        background:rgba(15,23,42,.26) !important;
        border:1px solid rgba(255,255,255,.065) !important;
        box-sizing:border-box !important;
    }

    html body #sidebar .ego-status-row > *:not(.ego-status-row__label):not(strong){
        display:none !important;
    }

    html body #sidebar .ego-status-row__label{
        min-width:0 !important;
        display:flex !important;
        align-items:center !important;
        gap:8px !important;
        color:#e5edf7 !important;
        font-size:12px !important;
        font-weight:900 !important;
        white-space:nowrap !important;
        overflow:hidden !important;
        text-overflow:ellipsis !important;
    }

    html body #sidebar .ego-status-row strong{
        justify-self:end !important;
        position:static !important;
        transform:none !important;
        font-size:15px !important;
        font-weight:950 !important;
        color:#fff !important;
    }

    html body #sidebar .ego-status-card__bottom{
        width:100% !important;
        margin-top:10px !important;
        height:32px !important;
        border-radius:13px !important;
        display:flex !important;
        align-items:center !important;
        justify-content:center !important;
        gap:7px !important;
        font-size:12px !important;
        font-weight:900 !important;
    }

    html body #sidebar .ego-side-lang-final{
        width:100% !important;
        margin:10px 0 0 !important;
        padding:4px !important;
        border-radius:18px !important;
        background:rgba(15,23,42,.58) !important;
        border:1px solid rgba(148,163,184,.20) !important;
        box-shadow:none !important;
        box-sizing:border-box !important;
    }

    html body #sidebar .ego-side-lang-final .ego-side-lang-switch{
        display:grid !important;
        grid-template-columns:repeat(3,1fr) !important;
        gap:5px !important;
        width:100% !important;
    }

    html body #sidebar .ego-side-lang-final .ego-side-lang-btn{
        appearance:none !important;
        border:0 !important;
        outline:0 !important;
        box-shadow:none !important;
        width:100% !important;
        height:30px !important;
        border-radius:999px !important;
        background:transparent !important;
        color:#cbd5e1 !important;
        font-size:11px !important;
        font-weight:950 !important;
        cursor:pointer !important;
    }

    html body #sidebar .ego-side-lang-final .ego-side-lang-btn.active{
        background:linear-gradient(135deg,#2dd4bf,#22d3ee) !important;
        color:#052f3a !important;
        box-shadow:0 8px 18px rgba(45,212,191,.25) !important;
    }

    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed{
        max-height:50px !important;
        min-height:50px !important;
    }

    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed .ego-status-final-toggle i{
        transform:rotate(-90deg) !important;
    }

    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed .ego-company-picker-final,
    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed .ego-status-grid,
    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed .ego-status-card__bottom,
    html body #sidebar .ego-status-card-final-v4.ego-status-final-collapsed .ego-side-lang-final{
        display:none !important;
    }

    html body #sidebar .ego-company-live-tiny,
    html body #sidebar .ego-company-live-tab,
    html body #sidebar .ego-company-live-btn,
    html body #sidebar .ego-company-direct-row,
    html body #sidebar .ego-company-switch-row,
    html body #sidebar .ego-company-final-row,
    html body #sidebar .ego-company-hard-row,
    html body #sidebar .ego-company-real-row,
    html body #sidebar .ego-company-clean-row,
    html body #sidebar .ego-company-context-row,
    html body #sidebar .ego-company-choice-row{
        display:none !important;
    }
</style>

<script>
(function(){
    function cleanStatusCardFinal(){
        var sidebar = document.querySelector('#sidebar');
        if(!sidebar) return;

        sidebar.querySelectorAll(
            '.ego-company-live-tiny,' +
            '.ego-company-live-tab,' +
            '.ego-company-live-btn,' +
            '.ego-company-direct-row,' +
            '.ego-company-switch-row,' +
            '.ego-company-final-row,' +
            '.ego-company-hard-row,' +
            '.ego-company-real-row,' +
            '.ego-company-clean-row,' +
            '.ego-company-context-row,' +
            '.ego-company-choice-row'
        ).forEach(function(el){
            el.remove();
        });

        sidebar.querySelectorAll('.ego-status-row').forEach(function(row){
            Array.prototype.slice.call(row.children).forEach(function(child){
                if(child.matches('.ego-status-row__label, strong')) return;
                child.remove();
            });
        });
    }

    function initStatusFinal(){
        cleanStatusCardFinal();

        var card = document.querySelector('#sidebar .ego-status-card-final-v4');
        if(!card) return;

        var btn = card.querySelector('.ego-status-final-toggle');
        if(btn && !btn.dataset.ready){
            btn.dataset.ready = '1';
            btn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();

                card.classList.toggle('ego-status-final-collapsed');

                try{
                    localStorage.setItem(
                        'ego_status_final_collapsed',
                        card.classList.contains('ego-status-final-collapsed') ? '1' : '0'
                    );
                }catch(err){}
            });
        }

        try{
            if(localStorage.getItem('ego_status_final_collapsed') === '1'){
                card.classList.add('ego-status-final-collapsed');
            }
        }catch(err){}
    }

    document.addEventListener('DOMContentLoaded', initStatusFinal);
    setTimeout(initStatusFinal, 100);
    setTimeout(initStatusFinal, 500);
    setTimeout(initStatusFinal, 1200);
    setTimeout(cleanStatusCardFinal, 1600);
})();
</script>
<!-- EGO_STATUS_FINAL_V4_END -->


<!-- EGO_COMPANY_ACTIVE_FIX_START -->
<script>
(function(){
    function refreshCompanyActive(companyId){
        if(!companyId) return;

        document.querySelectorAll('#sidebar .ego-company-picker-final__btn, #sidebar .ego-status-company-v3-btn').forEach(function(btn){
            var id = btn.getAttribute('data-company-id') || '';
            btn.classList.toggle('active', String(id) === String(companyId));
        });
    }

    function initCompanyActiveFix(){
        var saved = null;

        try{
            saved = localStorage.getItem('ego_active_company_id');
        }catch(e){}

        if(saved){
            refreshCompanyActive(saved);
        }

        document.querySelectorAll('#sidebar form input[name="company_id"]').forEach(function(input){
            var form = input.closest('form');
            var btn = form ? form.querySelector('button') : null;

            if(btn){
                btn.setAttribute('data-company-id', input.value);

                form.addEventListener('submit', function(){
                    try{
                        localStorage.setItem('ego_active_company_id', input.value);
                    }catch(e){}

                    refreshCompanyActive(input.value);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initCompanyActiveFix);
    setTimeout(initCompanyActiveFix, 200);
    setTimeout(initCompanyActiveFix, 800);
})();
</script>
<!-- EGO_COMPANY_ACTIVE_FIX_END -->

{{-- EGO_ROLE_PERMISSION_SIDEBAR_GUARD --}}
@includeIf('admin.role-permissions.partials.sidebar-guard')

{{-- EGO_TECH_MENU_CLEAN_V5_START --}}
<style>
/* =========================================================
   MENU WORKSPACE KỸ THUẬT — CLEAN V5
========================================================= */

.ego-tech-menu {
    margin-bottom: 3px !important;
}

/* Nhóm menu chính */
.ego-tech-menu > .ego-link {
    min-height: 42px !important;
    padding: 7px 9px !important;
    border-radius: 12px !important;
    gap: 9px !important;
}

/* Số thứ tự nhỏ và nhẹ hơn */
.ego-tech-menu-index {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 21px !important;
    height: 21px !important;
    min-width: 21px !important;
    border-radius: 7px !important;

    background: rgba(255, 255, 255, .07) !important;
    border: 1px solid rgba(255, 255, 255, .08) !important;
    color: rgba(226, 232, 240, .88) !important;

    font-size: 10px !important;
    font-weight: 800 !important;
    box-shadow: none !important;
}

.ego-tech-menu > .ego-link:hover .ego-tech-menu-index,
.ego-tech-menu > .ego-link.active .ego-tech-menu-index {
    background: rgba(34, 211, 238, .16) !important;
    border-color: rgba(34, 211, 238, .25) !important;
    color: #dffcff !important;
}

/* Tên nhóm */
.ego-tech-group-label {
    font-size: 13px !important;
    font-weight: 760 !important;
    letter-spacing: -.01em !important;
}

/* Submenu gọn, không thụt quá sâu */
.ego-tech-menu > .ego-sub {
    position: relative !important;

    margin: 4px 0 8px 13px !important;
    padding: 4px 0 4px 13px !important;

    gap: 2px !important;
    border-left: 1px solid rgba(148, 163, 184, .17) !important;
}

/* Xóa dấu chấm cũ gây rối */
.ego-tech-menu .ego-sublink::before {
    display: none !important;
    content: none !important;
}

/* Link submenu */
.ego-tech-menu .ego-sublink {
    display: flex !important;
    align-items: center !important;

    width: 100% !important;
    min-height: 34px !important;

    margin: 0 !important;
    padding: 8px 10px !important;

    border: 1px solid transparent !important;
    border-radius: 9px !important;

    color: rgba(203, 213, 225, .78) !important;
    background: transparent !important;

    font-size: 12.5px !important;
    font-weight: 600 !important;
    line-height: 1.25 !important;

    white-space: normal !important;
    box-shadow: none !important;
}

.ego-tech-menu .ego-sublink:hover {
    color: #f1fbff !important;
    background: rgba(34, 211, 238, .075) !important;
    border-color: rgba(34, 211, 238, .10) !important;
}

.ego-tech-menu .ego-sublink.active {
    color: #eaffff !important;
    background:
        linear-gradient(
            90deg,
            rgba(34, 211, 238, .15),
            rgba(14, 165, 233, .055)
        ) !important;

    border-color: rgba(34, 211, 238, .20) !important;
    box-shadow: inset 3px 0 0 rgba(34, 211, 238, .82) !important;
}

/* Không cho nút tạo xuất hiện trong menu */
.ego-tech-menu .ego-sublink--create {
    display: none !important;
}

/* Khoảng cách giữa các nhóm */
.ego-tech-menu + .ego-tech-menu {
    margin-top: 2px !important;
}

/* Badge */
.ego-tech-menu .ego-count-badge {
    min-width: 19px !important;
    height: 19px !important;
    padding: 0 5px !important;
    margin-left: auto !important;
    border-radius: 7px !important;
    font-size: 10px !important;
}

/* Mobile */
@media (max-width: 991px) {
    .ego-tech-menu > .ego-link {
        min-height: 44px !important;
    }

    .ego-tech-menu > .ego-sub {
        margin-left: 12px !important;
        padding-left: 12px !important;
    }

    .ego-tech-menu .ego-sublink {
        min-height: 38px !important;
        padding: 9px 10px !important;
        font-size: 12.8px !important;
    }
}
</style>
{{-- EGO_TECH_MENU_CLEAN_V5_END --}}

{{-- EGO_TECH_MENU_ACCORDION_V5_START --}}
<script>
(function () {
    function initTechnicalMenuAccordion() {
        const panels = Array.from(
            document.querySelectorAll(
                '#menuTechProjects,' +
                '#menuTechCoordination,' +
                '#menuTechDocuments,' +
                '#menuTechWarranty,' +
                '#menuTechReports'
            )
        );

        if (!panels.length) {
            return;
        }

        panels.forEach((panel) => {
            panel.addEventListener(
                'show.bs.collapse',
                function () {
                    panels.forEach((otherPanel) => {
                        if (
                            otherPanel === panel ||
                            !otherPanel.classList.contains('show')
                        ) {
                            return;
                        }

                        if (
                            window.bootstrap &&
                            window.bootstrap.Collapse
                        ) {
                            const collapse =
                                window.bootstrap.Collapse
                                    .getOrCreateInstance(
                                        otherPanel,
                                        { toggle: false }
                                    );

                            collapse.hide();
                        } else {
                            otherPanel.classList.remove('show');

                            const toggle = document.querySelector(
                                '[href="#' + otherPanel.id + '"]'
                            );

                            if (toggle) {
                                toggle.classList.remove('active');
                                toggle.setAttribute(
                                    'aria-expanded',
                                    'false'
                                );
                            }
                        }
                    });
                }
            );
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initTechnicalMenuAccordion
        );
    } else {
        initTechnicalMenuAccordion();
    }
})();
</script>
{{-- EGO_TECH_MENU_ACCORDION_V5_END --}}

{{-- EGO_FORCE_DEPARTMENT_NAVY_START --}}
<style>
/* Chỉ áp dụng khi có menu Workspace V4; Admin/Giám đốc không bị ảnh hưởng */
@media (min-width:992px){
    html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card){
        width:252px!important;
        min-width:252px!important;
        max-width:252px!important;
        flex:0 0 252px!important;
    }
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card){
    background:
        radial-gradient(circle at 20% 0%,rgba(8,145,178,.18),transparent 30%),
        linear-gradient(180deg,#071827 0%,#071524 54%,#05111e 100%)!important;
    border-right:1px solid rgba(34,211,238,.14)!important;
    color:#e5edf6!important;
    box-shadow:8px 0 28px rgba(2,8,23,.18)!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sidebar__header,
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sidebar__scroll,
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sidebar__footer,
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-nav{
    background:transparent!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sidebar__header{
    min-height:66px!important;
    padding:12px 16px!important;
    border-bottom:1px solid rgba(148,163,184,.12)!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-brand--logoonly{
    justify-content:flex-start!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-brand__logo-big{
    width:auto!important;
    height:31px!important;
    max-width:128px!important;
    object-fit:contain!important;
    filter:none!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-toggle{
    width:31px!important;
    height:31px!important;
    background:#10243a!important;
    border:1px solid rgba(148,163,184,.22)!important;
    color:#d7e3ef!important;
}

/* Thẻ Workspace */
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-v4-workspace-card{
    margin:12px 10px 15px!important;
    padding:11px!important;
    border:1px solid rgba(34,211,238,.25)!important;
    border-radius:12px!important;
    background:linear-gradient(145deg,#0b3047,#0a263b)!important;
    box-shadow:0 10px 22px rgba(0,0,0,.18)!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-v4-workspace-card__label{
    color:#7891a8!important;
    font-size:8px!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-v4-workspace-card__current strong{
    color:#fff!important;
    font-size:12px!important;
    white-space:nowrap!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-v4-workspace-card__actions{
    grid-template-columns:1fr 1fr!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-v4-workspace-card__actions a{
    height:29px!important;
    background:#102b42!important;
    border-color:rgba(148,163,184,.18)!important;
    color:#d7e3ef!important;
    font-size:9px!important;
}

/* Tiêu đề nhóm */
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-excel-section{
    margin:14px 12px 5px!important;
    border-color:rgba(148,163,184,.12)!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-excel-section > span{
    color:#7089a0!important;
    font-size:8.5px!important;
}

/* Menu phòng ban */
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-link{
    min-height:42px!important;
    margin:1px 2px!important;
    padding:7px 9px!important;
    border-radius:9px!important;
    border:1px solid transparent!important;
    background:transparent!important;
    color:#c4d2df!important;
    box-shadow:none!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-link .ego-ic{
    width:28px!important;
    height:28px!important;
    flex:0 0 28px!important;
    border-radius:8px!important;
    background:rgba(148,163,184,.08)!important;
    color:#8da6bb!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-link .ego-txt{
    color:inherit!important;
    font-size:11.5px!important;
    font-weight:750!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-link:hover{
    background:rgba(8,145,178,.13)!important;
    border-color:rgba(34,211,238,.14)!important;
    color:#fff!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-link.active{
    background:linear-gradient(90deg,rgba(8,145,178,.34),rgba(14,116,144,.16))!important;
    border-color:rgba(34,211,238,.23)!important;
    color:#fff!important;
    box-shadow:inset 3px 0 0 #22d3ee!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-link.active .ego-ic{
    background:#078ca2!important;
    color:#fff!important;
}

/* Menu con */
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sub,
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-excel-nested{
    border-color:rgba(34,211,238,.14)!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sublink{
    color:#8fa6ba!important;
    font-size:10.5px!important;
}

html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sublink:hover,
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card) .ego-sublink.active{
    background:rgba(8,145,178,.13)!important;
    color:#fff!important;
}

/* Thanh cuộn */
html body #sidebar.ego-sidebar:has(.ego-v4-workspace-card)
.ego-sidebar__scroll{
    padding:4px 8px 13px!important;
    scrollbar-color:rgba(34,211,238,.30) transparent!important;
}
</style>
{{-- EGO_FORCE_DEPARTMENT_NAVY_END --}}