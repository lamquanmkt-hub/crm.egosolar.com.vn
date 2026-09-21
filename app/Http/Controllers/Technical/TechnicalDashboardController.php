<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\AuthorizesTechnicalDashboard;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use App\Services\Technical\TechnicalPlanVsActualService;
use App\Services\Technical\TechnicalTeamService;
use App\Services\Technical\TechnicalWeekPlanService;
use App\Services\Technical\TechnicalWorkFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * TỔNG QUAN KỸ THUẬT — DÀNH CHO ADMIN / GIÁM ĐỐC (CHẾ ĐỘ CHỈ XEM).
 *
 * Nhóm KỸ THUẬT của Admin / Giám đốc gồm đúng 4 mục theo thứ tự, MỖI MỤC MỘT
 * controller riêng (hợp nhất 2026-09):
 *   1. Tổng quan          (/ky-thuat/dashboard)        — controller NÀY
 *   2. Kế hoạch           (/ky-thuat/dashboard/ke-hoach) — TechnicalDashboardPlanController
 *   3. Báo cáo tuần/tháng (/ky-thuat/dashboard/bao-cao)  — TechnicalDashboardReportController
 *   4. KPIs               (/ky-thuat/kpis)              — trang KPI chính thức sẵn có
 *      (`/ky-thuat/dashboard/kpis` chỉ còn redirect 302 qua TechnicalDashboardKpiController)
 *
 * Nguyên tắc bất di bất dịch:
 * - Admin / Giám đốc là vai trò xem và giám sát: KHÔNG có nút giao việc,
 *   thêm, sửa, xóa, chuyển ngày, duyệt báo cáo hay can thiệp tiến độ.
 * - Tuyệt đối không tạo dữ liệu giả, không can thiệp payroll, không sửa module Công trình.
 * - Khi mẫu số bằng 0, tỷ lệ hiển thị 'N/A', không hiển thị 0%.
 * - Mỗi trang có H1 riêng, breadcrumb riêng, route name riêng, active menu riêng.
 */
class TechnicalDashboardController extends Controller
{
    use AuthorizesTechnicalDashboard;

    public const PERMISSION_VIEW = 'technical.dashboard.view';

    public const MODE_WEEK = 'week';

    public const MODE_MONTH = 'month';

    public function __construct(
        private readonly TechnicalPlanVsActualService $stats,
        private readonly TechnicalTeamService $team,
        private readonly TechnicalWeekPlanService $plans,
        private readonly TechnicalWorkFeedService $feed,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | 1. Tổng quan (/ky-thuat/dashboard)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): View
    {
        $this->authorizeTechnicalDashboard($request);

        $mode = $request->query('mode') === self::MODE_MONTH ? self::MODE_MONTH : self::MODE_WEEK;
        $anchor = $this->resolveAnchor($request);
        [$from, $to] = $this->resolveRange($mode, $anchor);
        $filters = $this->resolveFilters($request);

        $summary = $this->stats->summary($from, $to, $filters);
        $rows = $this->stats->perUser($from, $to, $filters);

        return view('technical.dashboard.index', [
            'mode' => $mode,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'rangeLabel' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            'filters' => $filters,
            'cards' => $this->cards($summary, $rows),
            'detailCards' => $this->detailCards($summary, $rows),
            'summary' => $summary,
            'rows' => $rows,
            'sites' => $this->stats->perSite($from, $to, $filters),
            'trend' => $this->stats->weeklyTrend($to, $mode === self::MODE_MONTH ? 8 : 6, $filters),
            'members' => $this->team->members(),
            'siteOptions' => $this->siteOptions($from, $to),
            'statusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'planner' => $this->plans,
            'prev' => $this->shift($mode, $anchor, -1),
            'next' => $this->shift($mode, $anchor, 1),
            'current' => Carbon::today()->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Cards & Filters
    |--------------------------------------------------------------------------
    */

    private function cards(array $summary, Collection $rows): array
    {
        $totalStaff = $rows->count();
        $withPlan = $rows->filter(fn (array $r): bool => $r['has_week_plan'])->count();

        return [
            ['label' => 'Tổng nhân viên kỹ thuật', 'value' => $totalStaff, 'icon' => 'bi-people', 'tone' => ''],
            ['label' => 'Đã lập kế hoạch', 'value' => $withPlan, 'icon' => 'bi-calendar-check', 'tone' => 'tw-stat--ok'],
            ['label' => 'Công việc hoàn thành', 'value' => $summary['done_items'], 'icon' => 'bi-check2-circle', 'tone' => 'tw-stat--ok'],
            ['label' => 'Công việc quá hạn', 'value' => $summary['overdue_items'], 'icon' => 'bi-exclamation-octagon', 'tone' => 'tw-stat--danger'],
            ['label' => 'Báo cáo đã nộp', 'value' => $summary['reported_items'], 'icon' => 'bi-journal-check', 'tone' => 'tw-stat--ok'],
            ['label' => 'Báo cáo chưa nộp', 'value' => $summary['unreported_items'], 'icon' => 'bi-journal-x', 'tone' => 'tw-stat--warn'],
        ];
    }

    private function detailCards(array $summary, Collection $rows): array
    {
        $totalStaff = $rows->count();
        $withPlan = $rows->filter(fn (array $r): bool => $r['has_week_plan'])->count();

        return [
            ['label' => 'Chưa lập kế hoạch', 'value' => max(0, $totalStaff - $withPlan)],
            ['label' => 'Tổng công việc đã lên kế hoạch', 'value' => $summary['planned_items']],
            ['label' => 'Công việc chưa hoàn thành', 'value' => $summary['not_done_items'] + $summary['open_items']],
            ['label' => 'Công việc phát sinh', 'value' => $summary['unplanned_items']],
        ];
    }

    private function resolveAnchor(Request $request): Carbon
    {
        $raw = (string) ($request->query('date', '') ?: $request->query('week', ''));

        if ($raw === '') {
            return Carbon::today();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(string $mode, Carbon $anchor): array
    {
        if ($mode === self::MODE_MONTH) {
            return [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()];
        }

        $start = TechnicalWeekPlan::weekStartFor($anchor);

        return [$start, $start->copy()->addDays(6)];
    }

    private function shift(string $mode, Carbon $anchor, int $direction): string
    {
        return $mode === self::MODE_MONTH
            ? $anchor->copy()->addMonths($direction)->toDateString()
            : $anchor->copy()->addWeeks($direction)->toDateString();
    }

    /** @return array<string, mixed> */
    private function resolveFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('user_id')) {
            $requested = (int) $request->query('user_id');

            if ($this->team->manages($requested)) {
                $filters['user_id'] = $requested;
            }
        }

        if ($request->filled('site_id')) {
            $filters['site_id'] = (int) $request->query('site_id');
        }

        if ($request->filled('status')
            && array_key_exists((string) $request->query('status'), TechnicalPlanItem::STATUS_LABELS)) {
            $filters['status'] = (string) $request->query('status');
        }

        return $filters;
    }

    /** @return Collection<int, object> */
    private function siteOptions(Carbon $from, Carbon $to): Collection
    {
        return collect(
            DB::table('technical_plan_items')
                ->where('company_id', $this->feed->companyId())
                ->whereNull('deleted_at')
                ->whereNotNull('site_id')
                ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
                ->select('site_id as id')
                ->selectRaw('MAX(site_name) as name')
                ->groupBy('site_id')
                ->orderBy('name')
                ->limit(200)
                ->get(),
        );
    }
}
