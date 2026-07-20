<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingBudget extends Model
{
    protected $fillable = [
        'campaign_id',
        'platform',
        'month',
        'budget',
        'actual_spent',
        'note',
        'created_by',
        // legacy
        'campaign',
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
    ];

    public function marketingCampaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
