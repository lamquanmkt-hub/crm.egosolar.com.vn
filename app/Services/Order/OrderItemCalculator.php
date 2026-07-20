<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\CRM\Orders\Order;
use App\Services\PricingService;

/**
 * Xử lý tính toán giá, chiết khấu và quản lý items của đơn hàng.
 *
 * Quy tắc chiết khấu:
 * - discount_amount = giảm / 1 SP (ưu tiên cao hơn)
 * - discount_percent = giảm % (dùng khi discount_amount = 0)
 * - Chiết khấu không vượt quá subtotal
 */
class OrderItemCalculator
{
    /**
     * Tạo các item cho đơn hàng mới.
     *
     * @param  array  $items  Mảng item từ request
     * @param  PricingService  $pricing  Service lấy giá theo bảng giá
     */
    public function createOrderItems(Order $order, array $items, PricingService $pricing): void
    {
        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $warehouseId = (int) ($item['warehouse_id'] ?? 0) ?: (int) ($order->warehouse_id ?? 0);
            $qty = max(1, (int) ($item['quantity'] ?? 0));
            $discPercent = (float) ($item['discount_percent'] ?? 0);
            $discAmount = (float) ($item['discount_amount'] ?? 0);
            $tierId = (int) ($item['price_tier_id'] ?? 0) ?: ($order->price_tier_id ? (int) $order->price_tier_id : null);

            $price = $this->resolveUnitPrice($item, $productId, $tierId, $order->order_date, $pricing);

            $order->items()->create([
                'warehouse_id' => $warehouseId ?: null,
                'product_id' => $productId,
                'price_tier_id' => $tierId,
                'quantity' => $qty,
                'unit_price' => (float) $price,
                'discount_percent' => $this->normalizePercent($discPercent),
                'discount_amount' => max(0, $discAmount),
            ]);
        }
    }

    /**
     * Cập nhật items của đơn hàng (xóa cũ, thêm/sửa mới).
     *
     * @param  array  $items  Mảng item từ request
     */
    public function updateOrderItems(Order $order, array $items): void
    {
        $this->removeStaleItems($order, $items);

        $grandTotal = 0;

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['warehouse_id'])) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $warehouseId = (int) $item['warehouse_id'];
            $qty = max(1, (int) ($item['quantity'] ?? 0));
            $discPercent = (float) ($item['discount_percent'] ?? 0);
            $discAmount = (float) ($item['discount_amount'] ?? 0);

            // Giữ giá chốt nếu item đã tồn tại
            $existing = $order->items()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            $price = $existing
                ? (float) $existing->unit_price
                : (float) ($item['unit_price'] ?? 0);

            $lineTotal = $this->calcLineTotal($qty, $price, $discPercent, $discAmount);

            $order->items()->updateOrCreate(
                [
                    'order_id' => $order->id,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                ],
                [
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_percent' => $this->normalizePercent($discPercent),
                    'discount_amount' => max(0, $discAmount),
                    'price_tier_id' => (int) ($item['price_tier_id'] ?? 0) ?: ($order->price_tier_id ? (int) $order->price_tier_id : null),
                ]
            );

            $grandTotal += $lineTotal;
        }

        $warehouseIds = collect($items)->pluck('warehouse_id')->filter()->unique()->values();

        $order->update([
            'total_amount' => max(0, round($grandTotal, 2)),
            'warehouse_id' => $warehouseIds->count() === 1 ? (int) $warehouseIds->first() : null,
        ]);
    }

    /**
     * Tính tổng tiền đơn hàng từ mảng items.
     */
    public function calcOrderTotalFromItems(array $items): float
    {
        $sum = 0;

        foreach ($items as $it) {
            if (empty($it['product_id'])) {
                continue;
            }

            $sum += $this->calcLineTotal(
                (int) ($it['quantity'] ?? 0),
                (float) ($it['unit_price'] ?? 0),
                (float) ($it['discount_percent'] ?? 0),
                (float) ($it['discount_amount'] ?? 0)
            );
        }

        return max(0, round($sum, 2));
    }

    /**
     * Tính thành tiền cho 1 dòng sản phẩm.
     *
     * Ưu tiên: discount_amount (giảm/SP) > discount_percent (giảm %)
     *
     * @param  int  $qty  Số lượng
     * @param  float  $price  Đơn giá
     * @param  float  $discountPercent  Giảm %
     * @param  float  $discountAmount  Giảm tiền / 1 SP
     * @return float Thành tiền sau chiết khấu
     */
    public function calcLineTotal(int $qty, float $price, float $discountPercent, float $discountAmount = 0): float
    {
        $qty = max(0, $qty);
        $subtotal = $qty * $price;

        $discountPercent = $this->normalizePercent($discountPercent);
        $discountAmount = max(0, $discountAmount);

        $discount = ($discountAmount > 0)
            ? ($discountAmount * $qty)
            : ($subtotal * $discountPercent / 100);

        $discount = min($discount, $subtotal);

        return round($subtotal - $discount, 2);
    }

    /**
     * Xác định đơn giá: từ request hoặc từ bảng giá.
     *
     * @param  mixed  $orderDate
     */
    private function resolveUnitPrice(array $item, int $productId, ?int $tierId, $orderDate, PricingService $pricing): float
    {
        $price = $item['unit_price'] ?? null;

        if ($price === null || $price === '') {
            $price = $pricing->getUnitPrice($productId, $tierId, $orderDate);
        }

        return (float) $price;
    }

    /**
     * Xóa các item không còn trong request.
     */
    private function removeStaleItems(Order $order, array $items): void
    {
        $activePairs = collect($items)
            ->filter(fn ($it) => ! empty($it['product_id']) && ! empty($it['warehouse_id']))
            ->map(fn ($it) => ((int) $it['warehouse_id']).'|'.((int) $it['product_id']))
            ->values()
            ->toArray();

        $order->items()->get()->each(function ($dbItem) use ($activePairs) {
            $key = ((int) $dbItem->warehouse_id).'|'.((int) $dbItem->product_id);
            if (! in_array($key, $activePairs, true)) {
                $dbItem->delete();
            }
        });
    }

    /**
     * Chuẩn hóa phần trăm chiết khấu (0-100).
     */
    private function normalizePercent(float $percent): float
    {
        return max(0, min(100, $percent));
    }
}
