<?php

namespace App\Enums;

/**
 * Permission Enum - Define all permissions in the system
 * Type-safe, prevents magic strings, easier to refactor
 */
enum Permission: string
{
    // ===== LEAD PERMISSIONS =====
    case LEAD_VIEW_ALL = 'lead.view_all';
    case LEAD_CREATE = 'lead.create';
    case LEAD_UPDATE_ALL = 'lead.update_all';
    case LEAD_UPDATE_OWN = 'lead.update_own';
    case LEAD_VIEW_OWN = 'lead.view_own';
    case LEAD_RATE = 'lead.rate';
    case LEAD_CONVERT = 'lead.convert';
    case LEAD_ASSIGN = 'lead.assign';

    // ===== CUSTOMER PERMISSIONS =====
    case CUSTOMER_VIEW_OWN = 'customer.view_own';
    case CUSTOMER_VIEW_ALL = 'customer.view_all';
    case CUSTOMER_VIEW_SALES_ALL = 'customer.view_sales_all';
    case CUSTOMER_CREATE = 'customer.create';
    case CUSTOMER_UPDATE_OWN = 'customer.update_own';
    case CUSTOMER_UPDATE_SALES = 'customer.update_sales';
    case CUSTOMER_UPDATE_ALL = 'customer.update_all';
    case CUSTOMER_DELETE = 'customer.delete';
    case CUSTOMER_CHECK_DEBT = 'customer.check_debt';

    // ===== ORDER PERMISSIONS =====
    case ORDER_CREATE = 'order.create';
    case ORDER_VIEW_OWN = 'order.view_own';
    case ORDER_VIEW_STATUS = 'order.view_status';
    case ORDER_VIEW_ALL = 'order.view_all';
    case ORDER_VIEW_SALES_ALL = 'order.view_sales_all';
    case ORDER_VIEW_WAREHOUSE = 'order.view_warehouse';
    case ORDER_APPROVE_LEVEL1 = 'order.approve_level1';
    case ORDER_APPROVE_ACCOUNTING = 'order.approve_accounting';
    case ORDER_APPROVE_LEVEL2 = 'order.approve_level2';
    case ORDER_FORCE_APPROVE = 'order.force_approve';
    case ORDER_REJECT = 'order.reject';
    case ORDER_MARK_PAID = 'order.mark_paid';

    // ===== PAYMENT PERMISSIONS =====
    case PAYMENT_CREATE = 'payment.create';
    case PAYMENT_VIEW = 'payment.view';

    // ===== PRODUCT PERMISSIONS =====
    case PRODUCT_VIEW = 'product.view';
    case PRODUCTS_MANAGE = 'products.manage';
    case CATEGORIES_MANAGE = 'categories.manage';

    // ===== WAREHOUSE PERMISSIONS =====
    case WAREHOUSE_VIEW = 'warehouse.view';
    case WAREHOUSE_MANAGE = 'warehouse.manage';
    case WAREHOUSE_STOCK_CHECK = 'warehouse.stock_check';
    case WAREHOUSE_STOCK_UPDATE = 'warehouse.stock_update';
    case WAREHOUSE_EXPORT = 'warehouse.export';

    // ===== REPORT PERMISSIONS =====
    case REPORT_VIEW_BASIC = 'report.view_basic';
    case REPORT_VIEW_SALES = 'report.view_sales';
    case REPORT_VIEW_ALL = 'report.view_all';
    case REPORT_REVENUE = 'report.revenue';

    // ===== USER PERMISSIONS =====
    case USER_VIEW_STAFF = 'user.view_staff';
    case USER_VIEW_SALES = 'user.view_sales';
    case USER_MANAGE_SALES = 'user.manage_sales';
    case USER_MANAGE = 'user.manage';

    // ===== SYSTEM PERMISSIONS =====
    case SYSTEM_VIEW_LOGS = 'system.view_logs';
    case SETTING_SYSTEM = 'setting.system';

