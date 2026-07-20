<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Repository Eloquent thống kê dữ liệu thanh toán.
 */
class PaymentRepository implements PaymentRepositoryInterface
{
    /**
     * Tính tổng số tiền đã thanh toán.
     */
    public function sumPayments()
    {
        return DB::table('crm_payments')->sum('amount');
    }
}
