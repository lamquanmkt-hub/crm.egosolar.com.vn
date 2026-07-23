
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

@php
    /*
    |--------------------------------------------------------------------------
    | SIDEBAR MINI STATUS - SELF CONTAINED
    |--------------------------------------------------------------------------
    | File này tự tính 3 số ở khung dưới sidebar:
    | - Đang online: users.last_seen_at trong 5 phút gần nhất
    | - Đang làm việc: attendance_records có work_date hôm nay + check_in_at không rỗng
    | - Nhân viên hoạt động: users.is_active = 1
    |
    | Không bắt buộc phải sửa Controller. Chỉ cần middleware UpdateUserLastSeen
    | đã được đăng ký để cập nhật users.last_seen_at.
    */

    $egoStatusNow = now();

    $egoOnlineCount = 0;
    $egoWorkingToday = 0;
    $egoActiveEmployees = 0;
try {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('users') &&
            \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_seen_at')
        ) {
            $onlineQuery = \Illuminate\Support\Facades\DB::table('users')
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', $egoStatusNow->copy()->subMinutes(5));

            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                $onlineQuery->where('is_active', 1);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'deleted_at')) {
                $onlineQuery->whereNull('deleted_at');
            }

            $egoOnlineCount = (int) $onlineQuery->count();
        } else {
            $egoOnlineCount = auth()->check() ? 1 : 0;
        }
    } catch (\Throwable $e) {
        $egoOnlineCount = auth()->check() ? 1 : 0;
    }

    try {
        /*
         | 
<a href="{{ route('sales-quotations.index') }}"
   class="top-nav-link {{ request()->routeIs('sales-quotations.*') ? 'active' : '' }}"
   style="display:inline-flex;align-items:center;gap:6px;margin-right:18px;font-weight:800;color:#374151;text-decoration:none;">
    📄 Báo giá
</a>
Chấm công thực tế của hệ thống đang dùng:
         | table: attendance_records
         | user: user_id
         | ngày: work_date
         | check-in: check_in_at
         */
        if (
            \Illuminate\Support\Facades\Schema::hasTable('attendance_records') &&
            \Illuminate\Support\Facades\Schema::hasColumn('attendance_records', 'work_date') &&
            \Illuminate\Support\Facades\Schema::hasColumn('attendance_records', 'user_id')
        ) {
            $workingQuery = \Illuminate\Support\Facades\DB::table('attendance_records')
                ->whereDate('work_date', $egoStatusNow->toDateString())
                ->whereNotNull('user_id');

            if (\Illuminate\Support\Facades\Schema::hasColumn('attendance_records', 'check_in_at')) {
                $workingQuery->whereNotNull('check_in_at');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('attendance_records', 'status')) {
                $workingQuery->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', [
                            'absent',
                            'leave',
                            'off',
                            'rejected',
                            'cancelled',
                            'canceled',
                        ]);
                });
            }

            $egoWorkingToday = (int) $workingQuery
                ->distinct()
                ->count('user_id');
        }
    } catch (\Throwable $e) {
        $egoWorkingToday = 0;
    }

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            $employeesQuery = \Illuminate\Support\Facades\DB::table('users');

            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                $employeesQuery->where('is_active', 1);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'deleted_at')) {
                $employeesQuery->whereNull('deleted_at');
            }

            $egoActiveEmployees = (int) $employeesQuery->count();
        }
    } catch (\Throwable $e) {
        $egoActiveEmployees = 0;
    }


    /* EGO_PENDING_BADGES_START */
    $egoPendingOrdersCount = 0;
    $egoPendingMaterialRequestsCount = 0;
    $egoPendingPaymentRequestsCount = 0;
    $egoPendingProposalsCount = 0;

    try {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('crm_order_approvals') &&
            \Illuminate\Support\Facades\Schema::hasColumn('crm_order_approvals', 'status') &&
            \Illuminate\Support\Facades\Schema::hasColumn('crm_order_approvals', 'order_id')
        ) {
            $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['pending'])
                ->distinct()
                ->count('order_id');
        }
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
    }

    try {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('material_requests') &&
            \Illuminate\Support\Facades\Schema::hasColumn('material_requests', 'status')
        ) {
            $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['pending', 'submitted', 'admin_approved'])
                ->count();
        }
    } catch (\Throwable $e) {
        $egoPendingMaterialRequestsCount = 0;
    }

    try {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('payment_requests') &&
            \Illuminate\Support\Facades\Schema::hasColumn('payment_requests', 'status')
        ) {
            $egoPendingPaymentRequestsCount = (int) \Illuminate\Support\Facades\DB::table('payment_requests')
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['pending', 'submitted', 'admin_pending', 'admin_approved', 'accounting_pending'])
                ->count();
        }
    } catch (\Throwable $e) {
        $egoPendingPaymentRequestsCount = 0;
    }

    try {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('proposals') &&
            \Illuminate\Support\Facades\Schema::hasColumn('proposals', 'status')
        ) {
            $egoPendingProposalsCount = (int) \Illuminate\Support\Facades\DB::table('proposals')
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['pending', 'submitted'])
                ->count();
        }
    } catch (\Throwable $e) {
        $egoPendingProposalsCount = 0;
    }
    /* EGO_PENDING_BADGES_END */


    /* EGO_TASK_BADGE_FIXED_START */
    $egoMyUnfinishedTasksCount = 0;

    try {
        if (
            auth()->check() &&
            \Illuminate\Support\Facades\Schema::hasTable('tasks') &&
            \Illuminate\Support\Facades\Schema::hasColumn('tasks', 'assignee_id')
        ) {
            $egoMyTaskQuery = \Illuminate\Support\Facades\DB::table('tasks')
                ->where('assignee_id', auth()->id());

            if (\Illuminate\Support\Facades\Schema::hasColumn('tasks', 'status')) {
                $egoMyTaskQuery->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn(
                            \Illuminate\Support\Facades\DB::raw('LOWER(status)'),
                            ['approved', 'done', 'completed', 'complete', 'closed', 'cancelled', 'canceled']
                        );
                });
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('tasks', 'deleted_at')) {
                $egoMyTaskQuery->whereNull('deleted_at');
            }

            $egoMyUnfinishedTasksCount = (int) $egoMyTaskQuery->count();
        }
    } catch (\Throwable $e) {
        $egoMyUnfinishedTasksCount = 0;
    }
    /* EGO_TASK_BADGE_FIXED_END */


    $egoStatusItems = [
        [
            'key'   => 'online',
            'label' => 'Đang online',
            'value' => $egoOnlineCount,
            'icon'  => 'bi-wifi',
            'tone'  => 'online',
        ],
        [
            'key'   => 'working',
            'label' => 'Đang làm việc',
            'value' => $egoWorkingToday,
            'icon'  => 'bi-person-check',
            'tone'  => 'work',
        ],
        [
            'key'   => 'employees',
            'label' => 'Nhân viên hoạt động',
            'value' => $egoActiveEmployees,
            'icon'  => 'bi-people',
            'tone'  => 'people',
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

    $egoCanDashboardMenu = $egoCanSeeMenu('menu.dashboard');
    $egoCanBookingMenu = $egoCanSeeMenu('menu.booking');
    $egoCanCustomerMenu = $egoCanSeeMenu('menu.customers');
    $egoCanOrdersMenu = $egoCanSeeMenu('menu.orders');
    $egoCanProjectTestMenu = $egoCanSeeMenu('menu.project_test');
    $egoCanConstructionMenu = $egoCanSeeMenu('menu.sites');
    $egoCanPaymentRequestsMenu = $egoCanSeeMenu('menu.payment_requests');
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

<nav id="sidebar" class="ego-sidebar" aria-label="Main sidebar">
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

            {{-- DASHBOARD --}}
            <li class="ego-item" data-title="Trang chủ" data-ego-menu-permission="menu.dashboard">
                <a href="{{ route('dashboard') }}"
                   class="ego-link {{ active_route('dashboard') }}"
                   data-ego-type="nav">
                    <span class="ego-ic"><i class="bi bi-house-door"></i></span>
                    <span class="ego-txt">Trang chủ</span>
                </a>
            </li>
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
                       class="ego-link {{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') ? 'true' : 'false' }}"
                       aria-controls="menuKhachHang">
                        <span class="ego-ic"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="ego-txt">Khách hàng</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuKhachHang"
                        class="ego-sub collapse {{ request()->routeIs('customers.*') || request()->routeIs('customer-profiles.*') ? 'show' : '' }}"
                        data-ego-submenu>
                        <li>
                            <a href="{{ route('customers.index') }}"
                               class="ego-sublink {{ active_route('customers.index') }}"
                               data-ego-type="nav">
                                Danh sách KH
                            </a>
                        </li>

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

                    


                    


                    @if($egoCanWarrantyLookupMenu && \Illuminate\Support\Facades\Route::has('serial-warranty.index'))
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

            {{-- EGO_PROJECT_TEST_NEW_MENU_START --}}
            @can('project-test.access')
                <li class="ego-item" data-title="Công Trình Test new" data-ego-menu-permission="menu.project_test">
                    <a href="{{ route('project-test.index') }}"
                       class="ego-link {{ request()->routeIs('project-test.*') && !request()->routeIs('project-test.warehouse.*') ? 'active' : '' }}"
                       data-ego-type="nav"
                       style="position:relative;overflow:hidden;">
                        <span class="ego-ic" style="background:linear-gradient(135deg,rgba(20,184,166,.28),rgba(37,99,235,.22));color:#67e8f9;box-shadow:0 0 20px rgba(34,211,238,.15);"><i class="bi bi-diagram-3"></i></span>
                        <span class="ego-txt">Công Trình Test new</span>
                        <span style="margin-left:auto;padding:3px 7px;border-radius:999px;background:linear-gradient(135deg,#fb7185,#e11d48);color:#fff;font-size:8px;font-weight:950;letter-spacing:.08em;box-shadow:0 5px 15px rgba(225,29,72,.35);animation:egoBookingNewPulse 1.5s ease-in-out infinite;">NEW</span>
                    </a>
                </li>
            @endcan
            {{-- EGO_PROJECT_TEST_NEW_MENU_END --}}

            {{-- CÔNG TRÌNH --}}
            @if($egoCanConstructionMenu)
                <li class="ego-item ego-item--has-sub" data-title="Công trình" data-ego-sub="true" data-ego-menu-permission="menu.sites">
                    <a href="#menuConstruction"
                       class="ego-link {{ request()->is('cong-trinh*') || request()->is('don-vat-tu*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->is('cong-trinh*') || request()->is('don-vat-tu*') ? 'true' : 'false' }}"
                       aria-controls="menuConstruction">
                        <span class="ego-ic"><i class="bi bi-kanban"></i></span>
                        <span class="ego-txt">Công trình</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuConstruction"
                        class="ego-sub collapse {{ request()->is('cong-trinh*') || request()->is('don-vat-tu*') ? 'show' : '' }}"
                        data-ego-submenu>
                        {{-- EGO_SITE_WORKSPACE_V2_MENU_START --}}
                        @if($egoCanSiteWorkspaceMenu)
                            <li>
                                <a href="{{ route('sites-v2.index') }}"
                                   class="ego-sublink {{ request()->routeIs('sites-v2.*') ? 'active' : '' }}"
                                   data-ego-type="nav">
                                    Điều phối mới
                                    <span style="margin-left:6px;padding:2px 6px;border-radius:999px;background:#0f766e;color:#fff;font-size:9px;font-weight:800;">BETA</span>
                                </a>
                            </li>
                        @endif
                        {{-- EGO_SITE_WORKSPACE_V2_MENU_END --}}
                        <li>
                            <a href="{{ url('/cong-trinh') }}"
                               class="ego-sublink {{ request()->is('cong-trinh*') && !request()->is('cong-trinh-moi*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Công trình
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/don-vat-tu') }}"
                               class="ego-sublink {{ request()->is('don-vat-tu*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Đơn vật tư
                                @if(($egoPendingMaterialRequestsCount ?? 0) > 0)<span class="ego-count-badge" title="Đơn vật tư chờ duyệt">{{ $egoPendingMaterialRequestsCount }}</span>@endif
                            </a>
                        </li>

                        {{-- EGO_SITE_ASSEMBLY_MENU_START --}}
                        <li>
                            <a href="{{ route('site-assemblies.index') }}"
                               class="ego-sublink {{ request()->routeIs('site-assemblies.*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Lắp ráp / Sản xuất
                            </a>
                        </li>
                        {{-- EGO_SITE_ASSEMBLY_MENU_END --}}

                    </ul>
                </li>
            @endif

            {{-- ĐỀ NGHỊ THANH TOÁN --}}
            <li class="ego-item" data-title="Đề nghị thanh toán" data-ego-menu-permission="menu.payment_requests">
                <a href="{{ route('payment_requests.index') }}"
                   class="ego-link {{ active_route('payment_requests.*') }}"
                   data-ego-type="nav">
                    <span class="ego-ic"><i class="bi bi-receipt"></i></span>
                    <span class="ego-txt">Đề nghị thanh toán</span>
                    @if(($egoPendingPaymentRequestsCount ?? 0) > 0)<span class="ego-count-badge" title="Đề nghị thanh toán chờ duyệt">{{ $egoPendingPaymentRequestsCount }}</span>@endif
                </a>
            </li>
{{-- ĐỀ XUẤT --}}
@auth
<li class="ego-item" data-title="Đề xuất" data-ego-menu-permission="menu.proposals">
    <a href="{{ route('de-xuat.index') }}"
       class="ego-link {{ request()->is('de-xuat*') ? 'active' : '' }}"
       data-ego-type="nav">

        <span class="ego-ic">
            <i class="bi bi-lightbulb"></i>
        </span>

        <span class="ego-txt">Đề xuất</span>
        @if(($egoPendingProposalsCount ?? 0) > 0)<span class="ego-count-badge" title="Đề xuất chờ duyệt">{{ $egoPendingProposalsCount }}</span>@endif
    </a>
</li>
@endauth
{{-- KỸ THUẬT --}}
@if($egoCanTechnicalMenu)
<li class="ego-item ego-item--has-sub" data-title="Kỹ thuật" data-ego-sub="true" data-ego-menu-permission="menu.technical">
    <a href="#menuKyThuat"
       class="ego-link {{ request()->is('ky-thuat*') ? 'active' : '' }}"
       data-bs-toggle="collapse"
       data-ego-type="toggle">

        <span class="ego-ic"><i class="bi bi-tools"></i></span>
        <span class="ego-txt">Kỹ thuật</span>
        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>

    <ul id="menuKyThuat"
        class="ego-sub collapse {{ request()->is('ky-thuat*') ? 'show' : '' }}">

        <li>
            <a href="{{ route('ky-thuat.maintenance.index') }}"
               class="ego-sublink {{ request()->routeIs('ky-thuat.maintenance.*') ? 'active' : '' }}"
               data-ego-type="nav">
                Lịch bảo trì / bảo hành
            </a>
        </li>

        <li>
            <a href="/ky-thuat/luong"
               class="ego-sublink"
               data-ego-type="nav">
                Tính lương
            </a>
        </li>

    </ul>
</li>
@endif
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


                        <a href="{{ route('sales.work-reports.index') }}"
                           class="ego-sales-child-link {{ request()->routeIs('sales.work-reports.*') ? 'active' : '' }}">
                            <span class="ego-sales-child-dot"></span>
                            <span>Báo Cáo</span>
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

            {{-- SẢN PHẨM --}}
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
            @endphp

          @if($canProductMenu)
    <li class="ego-item ego-item--has-sub" data-title="Sản phẩm" data-ego-sub="true" data-ego-menu-permission="menu.products">
        <a href="#menuSP"
           class="ego-link {{ active_route(['products.input','products.output','products.history','products.serials.*','product-goods-receipts.*','products.create','categories.*','warehouses.*','brands.*','price-tiers.*','company-management.*']) }}"
           data-bs-toggle="collapse"
           data-ego-type="toggle"
           aria-expanded="{{ request()->routeIs('products.input') || request()->routeIs('products.output') || request()->routeIs('products.history') || request()->routeIs('products.serials.*') || request()->routeIs('products.create') || request()->routeIs('categories.*') || request()->routeIs('warehouses.*') || request()->routeIs('brands.*') || request()->routeIs('price-tiers.*') || request()->is('company-management*') || request()->is('products/goods-receipts*') ? 'true' : 'false' }}"
           aria-controls="menuSP">
            <span class="ego-ic"><i class="bi bi-box-seam"></i></span>
            <span class="ego-txt">Sản phẩm</span>
            <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
        </a>

        <ul id="menuSP"
            class="ego-sub collapse {{ (request()->routeIs('products.input') || request()->routeIs('products.output') || request()->routeIs('products.history') || request()->routeIs('products.serials.*') || request()->routeIs('products.create') || request()->routeIs('categories.*') || request()->routeIs('warehouses.*') || request()->routeIs('brands.*') || request()->routeIs('price-tiers.*') || request()->is('company-management*') || request()->is('products/goods-receipts*')) ? 'show' : '' }}"
            data-ego-submenu>

            @if($canSeeInputProducts && \Illuminate\Support\Facades\Route::has('products.input'))
                <li>
                    <a href="{{ route('products.input') }}"
                       class="ego-sublink {{ request()->routeIs('products.input') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Sản phẩm đầu vào
                    </a>
                </li>

                        {{-- EGO_PRODUCT_GOODS_RECEIPTS_MENU_START --}}
                        <li>
                            <a href="{{ url('/products/goods-receipts') }}"
                               class="ego-sublink {{ request()->is('products/goods-receipts*') ? 'active' : '' }}"
                               data-ego-type="nav">
                                Nhập sản phẩm
                            </a>
                        </li>
                        {{-- EGO_PRODUCT_GOODS_RECEIPTS_MENU_END --}}

            @endif

            {{-- EGO_PROJECT_TEST_WAREHOUSE_MENU_START --}}
            @can('project-test.warehouse')
                <li>
                    <a href="{{ route('project-test.warehouse.index') }}"
                       class="ego-sublink {{ request()->routeIs('project-test.warehouse.*') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Cấp vật tư công trình Test
                        <span style="margin-left:6px;padding:2px 6px;border-radius:999px;background:#0f766e;color:#fff;font-size:8px;font-weight:900;">NEW</span>
                    </a>
                </li>
            @endcan
            {{-- EGO_PROJECT_TEST_WAREHOUSE_MENU_END --}}

            @if(\Illuminate\Support\Facades\Route::has('products.output'))
                <li>
                    <a href="{{ route('products.output') }}"
                       class="ego-sublink {{ request()->routeIs('products.output') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Sản phẩm đầu ra
                    </a>
                </li>
            @endif


            @if(\Illuminate\Support\Facades\Route::has('products.serials.index'))
                <li>
                    <a href="{{ route('products.serials.index') }}"
                       class="ego-sublink {{ request()->routeIs('products.serials.*') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Quản lý seri
                    </a>
                </li>
            @endif

            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('products.manage'))
                <li>
                    <a href="{{ route('products.create') }}"
                       class="ego-sublink {{ active_route('products.create') }}"
                       data-ego-type="nav">
                        Thêm sản phẩm
                    </a>
                </li>
            @endif

            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('categories.manage'))
                <li>
                    <a href="{{ route('categories.index') }}"
                       class="ego-sublink {{ active_route('categories.*') }}"
                       data-ego-type="nav">
                        Danh mục sản phẩm
                    </a>
                </li>
            @endif

            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('brands.manage'))
                <li>
                    <a href="{{ route('brands.index') }}"
                       class="ego-sublink {{ active_route('brands.*') }}"
                       data-ego-type="nav">
                        Danh mục thương hiệu
                    </a>
                </li>
            @endif

            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('price-tiers.manage'))
                <li>
                    <a href="{{ route('price-tiers.index') }}"
                       class="ego-sublink {{ active_route('price-tiers.*') }}"
                       data-ego-type="nav">
                        Loại giá
                    </a>
                </li>
            @endif

            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('warehouse.manage') || $u->can('warehouse.view'))
                <li>
                    <a href="{{ route('warehouses.index') }}"
                       class="ego-sublink {{ active_route('warehouses.index') }}"
                       data-ego-type="nav">
                        Danh sách kho
                    </a>
                </li>
            @endif

            <li data-ego-company-management-menu="products">
                <a href="{{ url('/company-management') }}"
                   class="ego-sublink {{ request()->is('company-management*') || request()->is('products/goods-receipts*') ? 'active' : '' }}"
                   data-ego-type="nav">
                    Quản lý công ty
                </a>
            </li>

          
                        @if(\Illuminate\Support\Facades\Route::has('products.history'))
    <li>
        <a href="{{ route('products.history') }}"
           class="ego-sublink {{ request()->routeIs('products.history') ? 'active' : '' }}"
           data-ego-type="nav">
            Lịch sử xuất/nhập kho
        </a>
    </li>
