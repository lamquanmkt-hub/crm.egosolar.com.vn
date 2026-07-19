<?php
namespace App\Services;
use App\Models\Payments\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Facades\DB;

class PaymentService
{
//	public function __construct(protected OrderRepository $orders) {}
	protected $repo;
	public function __construct(PaymentRepository $repo)
	{
		$this->repo = $repo;
	}
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
	public function sumPayments()
	{
		return $this->repo->sumPayments();
	}
}
