<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái giao hàng của đơn (cột crm_orders.shipping_status).
 *
 * READY được đặt khi đã nhập thông tin giao hàng hoặc vừa xuất kho xong;
 * SHIPPED khi đánh dấu đã bàn giao cho đơn vị vận chuyển.
 */
enum ShippingStatus: string
{
    /** Đã sẵn sàng giao (có thông tin vận chuyển hoặc đã xuất kho). */
    case READY = 'ready';

    /** Đã bàn giao cho đơn vị vận chuyển. */
    case SHIPPED = 'shipped';

    /** Khách đã nhận hàng. */
    case DELIVERED = 'delivered';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::READY => 'Sẵn sàng giao',
            self::SHIPPED => 'Đã vận chuyển',
            self::DELIVERED => 'Đã giao khách',
        };
    }
}
