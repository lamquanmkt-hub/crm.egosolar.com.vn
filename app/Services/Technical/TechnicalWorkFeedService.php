<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Support\EgoCompanyLock;
use App\Support\Synced\EgoCompanyScope;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * TỔNG HỢP (đọc-gộp) công việc kỹ thuật từ các nguồn THẬT của hệ thống.
 *
 * Nguyên nhân gốc của "dashboard Kỹ thuật toàn số 0": trang cũ chỉ đọc bảng
 * `technical_work_records`, mà bảng đó chỉ được ghi bởi đúng form
 * /ky-thuat/ke-hoach. Mọi công việc kỹ thuật thật lại nằm ở 3 nơi khác.
 * Service này đọc thẳng 3 nơi đó, KHÔNG sao chép dữ liệu sang bảng mới.
 *
 * Thiết kế truy vấn:
 * - Mỗi nguồn là một SELECT trả về CÙNG một bộ cột đã chuẩn hoá.
 * - Ba SELECT được nối bằng UNION ALL rồi bọc trong subquery, nên việc lọc,
 *   sắp xếp, ĐẾM và PHÂN TRANG đều chạy ở tầng database (không kéo toàn bộ
 *   collection về PHP rồi mới đếm).
 * - Trạng thái gốc mỗi nguồn được quy về 4 nhóm chung bằng CASE trong SQL để
 *   có thể aggregate.
 * - Tên công trình / tên người được giao lấy bằng JOIN ngay trong subquery,
 *   nên không có N+1: một trang danh sách = 1 truy vấn dữ liệu + 1 truy vấn
 *   tra trạng thái báo cáo ngày.
 *
 * Khử trùng: khoá là (source_type, source_id, assigned_user_id). Trong phạm vi
 * schema hiện tại KHÔNG tồn tại cột liên kết chéo giữa 3 nguồn (đã kiểm chứng:
 * `tasks` không có cột trỏ tới solar_maintenance_schedules và ngược lại), nên
 * một đầu việc chỉ xuất hiện đúng một lần. Khoá vẫn được áp dụng lần cuối ở
 * PHP để phòng trường hợp dữ liệu nguồn có bản ghi trùng.
 */
class TechnicalWorkFeedService
{
    /**
     * Loại lịch bảo trì được tính là "bảo hành".
     *
     * Lấy đúng từ `SolarMaintenanceSchedule::TYPES` đang chạy: `warranty_inverter`
     * (bảo hành inverter) và `incident` (xử lý sự cố). Các loại còn lại
     * (periodic, panel_check, cleaning, monitoring, handover) là bảo trì/vận hành.
     */
    public const WARRANTY_TYPES = ['warranty_inverter', 'incident'];

    /** Trạng thái lịch bảo trì coi như đã kết thúc. */
    public const MAINTENANCE_CLOSED_STATUSES = ['completed', 'cancelled'];

    public const FILTER_ALL = 'all';

    public const FILTER_TODAY = 'today';

    public const FILTER_WEEK = 'week';

    public const FILTER_OVERDUE = 'overdue';

    public const FILTER_UNREPORTED = 'unreported';

    public const FILTER_DONE = 'done';

    public const FILTER_LABELS = [
        self::FILTER_ALL => 'Tất cả',
        self::FILTER_TODAY => 'Hôm nay',
        self::FILTER_WEEK => 'Tuần này',
        self::FILTER_OVERDUE => 'Quá hạn',
        self::FILTER_UNREPORTED => 'Chưa báo cáo',
        self::FILTER_DONE => 'Đã hoàn thành',
    ];

    /** Cột trả về của mọi nhánh UNION — thứ tự phải giống hệt nhau. */
    private const UNION_COLUMNS = [
        'source_type', 'source_id', 'site_id', 'site_name', 'site_code',
        'title', 'description', 'assigned_user_id', 'assigned_user_name',
        'raw_status', 'status_group', 'progress', 'planned_date', 'due_at',
        'company_id', 'sort_at',
    ];

