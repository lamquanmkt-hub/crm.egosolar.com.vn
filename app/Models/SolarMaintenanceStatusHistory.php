<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lịch sử chuyển trạng thái của đợt bảo trì điện mặt trời.
 */
class SolarMaintenanceStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'solar_maintenance_status_histories';

    protected $fillable = [
        'maintenance_schedule_id',
        'from_status',
        'to_status',
        'reason',
        'note',
        'changed_by',
        'changed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'changed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
