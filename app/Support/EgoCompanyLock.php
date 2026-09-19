<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EgoCompanyLock
{
    public const FALLBACK_ID = 2;
    public const CODE = 'EGO_INT';
    public const NAME = 'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO';

    private static ?int $resolvedId = null;
    private static ?string $resolvedName = null;

    public static function id(): int
    {
        if (self::$resolvedId !== null) {
            return self::$resolvedId;
        }

        $id = 0;

        try {
            if (Schema::hasTable('companies')) {
                $query = DB::table('companies');

                if (Schema::hasColumn('companies', 'code')) {
                    $id = (int) (clone $query)
                        ->whereIn('code', ['EGO_INT', 'EGO_QT'])
                        ->value('id');
                }

                if ($id <= 0 && Schema::hasColumn('companies', 'name')) {
                    $id = (int) (clone $query)
                        ->where(function ($q): void {
                            $q->where('name', 'like', '%QUỐC TẾ%')
                                ->orWhere('name', 'like', '%Quốc Tế%')
                                ->orWhere('name', 'like', '%Quoc Te%');
                        })
                        ->value('id');
                }
            }
        } catch (\Throwable) {
            $id = 0;
        }

        return self::$resolvedId = $id > 0 ? $id : self::FALLBACK_ID;
    }

    public static function name(): string
    {
        if (self::$resolvedName !== null) {
            return self::$resolvedName;
        }

        $name = '';

        try {
            if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'name')) {
                $name = trim((string) DB::table('companies')
                    ->where('id', self::id())
                    ->value('name'));
            }
        } catch (\Throwable) {
            $name = '';
        }

        return self::$resolvedName = $name !== '' ? $name : self::NAME;
    }

    public static function names(): array
    {
        return array_values(array_unique([
            self::name(),
            self::NAME,
            'Công ty TNHH TMKT Quốc Tế EGO',
            'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGO',
            'CÔNG TY TNHH TMKT QUỐC TẾ EGO',
            'Công ty TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
            'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGP',
        ]));
    }

    public static function apply(Request $request): void
    {
        $id = self::id();
        $name = self::name();

        foreach ([
            'company_id',
            'selected_company_id',
            'current_company_id',
            'ego_company_id',
            'active_company_id',
        ] as $key) {
            $request->session()->put($key, $id);
        }

        $request->session()->put('active_company_name', $name);

        foreach (['company_id' => $id, 'company' => $name, 'company_name' => $name] as $key => $value) {
            $request->query->set($key, $value);
            $request->request->set($key, $value);
        }

        $request->query->set('company_ids', [$id]);
        $request->request->set('company_ids', [$id]);
    }
}
