<?php

namespace App\Services;

use App\Models\Inventory\Stock\StockMovement;
use App\Repositories\ProductStockRepository;

/**
 * Service điều chuyển tồn kho và ghi nhận lịch sử biến động.
 */
class StockService
{
    /**
     * Khởi tạo service với repository tồn kho sản phẩm.
     */
    public function __construct(protected ProductStockRepository $stocks) {}

    /**
     * Điều chỉnh tồn kho và ghi lại lịch sử biến động (StockMovement).
     */
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
