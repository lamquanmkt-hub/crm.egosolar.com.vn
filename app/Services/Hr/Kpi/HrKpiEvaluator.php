<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Models\TechnicalKpiConfig;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chấm KPI theo bộ tiêu chí vị trí (HCNS, Kế toán kho) và quy đổi ra hệ số lương KPI.
 * KPI Kỹ sư (mã "technical") lấy từ module KPI kỹ thuật có sẵn.
 *
 *   % đạt tiêu chí: nhập tay, hoặc tự tính từ Thực tế so với Chỉ tiêu (cao hơn tốt / thấp hơn tốt), có trần.
 *   % KPI         = Σ(trọng số × % đạt) / Σ trọng số của các tiêu chí có phát sinh trong tháng.
 *   Hệ số lương   = bậc đầu tiên (xét từ min cao xuống) có % KPI ≥ min: cố định hoặc bằng % KPI, có trần.
 */
final class HrKpiEvaluator
{
    public const TECHNICAL = 'technical';

    public const STATUS_OK = 'ok';

    public const STATUS_NO_WEIGHT = 'no_weight';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const CALC_TYPES = [
        'manual' => 'Nhập % đạt',
        'higher_better' => 'Thực tế ÷ Chỉ tiêu (cao hơn tốt)',
        'lower_better' => 'Chỉ tiêu ÷ Thực tế (thấp hơn tốt)',
    ];

    /** Danh sách bộ KPI để gán cho nhân viên: [code => tên]. */
    public function templateOptions(): array
    {
        $options = self::technicalAvailable() ? [self::TECHNICAL => 'KPI Kỹ sư dân dụng (module Kỹ thuật)'] : [];
        if (SchemaCache::hasTable('hr_kpi_templates')) {
            foreach (DB::table('hr_kpi_templates')->where('is_active', true)->orderBy('id')->get() as $t) {
                $options[$t->code] = $t->name;
            }
        }

        return $options;
    }

    public function template(string $code): ?object
    {
        $template = DB::table('hr_kpi_templates')->where('code', $code)->first();
        if ($template) {
            $template->payout_tiers = json_decode((string) $template->payout_tiers, true) ?: [];
        }

        return $template;
    }

    public function criteria(int $templateId): Collection
    {
        return DB::table('hr_kpi_criteria')
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Điểm đã nhập của nhiều nhân viên trong tháng: [user_id => [criterion_id => row]]. */
    public function scores(array $userIds, string $month): Collection
    {
        return DB::table('hr_kpi_scores')
            ->whereIn('user_id', $userIds)
            ->where('payroll_month', $month)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->keyBy('criterion_id'));
    }

    /** % đạt của một tiêu chí; null = chưa nhập đủ. */
    public function achievement(object $criterion, ?object $score, float $cap): ?float
    {
        if (! $score) {
            return null;
        }
        if ($score->achievement !== null) {
            return min(max((float) $score->achievement, 0), $cap);
        }
        if ($score->actual_value === null || $criterion->target_value === null) {
            return null;
        }

        $actual = (float) $score->actual_value;
        $target = (float) $criterion->target_value;

        $value = match ($criterion->calc_type) {
            'higher_better' => $target > 0 ? $actual / $target * 100 : null,
            'lower_better' => $actual <= $target ? 100.0 : ($target > 0 ? $target / $actual * 100 : 0.0),
            default => null,
        };

        return $value === null ? null : min(max($value, 0), $cap);
    }

    /**
     * @param  Collection  $scores  [criterion_id => row]
     * @return array{total: ?float, status: string, rows: array, missing: array}
     */
    public function score(object $template, Collection $criteria, Collection $scores): array
    {
        $cap = (float) ($template->max_achievement ?? 120);
        $rows = [];
        $missing = [];
        $weightSum = 0.0;
        $points = 0.0;
        $noWeight = false;

        foreach ($criteria as $criterion) {
            $score = $scores->get($criterion->id);
            $notApplicable = $score && (bool) $score->not_applicable;
            $achievement = $notApplicable ? null : $this->achievement($criterion, $score, $cap);
            $weight = $criterion->weight !== null ? (float) $criterion->weight : null;

            if ($weight === null) {
                $noWeight = true;
            } elseif (! $notApplicable) {
                if ($achievement === null) {
                    $missing[] = $criterion->name;
                } else {
                    $weightSum += $weight;
                    $points += $weight * $achievement / 100;
                }
            }

            $rows[] = [
                'criterion' => $criterion,
                'score' => $score,
                'achievement' => $achievement,
                'not_applicable' => $notApplicable,
                'points' => ($weight !== null && $achievement !== null) ? $weight * $achievement / 100 : null,
            ];
        }

        if ($noWeight) {
            return ['total' => null, 'status' => self::STATUS_NO_WEIGHT, 'rows' => $rows, 'missing' => $missing];
        }
        if ($missing !== [] || $weightSum <= 0) {
            return ['total' => null, 'status' => self::STATUS_INCOMPLETE, 'rows' => $rows, 'missing' => $missing];
        }

        return ['total' => round($points / $weightSum * 100, 2), 'status' => self::STATUS_OK, 'rows' => $rows, 'missing' => []];
    }

