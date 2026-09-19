<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\CRM\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftRequest extends Model
{
    protected $table = 'hr_gift_requests';

    protected $fillable = [
        'company_id', 'code', 'customer_id', 'customer_name', 'customer_phone',
        'delivery_address', 'reason', 'expected_delivery_date', 'status', 'note',
        'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'stock_deducted_at',
        'delivery_status_updated_by', 'delivery_status_updated_at', 'delivered_at',
        'delivery_note', 'returned_at', 'cancelled_at',
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
        'delivery_status_updated_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GiftRequestItem::class, 'gift_request_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
