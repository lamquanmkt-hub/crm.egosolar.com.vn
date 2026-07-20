<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarRegionProfile extends Model
{
    protected $fillable = [
        'code',
        'name',
        'irradiation_min',
        'irradiation_max',
        'irradiation_default',
        'sun_hours_min',
        'sun_hours_max',
    ];
}
