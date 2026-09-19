<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\EgoCompanyLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait LockedToEgoInternational
{
    protected static function bootLockedToEgoInternational(): void
    {
        static::addGlobalScope('ego_international_only', function (Builder $builder): void {
            $builder->where(
                $builder->getModel()->qualifyColumn('company_id'),
                EgoCompanyLock::id()
            );
        });

        static::creating(function (Model $model): void {
            $model->setAttribute('company_id', EgoCompanyLock::id());
        });

        static::saving(function (Model $model): void {
            $model->setAttribute('company_id', EgoCompanyLock::id());
        });
    }
}
