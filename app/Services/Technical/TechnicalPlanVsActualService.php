<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SO SÁNH KẾ HOẠCH ↔ KẾT QUẢ.
 *
 * KHÔNG tính KPI, không tính lương. Service này chỉ tổng hợp số liệu thô, đúng
 * và có định nghĩa rõ ràng, để giai đoạn sau nối KPI mà không phải tính lại.
 *
 * ĐỊNH NGHĨA CHỈ SỐ (áp dụng thống nhất ở mọi màn hình)
 * -----------------------------------------------------
 * Phạm vi: các dòng kế hoạch có `plan_date` nằm trong khoảng [from, to], thuộc
 * company đang làm việc. Dòng đã huỷ (`cancelled`) bị loại khỏi MỌI mẫu số.
 *
 *  planned_items   = số dòng kế hoạch (trừ đã huỷ)            ← mẫu số chính
 *  done_items      = status = done
 *  not_done_items  = status = not_done
 *  moved_items     = status = moved (chuyển sang ngày sau)
 *  open_items      = status ∈ {planned, in_progress}
 *  overdue_items   = open_items có plan_date < hôm nay        ← "quá hạn"
 *  on_time_items   = done_items CHƯA từng bị dời ngày (moved_from_date IS NULL)
 *  late_items      = done_items ĐÃ bị dời ngày
 *  estimated_minutes = SUM(estimated_minutes) của planned_items
 *
 *  due_items       = planned_items có plan_date <= hôm nay    ← mẫu số tỷ lệ nộp
 *  reported_items  = số dòng kế hoạch (trong due_items) có ÍT NHẤT MỘT báo cáo
 *                    ở trạng thái HOÀN TẤT (submitted | approved)
 *  unplanned_items = số BÁO CÁO đánh dấu `is_unplanned` trong khoảng ngày
 *  actual_minutes  = SUM(work_hours) * 60 của các báo cáo hoàn tất gắn kế hoạch
 *
 *  completion_rate = done_items / planned_items   (mẫu 0 => null = "N/A")
 *  report_rate     = reported_items / due_items   (mẫu 0 => null = "N/A")
 *
 * Mọi con số đều được ĐẾM Ở DATABASE bằng aggregate; không tải collection rồi
 * mới đếm, không truy vấn trong vòng lặp.
 */
class TechnicalPlanVsActualService
{
    /** Trạng thái báo cáo được coi là "đã nộp / hoàn tất". */
    public const SUBMITTED_STATUSES = [
        TechnicalDailyReport::STATUS_SUBMITTED,
        TechnicalDailyReport::STATUS_APPROVED,
    ];

