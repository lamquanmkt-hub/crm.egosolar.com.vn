<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturnItem extends Model
{
    protected $table = 'order_return_items';

    protected $fillable = [
        'order_return_id', 'order_item_id', 'product_id', 'warehouse_id',
        'ordered_quantity', 'requested_quantity', 'received_quantity',
        'accepted_quantity', 'rejected_quantity', 'stock_posted_quantity',
        'unit_price', 'vat_rate', 'discount_amount', 'return_amount',
        'condition', 'resolution', 'note',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(OrderReturnSerial::class, 'order_return_item_id');
    }
}