    public function description(): string
    {
        return match ($this) {
            // Lead
            self::LEAD_VIEW_ALL => 'Xem tất cả Lead',
            self::LEAD_CREATE => 'Thêm mới Lead',
            self::LEAD_UPDATE_ALL => 'Cập nhật / chăm sóc mọi Lead',
            self::LEAD_UPDATE_OWN => 'Chăm sóc Lead được phân công',
            self::LEAD_VIEW_OWN => 'Xem Lead được phân công',
            self::LEAD_RATE => 'Đánh giá chất lượng Lead',
            self::LEAD_CONVERT => 'Chuyển Lead thành Khách hàng',
            self::LEAD_ASSIGN => 'Phân công Lead cho Sales',

            // Customer
            self::CUSTOMER_VIEW_OWN => 'Xem khách hàng do mình phụ trách',
            self::CUSTOMER_VIEW_ALL => 'Xem toàn bộ khách hàng',
            self::CUSTOMER_VIEW_SALES_ALL => 'Xem toàn bộ khách hàng của Sales',
            self::CUSTOMER_CREATE => 'Tạo khách hàng mới',
            self::CUSTOMER_UPDATE_OWN => 'Cập nhật khách hàng do mình phụ trách',
            self::CUSTOMER_UPDATE_SALES => 'Cập nhật khách hàng thuộc Sales phụ trách',
            self::CUSTOMER_UPDATE_ALL => 'Cập nhật tất cả khách hàng',
            self::CUSTOMER_DELETE => 'Xóa khách hàng',
            self::CUSTOMER_CHECK_DEBT => 'Kiểm tra công nợ khách hàng',

            // Order
            self::ORDER_CREATE => 'Tạo đơn hàng mới',
            self::ORDER_VIEW_OWN => 'Xem đơn hàng của mình',
            self::ORDER_VIEW_STATUS => 'Theo dõi trạng thái đơn hàng',
            self::ORDER_VIEW_ALL => 'Xem toàn bộ đơn hàng',
            self::ORDER_VIEW_SALES_ALL => 'Xem toàn bộ đơn hàng của Sales',
            self::ORDER_VIEW_WAREHOUSE => 'Xem đơn chờ xuất kho',
            self::ORDER_APPROVE_LEVEL1 => 'Duyệt đơn – Sales Manager (Bước 1)',
            self::ORDER_APPROVE_ACCOUNTING => 'Duyệt đơn – Kế toán (Bước 2)',
            self::ORDER_APPROVE_LEVEL2 => 'Duyệt đơn – Ban giám đốc (Duyệt cuối)',
            self::ORDER_FORCE_APPROVE => 'Duyệt vượt cấp',
            self::ORDER_REJECT => 'Từ chối đơn hàng',
            self::ORDER_MARK_PAID => 'Đánh dấu đã thanh toán khi duyệt',

            // Payment
            self::PAYMENT_CREATE => 'Ghi nhận đặt cọc / phiếu thu',
            self::PAYMENT_VIEW => 'Xem thông tin thanh toán',

            // Product
            self::PRODUCT_VIEW => 'Tra cứu sản phẩm (Giá & Tồn kho)',
            self::PRODUCTS_MANAGE => 'Thêm/Sửa/Xóa sản phẩm',
            self::CATEGORIES_MANAGE => 'Thêm/Sửa/Xóa danh mục',

            // Warehouse
            self::WAREHOUSE_VIEW => 'Xem danh sách kho hàng',
            self::WAREHOUSE_MANAGE => 'Tạo/Sửa kho',
            self::WAREHOUSE_STOCK_CHECK => 'Kiểm kê kho',
            self::WAREHOUSE_STOCK_UPDATE => 'Điều chỉnh tồn kho',
            self::WAREHOUSE_EXPORT => 'Xuất kho',

            // Report
            self::REPORT_VIEW_BASIC => 'Xem báo cáo cơ bản',
            self::REPORT_VIEW_SALES => 'Xem báo cáo kinh doanh của Sales',
            self::REPORT_VIEW_ALL => 'Xem toàn bộ báo cáo quản trị',
            self::REPORT_REVENUE => 'Xem báo cáo doanh thu',

            // User
            self::USER_VIEW_STAFF => 'Xem danh sách nhân viên',
            self::USER_VIEW_SALES => 'Xem danh sách nhân viên Sales',
            self::USER_MANAGE_SALES => 'Quản lý nhân viên Sales',
            self::USER_MANAGE => 'Quản lý tài khoản & phân quyền',

            // System
            self::SYSTEM_VIEW_LOGS => 'Xem nhật ký hệ thống',
            self::SETTING_SYSTEM => 'Cấu hình hệ thống',
        };
    }
}
