<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DỮ LIỆU CHỈ ĐỌC cho trang KẾ HOẠCH của Admin/Giám đốc, cộng thêm hai bộ số
 * bổ trợ cho bảng "Kết quả theo công trình" của trang BÁO CÁO.
 *
 * (Số liệu chính của trang Báo cáo vẫn do `TechnicalPlanVsActualService` tính;
 *  phần KPI "tham khảo" đã bị gỡ vì KPI chính thức nằm ở `/ky-thuat/kpis`.)
 *
 * Service này KHÔNG GHI bất cứ thứ gì: không tạo kế hoạch, không đổi trạng
 * thái, không ghi nhật ký, không đụng payroll. Mọi con số lấy từ đúng dữ liệu
 * Technical V2 đang có (`technical_plan_items`, `technical_week_plans`,
 * `technical_daily_reports`) và đều đã áp company scope.
 *
 * ĐỊNH NGHĨA BỔ SUNG (các chỉ số chưa có ở `TechnicalPlanVsActualService`)
 * ----------------------------------------------------------------------
 *  days_planned      = số NGÀY khác nhau trong tuần có ít nhất một dòng kế
 *                      hoạch chưa huỷ.
 *  overload_items    = số dòng kế hoạch nằm trong những NGÀY bị quá tải, tức
 *                      tổng `estimated_minutes` của ngày đó vượt giờ làm việc
 *                      chuẩn × `technical.week_plan.overload_ratio`.
 *  conflict_items    = số dòng kế hoạch TRÙNG KHUNG GIỜ với ít nhất một dòng
 *                      khác trong cùng người + cùng ngày (chỉ xét dòng có cả
 *                      `start_time` và `end_time`).
 *  risk_items        = hợp của hai tập trên (một dòng chỉ đếm một lần) —
 *                      chính là thẻ "Công việc quá tải hoặc trùng lịch".
 *  on_time_rate      = on_time_items / done_items  (mẫu 0 => null = "N/A").
 *                      `on_time_items` giữ nguyên định nghĩa sẵn có: việc đã
 *                      hoàn thành và CHƯA TỪNG bị dời ngày.
 *
 * Mọi tỷ lệ dùng chung quy ước: mẫu số bằng 0 trả về `null`, giao diện in
 * "N/A" qua `TechnicalPlanVsActualService::rateLabel()` — không bao giờ in 0%.
 */
class TechnicalDashboardReadService
{
    /** Trạng thái kế hoạch tuần dùng cho bộ lọc của trang Kế hoạch. */
    public const PLAN_STATUS_NONE = 'none';

    public function __construct(
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalTeamService $team,
        private readonly TechnicalWeekPlanService $plans,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Trang KẾ HOẠCH (chỉ xem)
    |--------------------------------------------------------------------------
    */

    /** Nhãn cho bộ lọc "Trạng thái kế hoạch". @return array<string, string> */
    public static function planStatusOptions(): array
    {
        return [
            self::PLAN_STATUS_NONE => TechnicalWeekPlan::LABEL_NOT_STARTED,
            TechnicalWeekPlan::STATUS_DRAFT => TechnicalWeekPlan::STATUS_LABELS[TechnicalWeekPlan::STATUS_DRAFT] ?? 'Đang lập',
            TechnicalWeekPlan::STATUS_FINALIZED => TechnicalWeekPlan::STATUS_LABELS[TechnicalWeekPlan::STATUS_FINALIZED] ?? 'Đã hoàn tất',
            TechnicalWeekPlan::STATUS_ADJUSTED => TechnicalWeekPlan::STATUS_LABELS[TechnicalWeekPlan::STATUS_ADJUSTED] ?? 'Đã được Trưởng phòng điều chỉnh',
        ];
    }

    /**
     * Một dòng cho MỖI nhân sự kỹ thuật trong tuần `$weekStart`.
     *
     * Hai truy vấn cố định (dòng kế hoạch của tuần + trạng thái kế hoạch tuần),
     * phần còn lại tính trong bộ nhớ trên đúng dữ liệu một tuần.
     *
     * @param  array<string, mixed>  $filters  user_id | site_id | plan_status
     * @return Collection<int, array<string, mixed>>
     */
    public function planRows(Carbon $weekStart, array $filters = []): Collection
    {
        [$from, $to] = $this->plans->weekRange($weekStart);

        $itemQuery = DB::table('technical_plan_items')
            ->where('company_id', $this->feed->companyId())
            ->whereNull('deleted_at')
            ->where('status', '!=', TechnicalPlanItem::STATUS_CANCELLED)
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $itemQuery->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $itemQuery->where('site_id', (int) $filters['site_id']);
        }

        $items = collect($itemQuery->get([
            'user_id', 'plan_date', 'start_time', 'end_time', 'estimated_minutes',
        ]))->groupBy(fn (object $row): int => (int) $row->user_id);

        $statuses = DB::table('technical_week_plans')
            ->where('company_id', $this->feed->companyId())
            ->whereNull('deleted_at')
            ->whereDate('week_start', $weekStart->toDateString())
            ->pluck('status', 'user_id')
            ->map(fn ($status): string => (string) $status)
            ->all();

        $overloadLimit = (int) round(
            $this->plans->dailyWorkingMinutes() * (float) config('technical.week_plan.overload_ratio', 1.0),
        );

        $members = $this->team->members();

        if (! empty($filters['user_id'])) {
            $members = $members->filter(fn (object $m): bool => (int) $m->id === (int) $filters['user_id']);
        }

        $rows = $members->map(function (object $member) use ($items, $statuses, $overloadLimit): array {
            $id = (int) $member->id;
            /** @var Collection<int, object> $own */
            $own = $items->get($id, collect());

            $byDay = $own->groupBy(fn (object $row): string => substr((string) $row->plan_date, 0, 10));

            $overload = 0;
            $conflict = 0;
            $risk = 0;

            foreach ($byDay as $dayRows) {
                $minutes = (int) $dayRows->sum(fn (object $r): int => (int) ($r->estimated_minutes ?? 0));
                $dayOverloaded = $overloadLimit > 0 && $minutes > $overloadLimit;
                $clashing = $this->clashingRowKeys($dayRows);

                if ($dayOverloaded) {
                    $overload += $dayRows->count();
                }

                $conflict += count($clashing);
                $risk += $dayOverloaded ? $dayRows->count() : count($clashing);
            }

            $status = $statuses[$id] ?? null;

            return [
                'user_id' => $id,
                'user_name' => (string) $member->name,
                'plan_status' => $status,
                'plan_status_key' => $status ?? self::PLAN_STATUS_NONE,
                'plan_status_label' => $status === null
                    ? TechnicalWeekPlan::LABEL_NOT_STARTED
                    : (TechnicalWeekPlan::STATUS_LABELS[$status] ?? $status),
                'days_planned' => $byDay->count(),
                'item_count' => $own->count(),
                'estimated_minutes' => (int) $own->sum(fn (object $r): int => (int) ($r->estimated_minutes ?? 0)),
                'overload_items' => $overload,
                'conflict_items' => $conflict,
                'risk_items' => $risk,
            ];
        })->values();

        if (! empty($filters['plan_status'])) {
            $wanted = (string) $filters['plan_status'];
            $rows = $rows->filter(fn (array $row): bool => $row['plan_status_key'] === $wanted)->values();
        }

        return $rows;
    }

