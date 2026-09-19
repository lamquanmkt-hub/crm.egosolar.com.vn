<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EgoCompanyScope
{
    public static function currentId(): int
    {
        return EgoCompanyLock::id();
    }

    public static function currentName(): string
    {
        return EgoCompanyLock::name();
    }

    public static function companyNames(?int $companyId = null): array
    {
        return EgoCompanyLock::names();
    }

    public static function applyToQuery($query, string $table, ?string $alias = null)
    {
        $companyId = EgoCompanyLock::id();
        $prefix = $alias ?: $table;

        try {
            if ($table === 'companies') {
                return $query->where($prefix.'.id', $companyId);
            }

            if (Schema::hasColumn($table, 'company_id')) {
                return $query->where($prefix.'.company_id', $companyId);
            }

            if (Schema::hasColumn($table, 'company')) {
                return $query->whereIn($prefix.'.company', EgoCompanyLock::names());
            }

            if (Schema::hasColumn($table, 'company_name')) {
                return $query->whereIn($prefix.'.company_name', EgoCompanyLock::names());
            }

            if (
                Schema::hasColumn($table, 'warehouse_id')
                && Schema::hasTable('crm_warehouses')
                && Schema::hasColumn('crm_warehouses', 'company_id')
            ) {
                return $query->whereIn($prefix.'.warehouse_id', function ($sub) use ($companyId): void {
                    $sub->from('crm_warehouses')
                        ->select('id')
                        ->where('company_id', $companyId);
                });
            }
        } catch (\Throwable) {
            return $query;
        }

        return $query;
    }
}
