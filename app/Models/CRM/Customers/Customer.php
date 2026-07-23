<?php

namespace App\Models\CRM\Customers;

use App\Models\Core\Region;
use App\Models\CRM\Leads\Lead;
use App\Models\CRM\Membership\Membership;
use App\Models\CRM\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'crm_customers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'facebook_name',
        'facebook_link',
        'zalo_id',
        'ai_chatbot_link',
        'customer_type_id',
        'region_id',
        'nickname',
        'group_note',
        'owner_id',
        'customer_status',
        'is_potential',
        'converted_to_member_at',

        // billing info
        'billing_company_name',
        'billing_tax_code',
        'billing_address',
        'billing_email',
    ];

    protected $casts = [
        'is_potential' => 'boolean',
        'converted_to_member_at' => 'datetime',
        'owner_id' => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'customer_type_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function latestLead()
    {
        return $this->hasOne(Lead::class)->latestOfMany('created_at');
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Lead::class, 'customer_id', 'lead_id', 'id', 'id');
    }

    public function latestOrder()
    {
        return $this->hasOneThrough(Order::class, Lead::class, 'customer_id', 'lead_id', 'id', 'id')
            ->latest('order_date');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_customer_tags', 'customer_id', 'tag_id')
            ->withPivot('tagged_by', 'tagged_at');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(CustomerDebt::class);
    }

    public function unpaidDebts(): HasMany
    {
        return $this->hasMany(CustomerDebt::class)->where('status', '!=', 'paid');
    }

    public function scopeVisibleToUser($query, User $user)
    {
        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->position ?? null,
            $user->type ?? null,
        ])));

        $isKhoOrWarehouse =
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kho', 'warehouse', 'admin']))
            || (method_exists($user, 'hasRole') && (
                $user->hasRole('kho')
                || $user->hasRole('warehouse')
                || $user->hasRole('admin')
            ))
            || str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'admin');

        if ($isKhoOrWarehouse || $user->can('customer.view_all')) {
            return $query;
        }

        if ($user->can('customer.view_own')) {
            return $query->where('owner_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
