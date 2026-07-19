<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'type',
        'is_paid',
        'note',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_paid' => 'boolean',
    ];
}
