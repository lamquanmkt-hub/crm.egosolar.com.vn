<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use RuntimeException;

final class EgoCompanyLock
{
    public const NAME = 'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO';

    public static function id(): int
    {
        $id = config('crm.single_company_id');

        if (!$id) {
            throw new RuntimeException('CRITICAL ERROR: SINGLE_COMPANY_ID is not configured in .env. The system is strictly running in single-company mode.');
        }

        return (int) $id;
    }

    public static function name(): string
    {
        return self::NAME;
    }

    public static function names(): array
    {
        return [
            self::NAME,
            'Công ty TNHH TMKT Quốc Tế EGO',
            'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGO',
            'CÔNG TY TNHH TMKT QUỐC TẾ EGO',
            'Công ty TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
            'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGP',
        ];
    }

    public static function apply(Request $request): void
    {
        // No longer applying company_id to request or session.
        // Handled globally by single company ID.
    }
}
