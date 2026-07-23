<?php

namespace App\Models\ProjectTest;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MaterialAllocation extends Model
{
    protected $table = 'project_test_material_allocations';

    protected $guarded = [];

    protected $casts = [
        'allocated_quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'issued_quantity' => 'decimal:3',
        'available_snapshot' => 'decimal:3',
        'is_serialized' => 'boolean',
        'selected_serial_unit_ids' => 'array',
        'selected_lots_json' => 'array',
        'unit_cost' => 'decimal:2',
        'reserved_at' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(MaterialItem::class, 'material_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
