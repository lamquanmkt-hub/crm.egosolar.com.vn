<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cấu hình module Kỹ thuật — Kế hoạch tuần (giai đoạn 2)
|--------------------------------------------------------------------------
|
| Mọi ngưỡng nghiệp vụ được đặt ở đây thay vì hardcode trong code/view.
|
| Giờ làm việc: hệ thống ĐÃ có bảng `attendance_settings`
| (work_start_time / work_end_time / min_work_minutes). Service kế hoạch tuần
| ưu tiên đọc bảng đó; các giá trị dưới đây chỉ là PHƯƠNG ÁN DỰ PHÒNG khi bảng
| chưa có dữ liệu (ví dụ cài đặt mới) — không phải nguồn sự thật thứ hai.
|
*/

return [

    'week_plan' => [

        /* Số phút làm việc tiêu chuẩn của một ngày — dự phòng khi attendance_settings trống. */
        'default_daily_minutes' => 480,

        /*
         * Hệ số quá tải: tổng thời lượng dự kiến trong ngày vượt
         * daily_minutes * overload_ratio thì cảnh báo "quá tải".
         */
        'overload_ratio' => 1.0,

        /* Trần cứng: quá ngưỡng này thì CHẶN hoàn tất kế hoạch tuần. */
        'hard_limit_minutes' => 16 * 60,

        /* Thời lượng mặc định gợi ý cho mỗi dòng kế hoạch (phút). */
        'default_item_minutes' => 240,

        /* Số dòng kế hoạch tối đa cho một ngày — chặn spam form. */
        'max_items_per_day' => 12,

        /* Số tuần tối đa được lùi/tiến so với tuần hiện tại. */
        'max_week_offset' => 8,

        /* Chủ nhật không bắt buộc có kế hoạch. */
        'optional_weekdays' => [7],
    ],

    /* Buổi làm việc — giá trị lưu DB => nhãn hiển thị. */
    'day_parts' => [
        'morning' => 'Sáng',
        'afternoon' => 'Chiều',
        'full_day' => 'Cả ngày',
        'custom' => 'Giờ cụ thể',
    ],

    'priorities' => [
        'low' => 'Thấp',
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ],

    /*
     * Role được coi là nhân sự kỹ thuật khi dựng danh sách "phạm vi quản lý"
     * của trưởng phòng. Dùng lại đúng các role đang tồn tại trong hệ thống
     * (SolarMaintenanceAccess::isSelectableTechnician) — không tạo role mới.
     */
    'staff_roles' => [
        'ky_thuat', 'technical', 'technician', 'technical_staff',
        'technical_leader', 'bao_hanh', 'maintenance',
    ],

    /* Từ khoá nhận diện phòng Kỹ thuật trong bảng departments. */
    'department_keywords' => ['ky_thuat', 'technical'],
];
