<?php

namespace App\Repositories\Interfaces;

/**
 * Interface khai báo các thao tác repository cho thanh toán.
 */
interface PaymentRepositoryInterface
{
    /**
     * Tính tổng số tiền đã thanh toán.
     *
     * @return mixed
     */
    public function sumPayments();
}
