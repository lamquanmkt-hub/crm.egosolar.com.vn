<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use App\Models\Core\Warehouse;
use App\Models\CRM\Orders\OrderItem;
use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConsignmentItem extends Model
{
    protected $table = 'customer_consignment_items';

    protected $fillable = [
        'consignment_id', 'order_item_id', 'product_id', 'source_warehouse_id',
        'quantity', 'issued_quantity', 'returned_quantity', 'sold_quantity',
        'released_quantity', 'unit_price', 'item_note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'issued_quantity' => 'integer',
        'returned_quantity' => 'integer',
        'sold_quantity' => 'integer',
        'released_quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignment::class, 'consignment_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(CustomerConsignmentSerial::class, 'consignment_item_id');
    }

    public function releaseItems(): HasMany
    {
        return $this->hasMany(CustomerConsignmentReleaseItem::class, 'consignment_item_id');
    }
}
