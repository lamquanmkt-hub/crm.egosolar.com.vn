<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\ValidatesPlanItems;
use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use App\Models\User;
use App\Services\Technical\TechnicalAccess;
use App\Services\Technical\TechnicalPlanLogger;
use App\Services\Technical\TechnicalPlanVsActualService;
use App\Services\Technical\TechnicalTeamService;
use App\Services\Technical\TechnicalWeekPlanService;
use App\Services\Technical\TechnicalWorkFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Màn hình của TRƯỞNG PHÒNG KỸ THUẬT: xem kế hoạch toàn phòng, giao thêm việc,
 * điều phối lịch, theo dõi báo cáo, tổng kết tuần.
 *
 * Giới hạn cố ý theo nghiệp vụ mới:
 * - Trưởng phòng KHÔNG nhập hộ báo cáo kết quả cho nhân viên (không có endpoint
 *   nào ở đây làm việc đó).
 * - MỌI điều chỉnh lên kế hoạch của người khác đều BẮT BUỘC có lý do và được
 *   ghi nhật ký (người chỉnh / trước / sau / thời gian / lý do).
 * - Không có bước "Admin duyệt": trưởng phòng tự quyết trong phạm vi của mình.
 */
class TechnicalPlanBoardController extends Controller
{
    use ValidatesPlanItems;

