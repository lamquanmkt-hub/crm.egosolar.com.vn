<?php

namespace App\Services;

use App\Models\CRM\Customers\CustomerDebt;
use App\Models\CRM\Orders\Order;
use App\Models\Payments\Payment;
use App\Traits\HandleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service xử lý payment/thanh toán đơn hàng
 *
 * Tách riêng để tuân thủ Single Responsibility Principle
 * Chỉ focus vào các thao tác: record payment, update debt
 */
class OrderPaymentService
{
    use HandleException;

    /**
     * Khởi tạo service với service thông báo.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Ghi nhận thanh toán
     *
     * @param Order $order
     * @param array $data [payment_date, amount, method_id, ...]
     * @throws \Exception
     */
    public function recordPayment(Order $order, array $data): void
    {
        try {
            DB::transaction(function () use ($order, $data) {
                $amount = (float) $data['amount'];

                if ($amount <= 0) {
                    throw new \Exception('Số tiền phải lớn hơn 0');
                }

                // Tạo record thanh toán
                Payment::create([
                    'order_id' => $order->id,
                    'payment_date' => $data['payment_date'],
                    'amount' => $amount,
                    'method_id' => $data['method_id'],
                    'recorded_by' => Auth::id(),
                    'note' => $data['note'] ?? null,
                ]);

                // Update công nợ nếu có
                $this->updateDebt($order, $amount);

                // Check xem đã thanh toán đủ chưa
                $this->checkPaymentCompletion($order);

                // Thông báo
                $this->notificationService->notifyPaymentRecorded($order);
            });
        } catch (\Exception $e) {
            $this->logAndThrow($e, 'recordPayment');
        }
    }

    /**
     * Update công nợ sau khi thanh toán
     */
    private function updateDebt(Order $order, float $paymentAmount): void
    {
        $debt = CustomerDebt::where('order_id', $order->id)->first();

        if (!$debt) {
            return;
        }

        $remainingDebt = $debt->amount - $paymentAmount;

        if ($remainingDebt <= 0) {
            $debt->update(['amount' => 0, 'status' => 'PAID']);
        } else {
            $debt->update(['amount' => $remainingDebt]);
        }
    }

    /**
     * Kiểm tra xem đơn đã thanh toán đủ chưa
     */
    private function checkPaymentCompletion(Order $order): void
    {
        $totalPaid = Payment::where('order_id', $order->id)
            ->sum('amount');

        $totalAmount = (float) $order->total_amount;

        if ($totalPaid >= $totalAmount) {
            $order->update(['payment_recorded' => true]);
        }
    }

    /**
     * Lấy thông tin thanh toán của đơn
     */
    public function getPaymentInfo(Order $order): array
    {
        $totalAmount = (float) $order->total_amount;
        $totalPaid = Payment::where('order_id', $order->id)
            ->sum('amount');
        $remaining = $totalAmount - $totalPaid;

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'remaining' => max(0, $remaining),
            'is_paid' => $remaining <= 0,
            'payment_percentage' => $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100, 2) : 0,
        ];
    }

    /**
     * Lấy danh sách thanh toán của đơn
     */
    public function getPayments(Order $order)
    {
        return $order->payments()
            ->with(['method', 'recordedBy'])
            ->orderByDesc('payment_date')
            ->get();
    }
}
