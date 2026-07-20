<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Hợp đồng service quản lý tồn kho sản phẩm (ProductStock).
 */
interface ProductStockServiceInterface
{
    public function checkStock($productId, $warehouseId, ?int $companyId = null): int;

    public function adjustStock(
        $productId,
        $warehouseId,
        int $changeQty,
        ?string $reason = null,
        $referenceId = null,
        ?int $companyId = null
    ): void;

    public function stockIn($productId, $warehouseId, int $quantity, string $reason = 'Nhập kho', ?int $companyId = null): void;

    public function stockOut($productId, $warehouseId, int $quantity, string $reason = 'Xuất kho', $referenceId = null, ?int $companyId = null): void;

    public function getTotalStockByProduct($productId): int;

    public function getLowStockProducts($threshold = 10): Collection|array;

    public function getOutOfStockProducts(): Collection|array;

    public function getStockReportByWarehouse($warehouseId): Collection|array;

    public function getStockSummary(): Collection|array;

    public function getStockHistory($productId, $warehouseId = null, $limit = 50): array|LengthAwarePaginator;
}