@endif
                    </ul>
                </li>
            @endif

            {{-- DIVIDER --}}
            <li class="ego-divider" role="separator"></li>
{{-- TÀI CHÍNH --}}
@if($egoCanFinanceMenu)
<li class="ego-item ego-item--has-sub" data-title="Tài chính" data-ego-sub="true" data-ego-menu-permission="menu.finance">
    <a href="#menuFinance"
       class="ego-link {{ request()->is('finance*') ? 'active' : '' }}"
       data-bs-toggle="collapse"
       data-ego-type="toggle"
       aria-expanded="{{ request()->is('finance*') ? 'true' : 'false' }}"
       aria-controls="menuFinance">
        <span class="ego-ic"><i class="bi bi-wallet2"></i></span>
        <span class="ego-txt">Tài chính</span>
        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
    </a>

    <ul id="menuFinance"
        class="ego-sub collapse {{ request()->is('finance*') ? 'show' : '' }}"
        data-ego-submenu>

        <li>
            <a href="{{ route('finance.index') }}"
               class="ego-sublink {{ request()->routeIs('finance.index') ? 'active' : '' }}"
               data-ego-type="nav">
                Tổng quan
            </a>
        </li>

        <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Công nợ khách hàng" data-ego-sub="true">
            <a href="#menuFinanceCustomerDebt"
               class="ego-sublink ego-sublink--toggle {{ request()->routeIs('finance.customer-debts.*') ? 'active' : '' }}"
               data-bs-toggle="collapse"
               data-ego-type="toggle"
               aria-expanded="{{ request()->routeIs('finance.customer-debts.*') ? 'true' : 'false' }}"
               aria-controls="menuFinanceCustomerDebt">
                <span>Công nợ khách hàng</span>
                <i class="bi bi-chevron-down ego-sub-caret"></i>
            </a>

            <ul id="menuFinanceCustomerDebt"
                class="ego-sub ego-sub--nested collapse {{ request()->routeIs('finance.customer-debts.*') ? 'show' : '' }}"
                data-ego-submenu>
                <li>
                    <a href="{{ route('finance.customer-debts.index') }}"
                       class="ego-sublink {{ request()->routeIs('finance.customer-debts.index') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Danh sách công nợ
                    </a>
                </li>
                <li>
                    <a href="{{ route('finance.customer-debts.by-customer') }}"
                       class="ego-sublink {{ request()->routeIs('finance.customer-debts.by-customer') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Theo khách hàng
                    </a>
                </li>
                <li>
                    <a href="{{ route('finance.customer-debts.payment-history') }}"
                       class="ego-sublink {{ request()->routeIs('finance.customer-debts.payment-history') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Lịch sử thanh toán
                    </a>
                </li>
            </ul>
        </li>

        <li>
            <a href="{{ route('finance.supplier-debts.index') }}"
               class="ego-sublink {{ request()->routeIs('finance.supplier-debts.index') ? 'active' : '' }}"
               data-ego-type="nav">
                Công nợ nhà cung cấp
            </a>
        </li>



        <li>
            <a href="{{ url('/payment-requests') }}"
   class="ego-sublink {{ request()->is('payment-requests*') ? 'active' : '' }}"
   data-ego-type="nav">
    Đề nghị thanh toán
    @if(($egoPendingPaymentRequestsCount ?? 0) > 0)<span class="ego-count-badge" title="Đề nghị thanh toán chờ duyệt">{{ $egoPendingPaymentRequestsCount }}</span>@endif
