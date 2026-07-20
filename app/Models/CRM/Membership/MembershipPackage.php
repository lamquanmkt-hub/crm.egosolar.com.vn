<?php

namespace App\Models\CRM\Membership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPackage extends Model
{
    use HasFactory;

    protected $table = 'crm_membership_packages';

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_months',
        'benefits',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_months' => 'integer',
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    // Memberships
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'package_id');
    }
}
