<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký audit của module bảo trì điện mặt trời (ai làm gì, giá trị cũ/mới, IP...).
 */
class SolarMaintenanceAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'solar_maintenance_audit_logs';

    protected $fillable = [
        'maintenance_schedule_id',
        'action',
        'old_values',
        'new_values',
        'user_id',
        'ip_address',
        'user_agent',
        // created_at do ứng dụng tự gán vì $timestamps = false (bảng không có updated_at)
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
