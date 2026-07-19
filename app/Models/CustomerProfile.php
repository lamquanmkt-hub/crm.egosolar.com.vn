<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    protected $table = 'customer_profiles';

    protected $fillable = [
        'customer_id',
        'agent_name',
        'agent_level',
        'price_tier_id',
        'deposit_amount',
        'deposit_date',
        'status',
        'phone',
        'email',
        'address',
        'tax_code',
        'representative_name',
        'representative_position',
        'contract_code',
        'contract_date',
        'next_followup_date',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'deposit_date' => 'date',
        'contract_date' => 'date',
        'next_followup_date' => 'date',
    ];

    public function documents()
    {
        return $this->hasMany(CustomerProfileDocument::class, 'customer_profile_id');
    }
}
