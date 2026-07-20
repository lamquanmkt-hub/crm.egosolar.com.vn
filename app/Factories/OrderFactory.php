<?php

namespace App\Factories;

use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderItem;
use Illuminate\Support\Str;

/**
 * Factory cho tạo Order và related objects
 *
 * Tuân thủ Factory pattern và Single Responsibility Principle
 */
class OrderFactory
{
    /**
     * Tạo order code unique
     */
    public static function generateOrderCode(): string
    {
        do {
            $code = 'ORD-'.now()->format('Ymd').'-'.Str::random(6);
        } while (Order::where('order_code', $code)->exists());

        return $code;
    }

    /**
     * Tạo Order từ data
     */
    public static function createOrder(array $data): Order
    {
        $orderData = [
            'order_code' => self::generateOrderCode(),
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'customer_id' => $data['customer_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'created_by' => auth()->id(),
            'current_department' => 'sales',
            'current_status_type_id' => null,
            'total_amount' => 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'shipping_address' => $data['shipping_address'] ?? null,
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
            'payment_recorded' => false,
        ];

        return Order::create($orderData);
    }

    /**
     * Tạo OrderItem từ order và items data
     */
    public static function createOrderItems(Order $order, array $itemsData): void
    {
        foreach ($itemsData as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_percent' => $itemData['discount_percent'] ?? 0,
                'line_total' => self::calculateLineTotal(
                    $itemData['quantity'],
                    $itemData['unit_price'],
                    $itemData['discount_percent'] ?? 0
                ),
            ]);
        }
    }

    /**
     * Tính line total = qty * price * (1 - discount%)
     */
    private static function calculateLineTotal(int $qty, float $price, float $discountPercent): float
    {
        $subtotal = $qty * $price;
        $discount = $subtotal * ($discountPercent / 100);

        return max(0, round($subtotal - $discount, 2));
    }
}
