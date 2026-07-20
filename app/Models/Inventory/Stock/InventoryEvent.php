<?php

namespace App\Models\Inventory\Stock;

use App\Models\Inventory\Serial\SerialEventLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryEvent extends Model
{
    use HasFactory;

    protected $table = 'crm_inventory_events';

    protected $fillable = [
        'event_type',
        'occurred_at',
        'created_by',
        'note',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Các loại sự kiện kho
     */
    public const TYPE_PURCHASE = 'purchase';          // Nhập mua

    public const TYPE_SALE = 'sale';                  // Bán hàng

    public const TYPE_TRANSFER = 'transfer';          // Chuyển kho

    public const TYPE_RETURN_IN = 'return_in';        // Nhận trả lại

    public const TYPE_RETURN_OUT = 'return_out';      // Trả lại NCC

    public const TYPE_ADJUSTMENT = 'adjustment';       // Điều chỉnh

    public const TYPE_INITIAL = 'initial';            // Nhập kho đầu kỳ

    public const TYPE_DAMAGE = 'damage';              // Hư hỏng

    public const TYPE_LOST = 'lost';                  // Mất mát

    public const TYPES = [
        self::TYPE_PURCHASE => 'Nhập mua',
        self::TYPE_SALE => 'Bán hàng',
        self::TYPE_TRANSFER => 'Chuyển kho',
        self::TYPE_RETURN_IN => 'Nhận trả lại',
        self::TYPE_RETURN_OUT => 'Trả lại NCC',
        self::TYPE_ADJUSTMENT => 'Điều chỉnh',
        self::TYPE_INITIAL => 'Nhập kho đầu kỳ',
        self::TYPE_DAMAGE => 'Hư hỏng',
        self::TYPE_LOST => 'Mất mát',
    ];

    /**
     * Người tạo sự kiện
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Các dòng serial trong sự kiện
     */
    public function serialEventLines(): HasMany
    {
        return $this->hasMany(SerialEventLine::class, 'event_id');
    }

    /**
     * Các tham chiếu liên quan (order, purchase order, etc.)
     */
    public function eventRefs(): HasMany
    {
        return $this->hasMany(InventoryEventRef::class, 'event_id');
    }

    /**
     * Lấy tên loại sự kiện
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->event_type] ?? $this->event_type;
    }

    /**
     * Scope: Lọc theo loại sự kiện
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope: Lọc theo người tạo
     */
    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope: Lọc theo khoảng thời gian
     */
    public function scopeBetweenDates($query, $fromDate, $toDate)
    {
        if ($fromDate) {
            $query->where('occurred_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('occurred_at', '<=', $toDate);
        }

        return $query;
    }

    /**
     * Scope: Sự kiện nhập kho
     */
    public function scopeInbound($query)
    {
        return $query->whereIn('event_type', [
            self::TYPE_PURCHASE,
            self::TYPE_RETURN_IN,
            self::TYPE_INITIAL,
            self::TYPE_TRANSFER, // Transfer có thể cả nhập và xuất
        ]);
    }

    /**
     * Scope: Sự kiện xuất kho
     */
    public function scopeOutbound($query)
    {
        return $query->whereIn('event_type', [
            self::TYPE_SALE,
            self::TYPE_RETURN_OUT,
            self::TYPE_DAMAGE,
            self::TYPE_LOST,
            self::TYPE_TRANSFER,
        ]);
    }

    /**
     * Đếm số lượng serial trong sự kiện
     */
    public function getSerialCountAttribute(): int
    {
        return $this->serialEventLines()->count();
    }
}
