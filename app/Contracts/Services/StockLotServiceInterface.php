<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Inventory\Catalog\Product;

/**
 * Hợp đồng service quản lý lô tồn kho (StockLot) theo FIFO.
 */
interface StockLotServiceInterface
{
    public function syncManualStock(
        Product $product,
        int $companyId,
        int $warehouseId,
        int $targetQty,
        float $costBeforeVat = 0,
        float $costVatPercent = 0
    ): void;

    public function receiveLot(
        Product $product,
        int $companyId,
        int $warehouseId,
        int $qtyIn,
        float $costBeforeVat,
        float $costVatPercent,
        float $extraCost = 0,
        array $meta = []
    ): int;

    public function issueLots(
        int $productId,
        ?int $companyId,
        int $warehouseId,
        int $qty,
        array $meta = []
    ): array;

    public function changeProductStock(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $changeQty,
        string $reason,
        int $referenceId = 0,
        ?string $referenceType = null,
        ?string $note = null
    ): void;

    public function issueOrderItemFifo($order, $item): array;
}
