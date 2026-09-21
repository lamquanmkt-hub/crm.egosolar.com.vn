<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Technical\TechnicalDailyReport;
use App\Services\Technical\TechnicalAccess;
use App\Services\Technical\TechnicalWorkFeedService;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Bàn làm việc Kỹ thuật (giai đoạn 1).
 *
 * Toàn bộ 4 màn hình dưới đây đọc CHUNG một nguồn duy nhất —
 * `TechnicalWorkFeedService` — nên không tồn tại bản sao dữ liệu nào giữa
 * Tổng quan / Công việc của tôi / Lịch công việc / Quản lý kỹ thuật.
 *
 * Module này chỉ ĐỌC dữ liệu Công trình. Không ghi, không đổi workflow.
 */
class TechnicalWorkboardController extends Controller
{
    public function __construct(
        private readonly TechnicalWorkFeedService $feed,
        private readonly TechnicalAccess $access,
    ) {}

    /**
     * Trang gốc `/ky-thuat` — tên route `ky-thuat.tong-quan` giữ nguyên.
     *
     * Giai đoạn 2 điều hướng theo vai trò (nghiệp vụ mới):
     *   - Admin / Giám đốc  → Dashboard kết quả (chế độ CHỈ XEM).
     *   - Trưởng phòng      → Tổng quan phòng (trang vận hành của họ).
     *   - Nhân viên kỹ thuật→ "Tổng quan của tôi" (chính trang này).
     *
     * Không xoá màn hình nào; chỉ đổi điểm đến mặc định.
     */
    public function overview(Request $request): View|RedirectResponse
    {
        $user = $this->authorizeModule($request);
        $canManage = $this->access->canManage($user);

        if ($user->isAdmin() && Route::has('technical.dashboard')) {
            return redirect()->route('technical.dashboard');
        }

        if ($canManage && Route::has('technical.manager.overview')) {
            return redirect()->route('technical.manager.overview');
        }

        $filters = $this->resolveFilters($request, $user, $canManage);

        $summary = $this->feed->summary($filters);
        $items = $this->feed->paginate($filters, 12);

        return view('technical.workboard.overview', [
            'summary' => $summary,
            'items' => $items,
            'filters' => $filters,
            'canManage' => $canManage,
            'filterOptions' => TechnicalWorkFeedService::FILTER_LABELS,
            'teamMembers' => $canManage ? $this->feed->assignedUsers() : collect(),
            'todayLabel' => Carbon::today()->format('d/m/Y'),
        ]);
    }

    /** Trang "Công việc của tôi". */
    public function myWork(Request $request): View
    {
        $user = $this->authorizeModule($request);
        $canManage = $this->access->canManage($user);
        $filters = $this->resolveFilters($request, $user, $canManage);

        return view('technical.workboard.my-work', [
            'items' => $this->feed->paginate($filters, 20),
            'filters' => $filters,
            'canManage' => $canManage,
            'filterOptions' => TechnicalWorkFeedService::FILTER_LABELS,
            'sourceOptions' => TechnicalWorkItem::SOURCE_LABELS,
            'statusOptions' => TechnicalWorkItem::GROUP_LABELS,
            'teamMembers' => $canManage ? $this->feed->assignedUsers() : collect(),
            'sites' => $this->siteOptions($filters),
        ]);
    }

