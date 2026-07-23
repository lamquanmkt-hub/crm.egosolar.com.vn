<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Site;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trang Công trình V2 chạy song song với module cũ.
 *
 * Nguyên tắc an toàn dữ liệu:
 * - Chỉ đọc trực tiếp các bảng hiện tại: sites, material_requests, users, companies.
 * - Không sao chép, không đổi ID và không migration dữ liệu.
 * - Các thao tác tạo/sửa/chi tiết vẫn dùng route cũ trong giai đoạn Beta.
 */
class SiteWorkspaceController extends Controller
{
    /**
     * Hiển thị workspace điều phối công trình theo đúng phạm vi role.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $roles = $this->roleNames($user);
        $isAdmin = in_array('admin', $roles, true);
        $managedByPermissionMatrix = $this->pagePermissionMatrixEnabled($user);

        $defaultRoles = [
            'admin',
            'management',
            'accounting',
            'warehouse',
            'kho',
            'sales',
            'sales_manager',
            'ky_thuat',
            'technical_manager',
        ];

        $hasProjectPermission = method_exists($user, 'can')
            && $user->can('page.projects');

        $allowed = $isAdmin
            || ($managedByPermissionMatrix
                ? $hasProjectPermission
                : (count(array_intersect($roles, $defaultRoles)) > 0 || $hasProjectPermission));

        abort_unless($allowed, 403, 'Bạn không có quyền truy cập Công trình.');

        $canSeeFinance = $isAdmin
            || count(array_intersect($roles, ['management', 'accounting'])) > 0
            || (method_exists($user, 'can') && (
                $user->can('projects.view_finance')
                || $user->can('finance.view')
                || $user->can('finance.manage')
            ));

        $scopeLabel = $this->scopeLabel($roles);
        $canOpenLegacy = count(array_intersect($roles, [
            'admin', 'ky_thuat', 'accounting', 'warehouse', 'kho', 'sales',
        ])) > 0;
        $activeCompanyId = $this->activeCompanyId($request);

        $scopeQuery = Site::query();

        if ($activeCompanyId > 0 && Schema::hasColumn('sites', 'company_id')) {
            $scopeQuery->where('company_id', $activeCompanyId);
        }

        $this->applyRoleScope($scopeQuery, $user, $roles);

        $overviewRows = (clone $scopeQuery)
            ->select($this->overviewColumns())
            ->orderByDesc(Schema::hasColumn('sites', 'updated_at') ? 'updated_at' : 'id')
            ->get();

        $materialSummary = $this->materialSummary($overviewRows->pluck('id')->map(fn ($id) => (int) $id));
        $creatorMap = $this->creatorMap($overviewRows->pluck('created_by')->filter()->map(fn ($id) => (int) $id));

        $overview = $overviewRows->map(function (Site $site) use ($materialSummary, $creatorMap): array {
            return $this->presentSite(
                $site,
                $materialSummary[(int) $site->id] ?? null,
                $creatorMap[(int) ($site->created_by ?? 0)] ?? null
            );
        });

        $stageCounts = collect($this->stageDefinitions())
            ->mapWithKeys(fn (array $definition, string $key) => [
                $key => $overview->where('workflow_stage', $key)->count(),
            ]);

        $kpis = [
            'total' => $overview->count(),
            'waiting' => $overview->whereIn('workflow_stage', [
                'intake', 'survey', 'approval', 'material', 'ready',
            ])->count(),
            'material' => $overview->where('workflow_stage', 'material')->count(),
            'installing' => $overview->where('workflow_stage', 'installing')->count(),
            'overdue' => $overview->where('is_overdue', true)->count(),
            'completed' => $overview->whereIn('workflow_stage', ['handover', 'warranty'])->count(),
        ];

        $query = clone $scopeQuery;
        $this->applyFilters($query, $request, $materialSummary, $creatorMap);

        $orderColumn = Schema::hasColumn('sites', 'updated_at') ? 'updated_at' : 'id';
        $sites = $query
            ->orderByDesc($orderColumn)
            ->paginate(18)
            ->withQueryString();

        $pageSiteIds = $sites->getCollection()->pluck('id')->map(fn ($id) => (int) $id);
        $pageMaterialSummary = $this->materialSummary($pageSiteIds);
        $pageCreatorMap = $this->creatorMap(
            $sites->getCollection()->pluck('created_by')->filter()->map(fn ($id) => (int) $id)
        );

        $sites->setCollection(
            $sites->getCollection()->map(function (Site $site) use ($pageMaterialSummary, $pageCreatorMap): Site {
                $presented = $this->presentSite(
                    $site,
                    $pageMaterialSummary[(int) $site->id] ?? null,
                    $pageCreatorMap[(int) ($site->created_by ?? 0)] ?? null
                );

                foreach ($presented as $key => $value) {
                    $site->setAttribute($key, $value);
                }

                return $site;
            })
        );

        $companies = collect();
        if (Schema::hasTable('companies')) {
            $companyQuery = DB::table('companies')->select('id', 'code', 'name');
            if (Schema::hasColumn('companies', 'is_active')) {
                $companyQuery->where('is_active', 1);
            }
            $companies = $companyQuery->orderBy('id')->get();
        }

        $financeSummary = null;
        if ($canSeeFinance) {
            $financeSummary = [
                'contract' => (float) $overviewRows->sum(fn (Site $site) => (float) ($site->contract_amount ?? 0)),
                'received' => $this->receivedAmount($overviewRows->pluck('id')->map(fn ($id) => (int) $id)),
            ];
            $financeSummary['debt'] = max(0, $financeSummary['contract'] - $financeSummary['received']);
        }

        return view('sites-v2.index', [
            'sites' => $sites,
            'kpis' => $kpis,
            'stageCounts' => $stageCounts,
            'stageDefinitions' => $this->stageDefinitions(),
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'scopeLabel' => $scopeLabel,
            'canSeeFinance' => $canSeeFinance,
            'financeSummary' => $financeSummary,
            'roles' => $roles,
            'canOpenLegacy' => $canOpenLegacy,
        ]);
    }

    /** @return array<int, string> */
    private function roleNames($user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()
                ->map(fn ($role) => strtolower(trim((string) $role)))
                ->filter()
                ->values()
                ->all();
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        return $role !== '' ? [$role] : [];
    }

