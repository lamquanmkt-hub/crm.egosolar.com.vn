<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalDailyReportFile;
use App\Models\Technical\TechnicalDailyReportHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Services\Technical\TechnicalAccess;
use App\Services\Technical\TechnicalDailyReportLogger;
use App\Services\Technical\TechnicalDashboardReadService;
use App\Services\Technical\TechnicalPlanVsActualService;
use App\Services\Technical\TechnicalTeamService;
use App\Services\Technical\TechnicalWeekPlanService;
use App\Services\Technical\TechnicalWorkFeedService;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Báo cáo ngày của kỹ thuật viên.
 *
 * Khác biệt cốt lõi so với màn hình "Báo cáo" cũ (`TechnicalWorkController`):
 * - Một người báo cáo được NHIỀU đầu việc trong cùng một ngày.
 * - Mỗi lần báo cáo là MỘT bản ghi riêng, không ghi đè bản cũ.
 * - Báo cáo gắn với đầu việc THẬT qua (source_type, source_id, site_id) và
 *   chỉ được tạo cho đầu việc mà người dùng thực sự được giao.
 * - Có luồng duyệt và nhật ký đầy đủ.
 *
 * Giai đoạn 1 KHÔNG chạm tới workflow Công trình: duyệt báo cáo chỉ đổi trạng
 * thái của chính báo cáo, không cập nhật tiến độ công trình.
 */
class TechnicalDailyReportController extends Controller
{
    /** Định dạng file minh chứng được phép tải lên. */
    private const ALLOWED_MIMES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx';

    /** Dung lượng tối đa mỗi file (KB) — 10 MB. */
    private const MAX_FILE_KB = 10240;

    private const MAX_FILES = 10;

    /** Disk PRIVATE: `local` trỏ vào storage/app/private (xem config/filesystems.php). */
    private const DISK = 'local';

    private const STORAGE_FOLDER = 'technical/daily-reports';

    /** Tab "Tổng hợp tuần" của trang Báo cáo (cùng route, cùng view). */
    public const TAB_WEEKLY = 'weekly-summary';

    public function __construct(
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalAccess $access,
        private readonly TechnicalPlanVsActualService $stats,
        private readonly TechnicalDashboardReadService $reader,
        private readonly TechnicalTeamService $team,
        private readonly TechnicalWeekPlanService $plans,
    ) {}