</a>
        </li>

        <li>
            <a href="{{ route('finance.salary') }}"
               class="ego-sublink {{ request()->routeIs('finance.salary') ? 'active' : '' }}"
               data-ego-type="nav">
                Lương & Hoa hồng
            </a>
        </li>

        <li>
            <a href="{{ route('finance.budget') }}"
               class="ego-sublink {{ request()->routeIs('finance.budget') ? 'active' : '' }}"
               data-ego-type="nav">
                Ngân sách
            </a>
        </li>

        <li>
            <a href="{{ route('finance.reports') }}"
               class="ego-sublink {{ request()->routeIs('finance.reports') ? 'active' : '' }}"
               data-ego-type="nav">
                Báo cáo
            </a>
        </li>
    </ul>
</li>
@endif

{{-- HỒ SƠ CÔNG TY --}}
@if(\Illuminate\Support\Facades\Route::has('company-documents.index'))
<li class="ego-item" data-title="Hồ sơ công ty" data-ego-menu-permission="menu.company">
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
                       class="ego-link {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') ? 'active' : '' }}"
                       data-bs-toggle="collapse"
                       data-ego-type="toggle"
                       aria-expanded="{{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') ? 'true' : 'false' }}"
                       aria-controls="menuNhanSuCaNhan">
                        <span class="ego-ic"><i class="bi bi-person-workspace"></i></span>
                        <span class="ego-txt">Nhân sự</span>
                        @if($egoPendingLeaveApprovalCount > 0)
                            <em class="ego-leave-parent-badge">{{ $egoPendingLeaveApprovalCount }}</em>
                        @endif
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>
                    <ul id="menuNhanSuCaNhan"
                        class="ego-sub collapse {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') ? 'show' : '' }}"
                        data-ego-submenu>
                        <li><a href="{{ route('hr.attendance.my') }}" class="ego-sublink {{ active_route('hr.attendance.my') }}" data-ego-type="nav">Chấm công của tôi</a></li>
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

            {{-- NHÂN SỰ --}}
