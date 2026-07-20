<?php

namespace App\Models\Inventory\Stock;

use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEventRef extends Model
{
    use HasFactory;

    protected $table = 'crm_inventory_event_refs';

    protected $fillable = [
        'event_id',
        'ref_type',
        'ref_id',
    ];

    /**
     * Các loại tham chiếu
     */
    public const REF_TYPE_ORDER = 'order';

    public const REF_TYPE_ORDER_ITEM = 'order_item';

    public const REF_TYPE_PURCHASE_ORDER = 'purchase_order';

    public const REF_TYPE_TRANSFER = 'transfer';

    public const REF_TYPE_RETURN = 'return';

    public const REF_TYPE_ADJUSTMENT = 'adjustment';

    public const REF_TYPES = [
        self::REF_TYPE_ORDER => 'Đơn hàng',
        self::REF_TYPE_ORDER_ITEM => 'Chi tiết đơn hàng',
        self::REF_TYPE_PURCHASE_ORDER => 'Đơn nhập hàng',
        self::REF_TYPE_TRANSFER => 'Phiếu chuyển kho',
        self::REF_TYPE_RETURN => 'Phiếu trả hàng',
        self::REF_TYPE_ADJUSTMENT => 'Phiếu điều chỉnh',
    ];

    /**
     * Sự kiện kho
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(InventoryEvent::class, 'event_id');
    }

    /**
     * Lấy đối tượng tham chiếu (polymorphic thủ công)
     */
    public function getReferencedModelAttribute()
    {
        return match ($this->ref_type) {
            self::REF_TYPE_ORDER => Order::find($this->ref_id),
            self::REF_TYPE_ORDER_ITEM => OrderItem::find($this->ref_id),
            // Thêm các loại khác khi cần
            default => null,
        };
    }

    /**
     * Lấy tên loại tham chiếu
     */
    public function getRefTypeNameAttribute(): string
    {
        return self::REF_TYPES[$this->ref_type] ?? $this->ref_type;
    }

    /**
     * Scope: Lọc theo loại tham chiếu
     */
    public function scopeOfRefType($query, string $refType)
    {
        return $query->where('ref_type', $refType);
    }

    /**
     * Scope: Lọc theo event
     */
    public function scopeOfEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope: Tìm theo tham chiếu
     */
    public function scopeForReference($query, string $refType, int $refId)
    {
        return $query->where('ref_type', $refType)->where('ref_id', $refId);
    }

    /**
     * Tạo reference cho Order
     */
    public static function createForOrder(int $eventId, int $orderId): self
    {
        return static::create([
            'event_id' => $eventId,
            'ref_type' => self::REF_TYPE_ORDER,
            'ref_id' => $orderId,
        ]);
    }

    /**
     * Tạo reference cho Order Item
     */
    public static function createForOrderItem(int $eventId, int $orderItemId): self
    {
        return static::create([
            'event_id' => $eventId,
            'ref_type' => self::REF_TYPE_ORDER_ITEM,
            'ref_id' => $orderItemId,
        ]);
    }
}
