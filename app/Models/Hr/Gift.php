<?php

declare(strict_types=1);

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gift extends Model
{
    protected $table = 'hr_gifts';

    protected $fillable = [
        'company_id', 'sku', 'name', 'gift_type', 'unit', 'cost_price',
        'minimum_stock', 'current_stock', 'notes', 'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'minimum_stock' => 'decimal:3',
        'current_stock' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GiftReceiptItem::class, 'gift_id');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(GiftRequestItem::class, 'gift_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(GiftStockMovement::class, 'gift_id');
    }
}
