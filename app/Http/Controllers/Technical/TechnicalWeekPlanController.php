<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\ValidatesPlanItems;
use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanDayMark;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use App\Models\User;
use App\Services\Technical\TechnicalAccess;
use App\Services\Technical\TechnicalPlanVsActualService;
use App\Services\Technical\TechnicalWeekPlanService;
use App\Services\Technical\TechnicalWorkFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Kế hoạch tuần của tôi" + "Công việc hôm nay" — màn hình của NHÂN VIÊN.
 *
 * Nguyên tắc phạm vi: mọi thao tác ghi đều áp lên kế hoạch của CHÍNH người
 * đang đăng nhập. Không có tham số nào cho phép thao tác lên kế hoạch người
 * khác ở controller này (việc đó thuộc `TechnicalPlanBoardController` và được
 * kiểm tra quyền quản lý riêng).
 *
 * Các thao tác trên trang "Công việc hôm nay" chỉ đổi trạng thái/tiến độ của
 * DÒNG KẾ HOẠCH — KHÔNG ghi gì vào workflow Công trình / task / lịch bảo trì.
 */
class TechnicalWeekPlanController extends Controller
{
    use ValidatesPlanItems;

    public function __construct(
        private readonly TechnicalWeekPlanService $plans,
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalAccess $access,
        private readonly TechnicalPlanVsActualService $stats,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Kế hoạch tuần của tôi
    |--------------------------------------------------------------------------
    */

    /**
     * Một trang KẾ HOẠCH duy nhất với ba tab NỘI BỘ (không thêm mục sidebar):
     *   `tab=this`    — tuần hiện tại (mặc định)
     *   `tab=prev`    — tuần trước
     *   `tab=history` — lịch sử các tuần đã lập của chính nhân viên
     */
    public function index(Request $request): View
    {
        $user = $this->authorizeModule($request);

        $tab = (string) $request->query('tab', '');
        $tab = in_array($tab, ['this', 'prev', 'history'], true) ? $tab : '';

        $thisWeek = TechnicalWeekPlan::weekStartFor(Carbon::today());

        $weekStart = match ($tab) {
            'prev' => $thisWeek->copy()->subWeek(),
            'history' => $thisWeek->copy(),
            default => $this->resolveWeek($request),
        };

        $data = $this->weekViewData($user, $weekStart);
        $data['tab'] = $tab !== '' ? $tab : ($weekStart->equalTo($thisWeek) ? 'this' : 'week');
        $data['history'] = $tab === 'history' ? $this->planHistory((int) $user->id) : collect();

        return view('technical.week-plan.index', $data);
    }

    /**
     * Lịch sử kế hoạch của CHÍNH nhân viên: mỗi tuần một dòng kèm số việc.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function planHistory(int $userId)
    {
        $plans = TechnicalWeekPlan::query()
            ->where('user_id', $userId)
            ->where('company_id', $this->feed->companyId())
            ->orderByDesc('week_start')
            ->limit(52)
            ->get();

        if ($plans->isEmpty()) {
            return collect();
        }

        $counts = TechnicalPlanItem::query()
            ->whereIn('week_plan_id', $plans->pluck('id')->all())
            ->selectRaw('week_plan_id, COUNT(*) as total')
            ->groupBy('week_plan_id')
            ->pluck('total', 'week_plan_id');

        return $plans->map(static fn (TechnicalWeekPlan $plan): object => (object) [
            'plan' => $plan,
            'items_count' => (int) ($counts[$plan->id] ?? 0),
        ]);
    }

    /** Thêm một việc vào kế hoạch. */
    public function storeItem(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $weekStart = $this->resolveWeek($request);
        $this->normalisePlanSourceKey($request);
        $data = $this->validateItem($request);

        $this->plans->addItem($user, $weekStart, $data, $user);

        return $this->backToWeek($weekStart, 'Đã thêm công việc vào kế hoạch tuần.');
    }

    public function updateItem(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $data = $this->validateItem($request, withSource: false);
        $data['status'] = $request->input('status');

        $this->plans->updateItem($item, $data, $user);

        return $this->backToWeek(Carbon::parse($item->plan_date), 'Đã cập nhật công việc.');
    }

    /** Chuyển một việc sang ngày khác (không kéo-thả: chọn ngày rồi gửi form). */
    public function moveItem(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $data = $request->validate(
            ['plan_date' => ['required', 'date']],
            ['plan_date.required' => 'Vui lòng chọn ngày chuyển đến.'],
        );

        $this->plans->updateItem($item, ['plan_date' => $data['plan_date']], $user);

        return $this->backToWeek(Carbon::parse($data['plan_date']), 'Đã chuyển công việc sang ngày khác.');
    }

    /** Sao chép một việc sang ngày khác. */
    public function copyItem(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $data = $request->validate(
            ['plan_date' => ['required', 'date']],
            ['plan_date.required' => 'Vui lòng chọn ngày cần sao chép đến.'],
        );

        $this->plans->copyItemToDate($item, Carbon::parse($data['plan_date']), $user);

        return $this->backToWeek(Carbon::parse($data['plan_date']), 'Đã sao chép công việc.');
    }

    public function destroyItem(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $weekStart = TechnicalWeekPlan::weekStartFor(Carbon::parse($item->plan_date));
        $this->plans->deleteItem($item, $user);

        return $this->backToWeek($weekStart, 'Đã xoá công việc khỏi kế hoạch.');
    }

    public function copyPreviousWeek(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $weekStart = $this->resolveWeek($request);

        $result = $this->plans->copyPreviousWeek($user, $weekStart, $user);

        $message = $result['copied'] > 0
            ? 'Đã sao chép '.$result['copied'].' công việc từ tuần trước.'
                .($result['skipped'] > 0 ? ' Bỏ qua '.$result['skipped'].' việc đã xong / bị trùng.' : '')
            : 'Tuần trước không có công việc nào để sao chép.';

        return $this->backToWeek($weekStart, $message);
    }

    public function markDay(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $weekStart = $this->resolveWeek($request);

        $data = $request->validate([
            'plan_date' => ['required', 'date'],
            'mark' => ['required', 'string', 'in:'.implode(',', array_keys(TechnicalPlanDayMark::MARK_LABELS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->plans->setDayMark(
            $user,
            $weekStart,
            Carbon::parse($data['plan_date']),
            $data['mark'],
            $data['reason'] ?? null,
        );

        return $this->backToWeek($weekStart, 'Đã đánh dấu ngày.');
    }

    /** "Lưu nháp" — kế hoạch tuần vốn được lưu ngay khi thêm việc. */
    public function saveDraft(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $weekStart = $this->resolveWeek($request);

        DB::transaction(fn () => $this->plans->firstOrCreatePlan($user, $weekStart));

        return $this->backToWeek($weekStart, 'Đã lưu nháp kế hoạch tuần.');
    }

    public function finalize(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $weekStart = $this->resolveWeek($request);

        $this->plans->finalize($user, $weekStart, $user);

        return $this->backToWeek($weekStart, 'Đã hoàn tất kế hoạch tuần.');
    }

    /*
    |--------------------------------------------------------------------------
    | Công việc hôm nay
    |--------------------------------------------------------------------------
    */

    public function today(Request $request): View
    {
        $user = $this->authorizeModule($request);
        $date = $this->resolveDate($request);

        $items = TechnicalPlanItem::query()
            ->where('user_id', $user->id)
            ->where('company_id', $this->feed->companyId())
            ->whereDate('plan_date', $date->toDateString())
            ->orderByRaw("FIELD(day_part, 'morning', 'afternoon', 'full_day', 'custom')")
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return view('technical.week-plan.today', [
            'date' => $date,
            'items' => $items,
            'reportMap' => $this->reportMapForItems($items->pluck('id')->all()),
            'statusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'weekdayLabel' => $this->plans->weekdayLabel($date),
            'planner' => $this->plans,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
        ]);
    }

    /** Bắt đầu / cập nhật tiến độ / kết thúc một dòng kế hoạch. */
    public function updateItemStatus(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(TechnicalPlanItem::STATUS_LABELS))],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'progress_percent.max' => 'Tiến độ chỉ nhận giá trị 0–100.',
        ]);

        $this->plans->changeStatus(
            $item,
            $data['status'],
            isset($data['progress_percent']) ? (int) $data['progress_percent'] : null,
            $user,
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Đã cập nhật trạng thái công việc.');
    }

    /**
     * "Đề xuất chuyển sang ngày sau" — đổi trạng thái dòng kế hoạch sang `moved`
     * và tạo dòng mới ở ngày kế tiếp. KHÔNG đụng dữ liệu nguồn Công trình.
     */
    public function proposeMoveToNextDay(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeOwnItem($item, $user);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:2000']],
            ['reason.required' => 'Vui lòng nêu lý do chuyển sang ngày sau.'],
        );

        $nextDay = Carbon::parse($item->plan_date)->addDay();

        $this->plans->changeStatus($item, TechnicalPlanItem::STATUS_MOVED, null, $user, $data['reason']);
        $this->plans->copyItemToDate($item, $nextDay, $user, $data['reason']);

        return back()->with('success', 'Đã chuyển công việc sang ngày '.$nextDay->format('d/m/Y').'.');
    }

    /*
    |--------------------------------------------------------------------------
    | Hỗ trợ
    |--------------------------------------------------------------------------
    */

    /** @return \App\Models\User */
    private function authorizeModule(Request $request)
    {
        $user = $request->user();
        abort_unless($this->access->canUseModule($user), 403, 'Bạn không thuộc phạm vi module Kỹ thuật.');

        return $user;
    }

    /** Dòng kế hoạch phải là của chính người đang đăng nhập và cùng công ty. */
    private function authorizeOwnItem(TechnicalPlanItem $item, User $user): void
    {
        abort_unless(
            (int) $item->company_id === $this->feed->companyId(),
            404,
            'Công việc không thuộc công ty đang làm việc.',
        );

        abort_unless(
            (int) $item->user_id === (int) $user->id,
            403,
            'Bạn chỉ được sửa kế hoạch của chính mình.',
        );
    }

    private function resolveWeek(Request $request): Carbon
    {
        return $this->plans->resolveWeekStart(
            $request->input('week') !== null ? (string) $request->input('week') : null,
            $request->input('offset') !== null ? (int) $request->input('offset') : null,
        );
    }

    private function resolveDate(Request $request): Carbon
    {
        $raw = (string) $request->query('date', '');

        if ($raw === '') {
            return Carbon::today();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    private function backToWeek(Carbon $weekStart, string $message): RedirectResponse
    {
        return redirect()
            ->route('technical.week-plan.index', ['week' => TechnicalWeekPlan::weekStartFor($weekStart)->toDateString()])
            ->with('success', $message);
    }

    /**
     * Dữ liệu chung cho trang kế hoạch tuần.
     *
     * @return array<string, mixed>
     */
    private function weekViewData(User $user, Carbon $weekStart): array
    {
        $plan = $this->plans->findPlan((int) $user->id, $weekStart);
        $itemsByDay = $this->plans->itemsByDay((int) $user->id, $weekStart);
        $check = $this->plans->validateWeek((int) $user->id, $weekStart);
        [$from, $to] = $this->plans->weekRange($weekStart);

        return [
            'owner' => $user,
            'plan' => $plan,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => TechnicalWeekPlan::weekDays($weekStart),
            'itemsByDay' => $itemsByDay,
            'dayMarks' => $this->plans->dayMarks((int) $user->id, $weekStart),
            'check' => $check,
            'planner' => $this->plans,
            'assignableItems' => $this->plans->assignableItems((int) $user->id),
            'sourceOptions' => TechnicalPlanItem::SOURCE_LABELS,
            'statusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'dayPartOptions' => (array) config('technical.day_parts'),
            'priorityOptions' => (array) config('technical.priorities'),
            'markOptions' => TechnicalPlanDayMark::MARK_LABELS,
            'dailyMinutes' => $this->plans->dailyWorkingMinutes(),
            'reportMap' => $this->reportMapForItems($itemsByDay->flatten()->pluck('id')->all()),
            'summary' => $this->stats->summary($from, $to, ['user_id' => (int) $user->id]),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
            'statusNotStarted' => TechnicalWeekPlan::LABEL_NOT_STARTED,
        ];
    }

    /**
     * Báo cáo mới nhất theo từng dòng kế hoạch — MỘT truy vấn cho cả trang.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, object>
     */
    private function reportMapForItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return TechnicalDailyReport::query()
            ->whereIn('plan_item_id', $itemIds)
            ->orderBy('plan_item_id')
            ->orderByDesc('id')
            ->get(['id', 'plan_item_id', 'status', 'report_date', 'progress_percent'])
            ->groupBy('plan_item_id')
            ->map(fn ($group) => $group->first())
            ->all();
    }
}
