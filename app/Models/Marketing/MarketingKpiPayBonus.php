<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingKpiPayBonus extends Model
{
    protected $table = 'marketing_kpi_pay_bonuses';

    protected $fillable = [
        'period',
        'key',
        'name',
        'calc_type',
        'threshold',
        'per_unit',
        'unit_label',
        'amount',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'per_unit' => 'integer',
        'amount' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
