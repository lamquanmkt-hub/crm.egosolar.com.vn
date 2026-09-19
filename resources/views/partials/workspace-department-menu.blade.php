@php
    $egoWsMenuUser = auth()->user();
    $egoWsContext = app(\App\Services\Workspace\WorkspaceContextService::class);
    $egoWsLevelService = app(\App\Services\Workspace\WorkspaceLevelService::class);
    $egoWsPageAccess = app(\App\Services\RolePermission\PageAccessService::class);

    $egoWsKey = $egoWsMenuUser ? $egoWsContext->current($egoWsMenuUser) : 'general';
    $egoWsLabel = $egoWsMenuUser ? $egoWsContext->labelFor($egoWsKey) : 'Nhân viên';
    $egoWsLevel = $egoWsMenuUser ? $egoWsLevelService->resolve($egoWsMenuUser) : 'staff';
    $egoWsIsAdmin = $egoWsMenuUser && $egoWsPageAccess->isAdmin($egoWsMenuUser);
    $egoWsIsExecutive = $egoWsIsAdmin || ($egoWsMenuUser && $egoWsMenuUser->hasRole('management'));

    $egoWsMake = static function (
        string $label,
        string $icon,
        ?string $route = null,
        ?string $fallback = null,
        ?string $permission = null,
        array $patterns = [],
        array $children = [],
        array $levels = []
    ): array {
        return compact('label', 'icon', 'route', 'fallback', 'permission', 'patterns', 'children', 'levels');
    };

    $common = [
        $egoWsMake('Chấm công', 'bi-check2-circle', 'hr.attendance.my', '/nhan-su/cham-cong-cua-toi', null, ['hr.attendance.*']),
        $egoWsMake('Lịch công tác', 'bi-geo-alt', 'business-trips.index', '/lich-cong-tac', null, ['business-trips.*']),
        $egoWsMake('Booking phòng họp', 'bi-calendar2-check', 'meeting-room-bookings.index', '/booking-phong-hop', null, ['meeting-room-bookings.*']),
        $egoWsMake('Công việc', 'bi-briefcase', 'tasks.index', '/chat/tasks', null, ['tasks.*']),
        $egoWsMake('EGO AI Copilot', 'bi-stars', 'ai.index', '/ai', null, ['ai.*']),
        $egoWsMake('Tin nhắn', 'bi-chat-dots', 'chat.inbox', '/chat', null, ['chat.*']),
        $egoWsMake('Hồ sơ công ty', 'bi-building', 'company-documents.index', '/company-documents', null, ['company-documents.*']),
        $egoWsMake('Đề xuất', 'bi-lightbulb', 'de-xuat.index', '/de-xuat', null, ['de-xuat.*']),
        $egoWsMake('Đề nghị thanh toán', 'bi-receipt', 'payment_requests.index', '/payment-requests', null, ['payment_requests.*']),
        $egoWsMake('Tạm ứng & Hoàn ứng', 'bi-cash-coin', 'payment_advances.index', '/payment-requests/tam-ung-hoan-ung', null, ['payment_advances.*']),
    ];

    $menus = [
        'sales' => [
            $egoWsMake('Tổng quan kinh doanh', 'bi-speedometer2', 'dashboard', '/dashboard', null, ['dashboard']),
            $egoWsMake('Khách hàng', 'bi-people', null, null, null, [], [
                $egoWsMake('Danh sách khách hàng', 'bi-list-ul', 'customers.index', '/customers', 'page.customers', ['customers.index','customers.show','customers.edit']),
                $egoWsMake('Chăm sóc & Pipeline', 'bi-kanban', 'customers.pipeline', '/customers/pipeline', 'page.customers', ['customers.pipeline','sales.work-reports.*']),
                $egoWsMake('Tổng quan khách hàng', 'bi-bar-chart-line', 'customers.overview', '/customers/overview', 'page.customers', ['customers.overview']),
                $egoWsMake('Danh sách đại lý', 'bi-person-badge', 'customer-profiles.index', '/customer-profiles', 'page.customers', ['customer-profiles.*']),
            ]),
            $egoWsMake('Bán hàng', 'bi-bag-check', null, null, null, [], [
                $egoWsMake('Báo giá', 'bi-file-earmark-text', 'sales-quotations.index', '/bao-gia', 'page.sales', ['sales-quotations.*']),
                $egoWsMake('Đơn hàng', 'bi-receipt-cutoff', 'orders.index', '/orders', 'page.orders', ['orders.*']),
                $egoWsMake('Ký gửi hàng hóa', 'bi-box-seam', 'customer-consignments.index', '/ky-gui-hang-hoa', 'page.orders', ['customer-consignments.*']),
            ]),
            $egoWsMake('Công trình', 'bi-buildings', 'projects-unified.index', '/du-an', 'page.sites', ['projects-unified.*']),
            $egoWsMake('Hiệu suất Sales', 'bi-graph-up-arrow', null, null, null, [], [
                $egoWsMake('Thu nhập & hoa hồng', 'bi-cash-coin', 'sales.commissions.index', '/sales/commissions', 'page.sales', ['sales.commissions.*']),
                $egoWsMake('KPI & công việc', 'bi-bullseye', 'sales.kpi.index', '/sales/kpi', 'page.sales', ['sales.kpi.*']),
            ]),
        ],
        'technical' => [
            $egoWsMake('Tổng quan kỹ thuật', 'bi-speedometer2', 'technical-workspace.overview', '/ky-thuat', 'page.technical', ['technical-workspace.overview']),
            $egoWsMake('Công trình', 'bi-buildings', 'projects-unified.index', '/du-an', 'page.sites', ['projects-unified.*']),
            $egoWsMake('Điều hành kỹ thuật', 'bi-calendar2-range', null, null, 'page.technical', [], [
                $egoWsMake('Báo cáo ngày tự động', 'bi-clipboard-data', 'technical-workspace.operations.daily-report', '/ky-thuat/dieu-hanh/bao-cao-ngay', 'page.technical', ['technical-workspace.operations.daily-report']),
                $egoWsMake('Kế hoạch tuần', 'bi-calendar-week', 'technical-workspace.operations.weekly-plan', '/ky-thuat/dieu-hanh/ke-hoach-tuan', 'page.technical', ['technical-workspace.operations.weekly-plan']),
                $egoWsMake('Lịch thi công', 'bi-calendar-event', 'technical-workspace.operations.installation-calendar', '/ky-thuat/dieu-hanh/lich-thi-cong', 'page.technical', ['technical-workspace.operations.installation-calendar']),
                $egoWsMake('Lịch bảo trì', 'bi-calendar2-heart', 'technical-workspace.operations.maintenance-calendar', '/ky-thuat/dieu-hanh/lich-bao-tri', 'page.technical', ['technical-workspace.operations.maintenance-calendar']),
            ]),
            $egoWsMake('Vật tư thi công', 'bi-box-seam', 'technical-workspace.materials.index', '/ky-thuat/vat-tu-thi-cong', 'page.technical', ['technical-workspace.materials.*']),
            $egoWsMake('Hồ sơ & bảo hành', 'bi-folder2-open', null, null, 'page.technical', [], [
                $egoWsMake('Hồ sơ kỹ thuật', 'bi-folder', 'technical-workspace.documents.all', '/ky-thuat/ho-so/ho-so-bien-ban', 'page.technical', ['technical-workspace.documents.*']),
                $egoWsMake('Bảo hành & O&M', 'bi-shield-check', 'ky-thuat.maintenance.index', '/ky-thuat/bao-tri-bao-hanh', 'page.technical', ['ky-thuat.maintenance.*','technical-workspace.warranty*']),
                $egoWsMake('Đề xuất đổi hàng BH', 'bi-arrow-repeat', 'ky-thuat.warranty-exchange.index', '/ky-thuat/de-xuat-doi-hang-bao-hanh', 'page.technical', ['ky-thuat.warranty-exchange.*']),
            ]),
        ],
        'warehouse' => [
            $egoWsMake('Tổng quan kho', 'bi-speedometer2', 'products.index', '/products', 'page.products', ['products.index']),
            $egoWsMake('Danh mục kho', 'bi-boxes', null, null, 'page.products', [], [
                $egoWsMake('Sản phẩm', 'bi-box', 'products.index', '/products', 'page.products', ['products.*']),
                $egoWsMake('Kho hàng', 'bi-house-gear', 'warehouses.index', '/warehouses', 'page.products', ['warehouses.*']),
                $egoWsMake('Thương hiệu sản phẩm', 'bi-tags', 'brands.index', '/brands', 'page.products', ['brands.*']),
                $egoWsMake('Nhóm sản phẩm', 'bi-grid', 'categories.index', '/categories', 'page.products', ['categories.*']),
            ]),
            $egoWsMake('Nhập – xuất kho', 'bi-arrow-left-right', null, null, 'page.products', [], [
                $egoWsMake('Phiếu nhập kho', 'bi-box-arrow-in-down', 'product-goods-receipts.index', '/products/goods-receipts', 'page.products', ['product-goods-receipts.*']),
                $egoWsMake('Lịch sử nhập', 'bi-journal-arrow-down', 'products.input', '/products/input', 'page.products', ['products.input']),
                $egoWsMake('Lịch sử xuất', 'bi-journal-arrow-up', 'products.output', '/products/output', 'page.products', ['products.output']),
                $egoWsMake('Cấp vật tư công trình', 'bi-truck', 'project-test.warehouse.index', '/san-pham-kho/xuat-cong-trinh', 'page.sites', ['project-test.warehouse.*']),
            ]),
            $egoWsMake('Đơn hàng & công trình', 'bi-clipboard-check', null, null, null, [], [
                $egoWsMake('Đơn hàng cần xử lý', 'bi-receipt', 'orders.index', '/orders', 'page.orders', ['orders.*']),
                $egoWsMake('Hàng ký gửi', 'bi-box-seam', 'customer-consignments.index', '/ky-gui-hang-hoa', 'page.orders', ['customer-consignments.*']),
                $egoWsMake('Công trình kho', 'bi-buildings', 'warehouse-projects.index', '/kho/cong-trinh', 'page.sites', ['warehouse-projects.*']),
            ]),
            /* EGO_WAREHOUSE_RECEIVABLE_LEFT_MENU_V2 */
            $egoWsMake('Công nợ phải thu', 'bi-cash-coin', 'finance.customer-debts.index', '/finance/customer-debts', null, ['finance.customer-debts.*']),
            /* EGO_WAREHOUSE_RECEIVABLE_LEFT_MENU_V2_END */
            $egoWsMake(
                'Lắp ráp / Sản xuất',
                'bi-tools',
                'site-assemblies.index',
                '/cong-trinh/lap-rap-san-xuat',
                'page.sites',
                ['site-assemblies.*']
            ),
            $egoWsMake('Serial & bảo hành', 'bi-upc-scan', null, null, 'page.products', [], [
                $egoWsMake('Tra cứu Serial/BH', 'bi-search', 'serial-warranty.index', '/serial-warranty', 'page.products', ['serial-warranty.*']),
                $egoWsMake('Quản lý Serial', 'bi-qr-code-scan', 'products.serials.index', '/products/serials', 'page.products', ['products.serials.*']),
                $egoWsMake('Hàng trả về', 'bi-arrow-counterclockwise', 'order-returns.dashboard', '/order-returns', 'page.orders', ['order-returns.*']),
            ]),
        ],
        'accounting' => [
            $egoWsMake('Tổng quan tài chính', 'bi-speedometer2', 'finance.index', '/finance', 'page.finance', ['finance.index']),
            $egoWsMake('Thu – chi', 'bi-wallet2', null, null, 'page.finance', [], [
                $egoWsMake('Phiếu thu', 'bi-arrow-down-circle', 'finance.receipts.index', '/finance/receipts', 'page.finance', ['finance.receipts.*']),
                $egoWsMake('Phiếu chi', 'bi-arrow-up-circle', 'finance.payments.index', '/finance/payments', 'page.finance', ['finance.payments.*']),
                $egoWsMake('Đề nghị thanh toán', 'bi-receipt', 'payment_requests.index', '/payment-requests', 'page.finance', ['payment_requests.*']),
            ]),
            $egoWsMake('Công nợ', 'bi-cash-stack', null, null, 'page.finance', [], [
                $egoWsMake('Công nợ khách hàng', 'bi-person-down', 'finance.customer-debts.index', '/finance/customer-debts', 'page.finance', ['finance.customer-debts.*']),
                $egoWsMake('Công nợ nhà cung cấp', 'bi-person-up', 'finance.supplier-debts.index', '/finance/supplier-debts', 'page.finance', ['finance.supplier-debts.*']),
            ]),
            $egoWsMake('Báo cáo & quản trị', 'bi-bar-chart', null, null, 'page.finance', [], [
                $egoWsMake('Bảng lương', 'bi-people', 'finance.salary', '/finance/salary', 'page.finance', ['finance.salary*']),
                $egoWsMake('Ngân sách', 'bi-pie-chart', 'finance.budget', '/finance/budget', 'page.finance', ['finance.budget*']),
                $egoWsMake('Báo cáo tài chính', 'bi-file-earmark-bar-graph', 'finance.reports', '/finance/reports', 'page.finance', ['finance.reports*']),
            ]),
            $egoWsMake('Đơn hàng & công trình', 'bi-buildings', null, null, null, [], [
                $egoWsMake('Đơn hàng', 'bi-receipt-cutoff', 'orders.index', '/orders', 'page.orders', ['orders.*']),
                $egoWsMake('Công trình', 'bi-building', 'projects-unified.index', '/du-an', 'page.sites', ['projects-unified.*']),
            ]),
        ],
        'hr' => [
            $egoWsMake('Tổng quan nhân sự', 'bi-speedometer2', 'hr.dashboard', '/nhan-su', 'page.hr', ['hr.dashboard']),
            $egoWsMake('Nhân sự', 'bi-people', null, null, 'page.hr', [], [
                $egoWsMake('Danh sách nhân viên', 'bi-person-lines-fill', 'hr.employees.index', '/nhan-su/employees', 'page.hr', ['hr.employees.*']),
                $egoWsMake('Hồ sơ nhân sự', 'bi-folder2-open', 'hr.records.index', '/nhan-su/records', 'page.hr', ['hr.records.*']),
                $egoWsMake('Phòng ban', 'bi-diagram-3', 'hr.departments.index', '/nhan-su/departments', 'page.hr', ['hr.departments.*']),
                $egoWsMake('Chức vụ', 'bi-person-vcard', 'hr.positions.index', '/nhan-su/positions', 'page.hr', ['hr.positions.*']),
            ]),
            $egoWsMake('Chấm công & nghỉ phép', 'bi-calendar-check', null, null, 'page.hr', [], [
                $egoWsMake('Bảng công nhân sự', 'bi-table', 'hr.attendance.index', '/nhan-su/cham-cong', 'page.hr', ['hr.attendance.index']),
                $egoWsMake('Nghỉ phép', 'bi-calendar-x', 'hr.leave.index', '/nhan-su/nghi-phep', 'page.hr', ['hr.leave.*']),
                $egoWsMake('Tăng ca', 'bi-clock-history', 'hr.overtime.index', '/nhan-su/tang-ca', 'page.hr', ['hr.overtime.*']),
            ]),
            $egoWsMake('Tuyển dụng', 'bi-person-plus', 'hr.recruitment.index', '/nhan-su/tuyen-dung', 'page.hr', ['hr.recruitment.*']),
            $egoWsMake('Hành chính', 'bi-building-gear', null, null, 'page.hr', [], [
                $egoWsMake('HC & vận hành', 'bi-gear', 'hr.operations.index', '/nhan-su/hc-van-hanh', 'page.hr', ['hr.operations.*']),
                $egoWsMake('Văn phòng phẩm', 'bi-box2', 'hr.office-supply-process.index', '/nhan-su/quy-trinh-phan-bo-vpp', 'page.hr', ['hr.office-supply-process.*']),
                $egoWsMake('Chi phí văn phòng', 'bi-cash-coin', 'hr.office-expenses.index', '/nhan-su/chi-phi-vp', 'page.hr', ['hr.office-expenses.*']),
            ]),
            $egoWsMake('Quà tặng', 'bi-gift', null, null, 'page.hr', [], [
                $egoWsMake('Tổng quan quà tặng', 'bi-grid', 'hr.gifts.index', '/nhan-su/qua-tang', 'page.hr', ['hr.gifts.index']),
                $egoWsMake('Kho quà tặng', 'bi-box-seam', 'hr.gifts.stock.index', '/nhan-su/qua-tang/kho', 'page.hr', ['hr.gifts.stock.*']),
                $egoWsMake('Xuất quà / yêu cầu tặng', 'bi-send', 'hr.gifts.requests.index', '/nhan-su/qua-tang/yeu-cau', 'page.hr', ['hr.gifts.requests.*']),
            ]),
        ],
        'marketing' => [
            $egoWsMake('Tổng quan Marketing', 'bi-speedometer2', 'marketing.dashboard', '/marketing/dashboard', 'page.marketing', ['marketing.dashboard']),
            $egoWsMake('Lead Marketing', 'bi-person-bounding-box', 'marketing.leads.index', '/marketing/leads', 'page.marketing', ['marketing.leads.*']),
            $egoWsMake('Nội dung & lịch đăng', 'bi-calendar3', 'marketing.reports.content-calendar', '/marketing/reports/content-calendar', 'page.marketing', ['marketing.reports.content-calendar*']),
            $egoWsMake('Tiến độ chiến dịch', 'bi-kanban', 'marketing.progress.index', '/marketing/progress', 'page.marketing', ['marketing.progress.*']),
            $egoWsMake('Báo cáo tuần', 'bi-journal-text', 'marketing.reports.weekly-tasks', '/marketing/reports/weekly-tasks', 'page.marketing', ['marketing.reports.weekly-tasks*']),
            $egoWsMake('Khách hàng', 'bi-people', 'customers.index', '/customers', 'page.customers', ['customers.*']),
        ],
        'admin' => [
            $egoWsMake('Dashboard Giám đốc', 'bi-speedometer2', 'dashboard', '/dashboard', null, ['dashboard']),
            $egoWsMake('Điều hành công ty', 'bi-grid-1x2', null, null, null, [], [
                $egoWsMake('Khách hàng', 'bi-people', 'customers.index', '/customers', null, ['customers.*']),
                $egoWsMake('Đơn hàng', 'bi-receipt-cutoff', 'orders.index', '/orders', null, ['orders.*']),
                $egoWsMake('Công trình', 'bi-buildings', 'projects-unified.index', '/du-an', null, ['projects-unified.*']),
                $egoWsMake('Tài chính', 'bi-cash-stack', 'finance.index', '/finance', null, ['finance.*']),
                $egoWsMake('Nhân sự', 'bi-person-workspace', 'hr.dashboard', '/nhan-su', null, ['hr.*']),
            ]),
            $egoWsMake('Quản trị hệ thống', 'bi-sliders', null, null, null, [], [
                $egoWsMake('Cài đặt', 'bi-gear', 'admin.settings.index', '/cai-dat', null, ['admin.settings.*']),
                $egoWsMake('Phân quyền', 'bi-shield-lock', 'admin.role-permissions.index', '/cai-dat/phan-quyen', null, ['admin.role-permissions.*']),
                $egoWsMake('Cấu hình Workspace', 'bi-grid', 'workspace.settings.index', '/workspace/settings', null, ['workspace.settings.*']),
            ]),
        ],
        'management' => [
            $egoWsMake('Dashboard Giám đốc', 'bi-speedometer2', 'dashboard', '/dashboard', null, ['dashboard']),
            $egoWsMake('Điều hành công ty', 'bi-grid-1x2', null, null, null, [], [
                $egoWsMake('Khách hàng', 'bi-people', 'customers.index', '/customers', null, ['customers.*']),
                $egoWsMake('Đơn hàng', 'bi-receipt-cutoff', 'orders.index', '/orders', null, ['orders.*']),
                $egoWsMake('Công trình', 'bi-buildings', 'projects-unified.index', '/du-an', null, ['projects-unified.*']),
                $egoWsMake('Tài chính', 'bi-cash-stack', 'finance.index', '/finance', null, ['finance.*']),
                $egoWsMake('Nhân sự', 'bi-person-workspace', 'hr.dashboard', '/nhan-su', null, ['hr.*']),
            ]),
        ],
        'general' => [
            $egoWsMake('Trang chủ', 'bi-house-door', 'dashboard', '/dashboard', null, ['dashboard']),
        ],
    ];

    $items = $menus[$egoWsKey] ?? $menus['general'];
    if (!in_array($egoWsKey, ['admin', 'management'], true)) {
        $items = array_merge($items, $common);
    }

    /* EGO_PAYMENT_ADVANCE_ALL_WORKSPACES_V2_START
     * Tạm ứng & hoàn ứng phải có trong menu mọi Workspace/phòng ban.
     * Sales/Technical/Warehouse/Accounting/HR/Marketing đã nhận từ $common.
     * Admin/Management trước đây bị loại khỏi $common nên bổ sung riêng ở đây.
     * Không cấp thêm quyền nghiệp vụ; route/controller của module vẫn kiểm soát dữ liệu.
     */
    if (in_array($egoWsKey, ['admin', 'management'], true)
        && \Illuminate\Support\Facades\Route::has('payment_advances.index')) {
        $items[] = $egoWsMake(
            'Tạm ứng & Hoàn ứng',
            'bi-cash-coin',
            'payment_advances.index',
            '/payment-requests/tam-ung-hoan-ung',
            null,
            ['payment_advances.*']
        );
    }
    /* EGO_PAYMENT_ADVANCE_ALL_WORKSPACES_V2_END */

    $egoWsResolveUrl = static function (array $item): string {
        $routeName = $item['route'] ?? null;
        if (is_string($routeName) && $routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName)) {
            return route($routeName);
        }
        return url((string) ($item['fallback'] ?? '/workspace'));
    };

    $egoWsCanShow = function (array $item) use ($egoWsMenuUser, $egoWsPageAccess, $egoWsIsAdmin, $egoWsIsExecutive, $egoWsLevel): bool {
        $levels = (array) ($item['levels'] ?? []);
        if ($levels && !in_array($egoWsLevel, $levels, true)) {
            return false;
        }
        if ($egoWsIsAdmin || $egoWsIsExecutive) {
            return true;
        }
        $permission = $item['permission'] ?? null;
        return !is_string($permission) || $permission === '' || $egoWsPageAccess->canAccess($egoWsMenuUser, $permission);
    };

    $egoWsIsActive = static function (array $item): bool {
        foreach ((array) ($item['patterns'] ?? []) as $pattern) {
            if (request()->routeIs($pattern)) return true;
        }
        foreach ((array) ($item['children'] ?? []) as $child) {
            foreach ((array) ($child['patterns'] ?? []) as $pattern) {
                if (request()->routeIs($pattern)) return true;
            }
        }
        return false;
    };
