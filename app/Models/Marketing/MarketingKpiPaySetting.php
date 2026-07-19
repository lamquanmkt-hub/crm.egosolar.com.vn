<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingKpiPaySetting extends Model
{
    protected $table = 'marketing_kpi_pay_settings';

    protected $fillable = [
        'period',
        'user_id',
        'base_salary',
        'kpi_salary_pool',
        'target_post',
        'target_video_ai',
        'target_video_review',
    ];
}
