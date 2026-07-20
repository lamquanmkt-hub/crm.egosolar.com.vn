<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum định nghĩa các bộ phận trong luồng phê duyệt đơn hàng.
 *
 * Luồng: Sales → Sales Manager → Accounting → Management → Warehouse → Completed
 *
 * Mỗi department biết:
 * - Tên hiển thị (label)
 * - Bộ phận tiếp theo (nextDepartment)
 * - Status code tương ứng (statusCode)
 */
enum OrderDepartment: string
{
    case SALES = 'sales';
    case SALES_MANAGER = 'sales_manager';
    case ACCOUNTING = 'accounting';
    case MANAGEMENT = 'management';
    case WAREHOUSE = 'warehouse';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::SALES => 'Sales',
            self::SALES_MANAGER => 'Sales Manager',
            self::ACCOUNTING => 'Kế toán',
            self::MANAGEMENT => 'Ban Giám đốc',
            self::WAREHOUSE => 'Kho vận',
            self::COMPLETED => 'Hoàn thành',
            self::CANCELLED => 'Đã hủy',
        };
    }

    /**
     * Bộ phận tiếp theo trong luồng duyệt.
     *
     * @return self|null Null nếu đã ở bước cuối
     */
    public function nextDepartment(): ?self
    {
        return match ($this) {
            self::SALES => self::SALES_MANAGER,
            self::SALES_MANAGER => self::ACCOUNTING,
            self::ACCOUNTING => self::MANAGEMENT,
            self::MANAGEMENT => self::WAREHOUSE,
            self::WAREHOUSE => self::COMPLETED,
            self::COMPLETED,
            self::CANCELLED => null,
        };
    }

    /**
     * Status code khi chuyển sang bước tiếp theo.
     */
    public function statusCode(): OrderStatusCode
    {
        return match ($this) {
            self::SALES => OrderStatusCode::PENDING_SALES_MANAGER,
            self::SALES_MANAGER => OrderStatusCode::PENDING_ACCOUNTING,
            self::ACCOUNTING => OrderStatusCode::PENDING_MANAGEMENT,
            self::MANAGEMENT => OrderStatusCode::READY_TO_SHIP,
            self::WAREHOUSE => OrderStatusCode::COMPLETED,
            self::COMPLETED => OrderStatusCode::COMPLETED,
            self::CANCELLED => OrderStatusCode::REJECTED,
        };
    }

    /**
     * Màu Bootstrap cho hiển thị badge.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::SALES => 'secondary',
            self::SALES_MANAGER => 'warning',
            self::ACCOUNTING => 'info',
            self::MANAGEMENT => 'warning',
            self::WAREHOUSE => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Kiểm tra đơn có thể duyệt ở bước này không.
     */
    public function isApprovable(): bool
    {
        return in_array($this, [
            self::SALES_MANAGER,
            self::ACCOUNTING,
            self::MANAGEMENT,
            self::WAREHOUSE,
        ], true);
    }
}
