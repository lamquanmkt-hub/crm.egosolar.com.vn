<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Hợp đồng service tính giá sản phẩm (ưu tiên bảng giá tier, fallback theo loại khách hàng).
 */
interface PricingServiceInterface
{
    public function getUnitPrice(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float;

    public function getUnitPriceWithVat(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float;

    public function getVatPercent(int $productId): float;

    /**
     * Tra % VAT cho dòng hàng đơn: ưu tiên bảng giá theo tier, fallback catalog.
     */
    public function resolveVatPercentForOrderItem(int $productId, ?int $priceTierId = null): float;
}
