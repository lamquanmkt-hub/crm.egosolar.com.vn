<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    protected $table = 'hr_overtime_requests';

    protected $fillable = [
        'user_id',
        'approver_id',
        'approved_by',
        'overtime_date',
        'start_at',
        'end_at',
        'hours',
        'status',
        'reason',
        'approval_note',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'overtime_date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'hours' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((string) $this->status) {
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ((string) $this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }
}
