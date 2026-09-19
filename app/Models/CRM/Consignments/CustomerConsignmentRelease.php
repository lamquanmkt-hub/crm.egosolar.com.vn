<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConsignmentRelease extends Model
{
    protected $table = 'customer_consignment_releases';

    protected $fillable = [
        'consignment_id', 'code', 'status', 'delivery_date', 'warranty_months', 'receiver_name',
        'receiver_phone', 'shipping_address', 'shipping_carrier', 'tracking_number',
        'note', 'created_by', 'processed_by', 'processed_at', 'cancelled_by',
        'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'warranty_months' => 'integer',
        'processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CANCELLED = 'cancelled';

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignment::class, 'consignment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerConsignmentReleaseItem::class, 'release_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
