<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolarMaintenanceProfile extends Model
{
    protected $table = 'solar_maintenance_profiles';

    protected $fillable = [
        'company_id','source_type','source_id','project_id','site_id','source_status','status',
        'site_name','customer_name','customer_phone','address','system_kwp','inverter_info',
        'handover_date','warranty_start_date','warranty_end_date','planned_at',
        'source_completed_at','created_by',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'planned_at' => 'datetime',
        'source_completed_at' => 'datetime',
        'system_kwp' => 'decimal:2',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(SolarMaintenanceSchedule::class, 'maintenance_profile_id');
    }
}
