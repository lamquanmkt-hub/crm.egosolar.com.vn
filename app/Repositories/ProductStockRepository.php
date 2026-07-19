<?php
namespace App\Repositories;
use App\Models\Inventory\Stock\ProductStock;

class ProductStockRepository extends BaseRepository
{
	public function __construct(ProductStock $stock)
	{
		$this->model = $stock;
	}
	public function adjustStock($productId, $warehouseId, $qtyChange)
	{
		$stock = $this->model->firstOrCreate([
			'product_id' => $productId,
			'warehouse_id' => $warehouseId,
		]);
		$stock->qty += $qtyChange;
		$stock->last_updated = now();
		$stock->save();
		return $stock;
	}
}