    /**
     * Công ty đang hoạt động.
     *
     * Giữ nguyên cơ chế hiện hành: ưu tiên session (`EgoCompanyScope`, được
     * `EgoCompanyContextMiddleware` đặt), nếu chưa có thì dùng công ty bị khoá
     * cứng của module Công trình (`EgoCompanyLock`) — tuyệt đối không bao giờ
     * để truy vấn chạy mà không có điều kiện company.
     */
    public function companyId(): int
    {
        $sessionCompany = EgoCompanyScope::currentId();

        return $sessionCompany > 0 ? $sessionCompany : EgoCompanyLock::id();
    }

    /**
     * Truy vấn gốc (đã UNION + lọc) cho phép tái sử dụng ở danh sách, lịch,
     * thẻ thống kê — tất cả dùng CHUNG một định nghĩa, không có bản sao.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): QueryBuilder
    {
        $companyId = $this->companyId();
        $branches = [];

        foreach ([
            $this->projectWorkflowBranch($companyId),
            $this->taskBranch($companyId),
            $this->maintenanceBranch($companyId),
        ] as $branch) {
            if ($branch !== null) {
                $branches[] = $branch;
            }
        }

        if ($branches === []) {
            // Không nuốt lỗi âm thầm: ghi cảnh báo để vận hành biết module
            // Kỹ thuật đang không có nguồn dữ liệu nào khả dụng.
            Log::warning('TechnicalWorkFeedService: không có nguồn công việc nào khả dụng.', [
                'company_id' => $companyId,
            ]);

            return DB::query()->fromSub($this->emptyBranch(), 'feed')->whereRaw('1 = 0');
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $query = DB::query()->fromSub($union, 'feed');

        return $this->applyFilters($query, $filters);
    }

    /**
     * Danh sách công việc có phân trang (phân trang chạy ở DB).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TechnicalWorkItem>
     */
    public function paginate(array $filters = [], int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = max(5, min(100, $perPage));

        $paginator = $this->query($filters)
            ->orderByRaw('CASE WHEN feed.status_group IN (?, ?) THEN 0 ELSE 1 END', [
                TechnicalWorkItem::GROUP_PENDING,
                TechnicalWorkItem::GROUP_IN_PROGRESS,
            ])
            ->orderByRaw('feed.due_at IS NULL')
            ->orderBy('feed.due_at')
            ->orderByDesc('feed.sort_at')
            ->paginate($perPage, ['*'], $pageName);

        $items = $this->hydrate(collect($paginator->items()));

        return new Paginator(
            $items->values()->all(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );
    }

    /**
     * Toàn bộ công việc trong một khoảng ngày (dùng cho lịch tuần/tháng).
     * Có trần cứng để một tháng dữ liệu lớn không thể làm sập trang.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TechnicalWorkItem>
     */
    public function betweenDates(Carbon $from, Carbon $to, array $filters = [], int $limit = 500): Collection
    {
        $rows = $this->query($filters)
            ->whereNotNull('feed.planned_date')
            ->whereBetween('feed.planned_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('feed.planned_date')
            ->orderByRaw('feed.due_at IS NULL')
            ->orderBy('feed.due_at')
            ->limit($limit)
            ->get();

        return $this->hydrate($rows);
    }

    /**
     * Toàn bộ số liệu thẻ thống kê trong MỘT truy vấn aggregate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toDateTimeString();
        $active = [TechnicalWorkItem::GROUP_PENDING, TechnicalWorkItem::GROUP_IN_PROGRESS];

        $base = $this->query(array_merge($filters, ['filter' => self::FILTER_ALL]));

        $row = $base
            ->selectRaw('COUNT(*) AS total_items')
            ->selectRaw(
                'SUM(CASE WHEN feed.planned_date = ? OR DATE(feed.due_at) = ? THEN 1 ELSE 0 END) AS today_items',
                [$today, $today]
            )
            ->selectRaw(
                'SUM(CASE WHEN feed.status_group = ? THEN 1 ELSE 0 END) AS in_progress_items',
                [TechnicalWorkItem::GROUP_IN_PROGRESS]
            )
            ->selectRaw(
                'SUM(CASE WHEN feed.due_at IS NOT NULL AND feed.due_at < ? AND feed.status_group IN (?, ?) THEN 1 ELSE 0 END) AS overdue_items',
                [$now, ...$active]
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN feed.status_group IN (?, ?) THEN feed.site_id END) AS active_sites',
                $active
            )
            ->first();

        return [
            'total_items' => (int) ($row->total_items ?? 0),
            'today_items' => (int) ($row->today_items ?? 0),
            'in_progress_items' => (int) ($row->in_progress_items ?? 0),
            'overdue_items' => (int) ($row->overdue_items ?? 0),
            'active_sites' => (int) ($row->active_sites ?? 0),
            'unreported_items' => $this->unreportedCount($filters),
            'pending_reports' => $this->pendingReportCount($filters),
            'open_warranties' => $this->openWarrantyCount($filters),
        ];
    }

    /**
     * "Báo cáo chưa nộp" = đầu việc CÒN PHẢI LÀM, đã tới ngày (planned_date
     * <= hôm nay hoặc đã quá hạn) mà người được giao CHƯA có báo cáo ngày hôm
     * nay ở trạng thái đã gửi / đã duyệt cho đúng đầu việc đó.
     *
     * @param  array<string, mixed>  $filters
     */
    public function unreportedCount(array $filters = []): int
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return 0;
        }

        return (int) $this->unreportedQuery($filters)->count();
    }