    /** Lịch công việc tuần / tháng — cùng feed, không bản sao. */
    public function calendar(Request $request): View
    {
        $user = $this->authorizeModule($request);
        $canManage = $this->access->canManage($user);
        $filters = $this->resolveFilters($request, $user, $canManage);
        $filters['filter'] = TechnicalWorkFeedService::FILTER_ALL;

        $mode = $request->query('mode') === 'month' ? 'month' : 'week';
        $anchor = $this->resolveAnchorDate($request);

        [$from, $to] = $mode === 'month'
            ? [$anchor->copy()->startOfMonth()->startOfWeek(), $anchor->copy()->endOfMonth()->endOfWeek()]
            : [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()];

        $items = $this->feed->betweenDates($from, $to, $filters);

        $byDate = $items->groupBy(
            fn (TechnicalWorkItem $item): string => $item->plannedDate?->toDateString() ?? ''
        );

        $days = [];
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $cursor->copy(),
                'key' => $key,
                'items' => $byDate->get($key, collect()),
                'is_today' => $cursor->isToday(),
                'in_focus' => $mode === 'month' ? $cursor->month === $anchor->month : true,
            ];
        }

        return view('technical.workboard.calendar', [
            'mode' => $mode,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'total' => $items->count(),
            'filters' => $filters,
            'canManage' => $canManage,
            'teamMembers' => $canManage ? $this->feed->assignedUsers() : collect(),
            'sourceOptions' => TechnicalWorkItem::SOURCE_LABELS,
            'prev' => $this->shiftedAnchor($anchor, $mode, -1),
            'next' => $this->shiftedAnchor($anchor, $mode, 1),
        ]);
    }

    /**
     * "Quản lý kỹ thuật" — chỉ dành cho người có quyền quản lý:
     * xem khối lượng theo nhân sự và hàng đợi báo cáo chờ duyệt.
     */
    public function management(Request $request): View
    {
        $user = $this->authorizeModule($request);
        abort_unless($this->access->canManage($user), 403, 'Chỉ trưởng kỹ thuật hoặc Ban giám đốc được xem trang này.');

        $filters = $this->resolveFilters($request, $user, true);
        $filters['filter'] = TechnicalWorkFeedService::FILTER_ALL;

        $rows = $this->feed->query($filters)
            ->selectRaw('feed.assigned_user_id as user_id')
            ->selectRaw('feed.assigned_user_name as user_name')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw("SUM(CASE WHEN feed.status_group = 'in_progress' THEN 1 ELSE 0 END) as in_progress_items")
            ->selectRaw("SUM(CASE WHEN feed.status_group = 'done' THEN 1 ELSE 0 END) as done_items")
            ->selectRaw(
                "SUM(CASE WHEN feed.due_at IS NOT NULL AND feed.due_at < ? AND feed.status_group IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as overdue_items",
                [Carbon::now()->toDateTimeString()]
            )
            ->selectRaw('COUNT(DISTINCT feed.site_id) as site_count')
            ->whereNotNull('feed.assigned_user_id')
            ->groupBy('feed.assigned_user_id', 'feed.assigned_user_name')
            ->orderByDesc('overdue_items')
            ->orderByDesc('total_items')
            ->get();

        $pendingReports = $this->pendingReportQueue();

        return view('technical.workboard.management', [
            'rows' => $rows,
            'pendingReports' => $pendingReports,
            'filters' => $filters,
            'summary' => $this->feed->summary($filters),
        ]);
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

    /**
     * Phạm vi dữ liệu:
     * - Kỹ thuật viên thuần: LUÔN bị ép về chính mình, không thể đổi bằng URL.
     * - Trưởng kỹ thuật / Admin (Giám đốc): xem toàn bộ trong company scope,
     *   có thể lọc theo một nhân sự cụ thể.
     *
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request, $user, bool $canManage): array
    {
        $requested = $request->filled('user_id') ? (int) $request->query('user_id') : null;

        $filters = [
            'user_id' => $this->access->visibleUserId($user, $canManage ? $requested : null),
            'filter' => $this->sanitizeFilter((string) $request->query('filter', TechnicalWorkFeedService::FILTER_ALL)),
        ];

        if ($request->filled('source_type')
            && array_key_exists((string) $request->query('source_type'), TechnicalWorkItem::SOURCE_LABELS)) {
            $filters['source_type'] = (string) $request->query('source_type');
        }

        if ($request->filled('status_group')
            && array_key_exists((string) $request->query('status_group'), TechnicalWorkItem::GROUP_LABELS)) {
            $filters['status_group'] = (string) $request->query('status_group');
        }

        if ($request->filled('site_id')) {
            $filters['site_id'] = (int) $request->query('site_id');
        }

        if ($request->filled('date') && $this->isValidDate((string) $request->query('date'))) {
            $filters['date'] = (string) $request->query('date');
        }

        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $filters['keyword'] = mb_substr($keyword, 0, 100);
        }

        return $filters;
    }

    private function sanitizeFilter(string $filter): string
    {
        return array_key_exists($filter, TechnicalWorkFeedService::FILTER_LABELS)
            ? $filter
            : TechnicalWorkFeedService::FILTER_ALL;
    }

    private function isValidDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveAnchorDate(Request $request): Carbon
    {
        $raw = (string) $request->query('date', '');

        if ($raw !== '' && $this->isValidDate($raw)) {
            return Carbon::parse($raw)->startOfDay();
        }

        return Carbon::today();
    }

    private function shiftedAnchor(Carbon $anchor, string $mode, int $direction): string
    {
        $shifted = $mode === 'month'
            ? $anchor->copy()->addMonths($direction)
            : $anchor->copy()->addWeeks($direction);

        return $shifted->toDateString();
    }

    /** Hàng đợi báo cáo chờ duyệt (chỉ hiển thị cho người có quyền quản lý). */
    private function pendingReportQueue()
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return collect();
        }

        return TechnicalDailyReport::query()
            ->with('user:id,name')
            ->where('company_id', $this->feed->companyId())
            ->where('status', TechnicalDailyReport::STATUS_SUBMITTED)
            ->orderBy('report_date')
            ->orderBy('id')
            ->limit(30)
            ->get();
    }

    /**
     * Danh sách công trình để lọc — lấy thẳng từ feed nên luôn khớp phạm vi
     * quyền và company scope, không cần truy vấn bảng sites riêng.
     *
     * @param  array<string, mixed>  $filters
     */
    private function siteOptions(array $filters)
    {
        return $this->feed->query([
            'filter' => TechnicalWorkFeedService::FILTER_ALL,
            'user_id' => $filters['user_id'] ?? null,
        ])
            ->select('feed.site_id as id', 'feed.site_name as name')
            ->whereNotNull('feed.site_id')
            ->groupBy('feed.site_id', 'feed.site_name')
            ->orderBy('feed.site_name')
            ->limit(300)
            ->get();
    }
}