@if($egoCanHrMenu)
<li class="ego-item ego-item--has-sub" data-title="Nhân sự" data-ego-sub="true" data-ego-menu-permission="menu.hr">
        <a href="#menuNhanSu"
           class="ego-link {{ request()->routeIs('hr.*') ? 'active' : '' }}"
           data-bs-toggle="collapse"
           data-ego-type="toggle"
           aria-expanded="{{ request()->routeIs('hr.*') ? 'true' : 'false' }}"
           aria-controls="menuNhanSu">
            <span class="ego-ic"><i class="bi bi-people"></i></span>
            <span class="ego-txt">Nhân sự</span>
                        {{-- EGO_LEAVE_HR_PARENT_BADGE_V110_START --}}
                        @if($egoPendingLeaveApprovalCount > 0)
                            <em class="ego-leave-parent-badge">{{ $egoPendingLeaveApprovalCount }}</em>
                        @endif
                        {{-- EGO_LEAVE_HR_PARENT_BADGE_V110_END --}}
            <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
        </a>

        <ul id="menuNhanSu"
            class="ego-sub collapse {{ request()->routeIs('hr.*') ? 'show' : '' }}"
            data-ego-submenu>

            <li>
                <a href="{{ route('hr.dashboard') }}"
                   class="ego-sublink {{ active_route('hr.dashboard') }}"
                   data-ego-type="nav">
                    Tổng quan
                </a>
            </li>

            <li>
                <a href="{{ route('hr.office-expenses.index') }}"
                   class="ego-sublink {{ active_route('hr.office-expenses.*') }}"
                   data-ego-type="nav">
                    Chi phí VP
                </a>
            </li>

            <li>
                <a href="{{ route('hr.operations.index') }}"
                   class="ego-sublink {{ active_route('hr.operations.*') }}"
                   data-ego-type="nav">
                    HC &amp; Vận Hành
                </a>
                <a href="{{ route('hr.recruitment.index') }}" class="ego-recruitment-sidebar-link" style="display:flex;align-items:center;gap:8px;margin:2px 10px;padding:9px 14px 9px 46px;border-radius:12px;color:#dbeafe;text-decoration:none;font-size:13px;font-weight:850;">
                    <span>Quy trình tuyển dụng</span>
                </a>
            </li>

