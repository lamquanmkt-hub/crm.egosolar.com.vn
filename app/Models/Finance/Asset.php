<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $table = 'finance_assets';

    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'start_use_date' => 'date',
        'warranty_until' => 'date',
        'next_maintenance_date' => 'date',
        'original_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
    ];
}
