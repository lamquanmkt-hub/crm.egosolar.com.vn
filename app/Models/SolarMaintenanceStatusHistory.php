<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolarMaintenanceStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'solar_maintenance_status_histories';

    protected $guarded = [];

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
