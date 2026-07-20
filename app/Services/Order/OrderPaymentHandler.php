<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\CRM\Customers\CustomerDebt;
use App\Models\CRM\Orders\Order;
use App\Models\Payments\Payment;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Xử lý ghi nhận thanh toán cho đơn hàng.
 *
 * Chịu trách nhiệm:
 * - Tạo bản ghi Payment
 * - Cập nhật công nợ
 * - Kiểm tra hoàn tất thanh toán
 * - Gửi thông báo
 */
class OrderPaymentHandler
{
    /**
     * Ghi nhận thanh toán cho đơn hàng.
     *
     * @param  int|string  $id
     * @param  array  $data  {payment_date, amount, method_id, note?}
     */
    public function recordPayment($id, array $data, OrderRepositoryInterface $orderRepo, NotificationService $notificationService): void
    {
        DB::transaction(function () use ($id, $data, $orderRepo, $notificationService) {
            $order = $orderRepo->find($id);
            $amount = (float) $data['amount'];

            $this->createPaymentRecord($order, $data);
            $this->updateDebtRecord($order, $amount);
            $this->checkPaymentCompletion($order);

            $notificationService->notifySales(
                $order->created_by,
                $order,
                'payment_received',
                'Đã thu '.number_format($amount)."đ cho đơn #{$order->order_code}"
            );
        });
    }

    /**
     * Tạo bản ghi thanh toán.
     */
    private function createPaymentRecord(Order $order, array $data): void
    {
        Payment::create([
            'order_id' => $order->id,
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'method_id' => $data['method_id'],
            'recorded_by' => Auth::id(),
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Cập nhật công nợ sau khi thanh toán.
     *
     * @param  float  $amount  Số tiền vừa thanh toán
     */
    private function updateDebtRecord(Order $order, float $amount): void
    {
        $debt = CustomerDebt::where('order_id', $order->id)->first();

        if (! $debt) {
            return;
        }

        $debt->paid_amount += $amount;
        $debt->status = ($debt->paid_amount >= $debt->total_amount) ? 'paid' : 'partial';
        $debt->save();
    }

    /**
     * Kiểm tra và đánh dấu đơn đã thanh toán đủ.
     */
    private function checkPaymentCompletion(Order $order): void
    {
        $totalPaid = $order->payments()->sum('amount');

        if ($totalPaid >= $order->total_amount) {
            $order->update(['payment_recorded' => true]);
        }
    }
}
