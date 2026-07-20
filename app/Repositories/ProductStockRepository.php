<?php

namespace App\Repositories;

use App\Models\Inventory\Stock\ProductStock;

/**
 * Repository thao tác dữ liệu tồn kho sản phẩm theo kho.
 */
class ProductStockRepository extends BaseRepository
{
    /**
     * Khởi tạo repository với model ProductStock.
     */
    public function __construct(ProductStock $stock)
    {
        $this->model = $stock;
    }

    /**
     * Điều chỉnh tồn kho sản phẩm tại kho (tạo bản ghi nếu chưa có).
     */
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
