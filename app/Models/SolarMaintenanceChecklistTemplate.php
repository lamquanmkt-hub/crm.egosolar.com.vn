<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarMaintenanceChecklistTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'maintenance_type',
        'item_key',
        'label',
        'description',
        'sort_order',
        'is_required',
        'requires_evidence',
        'min_evidence',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'requires_evidence' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_evidence' => 'integer',
    ];
}
