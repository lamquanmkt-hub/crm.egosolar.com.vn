<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'crm_payment_methods';

    public $timestamps = false;

    protected $fillable = [
        'method_name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class, 'method_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