<li class="nav-item">
    <a href="{{ route('hr.office-supply-process.index') }}" class="nav-link {{ request()->routeIs('hr.office-supply-process.*') ? 'active' : '' }}">
        <i class="fa fa-box-open"></i>
        <span>Quy trình phân bổ VPP</span>
    </a>
</li>

            @if(\Illuminate\Support\Facades\Route::has('hr.document-handovers.index'))
                <li>
                    <a href="{{ route('hr.document-handovers.index') }}"
                       class="ego-sublink ego-hr-clean-link {{ request()->routeIs('hr.document-handovers.*') ? 'active' : '' }}"
                       data-ego-type="nav">
                        Quy trình giao nhận HS
                    </a>
                </li>
            @endif




            <li>
                <a href="{{ route('hr.records.index') }}"
                   class="ego-sublink {{ active_route('hr.records.*') }}"
                   data-ego-type="nav">
                    HS nhân sự
                </a>
            </li>


            {{-- Nhân viên: gom Hồ sơ nhân sự + Phòng ban + Chức vụ vào bên trong --}}
            <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Nhân viên" data-ego-sub="true">
                <a href="#menuNhanSuNhanVien"
                   class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.employees.*') || request()->routeIs('hr.departments.*') || request()->routeIs('hr.positions.*') ? 'active' : '' }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ request()->routeIs('hr.employees.*') || request()->routeIs('hr.departments.*') || request()->routeIs('hr.positions.*') ? 'true' : 'false' }}"
                   aria-controls="menuNhanSuNhanVien">
                    <span>Nhân viên</span>
                    <i class="bi bi-chevron-down ego-sub-caret"></i>
                </a>

                <ul id="menuNhanSuNhanVien"
                    class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.employees.*') || request()->routeIs('hr.departments.*') || request()->routeIs('hr.positions.*') ? 'show' : '' }}"
                    data-ego-submenu>
                    <li>
                        <a href="{{ route('hr.employees.index') }}"
                           class="ego-sublink {{ active_route('hr.employees.*') }}"
                           data-ego-type="nav">
                            Danh sách nhân viên
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('hr.departments.index') }}"
                           class="ego-sublink {{ active_route('hr.departments.*') }}"
                           data-ego-type="nav">
                            Phòng ban
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('hr.positions.index') }}"
                           class="ego-sublink {{ active_route('hr.positions.*') }}"
                           data-ego-type="nav">
                            Chức vụ
                        </a>
                    </li>
                </ul>
            </li>

            {{-- Chấm công --}}
            <li class="ego-item ego-item--nested ego-item--has-sub" data-title="Chấm công" data-ego-sub="true">
                <a href="#menuNhanSuChamCong"
                   class="ego-sublink ego-sublink--toggle {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.overtime.*') ? 'active' : '' }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.overtime.*') ? 'true' : 'false' }}"
                   aria-controls="menuNhanSuChamCong">
                    <span>Chấm công</span>
                    <i class="bi bi-chevron-down ego-sub-caret"></i>
                </a>

                <ul id="menuNhanSuChamCong"
                    class="ego-sub ego-sub--nested collapse {{ request()->routeIs('hr.attendance.*') || request()->routeIs('hr.leave.*') || request()->routeIs('hr.overtime.*') ? 'show' : '' }}"
                    data-ego-submenu>

                    <li>
                        <a href="{{ route('hr.attendance.my') }}"
                           class="ego-sublink {{ active_route('hr.attendance.my') }}"
                           data-ego-type="nav">
                            Chấm công của tôi
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('hr.leave.index', ['tab' => 'mine']) }}"
                           class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab', 'mine') === 'mine' ? 'active' : '' }}"
                           data-ego-type="nav">
                            Đơn của tôi
                        </a>
                    </li>

                    @if($egoCanReviewLeave)
                        <li>
                            <a href="{{ route('hr.leave.index', ['tab' => 'approval', 'status' => 'pending']) }}"
                               class="ego-sublink {{ request()->routeIs('hr.leave.*') && request('tab') === 'approval' ? 'active' : '' }}"
                               data-ego-type="nav"
                               style="display:flex;align-items:center;gap:8px">
                                <span>Duyệt đơn nhân sự</span>
                                @if($egoPendingLeaveApprovalCount > 0)
                                    <span class="ego-booking-new-badge" style="min-width:24px!important;height:17px!important;padding:0 6px!important;line-height:17px!important">
                                        {{ $egoPendingLeaveApprovalCount }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endif

                    @if($egoCanViewCompanyAttendance)
                        <li>
                            <a href="{{ route('hr.attendance.index') }}"
                               class="ego-sublink {{ active_route('hr.attendance.index') }}"
                               data-ego-type="nav">
                                Bảng công nhân sự
                            </a>
                        </li>
                    @endif

                    <li>
                        <a href="{{ route('hr.overtime.index') }}"
                           class="ego-sublink {{ request()->routeIs('hr.overtime.*') ? 'active' : '' }}"
                           data-ego-type="nav">
                            Đăng ký tăng ca
                        </a>
                    </li>

                </ul>
            </li>
        </ul>
    </li>
@endif
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
                        <span class="ego-booking-new-badge" style="min-width:29px!important">NEW</span>
                        <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                    </a>

                    <ul id="menuSettings"
                        class="ego-sub collapse {{ $egoSettingsRouteActive ? 'show' : '' }}"
                        data-ego-submenu>
                        <li><a href="{{ route('admin.settings.index') }}" class="ego-sublink {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" data-ego-type="nav">Tổng quan</a></li>
                        <li><a href="{{ route('admin.settings.appearance') }}" class="ego-sublink {{ request()->routeIs('admin.settings.appearance*') ? 'active' : '' }}" data-ego-type="nav">Giao diện &amp; thương hiệu</a></li>
                        @if(\Illuminate\Support\Facades\Route::has('payment-methods.index'))
                            <li><a href="{{ route('payment-methods.index') }}" class="ego-sublink {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}" data-ego-type="nav">Phương thức thanh toán</a></li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('companies.index'))
                            <li><a href="{{ route('companies.index') }}" class="ego-sublink {{ request()->is('companies*') ? 'active' : '' }}" data-ego-type="nav">Thông tin công ty</a></li>
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
                    $egoCompanyQueryV4 = \Illuminate\Support\Facades\DB::table('companies')->select('id', 'code', 'name');

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

                @if($egoSidebarCompaniesV4->count())
                    <div class="ego-company-picker-final">
                        <div class="ego-company-picker-final__label">
                            <i class="bi bi-building"></i>
                            <span>Chọn công ty</span>
                        </div>

                        <div class="ego-company-picker-final__switch">
                            @foreach($egoSidebarCompaniesV4 as $company)
                                @php
                                    $activeCompanyV4 = (string)$egoCurrentCompanyIdV4 === (string)$company->id;
                                    $companyLabelV4 = $egoCompanyLabelV4($company);
                                @endphp

                                <form method="POST" action="{{ $egoCompanySwitchActionV4 }}">
                                    @csrf
                                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                                    <button type="submit"
                                            class="ego-company-picker-final__btn {{ $activeCompanyV4 ? 'active' : '' }}"
                                            data-company-id="{{ $company->id }}"
                                            title="{{ $company->name ?? $companyLabelV4 }}">
                                        {{ $companyLabelV4 }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif

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

  egoForceRefreshStatus();
  window.setInterval(egoForceRefreshStatus, 30000);
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
    setInterval(cleanStatusCardFinal, 800);
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