    /** @param array<string, mixed> $filters */
    private function unreportedQuery(array $filters): QueryBuilder
    {
        $today = Carbon::today()->toDateString();

        return $this->query(array_merge($filters, ['filter' => self::FILTER_UNREPORTED]))
            ->whereRaw('(feed.planned_date IS NULL OR feed.planned_date <= ?)', [$today]);
    }

    /**
     * "Báo cáo chờ duyệt" = báo cáo ngày đang ở trạng thái `submitted` trong
     * phạm vi người dùng được xem.
     *
     * @param  array<string, mixed>  $filters
     */
    public function pendingReportCount(array $filters = []): int
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return 0;
        }

        $query = TechnicalDailyReport::query()
            ->where('company_id', $this->companyId())
            ->where('status', TechnicalDailyReport::STATUS_SUBMITTED);

        $userId = $filters['user_id'] ?? null;

        if ($userId !== null) {
            $query->where('user_id', (int) $userId);
        }

        return (int) $query->count();
    }

    /**
     * "Bảo hành đang xử lý" = lịch bảo trì thuộc nhóm bảo hành/sự cố, chưa
     * hoàn thành và chưa huỷ. Khi người xem là kỹ thuật viên thuần thì chỉ
     * đếm các lịch được giao cho chính họ.
     *
     * @param  array<string, mixed>  $filters
     */
    public function openWarrantyCount(array $filters = []): int
    {
        if (! Schema::hasTable('solar_maintenance_schedules')) {
            return 0;
        }

        $companyId = $this->companyId();

        $query = DB::table('solar_maintenance_schedules as m')
            ->whereIn('m.type', self::WARRANTY_TYPES)
            ->whereNotIn('m.status', self::MAINTENANCE_CLOSED_STATUSES);

        if (Schema::hasColumn('solar_maintenance_schedules', 'deleted_at')) {
            $query->whereNull('m.deleted_at');
        }

        if (Schema::hasColumn('solar_maintenance_schedules', 'company_id')) {
            $query->where('m.company_id', $companyId);
        }

        $userId = $filters['user_id'] ?? null;

        if ($userId !== null && Schema::hasTable('solar_maintenance_assignees')) {
            $userId = (int) $userId;
            $query->where(function ($q) use ($userId): void {
                $q->whereExists(function ($sub) use ($userId): void {
                    $sub->selectRaw('1')
                        ->from('solar_maintenance_assignees as a')
                        ->whereColumn('a.maintenance_schedule_id', 'm.id')
                        ->where('a.user_id', $userId);
                })->orWhere('m.assigned_to', $userId);
            });
        }

        return (int) $query->count();
    }

    /**
     * Danh sách nhân sự kỹ thuật có việc trong phạm vi — dùng cho bộ lọc
     * "theo nhân sự" của người quản lý. Một truy vấn, không N+1.
     *
     * @return Collection<int, object>
     */
    public function assignedUsers(): Collection
    {
        return $this->query(['filter' => self::FILTER_ALL])
            ->select('feed.assigned_user_id as id', 'feed.assigned_user_name as name')
            ->whereNotNull('feed.assigned_user_id')
            ->groupBy('feed.assigned_user_id', 'feed.assigned_user_name')
            ->orderBy('feed.assigned_user_name')
            ->get();
    }

    /**
     * Tìm đúng một đầu việc theo nguồn — dùng khi tạo báo cáo ngày để xác
     * thực rằng người dùng thực sự được giao đầu việc đó.
     */
    public function findItem(string $sourceType, int $sourceId, ?int $userId = null): ?TechnicalWorkItem
    {
        $query = $this->query(['filter' => self::FILTER_ALL])
            ->where('feed.source_type', $sourceType)
            ->where('feed.source_id', $sourceId);

        if ($userId !== null) {
            $query->where('feed.assigned_user_id', $userId);
        }

        $row = $query->first();

        return $row ? $this->hydrate(collect([$row]))->first() : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Các nhánh nguồn
    |--------------------------------------------------------------------------
    */

    /** Nguồn 1 — Giao việc theo bước quy trình Công trình (chỉ ĐỌC). */
    private function projectWorkflowBranch(int $companyId): ?QueryBuilder
    {
        if (! $this->sourceReady('project_workflow', ['project_workflow_assignments', 'project_workflow_steps', 'sites'])) {
            return null;
        }

        $due = $this->firstAvailableColumn('project_workflow_steps', ['recommitted_due_at', 'due_at'], 'w');
        $plannedParts = array_filter([
            $this->column('project_workflow_assignments', 'started_at', 'a'),
            $this->column('project_workflow_steps', 'started_at', 'w'),
            'a.created_at',
        ]);

        $query = DB::table('project_workflow_assignments as a')
            ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
            ->join('sites as s', 's.id', '=', 'w.site_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('s.company_id', $companyId)
            ->selectRaw("'".TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW."' as source_type")
            ->selectRaw('a.id as source_id')
            ->selectRaw('w.site_id as site_id')
            ->selectRaw('s.name as site_name')
            ->selectRaw($this->column('sites', 'project_code', 's') ? 's.project_code as site_code' : 'NULL as site_code')
            ->selectRaw('w.step_code as title')
            ->selectRaw(($this->column('project_workflow_steps', 'requirement', 'w') ? 'w.requirement' : 'NULL').' as description')
            ->selectRaw('a.user_id as assigned_user_id')
            ->selectRaw('u.name as assigned_user_name')
            ->selectRaw('a.status as raw_status')
            ->selectRaw($this->statusCase('a.status', [
                TechnicalWorkItem::GROUP_DONE => ['approved'],
                TechnicalWorkItem::GROUP_CANCELLED => ['cancelled', 'rejected'],
                TechnicalWorkItem::GROUP_IN_PROGRESS => ['in_progress', 'submitted', 'revision'],
            ]).' as status_group')
            ->selectRaw(($this->column('project_workflow_assignments', 'progress_percent', 'a') ? 'COALESCE(a.progress_percent, 0)' : '0').' as progress')
            ->selectRaw('DATE(COALESCE('.implode(', ', $plannedParts).')) as planned_date')
            ->selectRaw(($due ?: 'NULL').' as due_at')
            ->selectRaw('s.company_id as company_id')
            ->selectRaw('COALESCE(a.updated_at, a.created_at) as sort_at');

        if ($this->column('project_workflow_assignments', 'is_active', 'a')) {
            $query->where('a.is_active', 1);
        }

        return $query;
    }

    /** Nguồn 2 — Task nội bộ có gắn công trình (chỉ ĐỌC). */
    private function taskBranch(int $companyId): ?QueryBuilder
    {
        if (! $this->sourceReady('task', ['tasks', 'sites'])) {
            return null;
        }

        if (! Schema::hasColumn('tasks', 'site_id')) {
            Log::warning('TechnicalWorkFeedService: bảng tasks chưa có cột site_id — bỏ qua nguồn Task nội bộ.');

            return null;
        }

        return DB::table('tasks as t')
            ->join('sites as s', 's.id', '=', 't.site_id')
            ->leftJoin('users as u', 'u.id', '=', 't.assignee_id')
            ->whereNotNull('t.site_id')
            ->where('s.company_id', $companyId)
            ->selectRaw("'".TechnicalWorkItem::SOURCE_TASK."' as source_type")
            ->selectRaw('t.id as source_id')
            ->selectRaw('t.site_id as site_id')
            ->selectRaw('s.name as site_name')
            ->selectRaw($this->column('sites', 'project_code', 's') ? 's.project_code as site_code' : 'NULL as site_code')
            ->selectRaw('t.title as title')
            ->selectRaw('t.description as description')
            ->selectRaw('t.assignee_id as assigned_user_id')
            ->selectRaw('u.name as assigned_user_name')
            ->selectRaw('t.status as raw_status')
            ->selectRaw($this->statusCase('t.status', [
                TechnicalWorkItem::GROUP_DONE => ['approved', 'completed', 'done'],
                TechnicalWorkItem::GROUP_CANCELLED => ['cancelled', 'rejected'],
                TechnicalWorkItem::GROUP_IN_PROGRESS => ['in_progress', 'submitted', 'revision'],
            ]).' as status_group')
            ->selectRaw(($this->column('tasks', 'progress_percent', 't') ? 'COALESCE(t.progress_percent, 0)' : '0').' as progress')
            ->selectRaw('DATE(COALESCE(t.due_at, t.created_at)) as planned_date')
            ->selectRaw('t.due_at as due_at')
            ->selectRaw('s.company_id as company_id')
            ->selectRaw('COALESCE(t.updated_at, t.created_at) as sort_at');
    }

    /**
     * Nguồn 3 — Lịch bảo trì / bảo hành (chỉ ĐỌC).
     *
     * Người được giao: ưu tiên bảng `solar_maintenance_assignees` (đã có UNIQUE
     * theo cặp lịch + người nên không sinh dòng trùng); lịch chưa có dòng phân
     * công nào thì rơi về cột `assigned_to` của chính lịch. Dùng COALESCE thay
     * vì UNION hai nhánh để một lịch không bị đếm hai lần.
     */
    private function maintenanceBranch(int $companyId): ?QueryBuilder
    {
        if (! $this->sourceReady('maintenance', ['solar_maintenance_schedules'])) {
            return null;
        }

        $hasAssignees = Schema::hasTable('solar_maintenance_assignees');
        $assignedExpr = $hasAssignees ? 'COALESCE(a.user_id, m.assigned_to)' : 'm.assigned_to';

        $query = DB::table('solar_maintenance_schedules as m')
            ->leftJoin('sites as s', 's.id', '=', 'm.site_id')
            ->selectRaw("'".TechnicalWorkItem::SOURCE_MAINTENANCE."' as source_type")
            ->selectRaw('m.id as source_id')
            ->selectRaw('m.site_id as site_id')
            ->selectRaw('COALESCE(s.name, m.site_name) as site_name')
            ->selectRaw($this->column('sites', 'project_code', 's') ? 's.project_code as site_code' : 'NULL as site_code')
            ->selectRaw('m.type as title')
            ->selectRaw('COALESCE('.($this->column('solar_maintenance_schedules', 'issue_note', 'm') ? 'm.issue_note, ' : '').'m.technical_note) as description')
            ->selectRaw($assignedExpr.' as assigned_user_id')
            ->selectRaw('u.name as assigned_user_name')
            ->selectRaw('m.status as raw_status')
            ->selectRaw($this->statusCase('m.status', [
                TechnicalWorkItem::GROUP_DONE => ['completed'],
                TechnicalWorkItem::GROUP_CANCELLED => ['cancelled', 'postponed'],
                TechnicalWorkItem::GROUP_IN_PROGRESS => [
                    'travelling', 'in_progress', 'waiting_material', 'waiting_submission',
                    'pending_approval', 'revision_requested', 'approved', 'waiting_customer',
                ],
            ]).' as status_group')
            ->selectRaw("CASE WHEN m.status = 'completed' THEN 100"
                ." WHEN m.status IN ('in_progress', 'waiting_material', 'waiting_submission', 'pending_approval') THEN 60"
                .' ELSE 0 END as progress')
            ->selectRaw('DATE(m.scheduled_date) as planned_date')
            ->selectRaw($this->maintenanceDueExpression().' as due_at')
            ->selectRaw('COALESCE(m.company_id, s.company_id) as company_id')
            ->selectRaw('COALESCE(m.updated_at, m.created_at) as sort_at')
            ->whereRaw('COALESCE(m.company_id, s.company_id) = ?', [$companyId]);

        if ($hasAssignees) {
            $query->leftJoin('solar_maintenance_assignees as a', 'a.maintenance_schedule_id', '=', 'm.id');
        }

        $query->leftJoin('users as u', function ($join) use ($assignedExpr): void {
            $join->on(DB::raw('u.id'), '=', DB::raw($assignedExpr));
        });

        if (Schema::hasColumn('solar_maintenance_schedules', 'deleted_at')) {
            $query->whereNull('m.deleted_at');
        }

        return $query;
    }

    /**
     * Hạn hoàn thành của lịch bảo trì: dùng `scheduled_end_at` nếu schema đã có
     * (migration technical_schedule_workspace), nếu chưa thì lấy cuối ngày của
     * `scheduled_date` để so sánh quá hạn không bị lệch múi giờ trong ngày.
     */
    private function maintenanceDueExpression(): string
    {
        if (Schema::hasColumn('solar_maintenance_schedules', 'scheduled_end_at')) {
            return "COALESCE(m.scheduled_end_at, TIMESTAMP(m.scheduled_date, '23:59:59'))";
        }

        return "TIMESTAMP(m.scheduled_date, '23:59:59')";
    }

    /** Nhánh rỗng an toàn để câu UNION luôn hợp lệ về mặt cú pháp. */
    private function emptyBranch(): QueryBuilder
    {
        $columns = array_map(
            static fn (string $column): string => 'NULL as '.$column,
            self::UNION_COLUMNS,
        );

        return DB::query()->selectRaw(implode(', ', $columns))->whereRaw('1 = 0');
    }

    /*
    |--------------------------------------------------------------------------
    | Lọc + chuyển đổi
    |--------------------------------------------------------------------------
    */

    /** @param array<string, mixed> $filters */
    private function applyFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        $userId = $filters['user_id'] ?? null;

        if ($userId !== null) {
            $query->where('feed.assigned_user_id', (int) $userId);
        }

        if (! empty($filters['site_id'])) {
            $query->where('feed.site_id', (int) $filters['site_id']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('feed.source_type', (string) $filters['source_type']);
        }

        if (! empty($filters['status_group'])) {
            $query->where('feed.status_group', (string) $filters['status_group']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('feed.planned_date', (string) $filters['date']);
        }

        if (! empty($filters['keyword'])) {
            $like = '%'.trim((string) $filters['keyword']).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('feed.title', 'like', $like)
                    ->orWhere('feed.site_name', 'like', $like)
                    ->orWhere('feed.description', 'like', $like);
            });
        }

        return $this->applyNamedFilter($query, (string) ($filters['filter'] ?? self::FILTER_ALL));
    }

    private function applyNamedFilter(QueryBuilder $query, string $filter): QueryBuilder
    {
        $today = Carbon::today();
        $active = [TechnicalWorkItem::GROUP_PENDING, TechnicalWorkItem::GROUP_IN_PROGRESS];

        return match ($filter) {
            self::FILTER_TODAY => $query->where(function ($q) use ($today): void {
                $q->whereDate('feed.planned_date', $today->toDateString())
                    ->orWhereRaw('DATE(feed.due_at) = ?', [$today->toDateString()]);
            }),
            self::FILTER_WEEK => $query->where(function ($q) use ($today): void {
                $start = $today->copy()->startOfWeek()->toDateString();
                $end = $today->copy()->endOfWeek()->toDateString();
                $q->whereBetween('feed.planned_date', [$start, $end])
                    ->orWhereRaw('DATE(feed.due_at) BETWEEN ? AND ?', [$start, $end]);
            }),
            self::FILTER_OVERDUE => $query
                ->whereNotNull('feed.due_at')
                ->where('feed.due_at', '<', Carbon::now()->toDateTimeString())
                ->whereIn('feed.status_group', $active),
            self::FILTER_DONE => $query->where('feed.status_group', TechnicalWorkItem::GROUP_DONE),
            self::FILTER_UNREPORTED => $this->applyUnreportedFilter($query),
            default => $query,
        };
    }

    /**
     * Còn phải làm và CHƯA có báo cáo ngày hôm nay (đã gửi hoặc đã duyệt) cho
     * đúng đầu việc + đúng người được giao.
     */
    private function applyUnreportedFilter(QueryBuilder $query): QueryBuilder
    {
        $query->whereIn('feed.status_group', [
            TechnicalWorkItem::GROUP_PENDING,
            TechnicalWorkItem::GROUP_IN_PROGRESS,
        ])->whereNotNull('feed.assigned_user_id');

        if (! Schema::hasTable('technical_daily_reports')) {
            return $query;
        }

        $today = Carbon::today()->toDateString();

        return $query->whereNotExists(function ($sub) use ($today): void {
            $sub->selectRaw('1')
                ->from('technical_daily_reports as r')
                ->whereColumn('r.source_type', 'feed.source_type')
                ->whereColumn('r.source_id', 'feed.source_id')
                ->whereColumn('r.user_id', 'feed.assigned_user_id')
                ->whereNull('r.deleted_at')
                ->whereDate('r.report_date', $today)
                ->whereIn('r.status', [
                    TechnicalDailyReport::STATUS_SUBMITTED,
                    TechnicalDailyReport::STATUS_APPROVED,
                ]);
        });
    }

    /**
     * Chuyển các dòng SQL thô thành DTO + gắn trạng thái báo cáo ngày.
     *
     * Trạng thái báo cáo được tra bằng ĐÚNG MỘT truy vấn cho cả trang
     * (whereIn theo tập khoá), không truy vấn trong vòng lặp.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, TechnicalWorkItem>
     */
    private function hydrate(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $reportMap = $this->reportStatusMap($rows);
        $stepLabels = $this->stepLabels();
        $maintenanceTypes = $this->maintenanceTypeLabels();
        $now = Carbon::now();
        $seen = [];
        $items = collect();

        foreach ($rows as $row) {
            $sourceType = (string) $row->source_type;
            $sourceId = (int) $row->source_id;
            $assignedUserId = $row->assigned_user_id !== null ? (int) $row->assigned_user_id : null;
            $key = $sourceType.':'.$sourceId.':'.($assignedUserId ?? 0);

            if (isset($seen[$key])) {
                continue; // khử trùng phòng thủ
            }
            $seen[$key] = true;

            $statusGroup = (string) $row->status_group;
            $dueDate = $row->due_at ? Carbon::parse($row->due_at) : null;
            $isOverdue = $dueDate !== null
                && $dueDate->lt($now)
                && in_array($statusGroup, TechnicalWorkItem::ACTIVE_GROUPS, true);

            $items->push(new TechnicalWorkItem(
                sourceType: $sourceType,
                sourceId: $sourceId,
                siteId: $row->site_id !== null ? (int) $row->site_id : null,
                siteName: $row->site_name !== null ? (string) $row->site_name : null,
                siteCode: $row->site_code !== null ? (string) $row->site_code : null,
                title: $this->resolveTitle($sourceType, (string) ($row->title ?? ''), $stepLabels, $maintenanceTypes),
                description: $row->description !== null ? (string) $row->description : null,
                assignedUserId: $assignedUserId,
                assignedUserName: $row->assigned_user_name !== null ? (string) $row->assigned_user_name : null,
                rawStatus: (string) ($row->raw_status ?? ''),
                statusGroup: $statusGroup,
                progress: (int) ($row->progress ?? 0),
                plannedDate: $row->planned_date ? Carbon::parse($row->planned_date) : null,
                dueDate: $dueDate,
                isOverdue: $isOverdue,
                reportStatus: $reportMap[$key] ?? 'none',
                url: $this->resolveUrl($sourceType, $sourceId, $row->site_id !== null ? (int) $row->site_id : null, (string) ($row->title ?? '')),
            ));
        }

        return $items;
    }

    /**
     * Trạng thái báo cáo ngày HÔM NAY cho từng đầu việc của trang hiện tại.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, string>
     */
    private function reportStatusMap(Collection $rows): array
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return [];
        }

        $sourceIds = $rows->pluck('source_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $userIds = $rows->pluck('assigned_user_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($sourceIds->isEmpty() || $userIds->isEmpty()) {
            return [];
        }

        $reports = TechnicalDailyReport::query()
            ->whereIn('source_id', $sourceIds->all())
            ->whereIn('user_id', $userIds->all())
            ->whereDate('report_date', Carbon::today()->toDateString())
            ->get(['source_type', 'source_id', 'user_id', 'status']);

        $priority = [
            TechnicalDailyReport::STATUS_APPROVED => 4,
            TechnicalDailyReport::STATUS_SUBMITTED => 3,
            TechnicalDailyReport::STATUS_REVISION => 2,
            TechnicalDailyReport::STATUS_DRAFT => 1,
        ];

        $map = [];

        foreach ($reports as $report) {
            $key = $report->source_type.':'.$report->source_id.':'.$report->user_id;
            $current = $map[$key] ?? null;

            if ($current === null || ($priority[$report->status] ?? 0) > ($priority[$current] ?? 0)) {
                $map[$key] = (string) $report->status;
            }
        }

        return $map;
    }

    /** @param array<string, string> $stepLabels @param array<string, string> $maintenanceTypes */
    private function resolveTitle(string $sourceType, string $raw, array $stepLabels, array $maintenanceTypes): string
    {
        return match ($sourceType) {
            TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW => $stepLabels[$raw] ?? ('Bước '.$raw),
            TechnicalWorkItem::SOURCE_MAINTENANCE => $maintenanceTypes[$raw] ?? ('Bảo trì '.$raw),
            default => $raw !== '' ? $raw : 'Công việc #'.$sourceType,
        };
    }

    /** URL mở đúng bản ghi GỐC ở module sở hữu dữ liệu (không sao chép dữ liệu). */
    private function resolveUrl(string $sourceType, int $sourceId, ?int $siteId, string $raw): ?string
    {
        try {
            return match ($sourceType) {
                TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW => $siteId && Route::has('projects-unified.show')
                    ? route('projects-unified.show', ['site' => $siteId]).($raw !== '' ? '?step='.$raw : '')
                    : null,
                TechnicalWorkItem::SOURCE_TASK => Route::has('tasks.show')
                    ? route('tasks.show', ['task' => $sourceId])
                    : null,
                TechnicalWorkItem::SOURCE_MAINTENANCE => Route::has('projects-unified.maintenance.show')
                    ? route('projects-unified.maintenance.show', ['schedule' => $sourceId])
                    : null,
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('TechnicalWorkFeedService: không dựng được URL nguồn.', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, string> */
    public function stepLabels(): array
    {
        $labels = [];

        foreach ((array) config('project_workflow_v2.steps', []) as $code => $definition) {
            $labels[(string) $code] = (string) ($definition['label'] ?? $code);
        }

        return $labels;
    }

    /** @return array<string, string> */
    public function maintenanceTypeLabels(): array
    {
        return \App\Models\SolarMaintenanceSchedule::TYPES;
    }

    /*
    |--------------------------------------------------------------------------
    | Trợ giúp schema
    |--------------------------------------------------------------------------
    */

    /** @param list<string> $tables */
    private function sourceReady(string $source, array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                Log::warning('TechnicalWorkFeedService: thiếu bảng nguồn — nguồn công việc bị bỏ qua.', [
                    'source' => $source,
                    'missing_table' => $table,
                ]);

                return false;
            }
        }

        return true;
    }

    /** Trả về "alias.column" nếu cột tồn tại, ngược lại null. */
    private function column(string $table, string $column, string $alias): ?string
    {
        return Schema::hasColumn($table, $column) ? $alias.'.'.$column : null;
    }

    /** @param list<string> $columns */
    private function firstAvailableColumn(string $table, array $columns, string $alias): ?string
    {
        $available = [];

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $available[] = $alias.'.'.$column;
            }
        }

        if ($available === []) {
            return null;
        }

        return count($available) === 1 ? $available[0] : 'COALESCE('.implode(', ', $available).')';
    }

    /**
     * Sinh biểu thức CASE quy trạng thái gốc về nhóm chung, để có thể ĐẾM ở DB.
     *
     * @param  array<string, list<string>>  $map  nhóm => danh sách trạng thái gốc
     */
    private function statusCase(string $column, array $map): string
    {
        $sql = 'CASE';

        foreach ($map as $group => $statuses) {
            $quoted = implode(', ', array_map(
                static fn (string $status): string => "'".$status."'",
                $statuses,
            ));
            $sql .= " WHEN LOWER({$column}) IN ({$quoted}) THEN '{$group}'";
        }

        return $sql." ELSE '".TechnicalWorkItem::GROUP_PENDING."' END";
    }
}
