<?php

namespace App\Models\CRM\Customers;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    protected $table = 'crm_customer_types';

    protected $fillable = [
        'name',
        'description',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_type_id');
    }
}
