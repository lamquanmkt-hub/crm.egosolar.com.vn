<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;

class SitePaymentTerm extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'percent',
        'amount',
        'due_date',
        'status',
        'note',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function receipts()
    {
        return $this->hasMany(\App\Models\Receipt::class, 'site_payment_term_id');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->receipts()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->paid_amount);
    }
}
