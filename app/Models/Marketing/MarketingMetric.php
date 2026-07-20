<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingMetric extends Model
{
    protected $guarded = ['id'];

    protected $fillable = [
        'campaign_id',
        'platform',
        'date_from',
        'date_to',
        'reach',
        'leads',
        'spend',
        'gender_breakdown',
        'age_breakdown',
        'region_breakdown',
        'note',
        'created_by',
    ];

    protected $casts = [
        'gender_breakdown' => 'array',
        'age_breakdown' => 'array',
        'region_breakdown' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
