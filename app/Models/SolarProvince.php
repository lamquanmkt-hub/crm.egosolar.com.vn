<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarProvince extends Model
{
    protected $fillable = [
        'name',
        'region',
        'sun_hours',
        'region_profile_id',
        'irradiation_override',
    ];

    public function regionProfile()
    {
        return $this->belongsTo(SolarRegionProfile::class, 'region_profile_id');
    }

    public function getEffectiveIrradiationAttribute()
    {
        if (! is_null($this->irradiation_override)) {
            return (float) $this->irradiation_override;
        }

        if ($this->regionProfile && ! is_null($this->regionProfile->irradiation_default)) {
            return (float) $this->regionProfile->irradiation_default;
        }

        return 4.6;
    }
}
