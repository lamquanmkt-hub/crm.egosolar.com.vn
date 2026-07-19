<?php
namespace App\Models\Marketing;
use Illuminate\Database\Eloquent\Model;
class MarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';
    protected $fillable = [
        'name',
        'platform',
        'note',
        'created_by',
    ];
}
