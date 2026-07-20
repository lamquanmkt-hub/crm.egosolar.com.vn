<?php

namespace App\Models\CRM\Customers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRatingType extends Model
{
    use HasFactory;

    protected $table = 'crm_customer_rating_types';

    protected $fillable = [
        'name',
        'color_code',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    // Customer Ratings
    public function customerRatings(): HasMany
    {
        return $this->hasMany(CustomerRating::class, 'rating_type_id');
    }
}
