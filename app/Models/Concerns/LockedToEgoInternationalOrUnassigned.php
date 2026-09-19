<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\EgoCompanyLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait LockedToEgoInternationalOrUnassigned
{
    protected static function bootLockedToEgoInternationalOrUnassigned(): void
    {
        static::addGlobalScope('ego_international_or_unassigned', function (Builder $builder): void {
            $column = $builder->getModel()->qualifyColumn('company_id');

            $builder->where(function (Builder $query) use ($column): void {
                $query->where($column, EgoCompanyLock::id())
                    ->orWhereNull($column);
            });
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('company_id')) {
                $model->setAttribute('company_id', EgoCompanyLock::id());
            }
        });
    }
}
