<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\AuthorizesTechnicalDashboard;
use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalWeekPlan;
use App\Models\User;
use App\Services\Technical\TechnicalDashboardReadService;
use App\Services\Technical\TechnicalTeamService;
use App\Services\Technical\TechnicalWeekPlanService;
use App\Services\Technical\TechnicalWorkFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * KẾ HOẠCH & GIAO VIỆC KỸ THUẬT — trang của Admin / Giám đốc.
 *
 * NGHIỆP VỤ MỚI 2026-09 (thay cho đặc tả "Admin chỉ xem"): Admin/Giám đốc được
 * TẠO kế hoạch và GIAO việc như trưởng phòng. Quyền backend vốn đã cho phép
 * (`TechnicalAccess::canManage()` trả true cho Admin), nên ở đây KHÔNG thêm
 * endpoint ghi mới: trang chỉ mở LIÊN KẾT GET sang bàn điều phối đã có
 * (`technical.manager.*`), nơi form giao việc / điều chỉnh sống và nơi lý do +
 * nhật ký được ép buộc. Nhờ vậy vẫn chỉ có MỘT luồng ghi kế hoạch duy nhất.
 *
 * Bản thân controller này vẫn CHỈ ĐỌC: HTML sinh ra không có form POST, không
 * `@csrf`, không `_method`. Dữ liệu lấy từ service Technical V2, không sao chép
 * bảng, không tạo kế hoạch giả.
 */
class TechnicalDashboardPlanController extends Controller
{
    use AuthorizesTechnicalDashboard;

    public function __construct(
        private readonly TechnicalDashboardReadService $reader,
        private readonly TechnicalWeekPlanService $plans,
        private readonly TechnicalTeamService $team,
        private readonly TechnicalWorkFeedService $feed,
        private readonly \App\Services\Technical\TechnicalAccess $access,
    ) {}

    /** Bảng tổng hợp kế hoạch tuần của toàn đội. */
    public function index(Request $request): View
    {
        $this->authorizeTechnicalDashboard($request);

        $weekStart = $this->resolveWeek($request);
        $filters = $this->resolveFilters($request);
        $rows = $this->reader->planRows($weekStart, $filters);

        [$from, $to] = $this->plans->weekRange($weekStart);

        $totals = $this->reader->planTotals($rows);

        return view('technical.dashboard.plans', [
            /*
             * Admin/Giám đốc nay ĐƯỢC tạo kế hoạch và giao việc (nghiệp vụ mới
             * 2026-09). Cờ này chỉ mở LIÊN KẾT GET sang bàn điều phối; quyền
             * thật vẫn do `TechnicalPlanBoardController` kiểm lại khi ghi.
             */
            'canAssign' => $this->access->canManage($request->user()),
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'rangeLabel' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            'rows' => $rows,
            'totals' => $totals,
            'cards' => $this->cards($totals),
            'filters' => $filters,
            'members' => $this->team->members(),
            'siteOptions' => $this->siteOptions($from, $to),
            'planStatusOptions' => TechnicalDashboardReadService::planStatusOptions(),
            'planner' => $this->plans,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
        ]);
    }

    /** Kế hoạch 7 ngày của MỘT nhân viên — vẫn chỉ xem. */
    public function show(Request $request, User $member): View
    {
        $this->authorizeTechnicalDashboard($request);

        abort_unless(
            $this->team->manages((int) $member->id),
            404,
            'Nhân sự này không nằm trong đội Kỹ thuật.',
        );

        $weekStart = $this->resolveWeek($request);

        return view('technical.dashboard.plan-detail', [
            'canAssign' => $this->access->canManage($request->user()),
            'member' => $member,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => TechnicalWeekPlan::weekDays($weekStart),
            'itemsByDay' => $this->plans->itemsByDay((int) $member->id, $weekStart),
            'dayMarks' => $this->plans->dayMarks((int) $member->id, $weekStart),
            'plan' => $this->plans->findPlan((int) $member->id, $weekStart),
            'check' => $this->plans->validateWeek((int) $member->id, $weekStart),
            'histories' => $this->historyFor((int) $member->id, $weekStart),
            'planner' => $this->plans,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Thẻ tổng hợp
    |--------------------------------------------------------------------------
    */

    /**
     * SÁU thẻ của trang Kế hoạch — mọi con số lấy thẳng từ `planTotals()`,
     * không tính lại trong Blade.
     *
     * @param  array<string, int>  $totals
     * @return array<int, array<string, mixed>>
     */
    private function cards(array $totals): array
    {
        $without = (int) $totals['without_plan'];
        $risk = (int) $totals['risk_items'];

        return [
            ['label' => 'Tổng nhân viên kỹ thuật', 'value' => (int) $totals['total_staff'], 'icon' => 'bi-people', 'tone' => ''],
            ['label' => 'Đã lập kế hoạch', 'value' => (int) $totals['with_plan'], 'icon' => 'bi-calendar-check', 'tone' => 'tw-stat--ok'],
            ['label' => 'Chưa lập kế hoạch', 'value' => $without, 'icon' => 'bi-calendar-x', 'tone' => $without > 0 ? 'tw-stat--warn' : 'tw-stat--ok'],
            ['label' => 'Tổng công việc trong kế hoạch', 'value' => (int) $totals['total_items'], 'icon' => 'bi-list-task', 'tone' => ''],
            ['label' => 'Tổng giờ dự kiến', 'value' => $this->plans->minutesLabel((int) $totals['estimated_minutes']), 'icon' => 'bi-clock-history', 'tone' => '', 'is_text' => true],
            ['label' => 'Cảnh báo trùng lịch / quá tải', 'value' => $risk, 'icon' => 'bi-exclamation-triangle', 'tone' => $risk > 0 ? 'tw-stat--danger' : 'tw-stat--ok'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Tham số
    |--------------------------------------------------------------------------
    */

    private function resolveWeek(Request $request): Carbon
    {
        return $this->plans->resolveWeekStart(
            $request->query('week') !== null ? (string) $request->query('week') : null,
            $request->query('offset') !== null ? (int) $request->query('offset') : null,
        );
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

        if ($request->filled('plan_status')
            && array_key_exists((string) $request->query('plan_status'), TechnicalDashboardReadService::planStatusOptions())) {
            $filters['plan_status'] = (string) $request->query('plan_status');
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

    /**
     * Nhật ký điều chỉnh kế hoạch quanh tuần đang xem — CHỈ ĐỌC.
     *
     * @return Collection<int, TechnicalPlanHistory>
     */
    private function historyFor(int $userId, Carbon $weekStart): Collection
    {
        [$from, $to] = $this->plans->weekRange($weekStart);

        return TechnicalPlanHistory::query()
            ->with('user:id,name')
            ->where('target_user_id', $userId)
            ->whereBetween('created_at', [
                $from->copy()->subWeek()->toDateTimeString(),
                $to->copy()->addWeek()->toDateTimeString(),
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}
