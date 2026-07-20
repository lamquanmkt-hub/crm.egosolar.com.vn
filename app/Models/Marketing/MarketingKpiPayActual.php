<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingKpiPayActual extends Model
{
    protected $table = 'marketing_kpi_pay_actuals';

    protected $fillable = [
        'period',
        'user_id',
        'actual_post',
        'actual_video_ai',
        'actual_video_review',
        'trend_videos',
        'livestreams',
        'is_fraud',
        'note',
    ];

    protected $casts = [
        'actual_post' => 'int',
        'actual_video_ai' => 'int',
        'actual_video_review' => 'int',
        'trend_videos' => 'array',
        'livestreams' => 'array',
        'is_fraud' => 'bool',
    ];
}
