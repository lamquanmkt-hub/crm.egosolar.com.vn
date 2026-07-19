<?php
namespace App\Models\Inventory\Pricing;
use Illuminate\Database\Eloquent\Model;

class PriceTier extends Model
{
    protected $table = 'crm_price_tiers';
    protected $fillable = ['code', 'name', 'priority', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}
