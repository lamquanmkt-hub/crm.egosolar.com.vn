<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        // Core
        'company_id',
        'created_by',
        'name',
        'status',
        'address',
        'contact_name',
        'contact_phone',
        'note',
        //update
        'deployment_started_at',
'completed_at',
'warranty_reminder_1_at',
'warranty_reminder_2_at',
'warranty_reminder_3_at',
'solar_panel_qty',
'solar_panel_wp',
'battery_kwh',

        // System info
        'system_kwp',
        'system_kw_ac',
        'system_type',
        'phase',
        'installed_at',
        'warranty_to',
        'technician_name',
        'monitoring_link',
        'monitoring_account',
        'stage',

        // Finance
        'contract_amount',
        'labor_cost',
        'transport_cost',
        'other_cost',
        'other_cost_note',

        'contract_signed_at',
        'finance_note',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'warranty_to' => 'date',
        'contract_signed_at' => 'date',

        'system_kwp' => 'float',
        'system_kw_ac' => 'float',
'deployment_started_at' => 'date',
'completed_at' => 'date',
'warranty_reminder_1_at' => 'date',
'warranty_reminder_2_at' => 'date',
'warranty_reminder_3_at' => 'date',
'solar_panel_qty' => 'integer',
'solar_panel_wp' => 'float',
'battery_kwh' => 'float',
        'contract_amount' => 'decimal:2',
    ];

    public function plannedMaterials()
    {
        return $this->hasMany(
            \App\Models\Projects\SitePlannedMaterial::class,
            'site_id'
        );
    }

    public function devices()
    {
        return $this->hasMany(
            \App\Models\Projects\SiteDevice::class,
            'site_id'
        );
    }

    public function paymentTerms()
    {
        return $this->hasMany(
            \App\Models\Projects\SitePaymentTerm::class,
            'site_id'
        );
    }

    public function materialRequests()
    {
        return $this->hasMany(
            \App\Models\Projects\MaterialRequest::class,
            'site_id'
        );
    }

    public function receipts()
    {
        return $this->hasMany(
            \App\Models\Receipt::class,
            'site_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(
            \App\Models\Payment::class,
            'site_id'
        );
    }

    public function paymentRequests()
    {
        return $this->hasMany(
            \App\Models\Payments\PaymentRequest::class,
            'site_id'
        );
    }
}