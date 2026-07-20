<?php

namespace App\Models\Core;

use App\Models\CRM;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'crm_regions';

    protected $fillable = ['name'];

    public function customers()
    {
        return $this->hasMany(CRM\Customers\Customer::class);
    }
}
