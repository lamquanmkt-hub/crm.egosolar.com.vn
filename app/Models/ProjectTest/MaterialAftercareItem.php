<?php

namespace App\Models\ProjectTest;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;

class MaterialAftercareItem extends Model
{
    protected $table = 'project_test_material_aftercare_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'processed_quantity' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:2',
        'stock_snapshot' => 'decimal:3',
        'replacement_unit_cost_snapshot' => 'decimal:2',
        'replacement_stock_snapshot' => 'decimal:3',
    ];

    public function request()
    {
        return $this->belongsTo(MaterialAftercareRequest::class, 'aftercare_request_id');
    }

    public function materialItem()
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

    public function replacementProduct()
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    public function replacementWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'replacement_warehouse_id');
    }
}
