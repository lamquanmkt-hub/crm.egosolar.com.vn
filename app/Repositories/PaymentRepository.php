<?php

namespace App\Repositories;

use App\Models\Payments\Payment;

/**
 * Repository thao tác dữ liệu thanh toán.
 */
class PaymentRepository
{
    /**
     * Lấy tất cả thanh toán kèm đơn hàng, phương thức và người ghi nhận.
     */
    public function all()
    {
        return Payment::with(['order', 'method', 'recordedBy'])->latest()->get();
    }

    /**
     * Tính tổng số tiền đã thanh toán.
     */
    public function sumPayments()
    {
        return Payment::sum('amount');
    }
}
