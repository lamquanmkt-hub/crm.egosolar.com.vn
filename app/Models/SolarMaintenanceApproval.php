<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bản ghi phê duyệt của đợt bảo trì điện mặt trời (gửi duyệt, duyệt, yêu cầu chỉnh sửa...).
 */
class SolarMaintenanceApproval extends Model
{
    protected $fillable = [
        'maintenance_schedule_id',
        'approval_level',
        'approver_id',
        'submitted_by',
        'action',
        'status',
        'comment',
        'submitted_at',
        'reviewed_at',
        'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
