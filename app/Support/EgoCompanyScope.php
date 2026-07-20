<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EgoCompanyScope
{
    public static function currentId(): int
    {
        return (int) session('active_company_id', 0);
    }

    public static function currentName(): string
    {
        return trim((string) session('active_company_name', ''));
    }

    public static function companyNames(?int $companyId = null): array
    {
        $companyId = $companyId ?: self::currentId();
        $names = [];

        try {
            if ($companyId > 0 && Schema::hasTable('companies')) {
                $name = DB::table('companies')->where('id', $companyId)->value('name');
                if ($name) {
                    $names[] = (string) $name;
                }
            }
        } catch (\Throwable $e) {
            //
        }

        $currentName = self::currentName();
        if ($currentName !== '') {
            $names[] = $currentName;
        }

        $joined = mb_strtolower(implode(' ', $names), 'UTF-8');

        if (str_contains($joined, 'việt nam') || str_contains($joined, 'viet nam')) {
            $names = array_merge($names, [
                'Công ty TNHH Ego Việt Nam',
                'CÔNG TY TNHH EGO VIỆT NAM',
                'Công ty TNHH Ego Viet Nam',
                'CÔNG TY TNHH EGO VIET NAM',
            ]);
        }

        if (str_contains($joined, 'quốc tế') || str_contains($joined, 'quoc te') || str_contains($joined, 'tmkt')) {
            $names = array_merge($names, [
                'Công ty TNHH TMKT Quốc Tế EGO',
                'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
                'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGO',
                'Công ty TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
                'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGP',
            ]);
        }

        return array_values(array_unique(array_filter($names)));
    }

    public static function applyToQuery($query, string $table, ?string $alias = null)
    {
        $companyId = self::currentId();

        if ($companyId <= 0) {
            return $query;
        }

        $prefix = $alias ?: $table;
        $names = self::companyNames($companyId);

        try {
            if (Schema::hasColumn($table, 'company_id')) {
                $query->where($prefix.'.company_id', $companyId);

                return $query;
            }

            if (Schema::hasColumn($table, 'company')) {
                $query->whereIn($prefix.'.company', $names);

                return $query;
            }

            if (Schema::hasColumn($table, 'company_name')) {
                $query->whereIn($prefix.'.company_name', $names);

                return $query;
            }
        } catch (\Throwable $e) {
            return $query;
        }

        return $query;
    }
}
