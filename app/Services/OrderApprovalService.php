<?php
namespace App\Services;
use App\Models\CRM\Orders\OrderApproval;
use App\Repositories\OrderRepository;
use App\Repositories\ProductStockRepository;
use Illuminate\Support\Facades\DB;

/**
 * Service duyệt đơn hàng theo cấp và trừ tồn kho khi kho duyệt.
 */
class OrderApprovalService
{
	/**
	 * Khởi tạo service với repository đơn hàng và tồn kho.
	 */
	public function __construct(
		protected OrderRepository $orders,
		protected ProductStockRepository $stocks
	) {}
	/**
	 * Duyệt đơn hàng theo cấp độ
	 */
	public function approve($orderId, $user, $level): void {
		DB::transaction(function () use ($orderId, $user, $level) {
			$order = $this->orders->find($orderId);
			OrderApproval::create([
				'order_id' => $order->id,
				'level' => $level,
				'approved_by' => $user->id,
				'approved_at' => now(),
				'status' => 'approved',
			]);
			if ($level === 'kho') {
				$this->updateStockAfterApproval($order);
				$order->update([
					'status' => 'APPROVED',
					'approved_by' => $user->id,
					'approved_at' => now(),
				]);
			}
		});
	}
	/**
	 * Trừ tồn kho cho từng sản phẩm sau khi kho duyệt đơn.
	 */
	private function updateStockAfterApproval($order): void {
		foreach ($order->items as $item) {
			$this->stocks->adjustStock(
				$item->product_id,
				$order->warehouse_id,
				-$item->quantity
			);
		}
	}
}
