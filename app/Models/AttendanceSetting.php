<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'work_start_time',
        'work_end_time',
        'late_grace_minutes',
        'late_penalty_per_time',
        'min_work_minutes',
        'require_gps',

        'workday_monday',
        'workday_tuesday',
        'workday_wednesday',
        'workday_thursday',
        'workday_friday',
        'saturday_mode',
        'saturday_custom_dates',
        'workday_sunday',
    ];

    protected $casts = [
        'require_gps' => 'boolean',

        'workday_monday' => 'boolean',
        'workday_tuesday' => 'boolean',
        'workday_wednesday' => 'boolean',
        'workday_thursday' => 'boolean',
        'workday_friday' => 'boolean',
        'workday_sunday' => 'boolean',

        'late_grace_minutes' => 'integer',
        'late_penalty_per_time' => 'integer',
        'min_work_minutes' => 'integer',

        'saturday_custom_dates' => 'array',
    ];
}