    /** Danh sách báo cáo ngày trong phạm vi quyền. */
    public function index(Request $request): View
    {
        $user = $this->authorizeModule($request);
        $canManage = $this->access->canManage($user);

        /*
         * HAI TAB TRONG CÙNG MỘT TRANG (2026-09):
         *   - "Báo cáo ngày"  (mặc định) — danh sách báo cáo, giữ nguyên như cũ.
         *   - "Tổng hợp tuần" (?tab=weekly-summary) — tổng hợp kết quả tuần,
         *     TÁI SỬ DỤNG đúng các service đã dựng cho dashboard báo cáo cũ.
         * Chuyển tab KHÔNG đổi mục sidebar đang active: vẫn cùng tên route.
         */
        if ((string) $request->query('tab', '') === self::TAB_WEEKLY) {
            return $this->weeklySummaryTab($request, $user, $canManage);
        }

        $query = TechnicalDailyReport::query()
            ->with(['user:id,name', 'approver:id,name'])
            ->withCount('files')
            ->where('company_id', $this->feed->companyId());

        $scopedUserId = $this->access->visibleUserId(
            $user,
            $canManage && $request->filled('user_id') ? (int) $request->query('user_id') : null,
        );

        if ($scopedUserId !== null) {
            $query->where('user_id', $scopedUserId);
        }

        if ($request->filled('status') && array_key_exists((string) $request->query('status'), TechnicalDailyReport::STATUS_LABELS)) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('report_date', '>=', (string) $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('report_date', '<=', (string) $request->query('to'));
        }

        $reports = $query->orderByDesc('report_date')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('technical.daily-reports.index', [
            'tab' => '',
            'reports' => $reports,
            'canManage' => $canManage,
            'statusOptions' => TechnicalDailyReport::STATUS_LABELS,
            'teamMembers' => $canManage ? $this->feed->assignedUsers() : collect(),
            'selectedUserId' => $canManage && $request->filled('user_id') ? (int) $request->query('user_id') : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tab "Tổng hợp tuần"
    |--------------------------------------------------------------------------
    |
    | KHÔNG viết lại phép tính: dùng nguyên `TechnicalPlanVsActualService`
    | (summary / perUser / perSite) và `TechnicalDashboardReadService`
    | (siteLeadNames / actualMinutesPerSite) — chính là bộ số liệu đã dựng cho
    | trang `/ky-thuat/dashboard/bao-cao` trước đây, nay chỉ đổi nơi hiển thị.
    |
    | Phân quyền: nhân viên thường bị ÉP về phạm vi của chính mình
    | (`TechnicalAccess::visibleUserId`), không thấy dữ liệu toàn đội.
    */

    /** @param \App\Models\User $user */
    private function weeklySummaryTab(Request $request, $user, bool $canManage): View
    {
        $weekStart = $this->plans->resolveWeekStart(
            $request->query('week') !== null ? (string) $request->query('week') : null,
            $request->query('offset') !== null ? (int) $request->query('offset') : null,
        );
        [$from, $to] = $this->plans->weekRange($weekStart);

        $filters = $this->weeklyFilters($request, $user, $canManage);

        $summary = $this->stats->summary($from, $to, $filters);
        $reportCounts = $this->stats->reportStatusCounts($from, $to, $filters);
        $perUserReports = $this->stats->reportCountsPerUser($from, $to, $filters);
        $perUserMinutes = $this->stats->actualMinutesPerUser($from, $to, $filters);

        $rows = $this->stats->perUser($from, $to, $filters)
            ->when(
                ! $canManage,
                fn ($c) => $c->filter(fn (array $r): bool => (int) $r['user_id'] === (int) $user->id),
            )
            ->map(function (array $row) use ($perUserReports, $perUserMinutes): array {
                $row['report_count'] = (int) ($perUserReports[$row['user_id']] ?? 0);
                $row['actual_minutes'] = (int) ($perUserMinutes[$row['user_id']] ?? 0);

                return $row;
            })
            ->values();

        $sites = $this->stats->perSite($from, $to, $filters);
        $leads = $this->reader->siteLeadNames($sites->pluck('site_id')->all());
        $siteMinutes = $this->reader->actualMinutesPerSite($from, $to, $filters);

        $sites = $sites->map(function (array $site) use ($leads, $siteMinutes): array {
            $siteId = (int) $site['site_id'];
            $planned = (int) ($site['planned_items'] ?? 0);
            $done = (int) ($site['done_items'] ?? 0);

            $site['total_items'] = $planned;
            $site['not_done_items'] = max(0, $planned - $done);
            $site['lead_name'] = $leads[$siteId] ?? null;
            $site['actual_minutes'] = (int) ($siteMinutes[$siteId] ?? 0);

            return $site;
        });

        return view('technical.daily-reports.index', [
            'tab' => self::TAB_WEEKLY,
            'canManage' => $canManage,
            'weekStart' => $weekStart,
            'from' => $from,
            'to' => $to,
            'rangeLabel' => 'Tuần '.$from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'thisWeek' => \App\Models\Technical\TechnicalWeekPlan::weekStartFor(Carbon::today())->toDateString(),
            'summary' => $summary,
            'reportCounts' => $reportCounts,
            'cards' => $this->weeklyCards($summary, $reportCounts),
            'rows' => $rows,
            'sites' => $sites,
            'filters' => $filters,
            'teamMembers' => $canManage ? $this->team->members() : collect(),
            'siteOptions' => $this->weeklySiteOptions($from, $to),
            'workStatusOptions' => TechnicalPlanItem::STATUS_LABELS,
            'planner' => $this->plans,
            'reports' => null,
            'statusOptions' => TechnicalDailyReport::STATUS_LABELS,
            'selectedUserId' => $filters['user_id'] ?? null,
        ]);
    }

    /**
     * TÁM thẻ của tab Tổng hợp tuần — số lấy nguyên từ service, không tính lại
     * trong Blade.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, int>  $reportCounts
     * @return array<int, array<string, mixed>>
     */
    private function weeklyCards(array $summary, array $reportCounts): array
    {
        $pending = (int) ($reportCounts[TechnicalDailyReport::STATUS_SUBMITTED] ?? 0);
        $revision = (int) ($reportCounts[TechnicalDailyReport::STATUS_REVISION] ?? 0);
        $notDone = (int) $summary['not_done_items'] + (int) $summary['open_items'];

        return [
            ['label' => 'Tổng báo cáo đã nộp', 'value' => (int) ($reportCounts['finalized'] ?? 0), 'icon' => 'bi-journal-check', 'tone' => 'tw-stat--ok'],
            ['label' => 'Báo cáo chờ duyệt', 'value' => $pending, 'icon' => 'bi-hourglass-split', 'tone' => $pending > 0 ? 'tw-stat--warn' : ''],
            ['label' => 'Báo cáo đã duyệt', 'value' => (int) ($reportCounts[TechnicalDailyReport::STATUS_APPROVED] ?? 0), 'icon' => 'bi-check2-circle', 'tone' => 'tw-stat--ok'],
            ['label' => 'Báo cáo yêu cầu sửa', 'value' => $revision, 'icon' => 'bi-arrow-counterclockwise', 'tone' => $revision > 0 ? 'tw-stat--danger' : ''],
            ['label' => 'Công việc hoàn thành', 'value' => (int) $summary['done_items'], 'icon' => 'bi-clipboard-check', 'tone' => 'tw-stat--ok'],
            ['label' => 'Công việc chưa hoàn thành', 'value' => $notDone, 'icon' => 'bi-clipboard-x', 'tone' => $notDone > 0 ? 'tw-stat--warn' : ''],
            ['label' => 'Công việc phát sinh', 'value' => (int) $summary['unplanned_items'], 'icon' => 'bi-plus-circle', 'tone' => ''],
            ['label' => 'Tổng giờ thực tế', 'value' => $this->plans->minutesLabel((int) $summary['actual_minutes']), 'icon' => 'bi-clock-history', 'tone' => '', 'is_text' => true],
        ];
    }

    /**
     * Bộ lọc của tab Tổng hợp tuần.
     *
     * `user_id` KHÔNG BAO GIỜ tin tham số URL: người không có quyền quản lý bị
     * ép về đúng id của chính mình.
     *
     * @param  \App\Models\User  $user
     * @return array<string, mixed>
     */
    private function weeklyFilters(Request $request, $user, bool $canManage): array
    {
        $filters = [];

        $requested = $canManage && $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $scoped = $this->access->visibleUserId($user, $requested);

        if ($scoped !== null) {
            $filters['user_id'] = $scoped;
        }

        if ($request->filled('site_id')) {
            $filters['site_id'] = (int) $request->query('site_id');
        }

        /*
         * `work_status` = trạng thái CÔNG VIỆC trong kế hoạch. Cố ý KHÔNG dùng
         * lại tên `status` vì ở tab "Báo cáo ngày" tên đó đã mang nghĩa trạng
         * thái DUYỆT báo cáo — hai bộ giá trị khác hẳn nhau.
         */
        if ($request->filled('work_status')
            && array_key_exists((string) $request->query('work_status'), TechnicalPlanItem::STATUS_LABELS)) {
            $filters['status'] = (string) $request->query('work_status');
        }

        return $filters;
    }

    /**
     * Công trình xuất hiện trong tuần — gộp dòng kế hoạch và báo cáo ngày.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function weeklySiteOptions(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $companyId = $this->feed->companyId();

        $fromPlans = DB::table('technical_plan_items')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotNull('site_id')
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
            ->select('site_id as id')
            ->selectRaw('MAX(site_name) as name')
            ->groupBy('site_id');

        $fromReports = DB::table('technical_daily_reports')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotNull('site_id')
            ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->select('site_id as id')
            ->selectRaw('MAX(site_name) as name')
            ->groupBy('site_id');

        return collect(
            DB::query()
                ->fromSub($fromPlans->unionAll($fromReports), 's')
                ->select('id')
                ->selectRaw('MAX(name) as name')
                ->groupBy('id')
                ->orderBy('name')
                ->limit(200)
                ->get(),
        );
    }

    /**
     * Form tạo báo cáo.
     *
     * Ba lối vào, KHÔNG lối nào bắt buộc phải có kế hoạch trước:
     *   - theo dòng kế hoạch  : `?plan_item_id=`
     *   - theo đầu việc nguồn : `?source_type=&source_id=`
     *   - việc phát sinh      : `?mode=phat-sinh` (hoặc `?is_unplanned=1`)
     */
    public function create(Request $request): View
    {
        $user = $this->authorizeModule($request);

        /* "Báo cáo việc phát sinh" — URL thân thiện, giữ nguyên tên route. */
        $unplannedMode = $request->query('mode') === 'phat-sinh' || $request->boolean('is_unplanned');

        $sourceType = (string) $request->query('source_type', '');
        $sourceId = (int) $request->query('source_id', 0);
        $item = null;

        if ($sourceType !== '' && $sourceId > 0) {
            $item = $this->feed->findItem($sourceType, $sourceId, (int) $user->id);
            abort_unless($item !== null, 403, 'Đầu việc này không được giao cho bạn.');
        }

        $reportDate = $this->resolveRequestedDate($request);
        $planItemId = (int) $request->query('plan_item_id', 0);
        $planItem = $planItemId > 0 ? $this->findOwnPlanItem($planItemId, (int) $user->id) : null;

        if ($planItem !== null) {
            $reportDate = Carbon::parse($planItem->plan_date);
        }

        return view('technical.daily-reports.form', [
            'report' => null,
            'item' => $item,
            'planItem' => $planItem,
            'planItems' => $this->planItemsForDate((int) $user->id, $reportDate),
            'myItems' => $this->myOpenItems((int) $user->id),
            'unplannedMode' => $unplannedMode,
            'reportDate' => $reportDate,
            'maxFileKb' => self::MAX_FILE_KB,
            'maxFiles' => self::MAX_FILES,
            'allowedMimes' => self::ALLOWED_MIMES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->normaliseWorkItemKey($request);

        /*
         * Ba cách hợp lệ để mở một báo cáo ngày:
         *  (1) Theo DÒNG KẾ HOẠCH của chính mình  — `plan_item_id`.
         *  (2) Theo ĐẦU VIỆC NGUỒN được giao       — `source_type` + `source_id`.
         *  (3) VIỆC PHÁT SINH ngoài kế hoạch       — `is_unplanned` + lý do.
         *
         * Mọi trường hợp đều xác thực lại ở phía server; không tin form.
         */
        $planItem = $this->resolvePlanItem($request, (int) $user->id);

        /*
         * Không còn ràng buộc "phải có kế hoạch mới được báo cáo": khi không
         * gắn dòng kế hoạch và cũng không chọn đầu việc nguồn nào, báo cáo được
         * hiểu là VIỆC PHÁT SINH — và khi đó lý do phát sinh là bắt buộc.
         */
        $unplanned = $request->boolean('is_unplanned')
            || ($planItem === null && trim((string) $request->input('source_type', '')) === '');

        if ($planItem !== null) {
            $unplanned = false; // đã có trong kế hoạch thì không phải việc phát sinh
        }

        $data = $this->validatePayload($request, withSource: $planItem === null && ! $unplanned);
        $extra = $this->validatePlanExtras($request, $unplanned);

        $link = $this->resolveReportLink($planItem, $data, $unplanned, $user);

        $files = $this->validatedFiles($request);
        $submit = $request->boolean('submit');

        $report = DB::transaction(function () use ($data, $extra, $link, $user, $files, $submit, $unplanned, $planItem): TechnicalDailyReport {
            $report = TechnicalDailyReport::create([
                'company_id' => $this->feed->companyId(),
                'user_id' => $user->id,
                'user_name' => $user->name,
                'report_date' => $data['report_date'],
                'source_type' => $link['source_type'],
                'source_id' => $link['source_id'],
                'plan_item_id' => $planItem?->id,
                'week_plan_id' => $planItem?->week_plan_id,
                'is_unplanned' => $unplanned,
                'unplanned_reason' => $unplanned ? $extra['unplanned_reason'] : null,
                'site_id' => $link['site_id'],
                'site_name' => $link['site_name'],
                'work_title' => $link['work_title'],
                'content' => $data['content'],
                'result_achieved' => $extra['result_achieved'],
                'not_done_reason' => $extra['not_done_reason'],
                'progress_percent' => $data['progress_percent'],
                'work_hours' => $data['work_hours'],
                'materials_note' => $data['materials_note'],
                'issues_note' => $data['issues_note'],
                'next_plan' => $data['next_plan'],
                'status' => $submit ? TechnicalDailyReport::STATUS_SUBMITTED : TechnicalDailyReport::STATUS_DRAFT,
                'submitted_at' => $submit ? now() : null,
            ]);

            $this->storeFiles($report, $files, (int) $user->id);

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_CREATE,
                null,
                $report->status,
            );

            if ($submit) {
                TechnicalDailyReportLogger::log(
                    $report,
                    TechnicalDailyReportHistory::ACTION_SUBMIT,
                    TechnicalDailyReport::STATUS_DRAFT,
                    TechnicalDailyReport::STATUS_SUBMITTED,
                );
            }

            return $report;
        });

        return redirect()
            ->route('technical.daily-reports.show', $report)
            ->with('success', $submit ? 'Đã gửi báo cáo, đang chờ duyệt.' : 'Đã lưu nháp báo cáo.');
    }

    public function show(Request $request, TechnicalDailyReport $report): View
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);

        $report->load(['user:id,name', 'approver:id,name', 'files', 'histories.user:id,name']);

        /*
         * Nghiệp vụ mới: Admin / Giám đốc KHÔNG duyệt từng báo cáo — giao diện
         * mặc định của họ là chế độ CHỈ XEM. Luồng duyệt ở backend được giữ
         * nguyên (route + quyền không đổi), chỉ ẩn nút ở màn hình của Admin.
         */
        $readOnlyForAdmin = (bool) $user->isAdmin();

        return view('technical.daily-reports.show', [
            'report' => $report,
            'canEdit' => $this->canEdit($report, $user),
            'canReview' => $this->canReview($report, $user) && ! $readOnlyForAdmin,
            'canReopen' => $this->canReopen($report, $user) && ! $readOnlyForAdmin,
        ]);
    }

    public function edit(Request $request, TechnicalDailyReport $report): View
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canEdit($report, $user), 403, 'Báo cáo đã gửi hoặc đã duyệt — không được sửa.');

        $report->load('files');

        return view('technical.daily-reports.form', [
            'report' => $report,
            'item' => null,
            'planItem' => $report->plan_item_id ? $this->findOwnPlanItem((int) $report->plan_item_id, (int) $user->id) : null,
            'planItems' => collect(),
            'myItems' => collect(),
            'unplannedMode' => (bool) $report->is_unplanned,
            'reportDate' => $report->report_date,
            'maxFileKb' => self::MAX_FILE_KB,
            'maxFiles' => self::MAX_FILES,
            'allowedMimes' => self::ALLOWED_MIMES,
        ]);
    }

