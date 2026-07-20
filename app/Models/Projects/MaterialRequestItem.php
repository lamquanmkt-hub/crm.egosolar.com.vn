<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng vật tư trong phiếu đề nghị vật tư của công trình.
 */
class MaterialRequestItem extends Model
{
    protected $table = 'material_request_items';

    protected $fillable = [
        'material_request_id',
        'product_id',
        'qty',
        'note',
        'unit',
        'unit_cost',
        'line_total',
        'vat_percent',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Inventory\Catalog\Product::class, 'product_id');
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }
}
