<?php
namespace App\Services;
use App\Models\Payments\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Facades\DB;

/**
 * Service xử lý nghiệp vụ ghi nhận thanh toán đơn hàng.
 */
class PaymentService
{
//	public function __construct(protected OrderRepository $orders) {}
	protected $repo;
	/**
	 * Khởi tạo service với repository thanh toán.
	 */
	public function __construct(PaymentRepository $repo)
	{
		$this->repo = $repo;
	}
	/**
	 * Ghi nhận thanh toán cho đơn hàng; đánh dấu đã thanh toán đủ khi tổng tiền đạt total_amount.
	 *
	 * @return \App\Models\Orders\Order Đơn hàng sau khi ghi nhận
	 */
	public function recordPayment($orderId, $amount, $methodId, $user)
	{
		return DB::transaction(function () use ($orderId, $amount, $methodId, $user) {
			$order = $this->orders->find($orderId);
			Payment::create([
				'order_id' => $order->id,
				'payment_date' => now(),
				'amount' => $amount,
				'method_id' => $methodId,
				'recorded_by' => $user->id,
			]);
			$totalPaid = $order->payments()->sum('amount');
			if ($totalPaid >= $order->total_amount) {
				$order->update(['payment_recorded' => 1]);
			}
			return $order;
		});
	}
	/**
	 * Tính tổng số tiền thanh toán đã ghi nhận.
	 */
	public function sumPayments()
	{
		return $this->repo->sumPayments();
	}
}
