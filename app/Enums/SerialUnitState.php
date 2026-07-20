<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái của một serial/IMEI (cột crm_serial_unit_states.state).
 *
 * Vòng đời: IN_STOCK (trong kho) → SOLD (đã bán cho khách, warehouse_id = null)
 * hoặc → REMOVED (xuất khỏi kho theo đường khác). RESERVED là giữ chỗ tạm.
 */
enum SerialUnitState: string
{
    /** Còn trong kho, sẵn sàng xuất. */
    case IN_STOCK = 'in_stock';

    /** Đã bán cho khách, không còn thuộc kho nào. */
    case SOLD = 'sold';

    /** Đã xuất khỏi kho (không qua đường bán hàng). */
    case REMOVED = 'removed';

    /** Đang giữ chỗ cho một đơn chưa chốt. */
    case RESERVED = 'reserved';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'Trong kho',
            self::SOLD => 'Đã bán',
            self::REMOVED => 'Đã xuất',
            self::RESERVED => 'Đang giữ chỗ',
        };
    }

    /**
     * Các trạng thái được tính là còn khả dụng để xuất bán.
     *
     * @return list<string>
     */
    public static function availableValues(): array
    {
        return [self::IN_STOCK->value];
    }
}
