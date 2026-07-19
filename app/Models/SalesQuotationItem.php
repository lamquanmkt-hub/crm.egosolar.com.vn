<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'sort_order',
        'section_key',
        'section_title',
        'product_id',
        'product_name',
        'sku',
        'brand',
        'model',
        'unit',
        'specs_text',
        'image_url',
        'qty',
        'unit_price',
        'vat_percent',
        'line_subtotal',
        'line_vat',
        'line_total',
        'note',
    ];

    public function quotation()
    {
        return $this->belongsTo(SalesQuotation::class, 'quotation_id');
    }
}
