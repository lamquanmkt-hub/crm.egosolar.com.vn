<?php
return [
    // ====================================================
    // 1. MARKETING (Bộ phận Marketing)
    // ====================================================
    'marketing' => [
        // Lead
        'lead.view_all'        => 'Xem tất cả Lead',
        'lead.create'          => 'Thêm mới Lead',
        'lead.update_all'      => 'Cập nhật / chăm sóc mọi Lead',
        'lead.rate'            => 'Đánh giá chất lượng Lead',
    ],
    // ====================================================
    // 2. SALES (Nhân viên kinh doanh)
    // ====================================================
    'sales' => [
        // Lead
        'lead.view_own'        => 'Xem Lead được phân công',
        'lead.update_own'      => 'Chăm sóc Lead được phân công',
        'lead.convert'         => 'Chuyển Lead thành Khách hàng',
        // Customer
        'customer.view_own'    => 'Xem khách hàng do mình phụ trách',
        'customer.create'      => 'Tạo khách hàng mới',
        'customer.update_own'  => 'Cập nhật khách hàng do mình phụ trách',
        // Order
        'order.create'         => 'Tạo đơn hàng mới',
        'order.view_own'       => 'Xem đơn hàng của mình',
        'order.view_status'    => 'Theo dõi trạng thái đơn hàng',
        // Lookup (read-only)
        'product.view'         => 'Tra cứu sản phẩm (Giá & Tồn kho)',
        'warehouse.view'       => 'Xem danh sách kho hàng',
    ],
    // ====================================================
    // 3. SALES MANAGER (Quản lý kinh doanh) - Duyệt bước 1
    // ====================================================
    'sales_manager' => [
        // Lead
        'lead.view_all'           => 'Xem tất cả Lead',
        'lead.assign'             => 'Phân công Lead cho Sales',
        'lead.update_all'         => 'Hỗ trợ / cập nhật Lead',
        // Customer (Sales manager vẫn được xem khách hàng để quản lý Sales)
        'customer.view_sales_all' => 'Xem toàn bộ khách hàng của Sales',
        'customer.create'      => 'Tạo khách hàng mới',
        'customer.update_sales'   => 'Cập nhật khách hàng thuộc Sales phụ trách',
        // Order
        'customer.view_all'       => 'Xem tất cả khách hàng',
        'order.view_sales_all'    => 'Xem toàn bộ đơn hàng của Sales',
        'order.approve_level1'    => 'Duyệt đơn – Sales Manager (Bước 1)',
        'order.reject'            => 'Từ chối đơn hàng',
        // ĐẶT CỌC khi tạo đơn (Sales_manager được phép ghi nhận đặt cọc)
        // (Giữ payment.create để tái dùng module Payment hiện có)
        'payment.create'          => 'Ghi nhận đặt cọc / phiếu thu (Sales Manager)',
        // Lookup
        'product.view'            => 'Tra cứu sản phẩm (Giá & Tồn kho)',
        'warehouse.view'          => 'Xem danh sách kho hàng',
        // Sales Management
        'user.view_sales'         => 'Xem danh sách nhân viên Sales',
        'user.manage_sales'       => 'Quản lý nhân viên Sales (phân công, KPI)',
        // Report
        'report.view_sales'       => 'Xem báo cáo kinh doanh của Sales',
    ],
    // ====================================================
    // 4. ACCOUNTING (Kế toán thuế) - Duyệt bước 2
    // Không được xem phần khách hàng + Không ghi nhận thanh toán
    // ====================================================
    'accounting' => [
        // Order
        'order.view_all'          => 'Xem đơn hàng (không xem phần khách hàng)',
        'order.approve_accounting'=> 'Duyệt đơn – Kế toán (Bước 2)',
        'order.reject'            => 'Từ chối đơn hàng',
        // Chỉ tick “đã thanh toán” (KHÔNG tạo phiếu thu)
        'order.mark_paid'         => 'Đánh dấu đã thanh toán khi duyệt',
        // Lookup
        'product.view'            => 'Tra cứu sản phẩm (Giá & Tồn kho)',
        'warehouse.view'          => 'Xem danh sách kho hàng',
        // Report
        'report.revenue'          => 'Xem báo cáo doanh thu',
    ],
    // ====================================================
    // 5. WAREHOUSE (Kế toán kho / Kho vận)
    // Full chức năng sản phẩm/kho - Không được phép xem khách hàng
    // ====================================================
    'warehouse' => [
        'warehouse.view'          => 'Xem danh sách kho hàng',
        'warehouse.manage'        => 'Tạo/Sửa kho',
        'warehouse.stock_check'   => 'Kiểm kê kho',
        'warehouse.stock_update'  => 'Điều chỉnh tồn kho',
        'warehouse.export'        => 'Xuất kho',

        'product.view'            => 'Xem sản phẩm',
        'products.manage'         => 'Thêm/Sửa/Xóa sản phẩm',
        'categories.manage'       => 'Thêm/Sửa/Xóa danh mục',

        // thêm để hiện menu con
        'brands.manage'           => 'Thêm/Sửa/Xóa thương hiệu',
        'price-tiers.manage'      => 'Thêm/Sửa/Xóa loại giá',
    ],

    // ====================================================
    // 6. MANAGEMENT (Ban giám đốc) - Duyệt cuối
    // ====================================================
    'management' => [
        // Management vẫn được xem khách hàng
        'customer.view_all'       => 'Xem toàn bộ khách hàng',
        // Order
        'order.view_all'          => 'Xem toàn bộ đơn hàng',
        'order.approve_level2'    => 'Duyệt đơn – Ban giám đốc (Duyệt cuối)',
        'order.force_approve'     => 'Duyệt vượt cấp',
        // Report
        'report.view_all'         => 'Xem toàn bộ báo cáo quản trị',
        // System
        'system.view_logs'        => 'Xem nhật ký hệ thống',
    ],
    // ====================================================
    // 7. ADMIN (Quản trị hệ thống)
    // ====================================================
    'admin' => [
        '*'                       => 'Toàn quyền hệ thống',
        // Customer
        'customer.view_all'       => 'Xem tất cả khách hàng',
        'customer.update_all'     => 'Cập nhật tất cả khách hàng',
        'customer.delete'         => 'Xóa khách hàng',
        // User & System
        'user.manage'             => 'Quản lý tài khoản & phân quyền',
        'setting.system'          => 'Cấu hình hệ thống',
        // Master data
        'products.manage'         => 'Quản lý sản phẩm',
        'categories.manage'       => 'Quản lý danh mục',
        'warehouse.manage'        => 'Quản lý kho',
    ],
    // ====================================================
    // ====================================================
// 8. KY_THUAT (Kỹ thuật công trình)
// ====================================================
'ky_thuat' => [
    // Công trình
    'site.view'                => 'Xem công trình',
    'site.manage'              => 'Tạo/Sửa công trình',

    // Đơn vật tư (đơn để xuất kho)
    'material_request.create'  => 'Tạo đơn vật tư',
    'material_request.view_own'=> 'Xem đơn vật tư của mình',
    'material_request.submit'  => 'Gửi duyệt đơn vật tư',

    // Lookup
    'product.view'             => 'Tra cứu sản phẩm (Giá & Tồn kho)',
    'warehouse.view'           => 'Xem danh sách kho hàng',
],

];
