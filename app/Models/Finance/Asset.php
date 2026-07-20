<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tài sản cố định của công ty — theo dõi mua sắm, khấu hao, bảo trì và tình trạng sử dụng.
 */
class Asset extends Model
{
    use SoftDeletes;

    protected $table = 'finance_assets';

    protected $fillable = [
        'code',
        'name',
        'category_id',
        'company_id',
        'assigned_to',
        'department',
        'serial_no',
        'purchase_date',
        'start_use_date',
        'warranty_until',
        'next_maintenance_date',
        'vendor',
        'invoice_no',
        'original_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'location',
        'status',
        'condition',
        'note',
        'created_by',
        'deleted_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'start_use_date' => 'date',
        'warranty_until' => 'date',
        'next_maintenance_date' => 'date',
        'original_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
    ];
}
