<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HrBusinessTrip extends Model
{
    protected $table = 'hr_business_trips';

    protected $fillable = [
        'code',
        'user_id',
        'created_by',
        'approver_id',
        'approved_by',
        'completed_by',
        'start_date',
        'end_date',
        'location',
        'purpose',
        'daily_allowance',
        'meal_allowance',
        'hotel_allowance',
        'transport_allowance',
        'other_allowance',
        'advance_amount',
        'allowance_note',
        'status',
        'approval_note',
        'rejection_reason',
        'completion_note',
        'approved_at',
        'rejected_at',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'hotel_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getAllowanceTotalAttribute(): float
    {
        return (float) $this->daily_allowance
            + (float) $this->meal_allowance
            + (float) $this->hotel_allowance
            + (float) $this->transport_allowance
            + (float) $this->other_allowance;
    }
}
