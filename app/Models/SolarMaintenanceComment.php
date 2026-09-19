<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarMaintenanceComment extends Model
{
    use SoftDeletes;

    protected $table = 'solar_maintenance_comments';

    protected $fillable = [
        'maintenance_schedule_id', 'company_id', 'user_id',
        'comment_type', 'body', 'mentions',
    ];

    protected $casts = [
        'mentions' => 'array',
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