    public function __construct(
        private readonly TechnicalWeekPlanService $plans,
        private readonly TechnicalPlanVsActualService $stats,
        private readonly TechnicalTeamService $team,
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalAccess $access,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Tổng quan phòng
    |--------------------------------------------------------------------------
    */

    public function overview(Request $request): View
    {
        $this->authorizeManager($request);
        $weekStart = $this->resolveWeek($request);
        [$from, $to] = $this->plans->weekRange($weekStart);
        $filters = $this->resolveFilters($request);

        $rows = $this->stats->perUser($from, $to, $filters);

        return view('technical.manager.overview', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'summary' => $this->stats->summary($from, $to, $filters),
            'rows' => $rows,
            'members' => $this->team->members(),
            'filters' => $filters,
            'planner' => $this->plans,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ma trận kế hoạch nhân viên
    |--------------------------------------------------------------------------
    */

    /** Bảng ma trận: mỗi dòng một nhân viên, mỗi cột một ngày trong tuần. */
    public function board(Request $request): View
    {
        $this->authorizeManager($request);
        $weekStart = $this->resolveWeek($request);
        $filters = $this->resolveFilters($request);
        $days = TechnicalWeekPlan::weekDays($weekStart);

        $matrix = $this->buildMatrix($weekStart, $filters);
        [$from, $to] = $this->plans->weekRange($weekStart);

        $members = $this->team->members();

        /*
         | Drawer nào cần mở sẵn khi trang vừa tải:
         |   ?open=create | ?open=assign          — deep-link rõ nghĩa
         |   ?focus=assign                        — TƯƠNG THÍCH NGƯỢC: liên kết
         |     cũ (menu, trang Admin) giờ mở thẳng drawer Giao việc thay vì cuộn
         |     tới khung chọn nhân viên đã bị gỡ.
         |   có lỗi validation                    — mở lại đúng form vừa submit
         */
        $open = (string) $request->query('open', '');

        if ($open === '' && $request->query('focus') === 'assign') {
            $open = 'assign';
        }

        if (session()->has('errors')) {
            $open = (string) (old('tp_form') ?: $open);
        }

        if (! in_array($open, ['create', 'assign'], true)) {
            $open = '';
        }

        $selectedMember = (int) old('user_id', (int) $request->query('user_id', 0));

        if ($selectedMember > 0 && ! $this->team->manages($selectedMember)) {
            $selectedMember = 0;
        }

        $selectedDate = (string) old('items.0.plan_date', (string) $request->query('date', ''));

        if ($selectedDate !== '' && ! in_array($selectedDate, array_map(
            static fn (Carbon $d): string => $d->toDateString(),
            $days,
        ), true)) {
            $selectedDate = '';
        }

        $formMember = $selectedMember > 0
            ? $selectedMember
            : (int) ($members->first()->id ?? 0);

        return view('technical.manager.board', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => $days,
            'matrix' => $matrix,
            'members' => $members,
            'sites' => $this->siteOptions($from, $to),
            'filters' => $filters,
            'planner' => $this->plans,
            'dailyMinutes' => $this->plans->dailyWorkingMinutes(),
            'statusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),

            // Dữ liệu cho hai drawer tạo / giao kế hoạch (render SERVER-SIDE).
            'openDrawer' => $open,
            'selectedMember' => $selectedMember,
            'selectedDate' => $selectedDate,
            'sourceOptions' => TechnicalPlanItem::SOURCE_LABELS,
            'feedSources' => TechnicalPlanItem::FEED_SOURCES,
            'dayPartOptions' => (array) config('technical.day_parts'),
            'priorityOptions' => (array) config('technical.priorities'),
            'defaultMinutes' => (int) config('technical.week_plan.default_item_minutes', 240),
            'assignableItems' => $formMember > 0
                ? $this->plans->assignableItems($formMember)
                : collect(),
            'assignableFor' => $formMember,
        ]);
    }

    /** Chi tiết kế hoạch của MỘT nhân viên (dạng cột). */
    public function detail(Request $request, User $member): View
    {
        $this->authorizeManager($request);
        abort_unless($this->team->manages((int) $member->id), 403, 'Nhân sự này không thuộc phạm vi quản lý của bạn.');

        $weekStart = $this->resolveWeek($request);
        $itemsByDay = $this->plans->itemsByDay((int) $member->id, $weekStart);

        return view('technical.manager.detail', [
            'member' => $member,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => TechnicalWeekPlan::weekDays($weekStart),
            'itemsByDay' => $itemsByDay,
            'dayMarks' => $this->plans->dayMarks((int) $member->id, $weekStart),
            'plan' => $this->plans->findPlan((int) $member->id, $weekStart),
            'check' => $this->plans->validateWeek((int) $member->id, $weekStart),
            'planner' => $this->plans,
            'assignableItems' => $this->plans->assignableItems((int) $member->id),
            'sourceOptions' => TechnicalPlanItem::SOURCE_LABELS,
            'statusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'dayPartOptions' => (array) config('technical.day_parts'),
            'priorityOptions' => (array) config('technical.priorities'),
            'histories' => $this->historyFor((int) $member->id, $weekStart),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Điều phối (ghi)
    |--------------------------------------------------------------------------
    */

    /**
     * Giao thêm việc cho một nhân viên từ TRANG CHI TIẾT — BẮT BUỘC lý do.
     *
     * Chỉ còn là một lớp mỏng: đọc form một dòng rồi gọi ĐÚNG lõi ghi dùng
     * chung `persistPlanRows()` mà drawer "Tạo kế hoạch" / "Giao việc" ở trang
     * ma trận cũng dùng. Không có nghiệp vụ ghi thứ hai.
     *
     * Khác biệt cố ý duy nhất: form một dòng này KHÔNG có ô xác nhận cảnh báo,
     * nên cảnh báo trùng lịch / quá tải vẫn là warning-only ở đây (hành vi sẵn
     * có, đang được khoá bởi test). Drawer mới có ô xác nhận nên bật cổng chặn.
     */
    public function assign(Request $request, User $member): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $this->authorizeMemberInScope($member);

        $this->normalisePlanSourceKey($request);
        $data = $this->validateItem($request);
        $reason = $this->validateReason($request);

        $weekStart = TechnicalWeekPlan::weekStartFor(Carbon::parse($data['plan_date']));

        // Mặc định việc trưởng phòng tự nghĩ ra là nguồn "manager_assigned";
        // nếu có chọn việc nguồn thì giữ nguồn thật để không mất liên kết.
        if (($data['source_type'] ?? '') === TechnicalPlanItem::SOURCE_PERSONAL) {
            $data['source_type'] = TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED;
        }

        $this->persistPlanRows(
            actor: $actor,
            member: $member,
            weekStart: $weekStart,
            rows: [$data],
            reason: $reason,
            assignMode: true,
            gateOnWarnings: false,
            confirmed: false,
        );

        return redirect()
            ->route('technical.manager.detail', ['member' => $member->id, 'week' => $weekStart->toDateString()])
            ->with('success', 'Đã giao thêm việc cho '.$member->name.'.');
    }

    /**
     * LỐI GHI DÙNG CHUNG của hai drawer ở trang ma trận:
     *   - "+ Tạo kế hoạch"  → nhiều dòng, `mode=draft` (Lưu nháp) hoặc
     *                          `mode=assign` (Lưu và giao kế hoạch)
     *   - "Giao việc"        → một dòng, `mode=assign`
     *
     * Toàn bộ các dòng nằm trong MỘT transaction (all-or-nothing) và đi qua
     * đúng `TechnicalWeekPlanService::addItem()` + `TechnicalPlanLogger` như
     * mọi thao tác ghi khác. Lý do LUÔN bắt buộc.
     */
    public function storePlan(Request $request): RedirectResponse
    {
        $actor = $this->authorizeManager($request);

        $member = $this->resolveTargetMember($request);
        $reason = $this->validateReason($request);

        $mode = (string) $request->input('mode', 'assign');

        if (! in_array($mode, ['draft', 'assign'], true)) {
            $mode = 'assign';
        }

        $rows = $this->validatePlanRows($request);
        $weekStart = $this->resolveWeek($request);

        $confirmed = (bool) $request->boolean('confirm_warnings');

        $this->persistPlanRows(
            actor: $actor,
            member: $member,
            weekStart: $weekStart,
            rows: $rows,
            reason: $reason,
            assignMode: $mode === 'assign',
            gateOnWarnings: true,
            confirmed: $confirmed,
        );

        $name = TechnicalWeekPlanService::displayLabel($member->name);

        return redirect()
            ->to($this->boardUrlPreservingView($request, $weekStart))
            ->with('success', $mode === 'assign'
                ? 'Đã giao kế hoạch cho '.$name.'.'
                : 'Đã lưu nháp kế hoạch cho '.$name.'.')
            ->with('success_link', route('technical.manager.detail', [
                'member' => $member->id,
                'week' => $weekStart->toDateString(),
            ]))
            ->with('success_link_label', 'Xem chi tiết kế hoạch của '.$name);
    }

    /**
     * Danh sách "Đầu việc" (công việc nguồn) đã được giao cho một nhân viên —
     * CHỈ ĐỌC, dùng cho ô chọn động trong drawer.
     *
     * Quyền được kiểm y hệt các màn hình ghi: người không quản lý được nhân sự
     * đó (hoặc không thuộc module) nhận 403, nên endpoint này không mở thêm
     * đường rò dữ liệu nào.
     */
    public function workItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManager($request);

        $member = $this->resolveTargetMember($request);

        $items = $this->plans->assignableItems((int) $member->id)
            ->map(fn ($item): array => [
                'source_type' => $item->sourceType,
                'source_id' => $item->sourceId,
                'source_label' => $item->sourceLabel(),
                'title' => TechnicalWeekPlanService::displayLabel($item->title),
                'site_name' => TechnicalWeekPlanService::displayLabel((string) ($item->siteName ?? '')),
            ])
            ->values()
            ->all();

        return response()->json([
            'user_id' => (int) $member->id,
            'items' => $items,
        ]);
    }

    /** Điều chỉnh một dòng kế hoạch của nhân viên — BẮT BUỘC lý do. */
    public function adjust(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $this->authorizeItemInScope($item);

        $data = $this->validateItem($request, withSource: false);
        $data['status'] = $request->input('status');
        $reason = $this->validateReason($request);

        $this->plans->updateItem($item, $data, $actor, $reason);

        return back()->with('success', 'Đã điều chỉnh kế hoạch và ghi nhật ký kèm lý do.');
    }

    /** Chuyển nhanh một việc sang ngày khác — BẮT BUỘC lý do. */
    public function move(Request $request, TechnicalPlanItem $item): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        $this->authorizeItemInScope($item);

        $data = $request->validate(
            ['plan_date' => ['required', 'date']],
            ['plan_date.required' => 'Vui lòng chọn ngày chuyển đến.'],
        );
        $reason = $this->validateReason($request);

        $this->plans->updateItem($item, ['plan_date' => $data['plan_date']], $actor, $reason);

        return back()->with('success', 'Đã chuyển công việc sang ngày khác.');
    }

    /** Yêu cầu nhân viên cập nhật lại kế hoạch tuần — BẮT BUỘC lý do. */
    public function requestUpdate(Request $request, User $member): RedirectResponse
    {
        $actor = $this->authorizeManager($request);
        abort_unless($this->team->manages((int) $member->id), 403, 'Nhân sự này không thuộc phạm vi quản lý của bạn.');

        $weekStart = $this->resolveWeek($request);
        $reason = $this->validateReason($request);

        $plan = $this->plans->findPlan((int) $member->id, $weekStart);

        if ($plan === null) {
            return back()->with('error', 'Nhân viên chưa lập kế hoạch tuần này.');
        }

        $this->plans->requestUpdate($plan, $actor, $reason);

        return back()->with('success', 'Đã gửi yêu cầu cập nhật kế hoạch kèm lý do.');
    }

    /*
    |--------------------------------------------------------------------------
    | Tổng kết tuần
    |--------------------------------------------------------------------------
    */

    public function weeklySummary(Request $request): View
    {
        $this->authorizeManager($request);
        $weekStart = $this->resolveWeek($request);
        [$from, $to] = $this->plans->weekRange($weekStart);
        $filters = $this->resolveFilters($request);

        return view('technical.manager.weekly-summary', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'summary' => $this->stats->summary($from, $to, $filters),
            'rows' => $this->stats->perUser($from, $to, $filters),
            'sites' => $this->stats->perSite($from, $to, $filters),
            'trend' => $this->stats->weeklyTrend($weekStart, 6, $filters),
            'members' => $this->team->members(),
            'filters' => $filters,
            'planner' => $this->plans,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Hỗ trợ
    |--------------------------------------------------------------------------
    */

    /** @return \App\Models\User */
    private function authorizeManager(Request $request)
    {
        $user = $request->user();
        abort_unless($this->access->canUseModule($user), 403, 'Bạn không thuộc phạm vi module Kỹ thuật.');
        abort_unless($this->access->canManage($user), 403, 'Chỉ trưởng phòng Kỹ thuật hoặc Ban giám đốc được xem trang này.');

        return $user;
    }

    /**
     * Nhân viên nhận kế hoạch, lấy từ BODY/QUERY và kiểm lại phạm vi quản lý.
     * Không bao giờ tin id gửi lên: `TechnicalTeamService::manages()` là cổng.
     */
    private function resolveTargetMember(Request $request): User
    {
        $id = (int) $request->input('user_id', $request->query('user_id', 0));

        abort_if($id <= 0, 403, 'Vui lòng chọn nhân viên nhận kế hoạch.');
        abort_unless($this->team->manages($id), 403, 'Nhân sự này không thuộc phạm vi quản lý của bạn.');

        $member = User::find($id);

        abort_if($member === null, 403, 'Nhân sự này không thuộc phạm vi quản lý của bạn.');

        return $member;
    }

    private function authorizeMemberInScope(User $member): void
    {
        abort_unless($this->team->manages((int) $member->id), 403, 'Nhân sự này không thuộc phạm vi quản lý của bạn.');
    }

    /**
     * Đọc và kiểm tra MẢNG dòng công việc của form nhiều dòng.
     *
     * Dùng lại `planItemRules()/planItemMessages()` của trait — tức đúng bộ quy
     * tắc mà form một dòng đang dùng, chỉ đổi tiền tố khoá thành `items.*.`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validatePlanRows(Request $request): array
    {
        $max = (int) config('technical.week_plan.max_items_per_day', 12) * 7;

        $rules = array_merge(
            ['items' => ['required', 'array', 'min:1', 'max:'.$max]],
            $this->planItemRules(withSource: true, prefix: 'items.*.'),
        );

        $messages = array_merge(
            [
                'items.required' => 'Vui lòng nhập ít nhất một công việc.',
                'items.min' => 'Vui lòng nhập ít nhất một công việc.',
                'items.max' => 'Một lần lưu tối đa '.$max.' công việc.',
            ],
            $this->planItemMessages('items.*.'),
        );

        $validated = $request->validate($rules, $messages);

        $rows = [];

        foreach (array_values((array) $validated['items']) as $index => $row) {
            $rows[] = $this->normalisePlanItemTimes((array) $row, 'items.'.$index.'.start_time');
        }

        return $rows;
    }

    /**
     * LÕI GHI DUY NHẤT cho mọi form tạo / giao kế hoạch của quản lý.
     *
     * - Toàn bộ dòng nằm trong MỘT `DB::transaction` → một dòng sai thì KHÔNG
     *   dòng nào được ghi (all-or-nothing).
     * - Mỗi dòng đi qua `TechnicalWeekPlanService::addItem()` nên nguồn công
     *   việc vẫn được xác thực lại phía server và nhật ký vẫn được ghi trong
     *   cùng transaction.
     * - `assignMode = false` (Lưu nháp): ghi dòng nhưng KHÔNG đánh dấu đã giao
     *   và KHÔNG đẩy tuần sang "Đã được Trưởng phòng điều chỉnh".
     * - `gateOnWarnings`: tính cảnh báo của tuần TRƯỚC và SAU khi ghi; cảnh báo
     *   MỚI phát sinh mà chưa được xác nhận → ném ValidationException, cả
     *   transaction bị huỷ, form quay lại kèm danh sách cảnh báo + ô xác nhận.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistPlanRows(
        User $actor,
        User $member,
        Carbon $weekStart,
        array $rows,
        string $reason,
        bool $assignMode,
        bool $gateOnWarnings,
        bool $confirmed,
    ): void {
        DB::transaction(function () use ($actor, $member, $weekStart, $rows, $reason, $assignMode, $gateOnWarnings, $confirmed): void {
            $warningsBefore = $gateOnWarnings && ! $confirmed
                ? $this->plans->validateWeek((int) $member->id, $weekStart)['warnings']
                : [];

            foreach ($rows as $row) {
                $this->plans->addItem(
                    $member,
                    $weekStart,
                    $row,
                    $actor,
                    $reason,
                    $assignMode ? true : false,
                );
            }

            if (! $gateOnWarnings || $confirmed) {
                return;
            }

            $warningsAfter = $this->plans->validateWeek((int) $member->id, $weekStart)['warnings'];
            $newWarnings = $this->warningsAddedBy($warningsBefore, $warningsAfter);

            if ($newWarnings === []) {
                return;
            }

            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_warnings' => $newWarnings,
            ]);
        });
    }

    /**
     * Cảnh báo do CHÍNH lần ghi này sinh thêm.
     *
     * So sánh theo SỐ LẦN xuất hiện chứ không phải theo tập hợp: hai công việc
     * trùng giờ có thể sinh ra cùng một câu cảnh báo (ví dụ hai dòng trùng tên),
     * `array_diff` sẽ bỏ sót trường hợp đó và cho ghi mà không hỏi xác nhận.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     * @return array<int, string>
     */
    private function warningsAddedBy(array $before, array $after): array
    {
        $seen = array_count_values($before);
        $added = [];

        foreach ($after as $warning) {
            if (($seen[$warning] ?? 0) > 0) {
                $seen[$warning]--;

                continue;
            }

            $added[] = $warning;
        }

        return array_values(array_unique($added));
    }

    /**
     * URL quay lại ma trận GIỮ NGUYÊN tuần đang xem và bộ lọc đang áp dụng —
     * người dùng không phải bấm lại bộ lọc sau khi lưu.
     */
    private function boardUrlPreservingView(Request $request, Carbon $weekStart): string
    {
        $keep = [];

        foreach (['user_id_filter' => 'user_id', 'site_id' => 'site_id', 'status' => 'status'] as $input => $query) {
            $value = $request->input('view_'.$query);

            if ($value !== null && $value !== '') {
                $keep[$query] = $value;
            }

            unset($input);
        }

        $keep['week'] = $weekStart->toDateString();

        return route('technical.manager.board', $keep);
    }

    private function authorizeItemInScope(TechnicalPlanItem $item): void
    {
        abort_unless(
            (int) $item->company_id === $this->feed->companyId(),
            404,
            'Công việc không thuộc công ty đang làm việc.',
        );

        abort_unless(
            $this->team->manages((int) $item->user_id),
            403,
            'Nhân sự này không thuộc phạm vi quản lý của bạn.',
        );
    }

    private function validateReason(Request $request): string
    {
        $data = $request->validate(
            ['reason' => TechnicalPlanLogger::reasonRules()],
            TechnicalPlanLogger::reasonMessages(),
        );

        return (string) $data['reason'];
    }

    private function resolveWeek(Request $request): Carbon
    {
        return $this->plans->resolveWeekStart(
            $request->input('week') !== null ? (string) $request->input('week') : null,
            $request->input('offset') !== null ? (int) $request->input('offset') : null,
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

        if ($request->filled('status')
            && array_key_exists((string) $request->query('status'), TechnicalPlanItem::STATUS_LABELS)) {
            $filters['status'] = (string) $request->query('status');
        }

        return $filters;
    }

    /**
     * Ma trận nhân viên × ngày — MỘT truy vấn GROUP BY cho toàn bộ ô, cộng một
     * truy vấn lấy tên công trình chính. Không truy vấn trong vòng lặp.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMatrix(Carbon $weekStart, array $filters): Collection
    {
        [$from, $to] = $this->plans->weekRange($weekStart);
        $today = Carbon::today()->toDateString();

        $cellQuery = DB::table('technical_plan_items as i')
            ->where('i.company_id', $this->feed->companyId())
            ->whereNull('i.deleted_at')
            ->whereBetween('i.plan_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('i.user_id')
            ->selectRaw('i.plan_date')
            ->selectRaw('COUNT(*) AS item_count')
            ->selectRaw("SUM(CASE WHEN i.status <> 'cancelled' THEN COALESCE(i.estimated_minutes, 0) ELSE 0 END) AS minutes")
            ->selectRaw('MAX(i.site_name) AS main_site')
            ->selectRaw('COUNT(DISTINCT i.site_id) AS site_count')
            ->selectRaw("SUM(CASE WHEN i.status IN ('planned', 'in_progress') AND i.plan_date < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->groupBy('i.user_id', 'i.plan_date');

        if (! empty($filters['user_id'])) {
            $cellQuery->where('i.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['site_id'])) {
            $cellQuery->where('i.site_id', (int) $filters['site_id']);
        }

        if (! empty($filters['status'])) {
            $cellQuery->where('i.status', (string) $filters['status']);
        }

        $cells = collect($cellQuery->get())
            ->groupBy(fn (object $row): int => (int) $row->user_id)
            ->map(fn (Collection $rows): Collection => $rows->keyBy(
                fn (object $row): string => substr((string) $row->plan_date, 0, 10),
            ));

        $planStatuses = DB::table('technical_week_plans')
            ->where('company_id', $this->feed->companyId())
            ->whereNull('deleted_at')
            ->whereDate('week_start', $weekStart->toDateString())
            ->get(['user_id', 'status', 'update_requested'])
            ->keyBy(fn (object $row): int => (int) $row->user_id);

        $days = TechnicalWeekPlan::weekDays($weekStart);
        $overloadLimit = (int) round(
            $this->plans->dailyWorkingMinutes() * (float) config('technical.week_plan.overload_ratio', 1.0),
        );

        $members = $this->team->members();

        if (! empty($filters['user_id'])) {
            $members = $members->filter(fn (object $m): bool => (int) $m->id === (int) $filters['user_id']);
        }

        return $members->map(function (object $member) use ($cells, $days, $planStatuses, $overloadLimit): array {
            $id = (int) $member->id;
            $memberCells = $cells->get($id, collect());
            $row = [];
            $totalMinutes = 0;
            $totalItems = 0;
            $hasOverload = false;

            foreach ($days as $day) {
                $key = $day->toDateString();
                $cell = $memberCells->get($key);
                $minutes = (int) ($cell->minutes ?? 0);
                $count = (int) ($cell->item_count ?? 0);

                $totalMinutes += $minutes;
                $totalItems += $count;
                $overloaded = $minutes > $overloadLimit && $overloadLimit > 0;
                $hasOverload = $hasOverload || $overloaded;

                $row[$key] = [
                    'count' => $count,
                    'minutes' => $minutes,
                    'main_site' => $cell->main_site ?? null,
                    'site_count' => (int) ($cell->site_count ?? 0),
                    'overdue' => (int) ($cell->overdue_count ?? 0),
                    'overloaded' => $overloaded,
                ];
            }

            $plan = $planStatuses->get($id);

            return [
                'user_id' => $id,
                'user_name' => (string) $member->name,
                'cells' => $row,
                'total_minutes' => $totalMinutes,
                'total_items' => $totalItems,
                'has_overload' => $hasOverload,
                'plan_status' => $plan->status ?? null,
                'update_requested' => (bool) ($plan->update_requested ?? false),
            ];
        })->values();
    }

    /** Công trình xuất hiện trong kế hoạch tuần — cho bộ lọc. */
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
     * Nhật ký điều chỉnh của một nhân viên, giới hạn quanh tuần đang xem.
     *
     * Một truy vấn, đã eager-load người thao tác — không N+1 khi hiển thị.
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
