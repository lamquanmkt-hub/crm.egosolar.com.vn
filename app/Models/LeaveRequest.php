<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'approver_id',
        'request_type',
        'leave_type',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'days',
        'reason',
        'status',
        'approved_at',
        'rejected_at',
        'approval_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'days' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }


    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(LeaveRequestApprovalLog::class)->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            'cancelled' => 'Đã huỷ',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }

    public function getRequestTypeLabelAttribute(): string
    {
        return match ($this->request_type) {
            'leave' => 'Nghỉ phép',
            'wfh' => 'Làm online',
            'business_trip' => 'Công tác',
            'late' => 'Xin đi trễ',
            'early_leave' => 'Xin về sớm',
            default => ucfirst((string) $this->request_type),
        };
    }

    public function getLeaveTypeLabelAttribute(): string
    {
        return match ($this->leave_type) {
            'annual' => 'Nghỉ phép năm',
            'unpaid' => 'Nghỉ không lương',
            'sick' => 'Nghỉ ốm',
            'personal' => 'Nghỉ việc riêng',
            'wfh' => 'Làm online',
            'business_trip' => 'Công tác',
            'late' => 'Xin đi trễ',
            'early_leave' => 'Xin về sớm',
            default => $this->leave_type ? ucfirst((string) $this->leave_type) : '-',
        };
    }
}