@endphp

<link rel="stylesheet" href="{{ asset('css/ego-workspace-menu-v2.css') }}?v={{ @filemtime(public_path('css/ego-workspace-menu-v2.css')) ?: time() }}">

<li class="ego-dyn-item ego-workspace-menu-context" aria-label="Workspace đang chọn">
    <a href="{{ route('workspace.index') }}" class="ego-workspace-menu-context__link">
        <span class="ego-workspace-menu-context__icon"><i class="bi bi-grid-3x3-gap"></i></span>
        <span class="ego-workspace-menu-context__copy">
            <small>ĐANG LÀM VIỆC</small>
            <strong>{{ $egoWsLabel }}</strong>
        </span>
        <i class="bi bi-arrow-repeat"></i>
    </a>
</li>

@foreach($items as $menuIndex => $item)
    @php
        $visibleChildren = collect((array) ($item['children'] ?? []))
            ->filter(fn(array $child): bool => $egoWsCanShow($child))
            ->values();
        $hasChildren = $visibleChildren->isNotEmpty();
        $showItem = $hasChildren || $egoWsCanShow($item);
        $active = $egoWsIsActive($item);
        $collapseId = 'egoWsMenu'.preg_replace('/[^A-Za-z0-9]/', '', ucfirst($egoWsKey)).$menuIndex;
    @endphp
    @if($showItem)
        <li class="ego-item ego-dyn-item {{ $hasChildren ? 'ego-item--has-sub' : '' }}" data-title="{{ $item['label'] }}">
            @if($hasChildren)
                <a href="#{{ $collapseId }}"
                   class="ego-link {{ $active ? 'active' : '' }}"
                   data-bs-toggle="collapse"
                   data-ego-type="toggle"
                   aria-expanded="{{ $active ? 'true' : 'false' }}"
                   aria-controls="{{ $collapseId }}">
                    <span class="ego-ic"><i class="bi {{ $item['icon'] }}"></i></span>
                    <span class="ego-txt">{{ $item['label'] }}</span>
                    <span class="ego-caret"><i class="bi bi-chevron-down"></i></span>
                </a>
                <ul id="{{ $collapseId }}" class="ego-sub collapse {{ $active ? 'show' : '' }}" data-ego-submenu>
                    @foreach($visibleChildren as $child)
                        @php $childActive = $egoWsIsActive($child); @endphp
                        <li>
                            <a href="{{ $egoWsResolveUrl($child) }}"
                               class="ego-sublink {{ $childActive ? 'active' : '' }}"
                               data-ego-type="nav">
                                <i class="bi {{ $child['icon'] }}"></i>
                                <span>{{ $child['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <a href="{{ $egoWsResolveUrl($item) }}"
                   class="ego-link {{ $active ? 'active' : '' }}"
                   data-ego-type="nav">
                    <span class="ego-ic"><i class="bi {{ $item['icon'] }}"></i></span>
                    <span class="ego-txt">{{ $item['label'] }}</span>
                </a>
            @endif
        </li>
    @endif
@endforeach
