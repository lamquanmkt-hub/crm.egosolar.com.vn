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

    /**
     * Bộ KPI hiệu lực cho kỳ: với HCNS có phiên bản đã duyệt (có cấu hình) thì dùng trọng số/chỉ tiêu/bậc của
     * phiên bản; ngược lại dùng cài đặt gốc như trước. Bản nháp KHÔNG dùng ở đây (chỉ xem trước).
     *
     * @return array{template: ?object, criteria: Collection, version: ?array}
     */
    public function effective(string $code, string $month): array
    {
        $template = $this->template($code);
        if (! $template) {
            return ['template' => null, 'criteria' => collect(), 'version' => null];
        }
        if ($code !== HcnsKpiRules::TEMPLATE || ! HcnsKpiRules::ready()) {
            return ['template' => $template, 'criteria' => $this->criteria((int) $template->id), 'version' => null];
        }

        $rule = app(HcnsKpiRules::class)->forMonth($month);
        $version = $rule['status'] === 'applied' ? $rule : null;
        [$tpl, $criteria] = app(HcnsKpiRules::class)->overlay($template, $this->allCriteria((int) $template->id), $version['config'] ?? null);

        return ['template' => $tpl, 'criteria' => $criteria, 'version' => $version];
    }

    /** Bộ KPI theo một phiên bản cụ thể (xem trước bản nháp). */
    public function withVersion(string $code, array $version): array
    {
        $template = $this->template($code);
        [$tpl, $criteria] = app(HcnsKpiRules::class)->overlay($template, $this->allCriteria((int) $template->id), $version['config'] ?? null);

        return ['template' => $tpl, 'criteria' => $criteria, 'version' => $version];
    }

    /**
     * Tổng KPI xem trước nếu một phiên bản (thường là bản nháp) được duyệt cho kỳ: cùng công thức với bộ chấm,
     * không ghi gì và không dùng cho lương.
     */
    public function previewForVersion(int $userId, string $month, array $version): array
    {
        ['template' => $tpl, 'criteria' => $criteria] = $this->withVersion(HcnsKpiRules::TEMPLATE, $version);
        $scores = $this->scores([$userId], $month)->get($userId, collect());
        $result = $this->score($tpl, $criteria, $scores, $this->sourceOverrides($tpl, $criteria, $userId, $month, $scores, null, $version));

        return $this->fromResult($tpl, $result) + ['result' => $result];
    }

    public function allCriteria(int $templateId): Collection
    {
        return DB::table('hr_kpi_criteria')->where('template_id', $templateId)->orderBy('sort_order')->orderBy('id')->get();
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
     * Nguồn tự động HCNS cho kỳ có PHIÊN BẢN QUY TẮC ĐÃ DUYỆT (áp dụng). Bản nháp không ghi đè điểm chính thức.
     * Trả [criterion_id => ['achievement' => ?float, 'not_applicable' => bool, 'reason' => string, 'rule' => string]].
     * Nguồn chưa đủ (chờ xác nhận, kỳ chưa kết thúc, thiếu dữ liệu) → achievement null → tổng "chưa đủ".
     * Không phát sinh chỉ thành N/A khi người chấm đã đánh dấu "Không phát sinh" (có người/giờ ghi nhận).
     */
    public function sourceOverrides(object $template, Collection $criteria, int $userId, string $month, Collection $scores, ?\Illuminate\Support\Carbon $now = null, ?array $asApplied = null): array
    {
        if ((string) $template->code !== HcnsKpiRules::TEMPLATE || ! HcnsKpiChecklist::available()) {
            return [];
        }
        // $asApplied: xem trước một bản nháp như thể đã duyệt (chỉ dùng ở trang Cài đặt, không ghi điểm).
        $rule = $asApplied ? ['status' => 'applied'] + $asApplied : app(HcnsKpiRules::class)->forMonth($month);
        if ($rule['status'] !== 'applied') {
            return [];
        }

        $now ??= now();
        $cap = (float) ($template->max_achievement ?? 120);
        $out = [];
        foreach ($criteria as $criterion) {
            $code = (string) $criterion->code;
            $source = HcnsKpiChecklist::SOURCES[$code] ?? null;
            // Phiên bản chưa chọn tính từ nguồn cho tiêu chí (HC06 chưa gộp, HC01–HC03 chỉ xem trước) → giữ chấm tay.
            if (! $source || ! HcnsKpiChecklist::sourceOfficial($code, (array) $rule['rules'])) {
                continue;
            }
            // Đã chọn nguồn nhưng nguồn chưa sẵn sàng (thiếu bảng/cột) → CHƯA ĐỦ, không âm thầm quay về điểm nhập tay.
            if (! HcnsKpiChecklist::sourceReady($code)) {
                $out[(int) $criterion->id] = ['achievement' => null, 'not_applicable' => false, 'actual' => null,
                    'reason' => 'Nguồn chưa sẵn sàng trên hệ thống — chưa đủ.', 'rule' => $rule['label']];

                continue;
            }
            $adapter = app($source[0]);
            $summary = $adapter->summarize($adapter->rows($userId, $month, $rule['rules'], $now), $month, $now, (array) $rule['rules']);
            $manual = $scores->get($criterion->id);
            // “Không phát sinh” chỉ nhận khi nguồn thật sự không có hồ sơ nào (và người chấm đã xác nhận có lý do).
            $na = $summary['data_status'] === 'no_items' && ($summary['allow_na'] ?? true) && $manual && (bool) $manual->not_applicable;
            $achievement = ! $na && $summary['data_status'] === 'complete' && $summary['value'] !== null
                ? $this->achievement($criterion, (object) ['achievement' => null, 'actual_value' => $summary['value']], $cap)
                : null;
            $out[(int) $criterion->id] = [
                'achievement' => $achievement,
                'not_applicable' => $na,
                'actual' => $summary['value'],
                'reason' => match (true) {
                    $na => 'Không phát sinh — đã được người chấm xác nhận.',
                    $summary['data_status'] === 'no_items' && ! ($summary['allow_na'] ?? true) => ($summary['measure_text'] ?? 'Chưa đủ dữ liệu nguồn').'.',
                    $summary['data_status'] === 'no_items' => 'Không có hồ sơ nguồn — cần người chấm xác nhận “Không phát sinh”.',
                    $summary['data_status'] === 'incomplete' => 'Nguồn còn hồ sơ chờ xác nhận/thiếu dữ liệu.',
                    $summary['data_status'] === 'open' => 'Kỳ chưa kết thúc.',
                    default => 'Nguồn '.$summary['numerator'].'/'.$summary['denominator'].'.',
                },
                'rule' => $rule['label'],
            ];
        }

        return $out;
    }

    /**
     * @param  Collection  $scores  [criterion_id => row]
     * @param  array  $overrides  kết quả sourceOverrides(): tiêu chí lấy từ nguồn tự động, bỏ qua số nhập tay (không cộng hai lần)
     * @return array{total: ?float, status: string, rows: array, missing: array}
     */
    public function score(object $template, Collection $criteria, Collection $scores, array $overrides = []): array
    {
        $cap = (float) ($template->max_achievement ?? 120);
        $rows = [];
        $missing = [];
        $weightSum = 0.0;
        $points = 0.0;
        $noWeight = false;

        foreach ($criteria as $criterion) {
            $score = $scores->get($criterion->id);
            $override = $overrides[(int) $criterion->id] ?? null;
            $notApplicable = $override ? $override['not_applicable'] : ($score && (bool) $score->not_applicable);
            $achievement = $override ? $override['achievement'] : ($notApplicable ? null : $this->achievement($criterion, $score, $cap));
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
                'source' => $override,
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

        $effective = $this->effective($templateCode, $month);
        $template = $effective['template'];
        if (! $template) {
            return ['percent' => null, 'rate' => null, 'label' => 'Không tìm thấy bộ KPI "'.$templateCode.'"', 'status' => self::STATUS_INCOMPLETE];
        }

        // Kỳ đã chốt lương có ảnh chụp KPI → trả đúng số đã chốt, không tính lại từ nguồn hiện tại.
        if ($snapshot = HcnsKpiSnapshots::current($userId, $month, $templateCode)) {
            return ['percent' => $snapshot->total !== null ? (float) $snapshot->total : null, 'rate' => $snapshot->rate !== null ? (float) $snapshot->rate : null,
                'label' => (string) $snapshot->label, 'status' => (string) $snapshot->status];
        }

        $criteria = $effective['criteria'];
        $scores = $this->scores([$userId], $month)->get($userId, collect());
        $result = $this->score($template, $criteria, $scores, $this->sourceOverrides($template, $criteria, $userId, $month, $scores));

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

        $evaluator = app(KpiStandardEvaluator::class)->useConfig($config, $month);
        if (! empty($evaluator->rule()['config_stale'])) {
            // Bộ tiêu chí kỹ thuật đổi từ kỳ này nhưng cấu hình trả tiền chưa được duyệt lại: không tự tính lương.
            return ['percent' => null, 'rate' => null, 'label' => 'Cấu hình KPI kỹ thuật chưa được duyệt cho bộ tiêu chí mới của kỳ', 'status' => self::STATUS_INCOMPLETE];
        }
        $data = $evaluator->collect($userId, [$month]);
        $total = $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments'], $data['assigned_work'])['total'];

        if ($total === null) {
            return ['percent' => null, 'rate' => null, 'label' => 'KPI kỹ thuật: chưa đủ số liệu tháng', 'status' => self::STATUS_INCOMPLETE];
        }

        $payout = $config->payoutRate((float) $total);

        return ['percent' => round((float) $total * 100, 2), 'rate' => round($payout['rate'], 4), 'label' => (string) $payout['label'], 'status' => self::STATUS_OK];
    }
}
