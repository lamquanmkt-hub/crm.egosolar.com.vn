<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ảnh chụp KPI HCNS khi duyệt bảng lương HR: cấu hình/phiên bản, kết quả từng tiêu chí (nguồn hay nhập tay),
 * checklist nguồn và số đã dùng trên dòng lương. Kỳ đã chốt hiển thị và trả số theo ảnh chụp, không tính lại
 * từ nguồn hiện tại. Mở khóa kỳ không xóa ảnh chụp, chỉ đánh dấu không còn hiện hành.
 */
final class HcnsKpiSnapshots
{
    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_kpi_period_snapshots');
    }

    /** Ảnh chụp hiện hành của một người/kỳ (chỉ tồn tại khi bảng lương kỳ đó đang ở trạng thái đã duyệt). */
    public static function current(int $userId, string $month, ?string $templateCode = HcnsKpiRules::TEMPLATE): ?object
    {
        if (! self::ready() || $templateCode !== HcnsKpiRules::TEMPLATE) {
            return null;
        }

        return DB::table('hr_kpi_period_snapshots')
            ->where('user_id', $userId)->where('payroll_month', $month)->where('template_code', $templateCode)->where('is_current', true)
            ->orderByDesc('id')->first();
    }

    /** Chụp KPI HCNS cho mọi dòng lương bộ HCNS của kỳ vừa duyệt. Trả số ảnh chụp đã tạo. */
    public function capture(int $periodId, int $actorId): int
    {
        if (! self::ready()) {
            return 0;
        }
        $period = DB::table('hr_payroll_periods')->where('id', $periodId)->first();
        if (! $period) {
            return 0;
        }
        $month = (string) $period->payroll_month;
        $evaluator = app(HrKpiEvaluator::class);
        $effective = $evaluator->effective(HcnsKpiRules::TEMPLATE, $month);
        if (! $effective['template']) {
            return 0;
        }
        $template = $effective['template'];
        $criteria = $effective['criteria'];
        $lines = DB::table('hr_payroll_lines')->where('period_id', $periodId)->where('kpi_template', HcnsKpiRules::TEMPLATE)->get();
        $count = 0;

        foreach ($lines as $line) {
            $userId = (int) $line->user_id;
            $scores = $evaluator->scores([$userId], $month)->get($userId, collect());
            $overrides = $evaluator->sourceOverrides($template, $criteria, $userId, $month, $scores);
            $result = $evaluator->score($template, $criteria, $scores, $overrides);
            $summary = $evaluator->fromResult($template, $result);
            $checklist = HcnsKpiChecklist::available()
                ? app(HcnsKpiChecklist::class)->forUser($userId, $month, $template, $criteria, $scores, null)
                : null;
            $manualRate = json_decode((string) $line->manual, true)['kpi_rate'] ?? null;

            $payload = self::plain([
                'version' => $effective['version'] ? [
                    'id' => $effective['version']['id'], 'version' => $effective['version']['version'],
                    'label' => $effective['version']['label'], 'effective_month' => $effective['version']['effective_month'],
                    'rules' => $effective['version']['rules'],
                ] : null,
                'template' => ['code' => $template->code, 'name' => $template->name, 'tiers' => $template->payout_tiers,
                    'max_achievement' => $template->max_achievement, 'max_payout_rate' => $template->max_payout_rate],
                'rows' => array_map(fn ($r) => [
                    'criterion' => (array) $r['criterion'], 'score' => $r['score'] ? (array) $r['score'] : null,
                    'achievement' => $r['achievement'], 'not_applicable' => $r['not_applicable'], 'points' => $r['points'], 'source' => $r['source'],
                ], $result['rows']),
                'result' => ['total' => $result['total'], 'status' => $result['status'], 'missing' => $result['missing']],
                'summary' => $summary,
                'checklist' => $checklist,
                'payroll_line' => ['id' => (int) $line->id, 'kpi_percent' => $line->kpi_percent, 'kpi_rate' => $line->kpi_rate,
                    'kpi_label' => $line->kpi_label, 'kpi_pay' => $line->kpi_pay ?? null, 'kpi_salary' => $line->kpi_salary,
                    'manual_kpi_rate' => $manualRate],
            ]);

            DB::table('hr_kpi_period_snapshots')->where('user_id', $userId)->where('payroll_month', $month)
                ->where('template_code', HcnsKpiRules::TEMPLATE)->where('is_current', true)
                ->update(['is_current' => false, 'superseded_at' => now(), 'superseded_by' => $actorId]);
            DB::table('hr_kpi_period_snapshots')->insert([
                'period_id' => $periodId, 'payroll_month' => $month, 'user_id' => $userId, 'template_code' => HcnsKpiRules::TEMPLATE,
                'version_id' => $effective['version']['id'] ?? null,
                'version_label' => $effective['version']['label'] ?? 'Cài đặt gốc (chưa có phiên bản duyệt)',
                'total' => $summary['percent'], 'rate' => $summary['rate'], 'status' => $summary['status'], 'label' => mb_substr((string) $summary['label'], 0, 190),
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'is_current' => true,
                'created_by' => $actorId, 'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /** Mở khóa kỳ: ảnh chụp không còn hiện hành (giữ lại làm lịch sử). */
    public function supersede(int $periodId, int $actorId): int
    {
        if (! self::ready()) {
            return 0;
        }

        return DB::table('hr_kpi_period_snapshots')->where('period_id', $periodId)->where('is_current', true)
            ->update(['is_current' => false, 'superseded_at' => now(), 'superseded_by' => $actorId]);
    }

    /**
     * Kết quả dạng HrKpiEvaluator::score() dựng lại từ ảnh chụp (đối tượng tiêu chí/điểm như lúc chốt).
     *
     * @return array{result: array, summary: array, checklist: ?array, version_label: string, created_at: string, payload: array}
     */
    public static function restore(object $snapshot): array
    {
        $payload = json_decode((string) $snapshot->payload, true) ?: [];
        $rows = array_map(fn ($r) => [
            'criterion' => (object) $r['criterion'], 'score' => $r['score'] ? (object) $r['score'] : null,
            'achievement' => $r['achievement'], 'not_applicable' => (bool) $r['not_applicable'], 'points' => $r['points'], 'source' => $r['source'],
        ], (array) ($payload['rows'] ?? []));

        return [
            'result' => ['total' => $payload['result']['total'] ?? null, 'status' => $payload['result']['status'] ?? null,
                'rows' => $rows, 'missing' => $payload['result']['missing'] ?? []],
            'summary' => $payload['summary'] ?? [],
            'checklist' => isset($payload['checklist']['blocks']) ? ['blocks' => array_map(fn ($b) => ['manual' => $b['manual'] ? (object) $b['manual'] : null] + $b, $payload['checklist']['blocks'])] + $payload['checklist'] : null,
            'version_label' => (string) $snapshot->version_label,
            'created_at' => (string) $snapshot->created_at,
            'payload' => $payload,
        ];
    }

    /** Lịch sử ảnh chụp của một người/kỳ (mới nhất trước). */
    public static function history(int $userId, string $month): Collection
    {
        return self::ready()
            ? DB::table('hr_kpi_period_snapshots as s')->leftJoin('users as u', 'u.id', '=', 's.created_by')
                ->where('s.user_id', $userId)->where('s.payroll_month', $month)->orderByDesc('s.id')->get(['s.*', 'u.name as creator_name'])
            : collect();
    }

    /** Đổi Carbon thành chuỗi giờ địa phương để hiển thị lại đúng múi giờ khi đọc ảnh chụp. */
    private static function plain(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return array_map(fn ($v) => self::plain($v), $value);
        }
        if (is_object($value)) {
            return self::plain((array) $value);
        }

        return $value;
    }
}