    private function pagePermissionMatrixEnabled($user): bool
    {
        if (! Schema::hasTable(config('permission.table_names.roles', 'roles'))
            || ! Schema::hasColumn(config('permission.table_names.roles', 'roles'), 'page_access_enabled')) {
            return false;
        }

        if (! method_exists($user, 'roles')) {
            return false;
        }

        $user->loadMissing('roles');

        return $user->roles->contains(
            fn ($role) => (bool) ($role->page_access_enabled ?? false)
        );
    }

    private function activeCompanyId(Request $request): int
    {
        $requested = (int) $request->input('company_id', 0);
        if ($requested > 0) {
            return $requested;
        }

        foreach ([
            'ego_company_id',
            'active_company_id',
            'selected_company_id',
            'company_id',
        ] as $key) {
            $id = (int) session($key, 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function scopeLabel(array $roles): string
    {
        if (count(array_intersect($roles, ['admin', 'management', 'accounting'])) > 0) {
            return 'Toàn công ty theo công ty đang chọn';
        }

        if (count(array_intersect($roles, ['technical_manager', 'warehouse', 'kho', 'sales_manager'])) > 0) {
            return 'Phạm vi phòng ban';
        }

        if (in_array('sales', $roles, true)) {
            return 'Khách hàng và công trình do bạn phụ trách';
        }

        return 'Công trình được giao cho bạn';
    }

    private function applyRoleScope(Builder $query, $user, array $roles): void
    {
        if (count(array_intersect($roles, [
            'admin', 'management', 'accounting', 'warehouse', 'kho',
            'technical_manager', 'sales_manager',
        ])) > 0) {
            return;
        }

        if (in_array('sales', $roles, true)) {
            if (Schema::hasColumn('sites', 'created_by')) {
                $query->where('created_by', (int) $user->id);
            }
            return;
        }

        if (in_array('ky_thuat', $roles, true)) {
            $name = trim((string) ($user->name ?? ''));
            $email = trim((string) ($user->email ?? ''));
            $emailName = $email !== '' ? strstr($email, '@', true) : '';

            $query->where(function (Builder $subQuery) use ($user, $name, $email, $emailName): void {
                $hasCondition = false;

                if (Schema::hasColumn('sites', 'created_by')) {
                    $subQuery->where('created_by', (int) $user->id);
                    $hasCondition = true;
                }

                if (Schema::hasColumn('sites', 'technician_name')) {
                    foreach (array_filter([$name, $email, $emailName]) as $needle) {
                        $method = $hasCondition ? 'orWhere' : 'where';
                        $subQuery->{$method}('technician_name', 'like', '%'.$this->escapeLike($needle).'%');
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $subQuery->whereRaw('1 = 0');
                }
            });

            return;
        }

        if (Schema::hasColumn('sites', 'created_by')) {
            $query->where('created_by', (int) $user->id);
        }
    }

    private function applyFilters(Builder $query, Request $request, Collection $materialSummary, Collection $creatorMap): void
    {
        if ($request->filled('q')) {
            $keyword = trim((string) $request->input('q'));
            $columns = array_values(array_filter([
                Schema::hasColumn('sites', 'name') ? 'name' : null,
                Schema::hasColumn('sites', 'address') ? 'address' : null,
                Schema::hasColumn('sites', 'contact_name') ? 'contact_name' : null,
                Schema::hasColumn('sites', 'contact_phone') ? 'contact_phone' : null,
                Schema::hasColumn('sites', 'quote_customer_company') ? 'quote_customer_company' : null,
                Schema::hasColumn('sites', 'technician_name') ? 'technician_name' : null,
            ]));

            $query->where(function (Builder $subQuery) use ($columns, $keyword): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $subQuery->{$method}($column, 'like', '%'.$this->escapeLike($keyword).'%');
                }
            });
        }

        if ($request->filled('company_id') && Schema::hasColumn('sites', 'company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        $selectedStage = trim((string) $request->input('stage', ''));
        if ($selectedStage !== '' && isset($this->stageDefinitions()[$selectedStage])) {
            $matchingIds = Site::query()
                ->whereIn('id', (clone $query)->select('id'))
                ->select($this->overviewColumns())
                ->get()
                ->filter(function (Site $site) use ($selectedStage, $materialSummary, $creatorMap): bool {
                    $presented = $this->presentSite(
                        $site,
                        $materialSummary[(int) $site->id] ?? null,
                        $creatorMap[(int) ($site->created_by ?? 0)] ?? null
                    );
                    return $presented['workflow_stage'] === $selectedStage;
                })
                ->pluck('id');

            $query->whereIn('id', $matchingIds->all());
        }
    }

    /** @return array<int, string> */
    private function overviewColumns(): array
    {
        return array_values(array_filter([
            'id',
            Schema::hasColumn('sites', 'company_id') ? 'company_id' : null,
            Schema::hasColumn('sites', 'created_by') ? 'created_by' : null,
            Schema::hasColumn('sites', 'name') ? 'name' : null,
            Schema::hasColumn('sites', 'status') ? 'status' : null,
            Schema::hasColumn('sites', 'stage') ? 'stage' : null,
            Schema::hasColumn('sites', 'address') ? 'address' : null,
            Schema::hasColumn('sites', 'contact_name') ? 'contact_name' : null,
            Schema::hasColumn('sites', 'contact_phone') ? 'contact_phone' : null,
            Schema::hasColumn('sites', 'system_kwp') ? 'system_kwp' : null,
            Schema::hasColumn('sites', 'system_kw_ac') ? 'system_kw_ac' : null,
            Schema::hasColumn('sites', 'battery_kwh') ? 'battery_kwh' : null,
            Schema::hasColumn('sites', 'technician_name') ? 'technician_name' : null,
            Schema::hasColumn('sites', 'installed_at') ? 'installed_at' : null,
            Schema::hasColumn('sites', 'deployment_started_at') ? 'deployment_started_at' : null,
            Schema::hasColumn('sites', 'completed_at') ? 'completed_at' : null,
            Schema::hasColumn('sites', 'warranty_to') ? 'warranty_to' : null,
            Schema::hasColumn('sites', 'contract_amount') ? 'contract_amount' : null,
            Schema::hasColumn('sites', 'created_at') ? 'created_at' : null,
            Schema::hasColumn('sites', 'updated_at') ? 'updated_at' : null,
        ]));
    }

    private function materialSummary(Collection $siteIds): Collection
    {
        if ($siteIds->isEmpty()
            || ! Schema::hasTable('material_requests')
            || ! Schema::hasColumn('material_requests', 'site_id')) {
            return collect();
        }

        $statusColumn = Schema::hasColumn('material_requests', 'status');
        $select = ['site_id', DB::raw('COUNT(*) AS total_requests')];

        if ($statusColumn) {
            $select[] = DB::raw("SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('DRAFT','SUBMITTED','PENDING','ACCOUNTING_APPROVED','ADMIN_APPROVED','WAITING','CHO_DUYET','CHO_KHO_DUYET') THEN 1 ELSE 0 END) AS pending_requests");
            $select[] = DB::raw("SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('EXPORTED','COMPLETED','COMPLETE','DONE','FINISHED','DA_XUAT_KHO','HOAN_THANH') THEN 1 ELSE 0 END) AS completed_requests");
        }

        return DB::table('material_requests')
            ->whereIn('site_id', $siteIds->unique()->values()->all())
            ->select($select)
            ->groupBy('site_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->site_id => $row]);
    }

    private function creatorMap(Collection $userIds): Collection
    {
        if ($userIds->isEmpty() || ! Schema::hasTable('users')) {
            return collect();
        }

        return DB::table('users')
            ->whereIn('id', $userIds->unique()->values()->all())
            ->select('id', 'name', 'email')
            ->get()
            ->mapWithKeys(fn ($user) => [(int) $user->id => $user]);
    }

    private function receivedAmount(Collection $siteIds): float
    {
        if ($siteIds->isEmpty()
            || ! Schema::hasTable('receipts')
            || ! Schema::hasColumn('receipts', 'site_id')
            || ! Schema::hasColumn('receipts', 'amount')) {
            return 0.0;
        }

        return (float) DB::table('receipts')
            ->whereIn('site_id', $siteIds->unique()->values()->all())
            ->sum('amount');
    }

    /** @return array<string, mixed> */
    private function presentSite(Site $site, ?object $material, ?object $creator): array
    {
        $rawStatus = strtolower(trim((string) ($site->status ?? '')));
        $rawStage = strtolower(trim((string) ($site->stage ?? '')));
        $pendingMaterials = (int) ($material->pending_requests ?? 0);
        $stage = $this->resolveWorkflowStage($rawStatus, $rawStage, $pendingMaterials);
        $definition = $this->stageDefinitions()[$stage];

        $scheduleDate = $this->dateValue($site->installed_at ?? null)
            ?? $this->dateValue($site->deployment_started_at ?? null);
        $completedDate = $this->dateValue($site->completed_at ?? null);
        $displayDate = in_array($stage, ['handover', 'warranty'], true)
            ? ($completedDate ?? $scheduleDate)
            : $scheduleDate;

        $isOverdue = false;
        if ($displayDate && ! in_array($stage, ['handover', 'warranty'], true)) {
            $isOverdue = $displayDate->copy()->endOfDay()->isPast();
        }

        $owner = trim((string) ($site->technician_name ?? ''));
        if ($owner === '') {
            $owner = trim((string) ($creator->name ?? ''));
        }
        if ($owner === '') {
            $owner = 'Chưa phân công';
        }

        return [
            'workflow_stage' => $stage,
            'workflow_label' => $definition['label'],
            'workflow_short' => $definition['short'],
            'workflow_icon' => $definition['icon'],
            'workflow_tone' => $definition['tone'],
            'workflow_progress' => $definition['progress'],
            'workflow_group' => $definition['group'],
            'next_action' => $this->nextAction($stage, $pendingMaterials),
            'owner_display' => $owner,
            'schedule_date_display' => $displayDate?->format('d/m/Y') ?? 'Chưa có lịch',
            'schedule_date_iso' => $displayDate?->format('Y-m-d'),
            'is_overdue' => $isOverdue,
            'material_total' => (int) ($material->total_requests ?? 0),
            'material_pending' => $pendingMaterials,
            'material_completed' => (int) ($material->completed_requests ?? 0),
            'site_code' => 'CT-'.str_pad((string) $site->id, 5, '0', STR_PAD_LEFT),
            'system_summary' => $this->systemSummary($site),
        ];
    }

    private function resolveWorkflowStage(string $status, string $stage, int $pendingMaterials): string
    {
        $token = $status.' '.$stage;

        if ($this->containsAny($token, ['warranty', 'bao_hanh', 'bảo hành'])) {
            return 'warranty';
        }
        if ($this->containsAny($token, ['done', 'completed', 'complete', 'finished', 'handover', 'ban_giao', 'hoan_thanh'])) {
            return 'handover';
        }
        if ($this->containsAny($token, ['paused', 'hold', 'stop', 'tam_dung'])) {
            return 'paused';
        }
        if ($this->containsAny($token, ['installing', 'installation', 'deploying', 'thi_cong', 'lap_dat'])) {
            return 'installing';
        }
        if ($this->containsAny($token, ['ready', 'scheduled', 'san_sang'])) {
            return 'ready';
        }
        if ($pendingMaterials > 0 || $this->containsAny($token, ['material', 'warehouse', 'vat_tu', 'cho_kho'])) {
            return 'material';
        }
        if ($this->containsAny($token, ['approve', 'approval', 'solution', 'design', 'phuong_an', 'duyet'])) {
            return 'approval';
        }
        if ($this->containsAny($token, ['survey', 'khao_sat'])) {
            return 'survey';
        }

        return 'intake';
    }

    /** @return array<string, array<string, mixed>> */
    private function stageDefinitions(): array
    {
        return [
            'intake' => [
                'label' => 'Chờ tiếp nhận', 'short' => 'Tiếp nhận', 'icon' => 'bi-inbox',
                'tone' => 'slate', 'progress' => 10, 'group' => 'waiting',
            ],
            'survey' => [
                'label' => 'Đang khảo sát', 'short' => 'Khảo sát', 'icon' => 'bi-rulers',
                'tone' => 'blue', 'progress' => 25, 'group' => 'waiting',
            ],
            'approval' => [
                'label' => 'Chờ duyệt phương án', 'short' => 'Chờ duyệt', 'icon' => 'bi-patch-check',
                'tone' => 'purple', 'progress' => 40, 'group' => 'waiting',
            ],
            'material' => [
                'label' => 'Chờ vật tư', 'short' => 'Vật tư', 'icon' => 'bi-box-seam',
                'tone' => 'amber', 'progress' => 55, 'group' => 'preparing',
            ],
            'ready' => [
                'label' => 'Sẵn sàng thi công', 'short' => 'Sẵn sàng', 'icon' => 'bi-calendar2-check',
                'tone' => 'cyan', 'progress' => 70, 'group' => 'preparing',
            ],
            'installing' => [
                'label' => 'Đang thi công', 'short' => 'Thi công', 'icon' => 'bi-tools',
                'tone' => 'green', 'progress' => 82, 'group' => 'working',
            ],
            'handover' => [
                'label' => 'Đã bàn giao', 'short' => 'Bàn giao', 'icon' => 'bi-check2-circle',
                'tone' => 'emerald', 'progress' => 100, 'group' => 'completed',
            ],
            'warranty' => [
                'label' => 'Đang bảo hành', 'short' => 'Bảo hành', 'icon' => 'bi-shield-check',
                'tone' => 'indigo', 'progress' => 100, 'group' => 'completed',
            ],
            'paused' => [
                'label' => 'Tạm dừng', 'short' => 'Tạm dừng', 'icon' => 'bi-pause-circle',
                'tone' => 'red', 'progress' => 35, 'group' => 'waiting',
            ],
        ];
    }

    private function nextAction(string $stage, int $pendingMaterials): string
    {
        return match ($stage) {
            'intake' => 'Tiếp nhận và phân công kỹ thuật',
            'survey' => 'Hoàn tất khảo sát, gửi phương án',
            'approval' => 'Quản lý kỹ thuật duyệt phương án',
            'material' => $pendingMaterials > 0
                ? 'Kho kiểm tra và xử lý đơn vật tư'
                : 'Tạo yêu cầu vật tư',
            'ready' => 'Chốt lịch và đội thi công',
            'installing' => 'Cập nhật nhật ký, checklist thi công',
            'handover' => 'Hoàn thiện hồ sơ bàn giao',
            'warranty' => 'Theo dõi lịch bảo trì, bảo hành',
            'paused' => 'Xác định nguyên nhân và người mở lại',
            default => 'Kiểm tra hồ sơ công trình',
        };
    }

    private function systemSummary(Site $site): string
    {
        $parts = [];
        if ((float) ($site->system_kwp ?? 0) > 0) {
            $parts[] = rtrim(rtrim(number_format((float) $site->system_kwp, 2, '.', ''), '0'), '.').' kWp';
        }
        if ((float) ($site->system_kw_ac ?? 0) > 0) {
            $parts[] = rtrim(rtrim(number_format((float) $site->system_kw_ac, 2, '.', ''), '0'), '.').' kW inverter';
        }
        if ((float) ($site->battery_kwh ?? 0) > 0) {
            $parts[] = rtrim(rtrim(number_format((float) $site->battery_kwh, 2, '.', ''), '0'), '.').' kWh pin';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Chưa cập nhật cấu hình hệ';
    }

    private function dateValue($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
