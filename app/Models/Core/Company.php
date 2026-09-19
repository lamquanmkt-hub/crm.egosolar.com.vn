<?php

namespace App\Models\Core;

use App\Support\EgoCompanyLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('ego_international_company_only', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('id'), EgoCompanyLock::id());
        });
    }

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'code',
        'tax_code',
        'phone',
        'email',
        'address',
        'logo',
        'bank_account',
        'bank_name',
        'bank_holder',
        'is_active',
        'bank_accounts',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(
            Warehouse::class,
            'company_warehouse',
            'company_id',
            'warehouse_id'
        )->withTimestamps();
    }

    public function directWarehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'company_id');
    }
}