    /** @return array{rate: float, label: string} */
    public function payout(object $template, float $percent): array
    {
        $tiers = collect((array) $template->payout_tiers)->sortByDesc(fn ($t) => (float) ($t['min'] ?? 0));

        foreach ($tiers as $tier) {
            if ($percent + 1e-9 < (float) ($tier['min'] ?? 0)) {
                continue;
            }

            $rate = ($tier['mode'] ?? 'fixed') === 'proportional'
                ? $percent / 100 * (float) ($tier['rate'] ?? 1)
                : (float) ($tier['rate'] ?? 0);
            if ($template->max_payout_rate !== null) {
                $rate = min($rate, (float) $template->max_payout_rate);
            }

            return ['rate' => round($rate, 4), 'label' => (string) ($tier['label'] ?? '')];
        }

        return ['rate' => 0.0, 'label' => 'Không đạt'];
    }

    /**
     * KPI tháng của một nhân viên theo bộ KPI được gán trong hồ sơ lương.
     *
     * @return array{percent: ?float, rate: ?float, label: string, status: string}
     */
    public function forEmployee(int $userId, string $month, ?string $templateCode): array
    {
        if ($templateCode === null || $templateCode === '') {
            return ['percent' => null, 'rate' => 1.0, 'label' => 'Không áp dụng KPI — hưởng đủ hiệu suất', 'status' => self::STATUS_NOT_APPLICABLE];
        }

        if ($templateCode === self::TECHNICAL) {
            return $this->technical($userId, $month);
        }

        $template = $this->template($templateCode);
        if (! $template) {
            return ['percent' => null, 'rate' => null, 'label' => 'Không tìm thấy bộ KPI "'.$templateCode.'"', 'status' => self::STATUS_INCOMPLETE];
        }

        $result = $this->score($template, $this->criteria((int) $template->id), $this->scores([$userId], $month)->get($userId, collect()));

        return $this->fromResult($template, $result);
    }

    /** @return array{percent: ?float, rate: ?float, label: string, status: string} */
    public function fromResult(object $template, array $result): array
    {
        if ($result['status'] === self::STATUS_NO_WEIGHT) {
            return ['percent' => null, 'rate' => null, 'label' => $template->name.': chưa có trọng số tiêu chí', 'status' => self::STATUS_NO_WEIGHT];
        }
        if ($result['total'] === null) {
            return ['percent' => null, 'rate' => null, 'label' => $template->name.': chưa chấm đủ tiêu chí', 'status' => self::STATUS_INCOMPLETE];
        }

        $payout = $this->payout($template, $result['total']);

        return ['percent' => $result['total'], 'rate' => $payout['rate'], 'label' => $payout['label'], 'status' => self::STATUS_OK];
    }

    /** Module KPI kỹ thuật (cấu hình theo phiên bản + bộ tính chuẩn) đã được cài trên hệ thống chưa. */
    public static function technicalAvailable(): bool
    {
        return class_exists(TechnicalKpiConfig::class)
            && class_exists(KpiStandardEvaluator::class)
            && method_exists(TechnicalKpiConfig::class, 'payoutRate')
            && SchemaCache::hasTable('technical_kpi_configs');
    }

    /** KPI kỹ sư: % tổng từ module KPI kỹ thuật (đã gồm số liệu dự án, nhập tay và điểm cộng/trừ). */
    private function technical(int $userId, string $month): array
    {
        if (! self::technicalAvailable()) {
            return ['percent' => null, 'rate' => null, 'label' => 'Chưa cài module KPI kỹ thuật — nhập tay hệ số KPI', 'status' => self::STATUS_INCOMPLETE];
        }

        $config = TechnicalKpiConfig::getActiveForDate($month.'-01');
        if (! $config) {
            return ['percent' => null, 'rate' => null, 'label' => 'Chưa có cấu hình KPI kỹ thuật đã duyệt', 'status' => self::STATUS_INCOMPLETE];
        }

        $evaluator = app(KpiStandardEvaluator::class)->useConfig($config);
        $data = $evaluator->collect($userId, [$month]);
        $total = $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments'])['total'];

        if ($total === null) {
            return ['percent' => null, 'rate' => null, 'label' => 'KPI kỹ thuật: chưa đủ số liệu tháng', 'status' => self::STATUS_INCOMPLETE];
        }

        $payout = $config->payoutRate((float) $total);

        return ['percent' => round((float) $total * 100, 2), 'rate' => round($payout['rate'], 4), 'label' => (string) $payout['label'], 'status' => self::STATUS_OK];
    }
}
