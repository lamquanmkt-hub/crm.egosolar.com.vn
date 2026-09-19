<?php

namespace App\Models\ProjectTest;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;

class MaterialItem extends Model
{
    protected $table = 'project_test_material_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'issued_quantity' => 'decimal:3',
    ];

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function allocations()
    {
        return $this->hasMany(MaterialAllocation::class, 'material_item_id');
    }
}