    public function __construct(
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalTeamService $team,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Tổng hợp toàn phạm vi
    |--------------------------------------------------------------------------
    */

    /**
     * Số liệu tổng của một khoảng ngày.
     *
     * @param  array<string, mixed>  $filters  user_id | site_id | status
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to, array $filters = []): array
    {
        $row = $this->baseItemQuery($from, $to, $filters)
            ->selectRaw('COUNT(*) AS planned_items')
            ->selectRaw("SUM(CASE WHEN i.status = 'done' THEN 1 ELSE 0 END) AS done_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'not_done' THEN 1 ELSE 0 END) AS not_done_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'moved' THEN 1 ELSE 0 END) AS moved_items")
            ->selectRaw("SUM(CASE WHEN i.status IN ('planned', 'in_progress') THEN 1 ELSE 0 END) AS open_items")
            ->selectRaw(
                "SUM(CASE WHEN i.status IN ('planned', 'in_progress') AND i.plan_date < ? THEN 1 ELSE 0 END) AS overdue_items",
                [Carbon::today()->toDateString()],
            )
            ->selectRaw("SUM(CASE WHEN i.status = 'done' AND i.moved_from_date IS NULL THEN 1 ELSE 0 END) AS on_time_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'done' AND i.moved_from_date IS NOT NULL THEN 1 ELSE 0 END) AS late_items")
            ->selectRaw('COALESCE(SUM(i.estimated_minutes), 0) AS estimated_minutes')
            ->selectRaw('SUM(CASE WHEN i.plan_date <= ? THEN 1 ELSE 0 END) AS due_items', [Carbon::today()->toDateString()])
            ->selectRaw(
                'SUM(CASE WHEN i.plan_date <= ? AND EXISTS ('
                .$this->reportExistsSql().') THEN 1 ELSE 0 END) AS reported_items',
                [Carbon::today()->toDateString(), ...self::SUBMITTED_STATUSES],
            )
            ->first();

        $planned = (int) ($row->planned_items ?? 0);
        $done = (int) ($row->done_items ?? 0);
        $due = (int) ($row->due_items ?? 0);
        $reported = (int) ($row->reported_items ?? 0);

        return [
            'planned_items' => $planned,
            'done_items' => $done,
            'not_done_items' => (int) ($row->not_done_items ?? 0),
            'moved_items' => (int) ($row->moved_items ?? 0),
            'open_items' => (int) ($row->open_items ?? 0),
            'overdue_items' => (int) ($row->overdue_items ?? 0),
            'on_time_items' => (int) ($row->on_time_items ?? 0),
            'late_items' => (int) ($row->late_items ?? 0),
            'estimated_minutes' => (int) ($row->estimated_minutes ?? 0),
            'due_items' => $due,
            'reported_items' => $reported,
            'unreported_items' => max(0, $due - $reported),
            'unplanned_items' => $this->unplannedCount($from, $to, $filters),
            'actual_minutes' => $this->actualMinutes($from, $to, $filters),
            'completion_rate' => $this->rate($done, $planned),
            'report_rate' => $this->rate($reported, $due),
        ];
    }

    /**
     * Một dòng số liệu cho MỖI nhân sự kỹ thuật — một truy vấn GROUP BY.
     *
     * Nhân sự chưa có dòng kế hoạch nào vẫn xuất hiện (với số 0) để Dashboard
     * chỉ ra được "ai chưa lập kế hoạch".
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perUser(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $today = Carbon::today()->toDateString();

        $rows = $this->baseItemQuery($from, $to, $filters)
            ->selectRaw('i.user_id AS user_id')
            ->selectRaw('COUNT(*) AS planned_items')
            ->selectRaw("SUM(CASE WHEN i.status = 'done' THEN 1 ELSE 0 END) AS done_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'not_done' THEN 1 ELSE 0 END) AS not_done_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'moved' THEN 1 ELSE 0 END) AS moved_items")
            ->selectRaw("SUM(CASE WHEN i.status = 'done' AND i.moved_from_date IS NULL THEN 1 ELSE 0 END) AS on_time_items")
            ->selectRaw(
                "SUM(CASE WHEN i.status IN ('planned', 'in_progress') AND i.plan_date < ? THEN 1 ELSE 0 END) AS overdue_items",
                [$today],
            )
            ->selectRaw('COALESCE(SUM(i.estimated_minutes), 0) AS estimated_minutes')
            ->selectRaw('SUM(CASE WHEN i.plan_date <= ? THEN 1 ELSE 0 END) AS due_items', [$today])
            ->selectRaw(
                'SUM(CASE WHEN i.plan_date <= ? AND EXISTS ('.$this->reportExistsSql().') THEN 1 ELSE 0 END) AS reported_items',
                [$today, ...self::SUBMITTED_STATUSES],
            )
            ->groupBy('i.user_id')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->user_id);

        $unplanned = $this->unplannedCountPerUser($from, $to, $filters);
        $planStatuses = $this->weekPlanStatuses($from, $filters);
        $members = $this->team->members();

        $onlyUser = isset($filters['user_id']) && $filters['user_id'] !== null
            ? (int) $filters['user_id']
            : null;

        return $members
            ->when($onlyUser !== null, fn (Collection $c): Collection => $c->filter(
                fn (object $m): bool => (int) $m->id === $onlyUser,
            ))
            ->map(function (object $member) use ($rows, $unplanned, $planStatuses): array {
                $id = (int) $member->id;
                $row = $rows->get($id);

                $planned = (int) ($row->planned_items ?? 0);
                $done = (int) ($row->done_items ?? 0);
                $due = (int) ($row->due_items ?? 0);
                $reported = (int) ($row->reported_items ?? 0);

                return [
                    'user_id' => $id,
                    'user_name' => (string) $member->name,
                    'week_plan_status' => $planStatuses[$id] ?? null,
                    'has_week_plan' => isset($planStatuses[$id]) && $planned > 0,
                    'planned_items' => $planned,
                    'done_items' => $done,
                    'not_done_items' => (int) ($row->not_done_items ?? 0),
                    'moved_items' => (int) ($row->moved_items ?? 0),
                    'on_time_items' => (int) ($row->on_time_items ?? 0),
                    'overdue_items' => (int) ($row->overdue_items ?? 0),
                    'estimated_minutes' => (int) ($row->estimated_minutes ?? 0),
                    'due_items' => $due,
                    'reported_items' => $reported,
                    'unreported_items' => max(0, $due - $reported),
                    'unplanned_items' => (int) ($unplanned[$id] ?? 0),
                    'completion_rate' => $this->rate($done, $planned),
                    'report_rate' => $this->rate($reported, $due),
                ];
            })
            ->values();
    }

    /**
     * Số liệu theo CÔNG TRÌNH — một truy vấn GROUP BY.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perSite(Carbon $from, Carbon $to, array $filters = [], int $limit = 50): Collection
    {
        $today = Carbon::today()->toDateString();

        return $this->baseItemQuery($from, $to, $filters)
            ->selectRaw('i.site_id AS site_id')
            ->selectRaw('MAX(i.site_name) AS site_name')
            ->selectRaw('COUNT(DISTINCT i.user_id) AS staff_count')
            ->selectRaw('COUNT(*) AS planned_items')
            ->selectRaw("SUM(CASE WHEN i.status = 'done' THEN 1 ELSE 0 END) AS done_items")
            ->selectRaw(
                "SUM(CASE WHEN i.status IN ('planned', 'in_progress') AND i.plan_date < ? THEN 1 ELSE 0 END) AS overdue_items",
                [$today],
            )
            ->selectRaw('COALESCE(AVG(i.progress_percent), 0) AS avg_progress')
            ->selectRaw(
                'SUM(CASE WHEN EXISTS ('.$this->reportIssueExistsSql().') THEN 1 ELSE 0 END) AS issue_items',
                self::SUBMITTED_STATUSES,
            )
            ->whereNotNull('i.site_id')
            ->groupBy('i.site_id')
            ->orderByDesc('planned_items')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'site_id' => (int) $row->site_id,
                'site_name' => (string) ($row->site_name ?? ('Công trình #'.$row->site_id)),
                'staff_count' => (int) $row->staff_count,
                'planned_items' => (int) $row->planned_items,
                'done_items' => (int) $row->done_items,
                'overdue_items' => (int) $row->overdue_items,
                'avg_progress' => (int) round((float) $row->avg_progress),
                'issue_items' => (int) $row->issue_items,
                'completion_rate' => $this->rate((int) $row->done_items, (int) $row->planned_items),
            ]);
    }

    /**
     * Kế hoạch so với hoàn thành theo TUẦN — cho biểu đồ đường/cột.
     *
     * @return array{labels: array<int, string>, planned: array<int, int>, done: array<int, int>}
     */
    public function weeklyTrend(Carbon $until, int $weeks = 6, array $filters = []): array
    {
        $weeks = max(2, min(26, $weeks));
        $lastStart = TechnicalWeekPlan::weekStartFor($until);
        $firstStart = $lastStart->copy()->subWeeks($weeks - 1);

        $rows = $this->baseItemQuery($firstStart, $lastStart->copy()->addDays(6), $filters)
            ->selectRaw('DATE(DATE_SUB(i.plan_date, INTERVAL WEEKDAY(i.plan_date) DAY)) AS week_start')
            ->selectRaw('COUNT(*) AS planned_items')
            ->selectRaw("SUM(CASE WHEN i.status = 'done' THEN 1 ELSE 0 END) AS done_items")
            ->groupBy('week_start')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->week_start);

        $labels = [];
        $planned = [];
        $done = [];

        for ($i = 0; $i < $weeks; $i++) {
            $start = $firstStart->copy()->addWeeks($i);
            $key = $start->toDateString();
            $row = $rows->get($key);

            $labels[] = $start->format('d/m');
            $planned[] = (int) ($row->planned_items ?? 0);
            $done[] = (int) ($row->done_items ?? 0);
        }

        return ['labels' => $labels, 'planned' => $planned, 'done' => $done];
    }

