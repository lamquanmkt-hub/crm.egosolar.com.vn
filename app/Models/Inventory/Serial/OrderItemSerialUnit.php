<?php

namespace App\Models\Inventory\Serial;

use App\Models\CRM\Orders\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemSerialUnit extends Model
{
    use HasFactory;

    protected $table = 'crm_order_item_serial_units';

    protected $fillable = [
        'order_item_id',
        'serial_unit_id',
    ];

    /**
     * Chi tiết đơn hàng
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Serial unit
     */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    /**
     * Scope: Lọc theo order item
     */
    public function scopeOfOrderItem($query, int $orderItemId)
    {
        return $query->where('order_item_id', $orderItemId);
    }

    /**
     * Scope: Lọc theo serial unit
     */
    public function scopeOfSerialUnit($query, int $serialUnitId)
    {
        return $query->where('serial_unit_id', $serialUnitId);
    }

    /**
     * Scope: Lọc theo order (thông qua order item)
     */
    public function scopeOfOrder($query, int $orderId)
    {
        return $query->whereHas('orderItem', function ($q) use ($orderId) {
            $q->where('order_id', $orderId);
        });
    }

    /**
     * Kiểm tra serial unit đã được gán cho order item nào chưa
     */
    public static function isSerialAssigned(int $serialUnitId): bool
    {
        return static::where('serial_unit_id', $serialUnitId)->exists();
    }

    /**
     * Gán serial unit cho order item
     */
    public static function assignSerial(int $orderItemId, int $serialUnitId): self
    {
        return static::create([
            'order_item_id' => $orderItemId,
            'serial_unit_id' => $serialUnitId,
        ]);
    }

    /**
     * Gỡ serial unit khỏi order item
     */
    public static function unassignSerial(int $orderItemId, int $serialUnitId): bool
    {
        return static::where('order_item_id', $orderItemId)
            ->where('serial_unit_id', $serialUnitId)
            ->delete() > 0;
    }
}
