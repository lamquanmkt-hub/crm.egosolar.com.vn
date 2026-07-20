<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kỹ thuật viên được phân công cho đợt bảo trì điện mặt trời (leader / member).
 */
class SolarMaintenanceAssignee extends Model
{
    protected $table = 'solar_maintenance_assignees';

    protected $fillable = [
        'maintenance_schedule_id',
        'user_id',
        'role',
        'assignment_role',
        'is_leader',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
