<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Hợp đồng Bộ máy tính hoa hồng: chính sách theo tháng, bộ quy tắc và tính lại dòng hoa hồng.
 *
 * Sinh từ implementation CommissionEngineService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface CommissionEngineServiceInterface
{
    public function ensureSchema(): void;

    public function currentPolicy(string $month): object;

    public function copyPreviousMonth(string $month): void;

    public function rulesForPolicy(int $policyId): Collection;

    public function settingsViewData(string $month): array;

    public function saveSettings(Request $request): string;

    public function applyDashboard(array $data, object $policy, Collection $rules, Request $request): array;

    public function recalculateRows($rows, object $policy, Collection $rules): Collection;

    public function ruleStats(Collection $rules): array;
}