    /*
    |--------------------------------------------------------------------------
    | Số liệu BÁO CÁO NGÀY (tab "Tổng hợp tuần" của /ky-thuat/bao-cao-ngay)
    |--------------------------------------------------------------------------
    |
    | Ba hàm dưới đây đếm trên `technical_daily_reports` (việc THỰC TẾ), khác với
    | các hàm phía trên vốn đếm trên `technical_plan_items` (việc DỰ KIẾN). Cùng
    | một bộ lọc, cùng company scope, vẫn đếm ở database bằng aggregate.
    */

    /**
     * Số báo cáo theo TỪNG trạng thái trong khoảng ngày.
     *
     * @param  array<string, mixed>  $filters  user_id | site_id
     * @return array<string, int>  draft | submitted | approved | revision_requested | total | finalized
     */
    public function reportStatusCounts(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rows = $this->reportQuery($from, $to, $filters)
            ->select('r.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('r.status')
            ->pluck('total', 'status');

        $counts = [];
        $total = 0;

        foreach (array_keys(TechnicalDailyReport::STATUS_LABELS) as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
            $total += $counts[$status];
        }

        $counts['total'] = $total;
        $counts['finalized'] = array_sum(array_map(
            static fn (string $s): int => (int) ($counts[$s] ?? 0),
            TechnicalDailyReport::FINALIZED_STATUSES,
        ));

        return $counts;
    }

    /**
     * Số báo cáo đã NỘP (submitted|approved) của từng nhân sự.
     *
     * @return array<int, int>
     */
    public function reportCountsPerUser(Carbon $from, Carbon $to, array $filters = []): array
    {
        return $this->reportQuery($from, $to, $filters)
            ->whereIn('r.status', self::SUBMITTED_STATUSES)
            ->select('r.user_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('r.user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /**
     * Giờ THỰC TẾ (phút) của từng nhân sự — từ `work_hours` của báo cáo đã nộp.
     *
     * @return array<int, int>
     */
    public function actualMinutesPerUser(Carbon $from, Carbon $to, array $filters = []): array
    {
        return $this->reportQuery($from, $to, $filters)
            ->whereIn('r.status', self::SUBMITTED_STATUSES)
            ->select('r.user_id')
            ->selectRaw('COALESCE(SUM(r.work_hours), 0) AS hours')
            ->groupBy('r.user_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->user_id => (int) round((float) $row->hours * 60),
            ])
            ->all();
    }

    /** Truy vấn nền trên bảng báo cáo ngày — company scope + khoảng ngày + bộ lọc. */
    private function reportQuery(Carbon $from, Carbon $to, array $filters = []): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('technical_daily_reports as r')
            ->where('r.company_id', $this->feed->companyId())
            ->whereNull('r.deleted_at')
            ->whereBetween('r.report_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('r.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->where('r.site_id', (int) $filters['site_id']);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Truy vấn nền
    |--------------------------------------------------------------------------
    */

    /**
     * Truy vấn gốc trên `technical_plan_items`, đã áp company scope, khoảng
     * ngày và bộ lọc. Dòng đã huỷ và đã xoá mềm bị loại khỏi mọi phép đếm.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseItemQuery(Carbon $from, Carbon $to, array $filters = []): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('technical_plan_items as i')
            ->where('i.company_id', $this->feed->companyId())
            ->whereNull('i.deleted_at')
            ->where('i.status', '!=', TechnicalPlanItem::STATUS_CANCELLED)
            ->whereBetween('i.plan_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('i.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->where('i.site_id', (int) $filters['site_id']);
        }

        if (! empty($filters['status']) && array_key_exists((string) $filters['status'], TechnicalPlanItem::STATUS_LABELS)) {
            $query->where('i.status', (string) $filters['status']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('i.source_type', (string) $filters['source_type']);
        }

        return $query;
    }

    /** SQL con: dòng kế hoạch có ít nhất một báo cáo HOÀN TẤT. */
    private function reportExistsSql(): string
    {
        return 'SELECT 1 FROM technical_daily_reports r'
            .' WHERE r.plan_item_id = i.id AND r.deleted_at IS NULL'
            .' AND r.status IN (?, ?)';
    }

    /** SQL con: dòng kế hoạch có báo cáo hoàn tất và có ghi nhận vấn đề phát sinh. */
    private function reportIssueExistsSql(): string
    {
        return 'SELECT 1 FROM technical_daily_reports r'
            .' WHERE r.plan_item_id = i.id AND r.deleted_at IS NULL'
            ." AND r.status IN (?, ?) AND r.issues_note IS NOT NULL AND r.issues_note <> ''";
    }

    /** @param array<string, mixed> $filters */
    private function unplannedCount(Carbon $from, Carbon $to, array $filters = []): int
    {
        return (int) $this->unplannedQuery($from, $to, $filters)->count();
    }

    /** @return array<int, int> */
    private function unplannedCountPerUser(Carbon $from, Carbon $to, array $filters = []): array
    {
        return $this->unplannedQuery($from, $to, $filters)
            ->select('r.user_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('r.user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /** @param array<string, mixed> $filters */
    private function unplannedQuery(Carbon $from, Carbon $to, array $filters = []): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('technical_daily_reports as r')
            ->where('r.company_id', $this->feed->companyId())
            ->whereNull('r.deleted_at')
            ->where('r.is_unplanned', 1)
            ->whereBetween('r.report_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('r.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->where('r.site_id', (int) $filters['site_id']);
        }

        return $query;
    }

    /** Tổng thời gian THỰC TẾ (phút) của các báo cáo hoàn tất gắn kế hoạch. */
    private function actualMinutes(Carbon $from, Carbon $to, array $filters = []): int
    {
        $query = DB::table('technical_daily_reports as r')
            ->where('r.company_id', $this->feed->companyId())
            ->whereNull('r.deleted_at')
            ->whereIn('r.status', self::SUBMITTED_STATUSES)
            ->whereBetween('r.report_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('r.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->where('r.site_id', (int) $filters['site_id']);
        }

        return (int) round((float) $query->sum('r.work_hours') * 60);
    }

    /**
     * Trạng thái kế hoạch tuần của từng nhân sự cho tuần chứa `$from`.
     *
     * @return array<int, string>
     */
    private function weekPlanStatuses(Carbon $from, array $filters = []): array
    {
        $weekStart = TechnicalWeekPlan::weekStartFor($from);

        $query = DB::table('technical_week_plans')
            ->where('company_id', $this->feed->companyId())
            ->whereNull('deleted_at')
            ->whereDate('week_start', $weekStart->toDateString());

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        return $query->pluck('status', 'user_id')
            ->map(fn ($status): string => (string) $status)
            ->all();
    }

    /** Tỷ lệ phần trăm; mẫu số 0 trả về null để giao diện hiển thị "N/A". */
    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round($numerator * 100 / $denominator, 1);
    }

    /** Định dạng tỷ lệ cho giao diện — null thành "N/A", không bao giờ thành 0%. */
    public static function rateLabel(?float $rate): string
    {
        return $rate === null ? 'N/A' : rtrim(rtrim(number_format($rate, 1), '0'), '.').'%';
    }
}
