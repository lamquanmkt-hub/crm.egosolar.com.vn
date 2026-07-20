<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái công nợ nhà cung cấp (cột finance_supplier_debts.status).
 *
 * Quy ước nghiệp vụ quan trọng: đợt thanh toán ở trạng thái 'planned' KHÔNG
 * được tính vào paid_amount — công nợ chỉ chuyển PARTIAL khi có tiền đã chi
 * hoặc đang chờ chi, và chỉ PAID khi đã trả đủ tổng nợ.
 */
enum SupplierDebtStatus: string
{
    /** Chưa phát sinh khoản chi nào. */
    case UNPAID = 'unpaid';

    /** Đã chi một phần, hoặc có đợt đang chờ chi. */
    case PARTIAL = 'partial';

    /** Đã trả đủ toàn bộ công nợ. */
    case PAID = 'paid';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Chưa thanh toán',
            self::PARTIAL => 'Thanh toán một phần',
            self::PAID => 'Đã thanh toán',
        };
    }

    /**
     * Suy ra trạng thái công nợ từ số tiền.
     *
     * @param  float  $totalAmount  Tổng nợ
     * @param  float  $paidAmount  Số tiền đã thực chi
     * @param  float  $pendingAmount  Số tiền đang chờ chi (đợt chưa duyệt xong)
     */
    public static function fromAmounts(float $totalAmount, float $paidAmount, float $pendingAmount): self
    {
        $remain = max($totalAmount - $paidAmount, 0);

        if ($remain <= 0 && $totalAmount > 0) {
            return self::PAID;
        }

        if ($paidAmount > 0 || $pendingAmount > 0) {
            return self::PARTIAL;
        }

        return self::UNPAID;
    }
}
