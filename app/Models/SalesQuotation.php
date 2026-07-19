<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quote_code',
        'quote_date',
        'valid_until',
        'status',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'billing_company_name',
        'billing_tax_code',
        'billing_address',
        'project_name',
        'project_address',
        'system_type',
        'system_kwp',
        'system_kw_ac',
        'battery_kwh',
        'config_summary',
        'application_note',
        'subtotal',
        'discount_amount',
        'vat_percent',
        'vat_amount',
        'grand_total',
        'payment_terms',
        'commercial_terms',
        'warranty_terms',
        'om_terms',
        'note',
        'created_by',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SalesQuotationItem::class, 'quotation_id')->orderBy('sort_order');
    }
}
