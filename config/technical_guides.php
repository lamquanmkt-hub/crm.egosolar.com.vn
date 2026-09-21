<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Đăng ký các bài "Cách sử dụng" của module Kỹ thuật
|--------------------------------------------------------------------------
|
| NỘI DUNG TĨNH TRONG SOURCE — có version qua git, KHÔNG dùng bảng DB.
| File này chỉ chứa METADATA; nội dung chi tiết của mỗi bài nằm ở
| `resources/views/technical/guides/{slug}.blade.php`.
|
| Mỗi bài khai báo:
|   slug              đoạn URL  /ky-thuat/huong-dan/{slug}
|   title             tên bài
|   group             staff | manager | admin  (nhóm hiển thị ở trang danh mục)
|   roles             MẢNG vai trò ĐƯỢC XEM — kiểm ở BACKEND (controller),
|                     không chỉ ẩn nút ở Blade.
|   icon              Bootstrap Icons có sẵn trong layout (không tải icon mới)
|   summary           mô tả 1 dòng cho card
|   reading_minutes   thời gian đọc ước lượng theo số bước
|   route_hint        tên route trang nghiệp vụ tương ứng (đích mặc định của
|                     nút "Quay lại trang đang sử dụng" khi `?return` không
|                     hợp lệ)
|   route_hint_params tham số kèm theo cho route_hint (tuỳ chọn)
|
| Vai trò được xác định bởi `App\Services\Technical\TechnicalAccess`
| (admin = `User::isAdmin()`; manager = canManage() không phải admin;
| staff = isScopedToSelf()). KHÔNG hardcode email.
*/

return [

    'groups' => [
        'staff' => [
            'label' => 'Dành cho Nhân viên Kỹ thuật',
            'description' => 'Tự lập kế hoạch tuần, thực hiện và báo cáo kết quả mỗi ngày.',
            'icon' => 'bi-person-workspace',
        ],
        'manager' => [
            'label' => 'Dành cho Trưởng phòng Kỹ thuật',
            'description' => 'Giao việc, điều chỉnh kế hoạch, duyệt báo cáo và tổng kết tuần.',
            'icon' => 'bi-diagram-3',
        ],
        'admin' => [
            'label' => 'Dành cho Admin / Ban giám đốc',
            'description' => 'Theo dõi toàn phòng, giao việc, mở lại báo cáo đã duyệt và KPI.',
            'icon' => 'bi-shield-lock',
        ],
    ],

    'guides' => [

        /* ---------------- NHÂN VIÊN KỸ THUẬT ---------------- */

        [
            'slug' => 'nhan-vien-tong-quan',
            'title' => 'Tổng quan Kỹ thuật của tôi',
            'group' => 'staff',
            'roles' => ['staff', 'manager', 'admin'],
            'icon' => 'bi-speedometer2',
            'summary' => 'Đọc 4 chỉ số đầu ngày và biết nên bấm vào đâu tiếp theo.',
            'reading_minutes' => 2,
            'route_hint' => 'ky-thuat.tong-quan',
        ],
        [
            'slug' => 'nhan-vien-ke-hoach-tuan',
            'title' => 'Lập kế hoạch tuần của tôi',
            'group' => 'staff',
            'roles' => ['staff', 'manager', 'admin'],
            'icon' => 'bi-calendar-week',
            'summary' => 'Thêm đầu việc từng ngày, sao chép tuần trước và hoàn tất kế hoạch tuần.',
            'reading_minutes' => 4,
            'route_hint' => 'technical.week-plan.index',
        ],
        [
            'slug' => 'nhan-vien-bao-cao-ngay',
            'title' => 'Viết và gửi báo cáo ngày',
            'group' => 'staff',
            'roles' => ['staff', 'manager', 'admin'],
            'icon' => 'bi-journal-text',
            'summary' => 'Báo cáo theo dòng kế hoạch, gửi duyệt và xử lý khi bị yêu cầu sửa.',
            'reading_minutes' => 4,
            'route_hint' => 'technical.daily-reports.index',
        ],
        [
            'slug' => 'nhan-vien-bao-cao-phat-sinh',
            'title' => 'Báo cáo công việc phát sinh',
            'group' => 'staff',
            'roles' => ['staff', 'manager', 'admin'],
            'icon' => 'bi-plus-circle',
            'summary' => 'Ghi nhận việc ngoài kế hoạch — bắt buộc nhập lý do phát sinh.',
            'reading_minutes' => 3,
            'route_hint' => 'technical.daily-reports.create',
            'route_hint_params' => ['mode' => 'phat-sinh'],
        ],

        /* ---------------- TRƯỞNG PHÒNG KỸ THUẬT ---------------- */

        [
            'slug' => 'truong-phong-ke-hoach-giao-viec',
            'title' => 'Tạo kế hoạch và giao việc cho nhân viên',
            'group' => 'manager',
            'roles' => ['manager', 'admin'],
            'icon' => 'bi-grid-3x3',
            'summary' => 'Hai form riêng: "Tạo kế hoạch" nhiều dòng và "Giao việc" nhanh một dòng.',
            'reading_minutes' => 6,
            'route_hint' => 'technical.manager.board',
        ],
        [
            'slug' => 'truong-phong-duyet-bao-cao',
            'title' => 'Duyệt và yêu cầu sửa báo cáo',
            'group' => 'manager',
            'roles' => ['manager', 'admin'],
            'icon' => 'bi-check2-square',
            'summary' => 'Xem báo cáo nhân viên gửi lên, duyệt hoặc trả lại kèm ý kiến bắt buộc.',
            'reading_minutes' => 4,
            'route_hint' => 'technical.daily-reports.index',
        ],
        [
            'slug' => 'truong-phong-tong-ket-tuan',
            'title' => 'Tổng quan phòng và tổng kết tuần',
            'group' => 'manager',
            'roles' => ['manager', 'admin'],
            'icon' => 'bi-clipboard-data',
            'summary' => 'Theo dõi tiến độ cả phòng theo tuần và đọc bảng tổng kết.',
            'reading_minutes' => 3,
            'route_hint' => 'technical.manager.weekly-summary',
        ],

        /* ---------------- ADMIN / BAN GIÁM ĐỐC ---------------- */

        [
            'slug' => 'admin-ke-hoach-giao-viec',
            'title' => 'Admin: Kế hoạch & Giao việc toàn phòng',
            'group' => 'admin',
            'roles' => ['admin'],
            'icon' => 'bi-calendar-week',
            'summary' => 'Từ trang Kế hoạch & Giao việc của Admin sang đúng form điều phối.',
            'reading_minutes' => 4,
            'route_hint' => 'technical.dashboard.plans',
        ],
        [
            'slug' => 'admin-mo-lai-bao-cao',
            'title' => 'Admin: Mở lại báo cáo đã duyệt',
            'group' => 'admin',
            'roles' => ['admin'],
            'icon' => 'bi-arrow-counterclockwise',
            'summary' => 'Chỉ Admin / Giám đốc được mở lại; bắt buộc lý do và có ghi lịch sử.',
            'reading_minutes' => 3,
            'route_hint' => 'technical.daily-reports.index',
        ],
        [
            'slug' => 'admin-kpi-ky-thuat',
            'title' => 'KPI Kỹ thuật: đọc và hiểu đúng số',
            'group' => 'admin',
            'roles' => ['admin'],
            'icon' => 'bi-bar-chart-line',
            'summary' => 'Năm tiêu chí, trọng số và nguồn dữ liệu của bảng KPI theo tháng.',
            'reading_minutes' => 5,
            'route_hint' => 'ky-thuat.kpis.index',
        ],
    ],
];
