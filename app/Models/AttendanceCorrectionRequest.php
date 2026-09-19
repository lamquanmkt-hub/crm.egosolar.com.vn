<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'work_date',
        'original_check_in_at',
        'original_check_out_at',
        'requested_check_in_at',
        'requested_check_out_at',
        'approved_check_in_at',
        'approved_check_out_at',
        'reason',
        'status',
        'pending_guard',
        'reviewed_by',
        'review_note',
        'reviewed_at',
        'cancelled_at',
        'before_apply_snapshot',
        'applied_snapshot',
    ];

    protected $casts = [
        'work_date' => 'date',
        'original_check_in_at' => 'datetime',
        'original_check_out_at' => 'datetime',
        'requested_check_in_at' => 'datetime',
        'requested_check_out_at' => 'datetime',
        'approved_check_in_at' => 'datetime',
        'approved_check_out_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'before_apply_snapshot' => 'array',
        'applied_snapshot' => 'array',
    ];

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionAttachment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((string) $this->status) {
            'pending' => 'Chờ HR duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            'cancelled' => 'Đã hủy',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ((string) $this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }
}
