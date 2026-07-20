<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingKpiPayRule extends Model
{
    protected $table = 'marketing_kpi_pay_rules';

    protected $fillable = [
        'period',
        'weight_review', 'weight_ai', 'weight_post',
        'bonus_over_review', 'bonus_over_ai', 'bonus_over_post',
        'bonus_config', // ✅ thêm dòng này
    ];

    protected $casts = [
        'weight_review' => 'float',
        'weight_ai' => 'float',
        'weight_post' => 'float',
        'bonus_over_review' => 'float',
        'bonus_over_ai' => 'float',
        'bonus_over_post' => 'float',
        'bonus_config' => 'array', // ✅ thêm dòng này
    ];

    public static function defaults(): array
    {
        return [
            'weight_review' => 40,
            'weight_ai' => 30,
            'weight_post' => 30,
            'bonus_over_review' => 3,
            'bonus_over_ai' => 1,
            'bonus_over_post' => 0.5,
        ];
    }
}
