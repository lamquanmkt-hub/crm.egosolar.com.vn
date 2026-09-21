<?php

/*
|--------------------------------------------------------------------------
| EGO Workspace Menu V4 — cấu trúc theo phòng ban
|--------------------------------------------------------------------------
|
| Mỗi workspace có một khối nghiệp vụ riêng. Các chức năng toàn công ty
| được đặt ở những khối cố định bên dưới để người dùng luôn tìm thấy ở
| cùng một vị trí, bất kể đang làm việc tại phòng ban nào.
|
*/

$section = static fn (string $label): array => [
    'type' => 'section',
    'label' => $label,
];

return [
    'supported_workspaces' => [
        'technical', 'sales', 'accounting', 'warehouse',
        'hr', 'marketing', 'general',
    ],

    'technical_head_roles' => [
        'admin', 'management', 'manager', 'director', 'ceo',
        'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
    ],

    'technical_head_positions' => [
        'giam doc', 'pho giam doc', 'manager', 'truong phong',
        'truong nhom', 'quan ly',
    ],

    'workspace_labels' => [
        'admin' => 'QUẢN TRỊ HỆ THỐNG',
        'management' => 'BAN GIÁM ĐỐC',
        'technical_head' => 'KỸ THUẬT',
        'technical_staff' => 'KỸ THUẬT',
        'technical_admin' => 'KỸ THUẬT',
        'sales' => 'KINH DOANH',
        'accounting' => 'KẾ TOÁN',
        'warehouse' => 'KHO',
        'hr' => 'NHÂN SỰ',
        'marketing' => 'MARKETING',
        'general' => 'KHÔNG GIAN LÀM VIỆC',
    ],

    'menus' => [
        'admin' => [
            $section('ĐIỀU HÀNH TOÀN CÔNG TY'),
            ['label' => 'Tổng quan Giám đốc', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'fallback' => '/dashboard', 'patterns' => ['dashboard']],
            ['label' => 'Công trình', 'icon' => 'bi-buildings', 'route' => 'project-test.index', 'fallback' => '/cong-trinh', 'patterns' => ['project-test.*', 'technical-projects.*']],
            ['label' => 'Đơn hàng', 'icon' => 'bi-receipt-cutoff', 'route' => 'orders.index', 'fallback' => '/orders', 'patterns' => ['orders.*']],
            ['label' => 'Tài chính', 'icon' => 'bi-graph-up-arrow', 'route' => 'finance.index', 'fallback' => '/finance', 'patterns' => ['finance.*']],
            ['label' => 'Kho & sản phẩm', 'icon' => 'bi-boxes', 'route' => 'warehouses.index', 'fallback' => '/warehouses', 'patterns' => ['warehouses.*', 'products.*']],
            ['label' => 'Nhân sự', 'icon' => 'bi-people', 'route' => 'hr.employees.index', 'fallback' => '/nhan-su', 'patterns' => ['hr.*']],
            $section('KỸ THUẬT'),
            ['label' => 'Tổng quan', 'icon' => 'bi-speedometer2', 'route' => 'technical.dashboard', 'fallback' => '/ky-thuat/dashboard', 'patterns' => ['technical.dashboard']],
            ['label' => 'Kế hoạch & Giao việc', 'icon' => 'bi-calendar-week', 'route' => 'technical.dashboard.plans', 'fallback' => '/ky-thuat/dashboard/ke-hoach', 'patterns' => ['technical.dashboard.plans', 'technical.dashboard.plans.detail', 'technical.manager.board', 'technical.manager.detail']],
            ['label' => 'Báo cáo ngày/tuần', 'icon' => 'bi-clipboard-data', 'route' => 'technical.daily-reports.index', 'fallback' => '/ky-thuat/bao-cao-ngay', 'patterns' => ['technical.daily-reports.*']],
            ['label' => 'KPIs', 'icon' => 'bi-bar-chart-line', 'route' => 'ky-thuat.kpis.index', 'fallback' => '/ky-thuat/kpis', 'patterns' => ['ky-thuat.kpis.*']],
            $section('QUẢN TRỊ HỆ THỐNG'),
            ['label' => 'Cài đặt & phân quyền', 'icon' => 'bi-gear', 'route' => 'admin.settings.index', 'fallback' => '/cai-dat', 'patterns' => ['admin.settings.*', 'admin.role-permissions.*']],
            ['label' => 'Cấu hình Workspace', 'icon' => 'bi-grid-3x3-gap', 'route' => 'admin.settings.workspace', 'fallback' => '/cai-dat/ung-dung-theo-vai-tro', 'patterns' => ['admin.settings.workspace*', 'workspace.settings.*']],
        ],

        'management' => [
            $section('ĐIỀU HÀNH TOÀN CÔNG TY'),
            ['label' => 'Tổng quan Giám đốc', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'fallback' => '/dashboard', 'patterns' => ['dashboard']],
            ['label' => 'Công trình', 'icon' => 'bi-buildings', 'route' => 'project-test.index', 'fallback' => '/cong-trinh', 'patterns' => ['project-test.*', 'technical-projects.*']],
            ['label' => 'Đơn hàng', 'icon' => 'bi-receipt-cutoff', 'route' => 'orders.index', 'fallback' => '/orders', 'patterns' => ['orders.*']],
            ['label' => 'Tài chính', 'icon' => 'bi-graph-up-arrow', 'route' => 'finance.index', 'fallback' => '/finance', 'patterns' => ['finance.*']],
            ['label' => 'Kho & sản phẩm', 'icon' => 'bi-boxes', 'route' => 'warehouses.index', 'fallback' => '/warehouses', 'patterns' => ['warehouses.*', 'products.*']],
            ['label' => 'Nhân sự', 'icon' => 'bi-people', 'route' => 'hr.employees.index', 'fallback' => '/nhan-su', 'patterns' => ['hr.*']],
            $section('KỸ THUẬT'),
            ['label' => 'Tổng quan', 'icon' => 'bi-speedometer2', 'route' => 'technical.dashboard', 'fallback' => '/ky-thuat/dashboard', 'patterns' => ['technical.dashboard']],
            ['label' => 'Kế hoạch & Giao việc', 'icon' => 'bi-calendar-week', 'route' => 'technical.dashboard.plans', 'fallback' => '/ky-thuat/dashboard/ke-hoach', 'patterns' => ['technical.dashboard.plans', 'technical.dashboard.plans.detail', 'technical.manager.board', 'technical.manager.detail']],
            ['label' => 'Báo cáo ngày/tuần', 'icon' => 'bi-clipboard-data', 'route' => 'technical.daily-reports.index', 'fallback' => '/ky-thuat/bao-cao-ngay', 'patterns' => ['technical.daily-reports.*']],
            ['label' => 'KPIs', 'icon' => 'bi-bar-chart-line', 'route' => 'ky-thuat.kpis.index', 'fallback' => '/ky-thuat/kpis', 'patterns' => ['ky-thuat.kpis.*']],
            $section('THIẾT LẬP ĐIỀU HÀNH'),
            ['label' => 'Cấu hình Workspace', 'icon' => 'bi-grid-3x3-gap', 'route' => 'admin.settings.workspace', 'fallback' => '/cai-dat/ung-dung-theo-vai-tro', 'patterns' => ['admin.settings.workspace*', 'workspace.settings.*']],
        ],

        /*
         * KỸ THUẬT — TRƯỞNG PHÒNG / BAN GIÁM ĐỐC (giai đoạn 1, 2026-09)
         *
         * "Quản lý kỹ thuật" chỉ nằm ở nhánh technical_head: đây chính là cơ
         * chế phân biệt trưởng kỹ thuật / kỹ thuật viên sẵn có của menu V4
         * (xem technical_head_roles + technical_head_positions ở đầu file).
         * Controller vẫn kiểm tra quyền lần nữa ở phía server.
         */
        'technical_head' => [
            /*
             * Giai đoạn 2 (2026-09): nhóm điều phối kế hoạch tuần của trưởng
             * phòng. "Báo cáo nhân viên" chính là danh sách báo cáo ngày ở chế
             * độ toàn phòng (controller tự mở rộng phạm vi cho người quản lý).
             */
            $section('QUẢN LÝ KỸ THUẬT'),
            ['label' => 'Tổng quan', 'icon' => 'bi-speedometer2', 'route' => 'technical.manager.overview', 'fallback' => '/ky-thuat/quan-ly/tong-quan', 'patterns' => ['technical.manager.overview', 'ky-thuat.tong-quan']],
            ['label' => 'Kế hoạch nhân viên', 'icon' => 'bi-grid-3x3', 'route' => 'technical.manager.board', 'fallback' => '/ky-thuat/quan-ly/ke-hoach', 'patterns' => ['technical.manager.board']],
            /*
             * 2026-09: mục này mở THẲNG drawer "Giao việc" trên trang ma trận
             * (`?open=assign`), không còn cuộn tới khung chọn nhân viên cũ
             * (`?focus=assign`). Patterns giữ nguyên (detail/assign) để trang
             * ma trận chỉ làm sáng đúng MỘT mục "Kế hoạch nhân viên".
             */
            ['label' => 'Giao việc', 'icon' => 'bi-person-plus', 'route' => 'technical.manager.board', 'query' => ['open' => 'assign'], 'fallback' => '/ky-thuat/quan-ly/ke-hoach', 'patterns' => ['technical.manager.detail', 'technical.manager.assign']],
            ['label' => 'Báo cáo', 'icon' => 'bi-journal-text', 'route' => 'technical.daily-reports.index', 'fallback' => '/ky-thuat/bao-cao-ngay', 'patterns' => ['technical.daily-reports.*']],
            ['label' => 'Tổng kết tuần', 'icon' => 'bi-clipboard-data', 'route' => 'technical.manager.weekly-summary', 'fallback' => '/ky-thuat/quan-ly/tong-ket-tuan', 'patterns' => ['technical.manager.weekly-summary']],

            /*
             * "Kế hoạch (bản cũ)" và "Báo cáo (bản cũ)" ĐÃ ĐƯỢC HẠ khỏi lối vào
             * chính ở giai đoạn 2. Route, controller và dữ liệu vẫn nguyên vẹn
             * (/ky-thuat/ke-hoach, /ky-thuat/bao-cao, technical-workspace.*),
             * chỉ không còn xuất hiện trong menu để tránh hai hệ song song.
             */
        ],

        /*
         * KỸ THUẬT — KỸ THUẬT VIÊN (giai đoạn 2): tự lập kế hoạch tuần, thực
         * hiện, báo cáo kết quả mỗi ngày. Chỉ thấy dữ liệu của chính mình.
         */
        'technical_staff' => [
            /*
             * Đơn giản hoá (2026-09): lối vào chính của kỹ thuật viên CHỈ còn
             * ba mục — Tổng quan / Kế hoạch / Báo cáo. Những màn hình khác
             * ("Công việc hôm nay", "Công việc của tôi", "Lịch công việc",
             * "Lịch sử báo cáo", KPIs, kế hoạch & báo cáo bản cũ) vẫn giữ
             * nguyên route và dữ liệu, chỉ không còn là mục sidebar: chúng
             * được mở từ chính ba trang trên (tab nội bộ hoặc nút).
             */
            $section('KỸ THUẬT'),
            ['label' => 'Tổng quan', 'icon' => 'bi-speedometer2', 'route' => 'ky-thuat.tong-quan', 'fallback' => '/ky-thuat', 'patterns' => ['ky-thuat.tong-quan', 'technical.work.my', 'technical.work.calendar', 'technical.today']],
            ['label' => 'Kế hoạch', 'icon' => 'bi-calendar-week', 'route' => 'technical.week-plan.index', 'fallback' => '/ky-thuat/ke-hoach-tuan', 'patterns' => ['technical.week-plan.*']],
            ['label' => 'Báo cáo', 'icon' => 'bi-journal-text', 'route' => 'technical.daily-reports.index', 'fallback' => '/ky-thuat/bao-cao-ngay', 'patterns' => ['technical.daily-reports.*']],

            /*
             * "Đề xuất đổi hàng BH" là bước của quy trình Bảo trì / Bảo hành,
             * không phải của kế hoạch — báo cáo kỹ thuật. Đưa ra khỏi nhóm
             * KỸ THUẬT nhưng KHÔNG để mồ côi: giữ đúng MỘT lối vào tối thiểu ở
             * nhóm riêng bên dưới.
             */
            $section('BẢO TRÌ / BẢO HÀNH'),
            ['label' => 'Đề xuất đổi hàng BH', 'icon' => 'bi-arrow-left-right', 'route' => 'ky-thuat.warranty-exchange.index', 'fallback' => '/ky-thuat/de-xuat-doi-hang-bao-hanh', 'patterns' => ['ky-thuat.warranty-exchange.*']],
        ],

        /*
         * KỸ THUẬT — ADMIN / GIÁM ĐỐC.
         *
         * BỔ SUNG 2026-09 (sau nghiệm thu giao diện): ĐÚNG BỐN mục, mỗi mục có
         * URL riêng, controller riêng, view riêng, H1 riêng và breadcrumb riêng.
         *
         * Hai lỗi cũ được sửa tại đây:
         *   1. "Tổng quan" và "Báo cáo tuần/tháng" từng trỏ CÙNG một route
         *      `technical.dashboard` (chỉ khác `?mode=month`), nên "Báo cáo
         *      tuần/tháng" render y hệt Tổng quan.
         *   2. "Báo cáo tuần/tháng" có `patterns => []` nên KHÔNG BAO GIỜ active
         *      (hàm active của partial V4 chỉ so `request()->routeIs()`), còn
         *      "Tổng quan" active ở cả hai URL => active trùng.
         * Nay mỗi mục khớp CHÍNH XÁC theo tên route riêng; trang chi tiết con
         * được liệt kê cùng mục cha để luôn chỉ đúng MỘT mục active.
         *
         * Header nhóm đổi "BÁO CÁO KỸ THUẬT" -> "KỸ THUẬT" cho đúng nội dung:
         * nhóm nay có cả Kế hoạch và KPIs, không chỉ còn báo cáo.
         *
         * CẬP NHẬT NGHIỆP VỤ 2026-09 (thay đặc tả "Ban giám đốc chỉ xem"):
         *   - "Kế hoạch" -> "Kế hoạch & Giao việc": Admin/Giám đốc nay ĐƯỢC tạo
         *     kế hoạch và giao việc như trưởng phòng.
         *   - "Báo cáo tuần/tháng" -> "Báo cáo ngày/tuần", ĐỔI URL từ
         *     `/ky-thuat/dashboard/bao-cao` sang `/ky-thuat/bao-cao-ngay` —
         *     trang báo cáo DÙNG CHUNG cho cả ba vai trò, hai tab "Báo cáo ngày"
         *     và "Tổng hợp tuần". URL cũ chỉ còn redirect 302 một chiều.
         */
        'technical_admin' => [
            $section('KỸ THUẬT'),
            ['label' => 'Tổng quan', 'icon' => 'bi-speedometer2', 'route' => 'technical.dashboard', 'fallback' => '/ky-thuat/dashboard', 'patterns' => ['technical.dashboard', 'ky-thuat.tong-quan']],
            ['label' => 'Kế hoạch & Giao việc', 'icon' => 'bi-calendar-week', 'route' => 'technical.dashboard.plans', 'fallback' => '/ky-thuat/dashboard/ke-hoach', 'patterns' => ['technical.dashboard.plans', 'technical.dashboard.plans.detail', 'technical.manager.board', 'technical.manager.detail']],
            ['label' => 'Báo cáo ngày/tuần', 'icon' => 'bi-clipboard-data', 'route' => 'technical.daily-reports.index', 'fallback' => '/ky-thuat/bao-cao-ngay', 'patterns' => ['technical.daily-reports.*']],
            ['label' => 'KPIs', 'icon' => 'bi-bar-chart-line', 'route' => 'ky-thuat.kpis.index', 'fallback' => '/ky-thuat/kpis', 'patterns' => ['ky-thuat.kpis.*']],

            /*
             * Nhóm cũ được GIỮ LẠI, chỉ bỏ đúng một mục: "KPI kỹ thuật"
             * (route bản cũ `ky-thuat.kpis.*`, khung 30/25/15/15/15 nhập tay
             * trong bảng lương) đã bị mục "KPIs" ở trên thay thế. Để lại sẽ
             * thành hai lối vào trùng nghĩa dẫn về hai hệ số liệu khác nhau.
             * Route, controller và dữ liệu KPI cũ GIỮ NGUYÊN, chỉ không còn là
             * lối vào chính của Admin.
             */
            $section('CÔNG TRÌNH & BẢO HÀNH'),
            ['label' => 'Công trình', 'icon' => 'bi-buildings', 'route' => 'technical-projects.all', 'fallback' => '/cong-trinh', 'patterns' => ['technical-projects.*', 'technical-workspace.projects.*', 'project-test.*']],
            ['label' => 'Bảo hành & O&M', 'icon' => 'bi-shield-check', 'route' => 'ky-thuat.maintenance.index', 'fallback' => '/ky-thuat/bao-tri-bao-hanh', 'patterns' => ['ky-thuat.maintenance.*']],
        ],

        'sales' => [
            $section('NGHIỆP VỤ KINH DOANH'),
            ['label' => 'Tổng quan kinh doanh', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'fallback' => '/dashboard', 'patterns' => ['dashboard', 'sales.dashboard*']],
            ['label' => 'Khách hàng', 'icon' => 'bi-people', 'route' => 'customers.index', 'fallback' => '/customers', 'patterns' => ['customers.*']],
            ['label' => 'Khách hàng đại lý', 'icon' => 'bi-person-badge', 'route' => null, 'fallback' => '/customer-profiles', 'patterns' => ['customer-profiles*']],
            ['label' => 'Báo giá', 'icon' => 'bi-file-earmark-text', 'route' => 'sales-quotations.index', 'fallback' => '/bao-gia', 'patterns' => ['sales-quotations.*']],
            ['label' => 'Đơn hàng', 'icon' => 'bi-receipt-cutoff', 'route' => 'orders.index', 'fallback' => '/orders', 'patterns' => ['orders.*']],
            ['label' => 'Công trình', 'icon' => 'bi-buildings', 'route' => 'sales-projects.index', 'fallback' => '/sales/cong-trinh', 'patterns' => ['sales-projects.*', 'project-test.*']],
            ['label' => 'Hàng ký gửi', 'icon' => 'bi-box-seam', 'route' => 'customer-consignments.index', 'fallback' => '/ky-gui-hang-hoa', 'patterns' => ['customer-consignments.*']],
            ['label' => 'KPI & hoa hồng', 'icon' => 'bi-bullseye', 'route' => 'sales.kpi.index', 'fallback' => '/sales/kpi', 'patterns' => ['sales.kpi.*', 'sales.commissions.*']],
        ],

        'accounting' => [
            $section('NGHIỆP VỤ KẾ TOÁN'),
            ['label' => 'Tổng quan tài chính', 'icon' => 'bi-speedometer2', 'route' => 'finance.index', 'fallback' => '/finance', 'patterns' => ['finance.index']],
            ['label' => 'Công nợ phải thu', 'icon' => 'bi-arrow-down-left-circle', 'route' => 'finance.customer-debts.index', 'fallback' => '/finance/customer-debts', 'patterns' => ['finance.customer-debts.*']],
            ['label' => 'Công nợ phải trả', 'icon' => 'bi-arrow-up-right-circle', 'route' => 'finance.supplier-debts.index', 'fallback' => '/finance/supplier-debts', 'patterns' => ['finance.supplier-debts.*']],
            ['label' => 'Quỹ & tài khoản', 'icon' => 'bi-bank', 'route' => 'finance.accounts.index', 'fallback' => '/finance/accounts', 'patterns' => ['finance.accounts.*']],
            ['label' => 'Báo cáo tài chính', 'icon' => 'bi-file-earmark-bar-graph', 'route' => 'finance.reports', 'fallback' => '/finance/reports', 'patterns' => ['finance.reports*']],
            ['label' => 'Tài sản', 'icon' => 'bi-pc-display', 'route' => 'finance.assets.index', 'fallback' => '/finance/assets', 'patterns' => ['finance.assets.*']],
        ],

        'warehouse' => [
            $section('NGHIỆP VỤ KHO'),
            ['label' => 'Tổng quan kho', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'fallback' => '/dashboard', 'patterns' => ['dashboard']],
            ['label' => 'Danh mục sản phẩm', 'icon' => 'bi-box', 'route' => 'products.index', 'fallback' => '/products', 'patterns' => ['products.index', 'products.show']],
            ['label' => 'Thương hiệu sản phẩm', 'icon' => 'bi-tags', 'route' => 'brands.index', 'fallback' => '/brands', 'patterns' => ['brands.*']],
            ['label' => 'Lắp ráp / Sản xuất', 'icon' => 'bi-tools', 'route' => 'site-assemblies.index', 'fallback' => '/cong-trinh/lap-rap-san-xuat', 'patterns' => ['site-assemblies.*']],
            [
                'label' => 'Nhập · Xuất · Tồn', 'icon' => 'bi-arrow-left-right',
                'children' => [
                    ['label' => 'Hàng nhập', 'icon' => 'bi-box-arrow-in-down', 'route' => 'product-goods-receipts.index', 'fallback' => '/products/goods-receipts', 'patterns' => ['product-goods-receipts.*', 'products.input']],
                    ['label' => 'Hàng xuất', 'icon' => 'bi-box-arrow-up', 'route' => 'products.output', 'fallback' => '/products/output', 'patterns' => ['products.output']],
                    ['label' => 'Hàng tồn', 'icon' => 'bi-boxes', 'route' => 'warehouses.index', 'fallback' => '/warehouses', 'patterns' => ['warehouses.inventory']],
                ],
            ],
            ['label' => 'Serial & bảo hành', 'icon' => 'bi-upc-scan', 'route' => 'serial-warranty.index', 'fallback' => '/serial-warranty', 'patterns' => ['serial-warranty.*', 'products.serials.*']],
            ['label' => 'Đơn hàng', 'icon' => 'bi-receipt-cutoff', 'route' => 'orders.index', 'fallback' => '/orders', 'patterns' => ['orders.*']],
            ['label' => 'Hàng ký gửi', 'icon' => 'bi-box-seam', 'route' => 'customer-consignments.index', 'fallback' => '/ky-gui-hang-hoa', 'patterns' => ['customer-consignments.*']],
            ['label' => 'Công trình', 'icon' => 'bi-buildings', 'route' => 'project-test.index', 'fallback' => '/cong-trinh', 'patterns' => ['project-test.*']],
            ['label' => 'Công nợ nhà cung cấp', 'icon' => 'bi-cash-stack', 'route' => 'finance.supplier-debts.index', 'fallback' => '/finance/supplier-debts', 'patterns' => ['finance.supplier-debts.*']],
        ],

        'hr' => [
            $section('NGHIỆP VỤ NHÂN SỰ'),
            ['label' => 'Tổng quan nhân sự', 'icon' => 'bi-speedometer2', 'route' => 'hr.dashboard', 'fallback' => '/nhan-su', 'patterns' => ['hr.dashboard']],
            ['label' => 'Nhân viên', 'icon' => 'bi-people', 'route' => 'hr.employees.index', 'fallback' => '/nhan-su/employees', 'patterns' => ['hr.employees.*', 'hr.records.*']],
            ['label' => 'Tuyển dụng', 'icon' => 'bi-person-plus', 'route' => 'hr.recruitment.requests', 'fallback' => '/nhan-su/tuyen-dung/yeu-cau', 'patterns' => ['hr.recruitment.*']],
            ['label' => 'Chấm công nhân sự', 'icon' => 'bi-calendar2-check', 'route' => 'hr.attendance.index', 'fallback' => '/nhan-su/cham-cong', 'patterns' => ['hr.attendance.index']],
            [
                'label' => 'Hành chính', 'icon' => 'bi-building-gear',
                'children' => [
                    ['label' => 'Chi phí văn phòng', 'icon' => 'bi-cash-coin', 'route' => 'hr.office-expenses.index', 'fallback' => '/nhan-su/chi-phi-vp', 'patterns' => ['hr.office-expenses.*']],
                    ['label' => 'Văn phòng phẩm', 'icon' => 'bi-box2', 'route' => 'hr.office-supply-process.index', 'fallback' => '/nhan-su/quy-trinh-phan-bo-vpp', 'patterns' => ['hr.office-supply-process.*']],
                    ['label' => 'Quà tặng', 'icon' => 'bi-gift', 'route' => 'hr.gifts.index', 'fallback' => '/nhan-su/qua-tang', 'patterns' => ['hr.gifts.*']],
                    ['label' => 'Tài sản & nhà cung cấp', 'icon' => 'bi-pc-display', 'route' => 'hr.operations.index', 'fallback' => '/nhan-su/hc-van-hanh', 'patterns' => ['hr.operations.*']],
                ],
            ],
        ],

        'marketing' => [
            $section('NGHIỆP VỤ MARKETING'),
            ['label' => 'Tổng quan Marketing', 'icon' => 'bi-speedometer2', 'route' => 'marketing.dashboard', 'fallback' => '/marketing/dashboard', 'patterns' => ['marketing.dashboard']],
            ['label' => 'Kế hoạch Marketing', 'icon' => 'bi-calendar-range', 'route' => 'marketing.plan.overview', 'fallback' => '/marketing/plan', 'patterns' => ['marketing.plan.*']],
            ['label' => 'Khách hàng tiềm năng', 'icon' => 'bi-people', 'route' => 'marketing.leads.index', 'fallback' => '/marketing/leads', 'patterns' => ['marketing.leads.*']],
            ['label' => 'Tiến độ chiến dịch', 'icon' => 'bi-kanban', 'route' => 'marketing.progress.index', 'fallback' => '/marketing/progress', 'patterns' => ['marketing.progress.*']],
            ['label' => 'Lịch nội dung', 'icon' => 'bi-calendar3', 'route' => 'marketing.reports.content-calendar', 'fallback' => '/marketing/reports/content-calendar', 'patterns' => ['marketing.reports.content-calendar*']],
            ['label' => 'Báo cáo Marketing', 'icon' => 'bi-file-earmark-bar-graph', 'route' => 'marketing.report.overview', 'fallback' => '/marketing/report/overview', 'patterns' => ['marketing.report.*']],
        ],

        'general' => [
            $section('CÔNG VIỆC CỦA TÔI'),
            ['label' => 'Việc của tôi', 'icon' => 'bi-person-workspace', 'route' => 'tasks.my', 'fallback' => '/chat/tasks/my', 'patterns' => ['tasks.my', 'tasks.show']],
        ],
    ],

    'common_sections' => [
        $section('CÔNG VIỆC CHUNG'),
        ['label' => 'Đề nghị thanh toán', 'icon' => 'bi-receipt', 'route' => 'payment_requests.index', 'fallback' => '/payment-requests', 'patterns' => ['payment_requests.*']],
        ['label' => 'Tạm ứng & Hoàn ứng', 'icon' => 'bi-wallet2', 'route' => 'payment_advances.index', 'fallback' => '/payment-requests/tam-ung-hoan-ung', 'patterns' => ['payment_advances.*']],
        ['label' => 'Công việc', 'icon' => 'bi-briefcase', 'route' => 'tasks.index', 'fallback' => '/chat/tasks', 'patterns' => ['tasks.*']],
        ['label' => 'Đề xuất', 'icon' => 'bi-lightbulb', 'route' => 'de-xuat.index', 'fallback' => '/de-xuat', 'patterns' => ['de-xuat.*']],

        $section('CÁ NHÂN'),
        ['label' => 'Chấm công', 'icon' => 'bi-calendar-check', 'route' => 'hr.attendance.my', 'fallback' => '/nhan-su/cham-cong-cua-toi', 'patterns' => ['hr.attendance.my']],
        ['label' => 'Đơn nghỉ phép', 'icon' => 'bi-calendar-x', 'route' => 'hr.leave.index', 'fallback' => '/nhan-su/leave-requests', 'patterns' => ['hr.leave.*']],
        ['label' => 'Đăng ký tăng ca', 'icon' => 'bi-clock-history', 'route' => 'hr.overtime.index', 'fallback' => '/nhan-su/tang-ca', 'patterns' => ['hr.overtime.*']],
        ['label' => 'Đặt phòng họp', 'icon' => 'bi-calendar2-plus', 'route' => 'meeting-room-bookings.index', 'fallback' => '/booking-phong-hop', 'patterns' => ['meeting-room-bookings.*']],

        $section('TÀI LIỆU'),
        ['label' => 'Hồ sơ công ty', 'icon' => 'bi-building', 'route' => 'company-documents.index', 'fallback' => '/company-documents', 'patterns' => ['company-documents.*']],
        ['label' => 'Giao nhận hồ sơ', 'icon' => 'bi-folder-symlink', 'route' => 'hr.document-handovers.index', 'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so', 'patterns' => ['hr.document-handovers.*']],
    ],
];
