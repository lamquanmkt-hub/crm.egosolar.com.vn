<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum định nghĩa các mã trạng thái đơn hàng.
 *
 * Tương ứng với bảng `crm_order_status_types.code`.
 * Dùng thay thế magic strings trong toàn bộ codebase.
 */
enum OrderStatusCode: string
{
    case PENDING_APPROVAL      = 'PENDING_APPROVAL';
    case PENDING_SALES_MANAGER = 'PENDING_SALES_MANAGER';
    case PENDING_ACCOUNTING    = 'PENDING_ACCOUNTING';
    case PENDING_MANAGEMENT    = 'PENDING_MANAGEMENT';
    case READY_TO_SHIP         = 'READY_TO_SHIP';
    case COMPLETED             = 'COMPLETED';
    case REJECTED              = 'REJECTED';
    case CANCELLED             = 'CANCELLED';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_APPROVAL      => 'Chờ gửi duyệt',
            self::PENDING_SALES_MANAGER => 'Chờ duyệt - Sales Manager',
            self::PENDING_ACCOUNTING    => 'Chờ duyệt - Kế toán',
            self::PENDING_MANAGEMENT    => 'Chờ duyệt - Ban Giám đốc',
            self::READY_TO_SHIP         => 'Sẵn sàng xuất kho',
            self::COMPLETED             => 'Hoàn thành',
            self::REJECTED              => 'Bị từ chối',
            self::CANCELLED             => 'Đã hủy',
        };
    }

    /**
     * Kiểm tra trạng thái có phải là trạng thái kết thúc.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Kiểm tra trạng thái đang chờ duyệt.
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING_APPROVAL,
            self::PENDING_SALES_MANAGER,
            self::PENDING_ACCOUNTING,
            self::PENDING_MANAGEMENT,
            self::READY_TO_SHIP,
        ], true);
    }
}