    public function update(Request $request, TechnicalDailyReport $report): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canEdit($report, $user), 403, 'Báo cáo đã gửi hoặc đã duyệt — không được sửa.');

        $data = $this->validatePayload($request, withSource: false);
        $extra = $this->validatePlanExtras($request, (bool) $report->is_unplanned);
        $files = $this->validatedFiles($request);
        $submit = $request->boolean('submit');
        $statusBefore = (string) $report->status;

        DB::transaction(function () use ($report, $data, $extra, $files, $submit, $statusBefore, $user): void {
            $report->fill([
                'report_date' => $data['report_date'],
                'content' => $data['content'],
                'result_achieved' => $extra['result_achieved'],
                'not_done_reason' => $extra['not_done_reason'],
                'unplanned_reason' => $report->is_unplanned ? $extra['unplanned_reason'] : $report->unplanned_reason,
                'progress_percent' => $data['progress_percent'],
                'work_hours' => $data['work_hours'],
                'materials_note' => $data['materials_note'],
                'issues_note' => $data['issues_note'],
                'next_plan' => $data['next_plan'],
            ]);

            if ($submit) {
                $report->status = TechnicalDailyReport::STATUS_SUBMITTED;
                $report->submitted_at = now();
            }

            $report->save();

            $this->storeFiles($report, $files, (int) $user->id);

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_UPDATE,
                $statusBefore,
                (string) $report->status,
            );

            if ($submit) {
                TechnicalDailyReportLogger::log(
                    $report,
                    TechnicalDailyReportHistory::ACTION_SUBMIT,
                    $statusBefore,
                    TechnicalDailyReport::STATUS_SUBMITTED,
                );
            }
        });

        return redirect()
            ->route('technical.daily-reports.show', $report)
            ->with('success', $submit ? 'Đã gửi báo cáo, đang chờ duyệt.' : 'Đã cập nhật báo cáo.');
    }

    /** Gửi duyệt một báo cáo đang ở trạng thái nháp / yêu cầu sửa. */
    public function submit(Request $request, TechnicalDailyReport $report): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canEdit($report, $user), 403, 'Báo cáo này không ở trạng thái được gửi duyệt.');

        $statusBefore = (string) $report->status;

        DB::transaction(function () use ($report, $statusBefore): void {
            $report->status = TechnicalDailyReport::STATUS_SUBMITTED;
            $report->submitted_at = now();
            $report->save();

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_SUBMIT,
                $statusBefore,
                TechnicalDailyReport::STATUS_SUBMITTED,
            );
        });

        return back()->with('success', 'Đã gửi báo cáo, đang chờ duyệt.');
    }

    public function approve(Request $request, TechnicalDailyReport $report): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canReview($report, $user), 403, 'Bạn không có quyền duyệt báo cáo này.');

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $statusBefore = (string) $report->status;

        DB::transaction(function () use ($report, $data, $user, $statusBefore): void {
            $report->status = TechnicalDailyReport::STATUS_APPROVED;
            $report->approved_by = $user->id;
            $report->approved_at = now();
            $report->review_note = $data['note'] ?? null;
            $report->save();

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_APPROVE,
                $statusBefore,
                TechnicalDailyReport::STATUS_APPROVED,
                $data['note'] ?? null,
            );
        });

        return back()->with('success', 'Đã duyệt báo cáo.');
    }

    /** Trả lại báo cáo để sửa — BẮT BUỘC có ý kiến. */
    public function requestRevision(Request $request, TechnicalDailyReport $report): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canReview($report, $user), 403, 'Bạn không có quyền xử lý báo cáo này.');

        $data = $request->validate(
            ['note' => TechnicalDailyReportLogger::reasonRules()],
            TechnicalDailyReportLogger::reasonMessages(),
        );

        $statusBefore = (string) $report->status;

        DB::transaction(function () use ($report, $data, $statusBefore): void {
            $report->status = TechnicalDailyReport::STATUS_REVISION;
            $report->review_note = $data['note'];
            $report->approved_by = null;
            $report->approved_at = null;
            $report->save();

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_REQUEST_REVISION,
                $statusBefore,
                TechnicalDailyReport::STATUS_REVISION,
                $data['note'],
            );
        });

        return back()->with('success', 'Đã trả lại báo cáo kèm ý kiến.');
    }

    /**
     * Mở lại một báo cáo ĐÃ DUYỆT — chỉ Admin (Giám đốc) và BẮT BUỘC lý do.
     */
    public function reopen(Request $request, TechnicalDailyReport $report): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless($this->canReopen($report, $user), 403, 'Chỉ Admin / Giám đốc được mở lại báo cáo đã duyệt.');

        $data = $request->validate(
            ['note' => TechnicalDailyReportLogger::reasonRules()],
            TechnicalDailyReportLogger::reasonMessages(),
        );

        $statusBefore = (string) $report->status;

        DB::transaction(function () use ($report, $data, $statusBefore): void {
            $report->status = TechnicalDailyReport::STATUS_REVISION;
            $report->approved_by = null;
            $report->approved_at = null;
            $report->review_note = $data['note'];
            $report->save();

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_REOPEN,
                $statusBefore,
                TechnicalDailyReport::STATUS_REVISION,
                $data['note'],
            );
        });

        return back()->with('success', 'Đã mở lại báo cáo để chỉnh sửa.');
    }

    /** Tải file minh chứng — luôn đi qua kiểm tra quyền, không có URL công khai. */
    public function downloadFile(Request $request, TechnicalDailyReport $report, TechnicalDailyReportFile $file): StreamedResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless((int) $file->report_id === (int) $report->id, 404);

        $disk = Storage::disk($file->disk ?: self::DISK);
        abort_unless($disk->exists($file->path), 404, 'File không còn tồn tại trên hệ thống.');

        return $disk->download($file->path, $file->original_name);
    }

    /** Xoá file minh chứng — chỉ chủ báo cáo, khi báo cáo còn sửa được. */
    public function destroyFile(Request $request, TechnicalDailyReport $report, TechnicalDailyReportFile $file): RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $this->authorizeView($report, $user);
        abort_unless((int) $file->report_id === (int) $report->id, 404);
        abort_unless($this->canEdit($report, $user), 403, 'Báo cáo đã gửi hoặc đã duyệt — không được xoá file.');

        DB::transaction(function () use ($report, $file): void {
            $disk = Storage::disk($file->disk ?: self::DISK);

            if ($disk->exists($file->path)) {
                $disk->delete($file->path);
            }

            $file->delete();

            TechnicalDailyReportLogger::log(
                $report,
                TechnicalDailyReportHistory::ACTION_UPDATE,
                (string) $report->status,
                (string) $report->status,
                'Xoá file minh chứng: '.$file->original_name,
            );
        });

        return back()->with('success', 'Đã xoá file minh chứng.');
    }

    /*
    |--------------------------------------------------------------------------
    | Quyền + validate
    |--------------------------------------------------------------------------
    */

    /** @return \App\Models\User */
    private function authorizeModule(Request $request)
    {
        $user = $request->user();
        abort_unless($this->access->canUseModule($user), 403, 'Bạn không thuộc phạm vi module Kỹ thuật.');

        return $user;
    }

    /**
     * Được XEM báo cáo: chủ báo cáo, hoặc người có quyền quản lý — và luôn
     * phải cùng công ty đang hoạt động. Không thể xem báo cáo người khác bằng
     * cách đổi ID trên URL.
     */
    private function authorizeView(TechnicalDailyReport $report, $user): void
    {
        abort_unless(
            (int) $report->company_id === $this->feed->companyId(),
            404,
            'Báo cáo không thuộc công ty đang làm việc.',
        );

        if ((int) $report->user_id === (int) $user->id) {
            return;
        }

        abort_unless($this->access->canManage($user), 403, 'Bạn không có quyền xem báo cáo của người khác.');
    }

    /** Chỉ CHÍNH CHỦ được sửa, và chỉ khi báo cáo ở nháp hoặc bị yêu cầu sửa. */
    private function canEdit(TechnicalDailyReport $report, $user): bool
    {
        return (int) $report->user_id === (int) $user->id
            && $report->isEditableByOwner();
    }

    /**
     * Được duyệt / trả lại: người có quyền quản lý.
     *
     * Giả định nghiệp vụ (theo đúng tinh thần P0): người duyệt KHÔNG được tự
     * duyệt báo cáo do chính mình viết, trừ Admin / Giám đốc.
     */
    private function canReview(TechnicalDailyReport $report, $user): bool
    {
        if (! $this->access->canManage($user)) {
            return false;
        }

        if ($report->status !== TechnicalDailyReport::STATUS_SUBMITTED) {
            return false;
        }

        if ((int) $report->user_id === (int) $user->id && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    /** Mở lại báo cáo đã duyệt: chỉ Admin / Giám đốc. */
    private function canReopen(TechnicalDailyReport $report, $user): bool
    {
        return $report->status === TechnicalDailyReport::STATUS_APPROVED && $user->isAdmin();
    }

    /**
     * Ô chọn đầu việc gửi lên một khoá gộp "source_type|source_id".
     *
     * Trang có JS tách sẵn thành hai input ẩn; hàm này tách lại ở phía server
     * để form vẫn hoạt động đúng khi trình duyệt tắt JavaScript — và để không
     * bao giờ tin vào giá trị do client tự đặt mà bỏ qua bước xác thực phân
     * công ở `store()`.
     */
    private function normaliseWorkItemKey(Request $request): void
    {
        $key = trim((string) $request->input('work_item_key', ''));

        // Chỉ điền khi client chưa gửi source_type (tức là JS không chạy).
        if ($key === '' || trim((string) $request->input('source_type', '')) !== '') {
            return;
        }

        $parts = explode('|', $key, 2);

        if (count($parts) === 2) {
            $request->merge([
                'source_type' => $parts[0],
                'source_id' => $parts[1],
            ]);
        }
    }

    /**
     * Validate nội dung báo cáo.
     *
     * Quy tắc ngày an toàn: không cho báo cáo cho ngày TƯƠNG LAI (báo cáo là
     * ghi nhận việc đã làm) và không lùi quá 60 ngày để tránh nhập sai năm.
     *
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $withSource = true): array
    {
        $rules = [
            'report_date' => [
                'required', 'date',
                'before_or_equal:'.Carbon::today()->toDateString(),
                'after_or_equal:'.Carbon::today()->subDays(60)->toDateString(),
            ],
            'content' => ['required', 'string', 'min:5', 'max:20000'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'materials_note' => ['nullable', 'string', 'max:5000'],
            'issues_note' => ['nullable', 'string', 'max:5000'],
            'next_plan' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withSource) {
            $rules['source_type'] = ['required', 'string', 'in:'.implode(',', TechnicalDailyReport::SOURCE_TYPES)];
            $rules['source_id'] = ['required', 'integer', 'min:1'];
        }

        $messages = [
            'report_date.before_or_equal' => 'Không thể báo cáo cho ngày trong tương lai.',
            'report_date.after_or_equal' => 'Ngày báo cáo quá xa trong quá khứ (tối đa 60 ngày).',
            'content.required' => 'Vui lòng mô tả nội dung đã thực hiện.',
            'content.min' => 'Nội dung đã thực hiện quá ngắn.',
            'progress_percent.max' => 'Tỷ lệ hoàn thành chỉ nhận giá trị 0–100.',
        ];

        $data = $request->validate($rules, $messages);

        return [
            'report_date' => $data['report_date'],
            'content' => $data['content'],
            'progress_percent' => (int) $data['progress_percent'],
            'work_hours' => isset($data['work_hours']) && $data['work_hours'] !== null
                ? (float) $data['work_hours']
                : null,
            'materials_note' => $data['materials_note'] ?? null,
            'issues_note' => $data['issues_note'] ?? null,
            'next_plan' => $data['next_plan'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
        ];
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function validatedFiles(Request $request): array
    {
        if (! $request->hasFile('files')) {
            return [];
        }

        $request->validate([
            'files' => ['array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimes:'.self::ALLOWED_MIMES, 'max:'.self::MAX_FILE_KB],
        ], [
            'files.max' => 'Tối đa '.self::MAX_FILES.' file cho mỗi lần tải lên.',
            'files.*.mimes' => 'Chỉ chấp nhận ảnh (jpg, png, webp, gif), PDF, Word hoặc Excel.',
            'files.*.max' => 'Mỗi file tối đa '.(int) (self::MAX_FILE_KB / 1024).' MB.',
        ]);

        return array_values(array_filter((array) $request->file('files')));
    }

    /**
     * Lưu file lên disk private với TÊN NGẪU NHIÊN; tên gốc giữ trong DB.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeFiles(TechnicalDailyReport $report, array $files, int $uploaderId): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $safeName = Str::uuid()->toString().'.'.preg_replace('/[^a-z0-9]/', '', $extension);
            $path = $file->storeAs(self::STORAGE_FOLDER.'/'.$report->id, $safeName, self::DISK);

            TechnicalDailyReportFile::create([
                'report_id' => $report->id,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $uploaderId,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Liên kết với kế hoạch tuần (giai đoạn 2)
    |--------------------------------------------------------------------------
    */

    /**
     * Dòng kế hoạch gửi kèm form — LUÔN phải thuộc về chính người đang đăng
     * nhập và cùng công ty. Gửi id của người khác sẽ bị chặn 403.
     */
    private function resolvePlanItem(Request $request, int $userId): ?TechnicalPlanItem
    {
        $planItemId = (int) $request->input('plan_item_id', 0);

        if ($planItemId <= 0) {
            return null;
        }

        $item = $this->findOwnPlanItem($planItemId, $userId);
        abort_unless($item !== null, 403, 'Công việc này không nằm trong kế hoạch của bạn.');

        return $item;
    }

    private function findOwnPlanItem(int $planItemId, int $userId): ?TechnicalPlanItem
    {
        if (! Schema::hasTable('technical_plan_items')) {
            return null;
        }

        return TechnicalPlanItem::query()
            ->whereKey($planItemId)
            ->where('user_id', $userId)
            ->where('company_id', $this->feed->companyId())
            ->first();
    }

    /**
     * Xác định liên kết nguồn của báo cáo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveReportLink(?TechnicalPlanItem $planItem, array $data, bool $unplanned, $user): array
    {
        if ($planItem !== null) {
            return [
                'source_type' => (string) $planItem->source_type,
                'source_id' => $planItem->source_id,
                'site_id' => $planItem->site_id,
                'site_name' => $planItem->site_name,
                'work_title' => (string) $planItem->title,
            ];
        }

        if ($unplanned && empty($data['source_type'])) {
            return [
                'source_type' => TechnicalPlanItem::SOURCE_PERSONAL,
                'source_id' => null,
                'site_id' => null,
                'site_name' => null,
                'work_title' => 'Công việc phát sinh',
            ];
        }

        // Chống giả mạo: đầu việc phải thực sự được giao cho CHÍNH người đang
        // đăng nhập, kiểm tra ở phía server từ feed (không tin tham số form).
        $item = $this->feed->findItem((string) $data['source_type'], (int) $data['source_id'], (int) $user->id);
        abort_unless($item !== null, 403, 'Đầu việc này không được giao cho bạn.');

        return [
            'source_type' => $item->sourceType,
            'source_id' => $item->sourceId,
            'site_id' => $item->siteId,
            'site_name' => $item->siteName,
            'work_title' => $item->title,
        ];
    }

    /**
     * Các trường bổ sung của nghiệp vụ mới.
     *
     * Việc phát sinh ngoài kế hoạch BẮT BUỘC có lý do phát sinh — đây là điều
     * kiện để không "hợp thức hoá" việc phát sinh thành việc đã lập từ đầu tuần.
     *
     * @return array<string, ?string>
     */
    private function validatePlanExtras(Request $request, bool $unplanned): array
    {
        $rules = [
            'result_achieved' => ['nullable', 'string', 'max:5000'],
            'not_done_reason' => ['nullable', 'string', 'max:5000'],
        ];

        if ($unplanned) {
            $rules['unplanned_reason'] = ['required', 'string', 'min:5', 'max:2000'];
        }

        $data = $request->validate($rules, [
            'unplanned_reason.required' => 'Công việc phát sinh bắt buộc nhập lý do phát sinh.',
            'unplanned_reason.min' => 'Lý do phát sinh phải có ít nhất 5 ký tự.',
        ]);

        return [
            'result_achieved' => $data['result_achieved'] ?? null,
            'not_done_reason' => $data['not_done_reason'] ?? null,
            'unplanned_reason' => $data['unplanned_reason'] ?? null,
        ];
    }

    /**
     * Dòng kế hoạch của người dùng trong một ngày — để chọn khi viết báo cáo.
     *
     * @return \Illuminate\Support\Collection<int, TechnicalPlanItem>
     */
    private function planItemsForDate(int $userId, Carbon $date)
    {
        if (! Schema::hasTable('technical_plan_items')) {
            return collect();
        }

        return TechnicalPlanItem::query()
            ->where('user_id', $userId)
            ->where('company_id', $this->feed->companyId())
            ->whereDate('plan_date', $date->toDateString())
            ->whereNotIn('status', [TechnicalPlanItem::STATUS_CANCELLED])
            ->orderByRaw("FIELD(day_part, 'morning', 'afternoon', 'full_day', 'custom')")
            ->orderBy('id')
            ->get();
    }

    private function resolveRequestedDate(Request $request): Carbon
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

    /**
     * Các đầu việc còn mở của chính người dùng — để chọn nhanh khi tạo báo cáo.
     *
     * @return \Illuminate\Support\Collection<int, TechnicalWorkItem>
     */
    private function myOpenItems(int $userId)
    {
        return $this->feed->paginate([
            'user_id' => $userId,
            'filter' => TechnicalWorkFeedService::FILTER_ALL,
            'status_group' => null,
        ], 50)->getCollection()
            ->filter(fn (TechnicalWorkItem $item): bool => $item->isActive())
            ->values();
    }
}
