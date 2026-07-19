<?php
declare(strict_types=1);
namespace App\Services;
use Illuminate\Support\Facades\DB;
class PricingService
{
    /**
     * Lấy đơn giá cho sản phẩm.
     *
     * Ưu tiên: Bảng giá (price_tier) → Giá theo loại khách → Giá retail → Giá chung
     *
     * @param int         $productId
     * @param int|null    $priceTierId    Bảng giá (nếu có)
     * @param string|null $at             Thời điểm hiệu lực
     * @param int|null    $customerTypeId 1=Đại lý, 2=Lẻ (MỚI)
     * @return float Giá TRƯỚC VAT
     */
    public function getUnitPrice(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float {
        // 1. Ưu tiên bảng giá theo tier
        if ($priceTierId) {
            $tierPrice = $this->getTierPrice($productId, $priceTierId, $at);
            if ($tierPrice > 0) {
                return $tierPrice;
            }
        }
        // 2. Fallback theo loại khách hàng
        return $this->fallbackProductPrice($productId, $customerTypeId);
    }
    /**
     * Lấy giá sau VAT.
     */
    public function getUnitPriceWithVat(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float {
        $basePrice = $this->getUnitPrice($productId, $priceTierId, $at, $customerTypeId);
        $vat = $this->getVatPercent($productId);
        return round($basePrice * (1 + $vat / 100), 0);
    }
    /**
     * Lấy giá từ bảng giá tier.
     */
    private function getTierPrice(int $productId, int $priceTierId, ?string $at): float
    {
        $at = $at ?: now()->toDateTimeString();
        $row = DB::table('crm_product_prices')
            ->select('price')
            ->where('product_id', $productId)
            ->where('price_tier_id', $priceTierId)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
            ->orderByRaw('effective_from IS NULL')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        return ($row && isset($row->price)) ? (float) $row->price : 0.0;
    }
    /**
     * Fallback giá theo loại khách hàng.
     *
     * ✅ FIX: Trước đây luôn trả price_retail, bỏ qua agent.
     * Giờ: Đại lý (1) → price_agent, Lẻ (2) → price_retail, Default → price_retail
     *
     * @param int      $productId
     * @param int|null $customerTypeId 1=Đại lý, 2=Lẻ
     * @return float Giá TRƯỚC VAT
     */
    private function fallbackProductPrice(int $productId, ?int $customerTypeId = null): float
    {
        $p = DB::table('crm_product_catalog')
            ->select('price', 'price_retail', 'price_agent')
            ->where('id', $productId)
            ->first();
        if (!$p) {
            return 0.0;
        }
        // ✅ FIX: Đại lý (customer_type_id = 1) → ưu tiên price_agent
        if ((int) $customerTypeId === 1) {
            $agentPrice = (float) ($p->price_agent ?? 0);
            if ($agentPrice > 0) {
                return $agentPrice;
            }
        }
        // Mặc định: price_retail → price → 0
        return (float) ($p->price_retail ?? $p->price ?? 0);
    }
    /**
     * Lấy % VAT của sản phẩm.
     */
    public function getVatPercent(int $productId): float
    {
        $vat = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->value('vat_percent');
        return max(0, (float) ($vat ?? 0));
    }
}
