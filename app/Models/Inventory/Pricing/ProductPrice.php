<?php

namespace App\Models\Inventory\Pricing;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $table = 'crm_product_prices';

    protected $fillable = [
        'product_id',
        'price_tier_id',
        'price',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function priceTier()
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
