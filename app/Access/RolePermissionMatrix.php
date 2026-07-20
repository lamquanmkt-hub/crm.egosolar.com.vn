<?php

namespace App\Access;

use App\Enums\Permission;

/**
 * RolePermissionMatrix - Define role-permission mapping
 *
 * Tuân thủ:
 * - SRP: Chỉ định nghĩa mapping
 * - OCP: Dễ thêm role/permission mới
 * - Type-safe: Dùng Permission Enum
 */
class RolePermissionMatrix
{
    /**
     * Get permissions for a role
     *
     * @return array<Permission>
     */
    public static function getPermissions(string $role): array
    {
        return match ($role) {
            'marketing' => self::getMarketingPermissions(),
            'sales' => self::getSalesPermissions(),
            'sales_manager' => self::getSalesManagerPermissions(),
            'accounting' => self::getAccountingPermissions(),
            'warehouse' => self::getWarehousePermissions(),
            'management' => self::getManagementPermissions(),
            'admin' => self::getAdminPermissions(),
            default => [],
        };
    }

    /**
     * Marketing - Bộ phận Marketing
     */
    private static function getMarketingPermissions(): array
    {
        return [
            Permission::LEAD_VIEW_ALL,
            Permission::LEAD_CREATE,
            Permission::LEAD_UPDATE_ALL,
            Permission::LEAD_RATE,
        ];
    }

    /**
     * Sales - Nhân viên kinh doanh
     */
    private static function getSalesPermissions(): array
    {
        return [
            // Lead
            Permission::LEAD_VIEW_OWN,
            Permission::LEAD_UPDATE_OWN,
            Permission::LEAD_CONVERT,

            // Customer
            Permission::CUSTOMER_VIEW_OWN,
            Permission::CUSTOMER_CREATE,
            Permission::CUSTOMER_UPDATE_OWN,

            // Order
            Permission::ORDER_CREATE,
            Permission::ORDER_VIEW_OWN,
            Permission::ORDER_VIEW_STATUS,

            // Lookup
            Permission::PRODUCT_VIEW,
            Permission::WAREHOUSE_VIEW,
        ];
    }

    /**
     * Sales Manager - Quản lý kinh doanh (Duyệt bước 1)
     */
    private static function getSalesManagerPermissions(): array
    {
        return [
            // Lead
            Permission::LEAD_VIEW_ALL,
            Permission::LEAD_ASSIGN,
            Permission::LEAD_UPDATE_ALL,

            // Customer - Xem toàn bộ khách hàng
            Permission::CUSTOMER_VIEW_ALL,
            Permission::CUSTOMER_VIEW_SALES_ALL,
            Permission::CUSTOMER_UPDATE_SALES,

            // Order
            Permission::ORDER_VIEW_SALES_ALL,
            Permission::ORDER_APPROVE_LEVEL1,
            Permission::ORDER_REJECT,

            // Payment
            Permission::PAYMENT_CREATE,

            // Lookup
            Permission::PRODUCT_VIEW,
            Permission::WAREHOUSE_VIEW,

            // Management
            Permission::USER_VIEW_SALES,
            Permission::USER_MANAGE_SALES,

            // Report
            Permission::REPORT_VIEW_SALES,
        ];
    }

    /**
     * Accounting - Kế toán thuế (Duyệt bước 2)
     * Không được xem khách hàng, chỉ xem đơn hàng
     */
    private static function getAccountingPermissions(): array
    {
        return [
            // Order
            Permission::ORDER_VIEW_ALL,
            Permission::ORDER_APPROVE_ACCOUNTING,
            Permission::ORDER_REJECT,
            Permission::ORDER_MARK_PAID,

            // Lookup
            Permission::PRODUCT_VIEW,
            Permission::WAREHOUSE_VIEW,

            // Report
            Permission::REPORT_REVENUE,
        ];
    }

    /**
     * Warehouse - Kế toán kho / Kho vận
     * Full product & warehouse, không xem khách hàng
     */
    private static function getWarehousePermissions(): array
    {
        return [
            // Warehouse
            Permission::WAREHOUSE_VIEW,
            Permission::WAREHOUSE_MANAGE,
            Permission::WAREHOUSE_STOCK_CHECK,
            Permission::WAREHOUSE_STOCK_UPDATE,
            Permission::WAREHOUSE_EXPORT,

            // Product
            Permission::PRODUCT_VIEW,
            Permission::PRODUCTS_MANAGE,
            Permission::CATEGORIES_MANAGE,

            // Order
            Permission::ORDER_VIEW_WAREHOUSE,
        ];
    }

    /**
     * Management - Ban giám đốc (Duyệt cuối)
     */
    private static function getManagementPermissions(): array
    {
        return [
            // Customer
            Permission::CUSTOMER_VIEW_ALL,

            // Order
            Permission::ORDER_VIEW_ALL,
            Permission::ORDER_APPROVE_LEVEL2,
            Permission::ORDER_FORCE_APPROVE,

            // Report
            Permission::REPORT_VIEW_ALL,

            // System
            Permission::SYSTEM_VIEW_LOGS,
        ];
    }

    /**
     * Admin - Quản trị hệ thống
     * Toàn quyền hệ thống
     */
    private static function getAdminPermissions(): array
    {
        return array_values(Permission::cases());
    }

    /**
     * Get all roles
     *
     * @return array<string>
     */
    public static function getAllRoles(): array
    {
        return ['marketing', 'sales', 'sales_manager', 'accounting', 'warehouse', 'management', 'admin'];
    }

    /**
     * Get role description
     */
    public static function getRoleDescription(string $role): string
    {
        return match ($role) {
            'marketing' => 'Bộ phận Marketing',
            'sales' => 'Nhân viên kinh doanh',
            'sales_manager' => 'Quản lý kinh doanh',
            'accounting' => 'Kế toán thuế',
            'warehouse' => 'Kế toán kho / Kho vận',
            'management' => 'Ban giám đốc',
            'admin' => 'Quản trị hệ thống',
            default => 'Unknown',
        };
    }
}
