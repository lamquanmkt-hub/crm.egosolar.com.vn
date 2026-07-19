<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarCalculatorLog extends Model
{
    protected $fillable = [
        'user_id',
        'province',
        'monthly_bill',
        'monthly_kwh',
        'recommended_kwp',
        'investment_cost',
        'payback_years',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}