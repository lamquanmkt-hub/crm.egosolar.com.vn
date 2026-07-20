<?php

namespace App\Models\CRM\Orders;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Serial\SerialUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'crm_order_items';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'warehouse_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount', // ✅ NEW: cho phép lưu giảm (đ)
        'price_tier_id',   // ✅ nếu bảng có cột này (OrderService đang set)
    ];

    protected $casts = [
        'order_id' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'price_tier_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2', // ✅ NEW
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function serialUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            SerialUnit::class,
            'crm_order_item_serial_units',
            'order_item_id',
            'serial_unit_id'
        );
    }
}
