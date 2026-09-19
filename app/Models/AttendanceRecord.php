<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'late_minutes',
        'early_leave_minutes',
        'work_minutes',
        'status',
        'note',
        'check_in_lat',
        'check_in_lng',
        'check_in_address',
        'check_out_lat',
        'check_out_lng',
        'check_out_address',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'decimal:7',
        'check_in_lng' => 'decimal:7',
        'check_out_lat' => 'decimal:7',
        'check_out_lng' => 'decimal:7',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'absent' => 'Vắng mặt',
            'checked_in' => 'Đã check-in',
            'late' => 'Đi muộn',
            'completed' => 'Hoàn tất',
            'early_leave' => 'Về sớm',
            'incomplete' => 'Thiếu check-out',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'checked_in' => 'primary',
            'late' => 'warning',
            'early_leave' => 'info',
            'incomplete' => 'danger',
            'absent' => 'secondary',
            default => 'secondary',
        };
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

}