    /**
     * Thẻ tổng hợp của trang Kế hoạch — tính thẳng từ các dòng đã lấy.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function planTotals(Collection $rows): array
    {
        $withPlan = $rows->filter(
            fn (array $r): bool => $r['plan_status'] !== null && $r['item_count'] > 0,
        )->count();

        return [
            'total_staff' => $rows->count(),
            'with_plan' => $withPlan,
            'without_plan' => max(0, $rows->count() - $withPlan),
            'total_items' => (int) $rows->sum('item_count'),
            'estimated_minutes' => (int) $rows->sum('estimated_minutes'),
            'risk_items' => (int) $rows->sum('risk_items'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Trang BÁO CÁO (bổ sung cho bảng "Kết quả theo công trình")
    |--------------------------------------------------------------------------
    */

    /**
     * Tên NGƯỜI PHỤ TRÁCH của từng công trình.
     *
     * Ưu tiên `sites.lead_engineer_id` -> `users.name`; nếu trống thì lấy
     * `sites.technician_name`; không có gì thì bỏ trống để giao diện in "—".
     * CHỈ ĐỌC bảng `sites` — không sửa module Công trình.
     *
     * @param  array<int, int>  $siteIds
     * @return array<int, string>
     */
    public function siteLeadNames(array $siteIds): array
    {
        $siteIds = array_values(array_unique(array_filter(array_map('intval', $siteIds))));

        if ($siteIds === []) {
            return [];
        }

        $rows = DB::table('sites as s')
            ->leftJoin('users as u', 'u.id', '=', 's.lead_engineer_id')
            ->whereIn('s.id', $siteIds)
            ->get(['s.id as site_id', 'u.name as lead_name', 's.technician_name as technician_name']);

        $names = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->lead_name ?? ''));

            if ($name === '') {
                $name = trim((string) ($row->technician_name ?? ''));
            }

            if ($name !== '') {
                $names[(int) $row->site_id] = $name;
            }
        }

        return $names;
    }

    /**
     * GIỜ THỰC TẾ (phút) theo từng công trình trong kỳ — lấy từ `work_hours`
     * của các báo cáo ngày đã nộp, gom bằng MỘT truy vấn GROUP BY.
     *
     * @param  array<string, mixed>  $filters  user_id | site_id
     * @return array<int, int>
     */
    public function actualMinutesPerSite(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = DB::table('technical_daily_reports')
            ->where('company_id', $this->feed->companyId())
            ->whereNull('deleted_at')
            ->whereIn('status', TechnicalPlanVsActualService::SUBMITTED_STATUSES)
            ->whereNotNull('site_id')
            ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->where('site_id', (int) $filters['site_id']);
        }

        return $query
            ->select('site_id')
            ->selectRaw('COALESCE(SUM(work_hours), 0) AS hours')
            ->groupBy('site_id')
            ->pluck('hours', 'site_id')
            ->map(fn ($hours): int => (int) round((float) $hours * 60))
            ->all();
    }

    /**
     * Khoá của những dòng TRÙNG KHUNG GIỜ trong cùng một ngày.
     *
     * @param  Collection<int, object>  $dayRows
     * @return array<int, int>
     */
    private function clashingRowKeys(Collection $dayRows): array
    {
        $timed = $dayRows
            ->values()
            ->filter(fn (object $r): bool => ! empty($r->start_time) && ! empty($r->end_time))
            ->all();

        $clashing = [];
        $keys = array_keys($timed);

        foreach ($keys as $i) {
            foreach ($keys as $j) {
                if ($j <= $i) {
                    continue;
                }

                $aStart = (string) $timed[$i]->start_time;
                $aEnd = (string) $timed[$i]->end_time;
                $bStart = (string) $timed[$j]->start_time;
                $bEnd = (string) $timed[$j]->end_time;

                if ($aStart < $bEnd && $bStart < $aEnd) {
                    $clashing[$i] = $i;
                    $clashing[$j] = $j;
                }
            }
        }

        return array_values($clashing);
    }
}
