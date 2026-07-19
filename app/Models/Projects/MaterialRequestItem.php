<?php

namespace App\Models\Projects;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestItem extends Model
{
    protected $table = 'material_request_items';

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Inventory\Catalog\Product::class, 'product_id');
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }
}