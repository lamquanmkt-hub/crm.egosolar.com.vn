<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesDailyKpi extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'posts_count',
        'calls_answered',
        'company_data_called',
        'followed_up_all_previous',
        'notes',
        'missing_kpi_count',
        'penalty_amount',
        'completion_percent',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'followed_up_all_previous' => 'boolean',
        'posts_count' => 'integer',
        'calls_answered' => 'integer',
        'company_data_called' => 'integer',
        'missing_kpi_count' => 'integer',
        'penalty_amount' => 'integer',
        'completion_percent' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postLinks(): HasMany
    {
        return $this->hasMany(SalesDailyKpiPostLink::class);
    }
}