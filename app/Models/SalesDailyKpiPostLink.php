<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDailyKpiPostLink extends Model
{
    protected $fillable = [
        'sales_daily_kpi_id',
        'post_url',
    ];

    public function salesDailyKpi(): BelongsTo
    {
        return $this->belongsTo(SalesDailyKpi::class);
    }
}
