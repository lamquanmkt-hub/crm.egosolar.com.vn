<?php
namespace App\Services;
use App\Models\Inventory\Stock\StockMovement;
use App\Repositories\ProductStockRepository;

class StockService
{
	public function __construct(protected ProductStockRepository $stocks) {}
	public function moveStock($productId, $warehouseId, $qtyChange, $reason, $referenceId, $userId)
	{
		$stock = $this->stocks->adjustStock($productId, $warehouseId, $qtyChange);
		StockMovement::create([
			'product_id' => $productId,
			'warehouse_id' => $warehouseId,
			'change_qty' => $qtyChange,
			'reason' => $reason,
			'reference_id' => $referenceId,
			'created_by' => $userId,
		]);
		return $stock;
	}
}
