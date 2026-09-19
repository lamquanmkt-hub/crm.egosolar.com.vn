<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Enums\MaterialRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Projects\Site;
use App\Models\User;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Services\Projects\UnifiedProjectAccess;
use App\Services\Projects\UnifiedProjectRevenue;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Module DỰ ÁN thống nhất.
 *
 * Dữ liệu trung tâm vẫn là bảng sites hiện có. Dữ liệu Công Trình Test được
 * ánh xạ an toàn sang sites bởi migration, đồng thời giữ nguyên toàn bộ bảng cũ.
 */
class UnifiedProjectController extends Controller
{
    /** @return array<string, array{label:string,short:string,progress:int,color:string,icon:string}> */
    public static function phases(): array
    {
        return [
            'init' => [
                'label' => 'Khởi tạo & phân công',
                'short' => 'Khởi tạo',
                'progress' => 10,
                'color' => 'blue',
                'icon' => 'bi-folder-plus',
            ],
            'design' => [
                'label' => 'Khảo sát & thiết kế',
                'short' => 'Khảo sát & thiết kế',
                'progress' => 30,
                'color' => 'purple',
                'icon' => 'bi-rulers',
            ],
            'prepare' => [
                'label' => 'Chuẩn bị thi công',
                'short' => 'Chuẩn bị',
                'progress' => 55,
                'color' => 'orange',
                'icon' => 'bi-box-seam',
            ],
            'execute' => [
                'label' => 'Thi công & nghiệm thu',
                'short' => 'Thi công',
                'progress' => 100,
                'color' => 'teal',
                'icon' => 'bi-tools',
            ],
            'om' => [
                'label' => 'Bảo hành & bảo trì',
                'short' => 'Bảo hành',
                'progress' => 100,
                'color' => 'green',
                'icon' => 'bi-shield-check',
            ],
        ];
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);
        $workflowV2 = app(ProjectWorkflowV2Service::class);

        $query = Site::query();
        $this->applyExistingProjectScope($query);
        $this->applyCompanyScope($query, $request);
        $this->applyUserScope($query, $user);

        $baseQuery = clone $query;
        $this->applyFilters($query, $request);
        $this->applyDashboardStatusFilter($query, $request);
        $workflowV2->applyListFilter($query, $request->query('workflow_step'));

        $orderColumn = Schema::hasColumn('sites', 'updated_at') ? 'updated_at' : 'id';
        $projects = $query
            ->orderByDesc($orderColumn)
            ->paginate(18)
            ->withQueryString();

        $workflowMap = $workflowV2->listPresentation(
            $projects->getCollection()->pluck('id')->map(fn ($id) => (int) $id)
        );

        $engineerMap = $this->userMap(
            $projects->getCollection()
                ->pluck('lead_engineer_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
        );

        $maintenanceMap = $this->maintenanceSummary(
            $projects->getCollection()->pluck('id')->map(fn ($id) => (int) $id)
        );
        $taskMap = $this->taskSummary(
            $projects->getCollection()->pluck('id')->map(fn ($id) => (int) $id)
        );
        $canSeeFinance = $this->canViewFinance($user);
        $canSeeRevenue = $canSeeFinance || app(UnifiedProjectAccess::class)->isSalesScoped($user);
        $revenueMap = $canSeeRevenue ? app(UnifiedProjectRevenue::class)->forSites($projects->getCollection()) : [];
        $materialMap = $this->materialSummary(
            $projects->getCollection()->pluck('id')->map(fn ($id) => (int) $id),
            $canSeeFinance
        );

        $projects->setCollection(
            $projects->getCollection()->map(function (Site $site) use ($engineerMap, $maintenanceMap, $taskMap, $materialMap, $workflowMap): Site {
                $site->setAttribute('unified', $this->presentProject(
                    $site,
                    $engineerMap[(int) ($site->lead_engineer_id ?? 0)] ?? null,
                    $maintenanceMap[(int) $site->id] ?? null,
                    $taskMap[(int) $site->id] ?? null,
                    $materialMap[(int) $site->id] ?? null,
                ));
                $site->setAttribute('workflow_v2', $workflowMap[(int) $site->id] ?? null);

                return $site;
            })
        );

        $summaryRows = (clone $baseQuery)
            ->select($this->summaryColumns())
            ->get();

        $phaseCounts = collect(array_keys(self::phases()))
            ->mapWithKeys(fn (string $phase) => [
                $phase => $summaryRows->filter(fn (Site $site) => $this->phaseOf($site) === $phase)->count(),
            ]);
        $workflowSummary = $workflowV2->summary(
            $summaryRows->pluck('id')->map(fn ($id) => (int) $id)
        );

        $kpis = [
            'total' => $summaryRows->count(),
            'design' => (int) ($phaseCounts['design'] ?? 0),
            'prepare' => (int) ($phaseCounts['prepare'] ?? 0),
            'execute' => (int) ($phaseCounts['execute'] ?? 0),
            'om' => (int) ($phaseCounts['om'] ?? 0),
            'overdue' => $summaryRows->filter(fn (Site $site) => $this->isOverdue($site))->count(),
        ];

        $companyOptions = $this->companies();
        $engineers = $this->engineers();
        $financeSummary = $canSeeRevenue
            ? $this->financeSummary($summaryRows->pluck('id')->map(fn ($id) => (int) $id), $summaryRows)
            : null;

        return view('projects-unified.index', [
            'projects' => $projects,
            'phases' => self::phases(),
            'phaseCounts' => $phaseCounts,
            'kpis' => $kpis,
            'companies' => $companyOptions,
            'engineers' => $engineers,
            'canCreate' => $this->canCreate($user),
            'canSeeFinance' => $canSeeFinance,
            'canWriteFinance' => $this->canWriteFinance($user),
            'financeSummary' => $financeSummary,
            'canSeeRevenue' => $canSeeRevenue,
            'revenueMap' => $revenueMap,
            'isSalesScope' => app(UnifiedProjectAccess::class)->isSalesScoped($user),
            'scopeLabel' => $this->scopeLabel($user),
            'workflowDefinitions' => $workflowV2->definitions(),
            'workflowCounts' => $workflowSummary['counts'],
            'workflowKpis' => $workflowSummary,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $this->canCreate($user), 403);

        return view('projects-unified.create', [
            'companies' => $this->companies(),
            'engineers' => $this->engineers(),
            'phases' => self::phases(),
            'activeCompanyId' => app(UnifiedProjectAccess::class)->isSalesScoped($user)
                ? \App\Support\EgoCompanyLock::id() : $this->activeCompanyId($request),
            'isSalesScope' => app(UnifiedProjectAccess::class)->isSalesScoped($user),
            'canEnterContract' => $this->canViewFinance($user) || app(UnifiedProjectAccess::class)->isSalesScoped($user),
            'canSeeFinance' => $this->canViewFinance($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canCreate($user), 403);

        $salesScope = app(UnifiedProjectAccess::class)->isSalesScoped($user);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'project_type' => ['required', Rule::in(['solar_farm', 'factory', 'industrial', 'large_residential', 'other'])],
            'address' => ['required', 'string', 'max:700'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'system_kwp' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'lead_engineer_id' => [$salesScope ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'target_completion_at' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'note' => ['nullable', 'string'],
            'contract_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Sales may set the initial canonical contract amount; other finance writes remain admin-only.
        if (! $this->canViewFinance($user) && ! $salesScope) {
            unset($data['contract_amount']);
        }
        if ($salesScope) {
            $data['company_id'] = \App\Support\EgoCompanyLock::id();
            $data['lead_engineer_id'] = null;
        }

        $site = DB::transaction(function () use ($data, $user, $salesScope): Site {
            $row = [
                'company_id' => ($data['company_id'] ?? 0) > 0 ? (int) $data['company_id'] : null,
                'created_by' => (int) $user->id,
                'request_source' => $salesScope ? 'sales' : 'technical',
                'sales_user_id' => $salesScope ? (int) $user->id : null,
                'project_code' => $this->nextProjectCode(),
                'project_type' => $data['project_type'],
                'name' => trim($data['name']),
                'status' => 'active',
                'stage' => 'init',
                'project_phase' => 'init',
                'progress_percent' => 0,
                'phase_progress_percent' => 0,
                'calculated_progress_percent' => 0,
                'approved_progress_percent' => 0,
                'project_phase_status' => 'in_progress',
                'priority' => $data['priority'],
                'lead_engineer_id' => !empty($data['lead_engineer_id']) ? (int) $data['lead_engineer_id'] : null,
                'target_completion_at' => $data['target_completion_at'] ?? null,
                'address' => trim($data['address']),
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'system_kwp' => $data['system_kwp'] ?? null,
                'system_type' => $data['system_type'] ?? null,
                'note' => $data['note'] ?? null,
                // The production sites.contract_amount column is NOT NULL.
                // Empty finance input (or a user without finance access) must
                // therefore fall back to zero instead of being inserted as NULL.
                'contract_amount' => (float) ($data['contract_amount'] ?? 0),
                'legacy_source' => null,
                'legacy_source_id' => null,
            ];

            $site = Site::query()->create($this->filterColumns('sites', $row));
            $this->history($site, 'project_created', null, 'init', 'Khởi tạo dự án và phân công kỹ sư phụ trách.');
            app(ProjectWorkflowV2Service::class)->ensure($site);

            return $site;
        });

        return redirect()
            ->route('projects-unified.show', $site)
            ->with('success', 'Đã tạo công trình mới.');
    }

    public function searchMaterialProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($this->canWarehouse($user) || $this->canManage($user)), 403);
        abort_unless(Schema::hasTable('crm_product_catalog'), 503);

        $keyword = trim((string) $request->query('q', ''));
        if (mb_strlen($keyword) < 2) {
            return response()->json(['data' => []]);
        }

        $query = DB::table('crm_product_catalog as p');
        $hasStockQty = Schema::hasTable('crm_product_stock') && Schema::hasColumn('crm_product_stock', 'qty');
        if ($hasStockQty) {
            $query->leftJoin('crm_product_stock as s', 's.product_id', '=', 'p.id');
        }

        $query->where(function ($sub) use ($keyword): void {
            $sub->where('p.name', 'like', '%'.$keyword.'%');
            foreach (['sku', 'barcode', 'code'] as $column) {
                if (Schema::hasColumn('crm_product_catalog', $column)) {
                    $sub->orWhere('p.'.$column, 'like', '%'.$keyword.'%');
                }
            }
        });
        if (Schema::hasColumn('crm_product_catalog', 'is_active')) {
            $query->where('p.is_active', 1);
        }

        $selects = ['p.id', 'p.name'];
        foreach (['sku', 'unit', 'barcode', 'code', 'is_serialized'] as $column) {
            if (Schema::hasColumn('crm_product_catalog', $column)) {
                $selects[] = 'p.'.$column;
            }
        }
        $catalogCostColumn = collect(['cost_price', 'purchase_price', 'unit_cost', 'avg_cost', 'price_cost'])
            ->first(fn (string $column): bool => Schema::hasColumn('crm_product_catalog', $column));
        if ($catalogCostColumn) {
            $selects[] = 'p.'.$catalogCostColumn;
        }
        $query->select($selects);
        if ($hasStockQty) {
            $query->selectRaw('COALESCE(SUM(s.qty),0) as stock_qty');
            $query->groupBy($selects);
        } else {
            $query->selectRaw('0 as stock_qty');
        }

        $canSeeProductCost = $this->canViewFinance($user) || $this->canWarehouse($user);
        $rows = $query->orderBy('p.name')->limit(30)->get()->map(function ($row) use ($catalogCostColumn, $canSeeProductCost): array {
            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sku' => (string) ($row->sku ?? $row->code ?? ''),
                'barcode' => (string) ($row->barcode ?? ''),
                'unit' => (string) ($row->unit ?? 'cái'),
                'stock_qty' => (float) ($row->stock_qty ?? 0),
                'unit_cost' => $catalogCostColumn && $canSeeProductCost ? (float) ($row->{$catalogCostColumn} ?? 0) : 0.0,
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, Site $site): View
    {
        $user = $request->user();
        abort_unless($user, 403);
        $this->abortIfCannotView($site, $user);
        $canSeeFinance = $this->canViewFinance($user);
        if ((string) $request->query('step', '') === 'finance') {
            abort_unless($canSeeFinance, 403);
        }
        $workflowV2 = app(ProjectWorkflowV2Service::class);
        $workflow = $workflowV2->present($site, $user, $request->query('step'));

        $leadEngineer = null;
        if (! empty($site->lead_engineer_id)) {
            $leadEngineer = User::query()->find((int) $site->lead_engineer_id);
        }

        $tasks = collect();
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'site_id')) {
            $tasks = DB::table('tasks as t')
                ->leftJoin('users as u', 'u.id', '=', 't.assignee_id')
                ->where('t.site_id', $site->id)
                ->select([
                    't.id', 't.title', 't.status', 't.priority', 't.progress_percent',
                    't.due_at', 't.updated_at', 'u.name as assignee_name',
                ])
                ->orderByDesc('t.id')
                ->limit(30)
                ->get();
        }

        $materials = collect();
        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'site_id')) {
            $materialQuery = DB::table('material_requests')
                ->where('site_id', $site->id)
                ->orderByDesc('id')
                ->limit(30);

            if (! $canSeeFinance) {
                // Không tải giá vốn/giá trị đơn vật tư xuống bộ nhớ hoặc view của phòng Kỹ thuật.
                $safeColumns = collect(['id', 'site_id', 'status', 'note', 'updated_at'])
                    ->filter(fn (string $column): bool => Schema::hasColumn('material_requests', $column))
                    ->values()
                    ->all();
                $materialQuery->select($safeColumns ?: ['id']);
            }

            $materials = $materialQuery->get();
        }

        $maintenance = collect();
        if (Schema::hasTable('solar_maintenance_schedules') && Schema::hasColumn('solar_maintenance_schedules', 'site_id')) {
            $maintenance = DB::table('solar_maintenance_schedules')
                ->where('site_id', $site->id)
                ->whereNull('deleted_at')
                ->orderByDesc('scheduled_date')
                ->limit(30)
                ->get();
        }

        $paymentTerms = collect();
        if ($canSeeFinance && Schema::hasTable('site_payment_terms') && Schema::hasColumn('site_payment_terms', 'site_id')) {
            $paymentTerms = DB::table('site_payment_terms')
                ->where('site_id', $site->id)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', '!=', 'archived');
                })
                ->orderBy('id')
                ->get();
        }

        $history = collect();
        if (Schema::hasTable('project_unified_histories')) {
            $historyQuery = DB::table('project_unified_histories as h')
                ->leftJoin('users as u', 'u.id', '=', 'h.user_id')
                ->where('h.site_id', $site->id);

            if (! $canSeeFinance) {
                $historyQuery->whereNotIn('h.action', [
                    'admin_finance_updated', 'payment_term_created', 'project_payment_recorded',
                    'project_payment_updated', 'payment_recorded', 'finance_updated',
                ]);
            }

            $history = $historyQuery
                ->select('h.*', 'u.name as user_name')
                ->orderByDesc('h.id')
                ->limit(60)
                ->get();
        }

        $documents = collect();
        if (Schema::hasTable('solar_site_documents') && Schema::hasColumn('solar_site_documents', 'site_id')) {
            $documentQuery = DB::table('solar_site_documents as d')
                ->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')
                ->where('d.site_id', $site->id)
                ->when(Schema::hasColumn('solar_site_documents', 'deleted_at'), function ($query): void {
                    $query->whereNull('d.deleted_at');
                });

            if (! $canSeeFinance && Schema::hasColumn('solar_site_documents', 'category')) {
                $documentQuery->whereNotIn('d.category', $this->financialDocumentCategories());
            }

            $documents = $documentQuery
                ->select('d.*', 'u.name as uploader_name')
                ->orderByDesc('d.id')
                ->limit(200)
                ->get();
        }

        $legacy = $this->legacyContext($site);
        $exportedMaterialRequests = $canSeeFinance
            ? $this->exportedMaterialRequests($site)
            : collect();
        $financeExpenses = $canSeeFinance
            ? $this->projectFinanceExpenses($site)
            : collect();
        $finance = $canSeeFinance
            ? $this->singleFinanceSummary($site, $exportedMaterialRequests, $financeExpenses)
            : null;

        $materialWorkflow = $this->materialWorkflowData($site, $user);
        $paymentWorkflow = $canSeeFinance
            ? $this->paymentWorkflowData($site)
            : [
                'terms' => collect(),
                'records' => collect(),
                'accounts' => collect(),
                'term_paid' => collect(),
            ];

        $phaseKey = $this->phaseOf($site);
        $phaseReview = $this->currentPhaseReview($site, $phaseKey);
        $phaseChecklist = $this->buildPhaseChecklist(
            $site,
            $phaseKey,
            $documents,
            $tasks,
            $materials,
            $maintenance,
            $legacy,
            $phaseReview,
        );
        $progressEngine = $this->calculateProjectProgress($site, $phaseKey, $phaseChecklist);
        $this->syncCalculatedProgress($site, $progressEngine);

        $projectPresentation = $this->presentProject(
            $site,
            $leadEngineer,
            $this->maintenanceSummary(collect([(int) $site->id]))[(int) $site->id] ?? null,
            $this->taskSummary(collect([(int) $site->id]))[(int) $site->id] ?? null,
            $this->materialSummary(collect([(int) $site->id]), $canSeeFinance)[(int) $site->id] ?? null,
            $progressEngine,
        );

        $documentStats = $documents
            ->groupBy(fn ($document) => (string) ($document->category ?? 'other'))
            ->map(fn ($items) => $items->count());

        return view('projects-unified.show', [
            'site' => $site,
            'project' => $projectPresentation,
            'phases' => self::phases(),
            'leadEngineer' => $leadEngineer,
            'engineers' => $this->engineers(),
            'tasks' => $tasks,
            'materials' => $materials,
            'maintenance' => $maintenance,
            'paymentTerms' => $paymentTerms,
            'history' => $history,
            'documents' => $documents,
            'documentStats' => $documentStats,
            'phaseChecklist' => $phaseChecklist,
            'legacy' => $legacy,
            'finance' => $finance,
            'salesRevenue' => app(UnifiedProjectAccess::class)->isSalesScoped($user)
                ? (app(UnifiedProjectRevenue::class)->forSites(collect([$site]))[(int) $site->id] ?? null) : null,
            'exportedMaterialRequests' => $exportedMaterialRequests,
            'financeExpenses' => $financeExpenses,
            'canManage' => $this->canManage($user),
            'canSeeFinance' => $canSeeFinance,
            'canWriteFinance' => $this->canWriteFinance($user),
            'canProposeMaterials' => $materialWorkflow['can_propose'],
            'canApproveMaterials' => $materialWorkflow['can_approve'],
            'canWarehouse' => $materialWorkflow['can_warehouse'],
            'canAdminApproveMaterials' => $workflowV2->isAdmin($user),
            'canConfirmMaterialReceipt' => $workflowV2->matchesAnyGroup($user, ['technical', 'technical_manager', 'supervisor']) || $workflowV2->isAdmin($user),
            'canConfirmMaterialSupervisor' => $workflowV2->matchesAnyGroup($user, ['supervisor', 'technical_manager']) || $workflowV2->isAdmin($user),
            'canRecordPayment' => $this->canRecordPayment($user),
            'materialProposals' => $materialWorkflow['proposals'],
            'materialProposalItems' => $materialWorkflow['items'],
            'materialKpis' => $materialWorkflow['kpis'],
            'productOptions' => $materialWorkflow['products'],
            'warehouseOptions' => $materialWorkflow['warehouses'],
            'paymentRecords' => $paymentWorkflow['records'],
            'accounts' => $paymentWorkflow['accounts'],
            'paymentTermPaid' => $paymentWorkflow['term_paid'],
            'progressEngine' => $progressEngine,
            'phaseReview' => $phaseReview,
            'phaseApprovalStatus' => $this->phaseApprovalStatus($site, $phaseReview),
            'canSubmitPhase' => $this->canSubmitProjectPhase($site, $user),
            'canApprovePhase' => $this->canApproveProjectPhase($user),
            'workflow' => $workflow,
        ]);
    }

    /**
     * Checklist vận hành theo giai đoạn hiện tại.
     *
     * Mỗi checklist có một điều kiện phê duyệt cuối. Tiến độ giai đoạn được
     * tính tự động từ dữ liệu thật; người dùng không nhập phần trăm tùy ý.
     *
     * @return array<string,mixed>
     */
    private function buildPhaseChecklist(
        Site $site,
        string $phase,
        Collection $documents,
        Collection $tasks,
        Collection $materials,
        Collection $maintenance,
        array $legacy,
        ?object $phaseReview = null,
    ): array {
        $documentCategories = $documents
            ->pluck('category')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique();

        $hasDocument = fn (array $categories): bool => $documentCategories
            ->intersect($categories)
            ->isNotEmpty();

        $hasLegacySurvey = ! empty($legacy['survey']);
        $hasLegacyProposal = ! empty($legacy['proposal']);
        $hasLegacyAcceptance = ! empty($legacy['acceptance']);
        $hasLegacyWarranty = ! empty($legacy['warranty']);

        $hasAnyTask = $tasks->isNotEmpty();
        $hasApprovedTask = $tasks->contains(function ($task): bool {
            return in_array(strtolower((string) ($task->status ?? '')), [
                'approved', 'completed', 'done',
            ], true);
        });

        $materialProposalStatuses = collect();
        if (Schema::hasTable('project_material_proposals')) {
            $materialProposalStatuses = DB::table('project_material_proposals')
                ->where('site_id', $site->id)
                ->pluck('status')
                ->map(fn ($status) => strtoupper((string) $status));
        }
        $hasMaterialProposal = $materialProposalStatuses->isNotEmpty() || $materials->isNotEmpty()
            || (isset($legacy['material_requests']) && $legacy['material_requests']->isNotEmpty());
        $hasWarehouseReady = $materialProposalStatuses->intersect([
            'WAREHOUSE_ALLOCATED', 'READY_FOR_EXPORT', 'EXPORTED',
        ])->isNotEmpty() || $materials->contains(function ($row): bool {
            return in_array(strtolower((string) ($row->status ?? '')), [
                'admin_approved', 'warehouse_approved', 'ready_for_export', 'exported', 'issued', 'completed',
            ], true);
        });

        $reviewStatus = strtolower((string) ($phaseReview->status ?? ''));
        $approvalState = match ($reviewStatus) {
            'pending' => 'pending_approval',
            'revision' => 'revision',
            'approved' => 'approved',
            default => 'not_started',
        };
        $approvalLabel = match ($approvalState) {
            'pending_approval' => 'Đang chờ Giám đốc duyệt chuyển bước',
            'revision' => 'Cần bổ sung theo yêu cầu phê duyệt',
            'approved' => 'Giai đoạn đã được phê duyệt',
            default => 'Giám đốc duyệt chuyển bước',
        };
        $approvalHint = match ($approvalState) {
            'pending_approval' => 'Hồ sơ đã gửi duyệt; chờ người có thẩm quyền xử lý.',
            'revision' => (string) ($phaseReview->review_note ?? 'Bổ sung hồ sơ và gửi duyệt lại.'),
            'approved' => 'Tiến độ giai đoạn đã được khóa và ghi nhận chính thức.',
            default => 'Chỉ mở giai đoạn tiếp theo sau khi được phê duyệt.',
        };

        $item = static function (
            string $key,
            string $label,
            string $hint,
            bool $complete,
            string $tab,
            string $state = '',
        ): array {
            return [
                'key' => $key,
                'label' => $label,
                'hint' => $hint,
                'complete' => $complete,
                'tab' => $tab,
                'state' => $state !== '' ? $state : ($complete ? 'complete' : 'not_started'),
            ];
        };

        $approvalItem = $item(
            'phase_approval',
            $approvalLabel,
            $approvalHint,
            $approvalState === 'approved',
            'overview',
            $approvalState,
        );

        $items = match ($phase) {
            'init' => [
                $item('lead_engineer', 'Đã phân công kỹ sư phụ trách', 'Có một kỹ sư chịu trách nhiệm chính.', (int) ($site->lead_engineer_id ?? 0) > 0, 'overview'),
                $item('basic_information', 'Đủ thông tin dự án cơ bản', 'Tên, địa điểm, quy mô hệ thống đã được cập nhật.', trim((string) ($site->name ?? '')) !== '' && trim((string) ($site->address ?? '')) !== '' && (float) ($site->system_kwp ?? 0) > 0, 'overview'),
                $item('target_date', 'Có mốc hoàn thành dự kiến', 'Dùng để cảnh báo tiến độ và điều hành.', ! empty($site->target_completion_at), 'overview'),
                $item('initial_document', 'Có hồ sơ ban đầu', 'Yêu cầu kỹ thuật, hợp đồng hoặc tài liệu đầu vào.', $hasDocument(['contract', 'overview', 'other']), 'documents'),
                $approvalItem,
            ],
            'design' => [
                $item('lead_engineer', 'Đã phân công kỹ sư phụ trách', 'Có một kỹ sư chịu trách nhiệm chính.', (int) ($site->lead_engineer_id ?? 0) > 0, 'overview'),
                $item('basic_information', 'Đủ thông tin dự án cơ bản', 'Tên, địa điểm và quy mô hệ thống đã được cập nhật.', trim((string) ($site->name ?? '')) !== '' && trim((string) ($site->address ?? '')) !== '' && (float) ($site->system_kwp ?? 0) > 0, 'overview'),
                $item('survey_document', 'Có biên bản hoặc hồ sơ khảo sát', 'Biên bản, ảnh hiện trạng hoặc dữ liệu đo đạc.', $hasDocument(['survey', 'before', 'overview']) || $hasLegacySurvey, 'documents'),
                $item('technical_design', 'Có phương án hoặc bản vẽ kỹ thuật', 'PDF, CAD, sơ đồ hoặc phương án tính toán.', $hasDocument(['diagram', 'datasheet', 'report']) || $hasLegacyProposal, 'documents'),
                $item('engineering_task', 'Đã giao và theo dõi việc khảo sát/thiết kế', 'Công việc phải liên kết trực tiếp với dự án.', $hasAnyTask, 'technical'),
                $approvalItem,
            ],
            'prepare' => [
                $item('approved_design', 'Phương án kỹ thuật đã được duyệt', 'Là căn cứ chính thức để chuẩn bị triển khai.', $this->hasApprovedPhase($site, 'design') || $hasLegacyProposal, 'documents'),
                $item('material_proposal', 'Đã lập đề xuất vật tư', 'Kỹ thuật đã khai báo nhu cầu vật tư dự án.', $hasMaterialProposal, 'materials'),
                $item('warehouse_ready', 'Kho đã xác nhận tình trạng hàng', 'Đã ghép hàng, sẵn sàng xuất hoặc có phương án xử lý thiếu.', $hasWarehouseReady, 'materials'),
                $item('execution_task', 'Đã phân công công việc chuẩn bị', 'Có công việc kế hoạch, biện pháp hoặc chuẩn bị hiện trường.', $hasAnyTask, 'technical'),
                $item('schedule', 'Đã chốt mốc/lịch triển khai', 'Có hạn dự án hoặc lịch triển khai được cập nhật.', ! empty($site->target_completion_at), 'overview'),
                $approvalItem,
            ],
            'execute' => [
                $item('work_tasks', 'Có công việc thi công đang được theo dõi', 'Công việc phải gắn đúng dự án.', $hasAnyTask, 'technical'),
                $item('approved_reports', 'Có báo cáo công việc đã được duyệt', 'Chỉ báo cáo được duyệt mới tính hoàn thành chính thức.', $hasApprovedTask, 'technical'),
                $item('field_documents', 'Có nhật ký hoặc ảnh hiện trường', 'Báo cáo, ảnh thi công hoặc video hiện trường.', $hasDocument(['during', 'after', 'report', 'video']) || (isset($legacy['logs']) && $legacy['logs']->isNotEmpty()), 'documents'),
                $item('acceptance', 'Có hồ sơ nghiệm thu', 'Biên bản nghiệm thu hoặc hồ sơ hoàn công.', $hasDocument(['acceptance']) || $hasLegacyAcceptance, 'documents'),
                $item('handover', 'Có hồ sơ và ngày bàn giao', 'Bắt buộc trước khi kích hoạt bảo hành.', ($hasDocument(['handover']) || $hasLegacyAcceptance) && (! empty($site->completed_at) || ! empty($site->installed_at)), 'documents'),
                $approvalItem,
            ],
            'om' => [
                $item('deployment_approved', 'Dự án đã nghiệm thu và bàn giao', 'Tiến độ triển khai được khóa ở 100%.', true, 'overview'),
                $item('warranty_document', 'Có hồ sơ bảo hành/bàn giao', 'Hồ sơ bảo hành, nghiệm thu hoặc bàn giao.', $hasDocument(['warranty', 'handover', 'acceptance']) || $hasLegacyWarranty || $hasLegacyAcceptance, 'documents'),
                $item('warranty_period', 'Đã xác định thời hạn bảo hành', 'Có ngày kết thúc bảo hành trong hồ sơ.', ! empty($site->warranty_to), 'maintenance'),
                $item('maintenance_schedule', 'Đã tạo lịch bảo trì/bảo hành', 'Có ít nhất một lịch O&M liên kết dự án.', $maintenance->isNotEmpty(), 'maintenance'),
            ],
            default => [],
        };

        $completed = collect($items)->where('complete', true)->count();
        $total = count($items);
        $preApprovalItems = collect($items)->reject(fn (array $row): bool => $row['key'] === 'phase_approval');
        $readyToSubmit = $phase !== 'om'
            && $preApprovalItems->isNotEmpty()
            && $preApprovalItems->every(fn (array $row): bool => (bool) $row['complete']);

        return [
            'items' => array_values($items),
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'ready_to_submit' => $readyToSubmit,
            'approval_state' => $approvalState,
            'approval_note' => (string) ($phaseReview->review_note ?? ''),
        ];
    }

    public function materialProposalExcelTemplate(Request $request, Site $site): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canProposeMaterials($user), 403);
        $this->abortIfCannotView($site, $user);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('De xuat vat tu');
        $sheet->fromArray([
            ['Tên vật tư', 'Số lượng', 'Ghi chú'],
            ['Tấm pin năng lượng mặt trời 730W', 10, 'Lắp mái khu A'],
            ['Inverter hybrid 10kW', 1, 'Ưu tiên Deye'],
        ]);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(42);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'mau-de-xuat-vat-tu.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function previewMaterialProposalExcel(Request $request, Site $site): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canProposeMaterials($user), 403);
        $this->abortIfCannotView($site, $user);

        $request->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('spreadsheet')->getRealPath());
            $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Không đọc được file Excel. Hãy dùng file mẫu và thử lại.'], 422);
        }

        $items = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($sheetRows, 1) as $offset => $columns) {
            $lineNumber = $offset + 2;
            $name = trim((string) ($columns[0] ?? ''));
            $quantityValue = str_replace(',', '.', trim((string) ($columns[1] ?? '')));
            $note = trim((string) ($columns[2] ?? ''));

            if ($name === '' && $quantityValue === '' && $note === '') {
                continue;
            }

            if ($name === '') {
                $errors[] = 'Dòng '.$lineNumber.': thiếu tên vật tư.';

                continue;
            }

            if (! is_numeric($quantityValue) || (float) $quantityValue <= 0) {
                $errors[] = 'Dòng '.$lineNumber.': số lượng phải lớn hơn 0.';

                continue;
            }

            if (count($items) >= 200) {
                $errors[] = 'Mỗi đề xuất chỉ được nhập tối đa 200 dòng vật tư.';
                break;
            }

            $normalizedName = mb_strtolower($name);
            $duplicate = isset($seen[$normalizedName]);
            $seen[$normalizedName] = true;

            $items[] = [
                'name' => mb_substr($name, 0, 255),
                'qty' => (float) $quantityValue,
                'note' => mb_substr($note, 0, 1000),
                'duplicate' => $duplicate,
                'row' => $lineNumber,
            ];
        }

        if ($items === []) {
            return response()->json([
                'message' => 'File không có dòng vật tư hợp lệ.',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'data' => $items,
            'errors' => $errors,
            'duplicates' => count(array_filter($items, fn (array $item): bool => $item['duplicate'])),
        ]);
    }

    public function storeMaterialProposal(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canProposeMaterials($user), 403);
        $this->abortIfCannotView($site, $user);
        abort_unless(Schema::hasTable('project_material_proposals') && Schema::hasTable('project_material_proposal_items'), 503);

        $data = $request->validate([
            'needed_at' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['normal', 'high', 'urgent'])],
            'proposal_type' => ['nullable', Rule::in(['INITIAL', 'ADDITIONAL', 'REPLACEMENT'])],
            'warranty_scope' => ['nullable', 'required_if:proposal_type,REPLACEMENT', Rule::in(['in_scope', 'out_of_scope', 'pending_assessment'])],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'required_if:proposal_type,ADDITIONAL', 'file', 'max:51200'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.spec' => ['nullable', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.need_date' => ['nullable', 'date'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.is_critical' => ['nullable', 'boolean'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('project-material-proposals/'.$site->id, 'public');
        }

        DB::transaction(function () use ($data, $attachmentPath, $site, $user): void {
            $proposalId = DB::table('project_material_proposals')->insertGetId([
                'site_id' => (int) $site->id,
                'created_by' => (int) $user->id,
                'status' => 'SUBMITTED',
                'priority' => $data['priority'] ?? 'normal',
                'proposal_type' => $data['proposal_type'] ?? 'INITIAL',
                'warranty_scope' => $data['warranty_scope'] ?? null,
                'needed_at' => $data['needed_at'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'note' => $data['note'] ?? null,
                'attachment_path' => $attachmentPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                DB::table('project_material_proposal_items')->insert([
                    'proposal_id' => $proposalId,
                    'requested_name' => trim((string) $item['name']),
                    'requested_spec' => $item['spec'] ?? null,
                    'requested_qty' => (float) $item['qty'],
                    'requested_unit' => trim((string) ($item['unit'] ?? 'Cái')) ?: 'Cái',
                    'need_date' => $item['need_date'] ?? ($data['needed_at'] ?? null),
                    'is_critical' => ! empty($item['is_critical']),
                    'request_note' => $item['note'] ?? null,
                    'warehouse_status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->history($site, 'material_proposal_created', null, (string) $proposalId, 'Kỹ thuật tạo đề xuất vật tư dự án.');
        });

        return back()->with('success', 'Đã gửi đề xuất vật tư cho Admin duyệt.')->withFragment('materials');
    }

    public function approveMaterialProposal(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAdminMaterialApproval($user), 403);

        $data = $request->validate(['approval_note' => ['nullable', 'string', 'max:2000']]);
        abort_unless(Schema::hasTable('project_material_proposals'), 503);

        abort_unless(Schema::hasTable('material_requests') && Schema::hasTable('material_request_items'), 503);

        $materialRequestId = DB::transaction(function () use ($site, $proposal, $user, $data): int {
            $proposalRow = DB::table('project_material_proposals')
                ->where('id', $proposal)->where('site_id', $site->id)
                ->whereIn('status', ['SUBMITTED', 'PENDING', 'NEEDS_REVISION'])->lockForUpdate()->first();
            abort_unless($proposalRow, 404);

            $items = DB::table('project_material_proposal_items')->where('proposal_id', $proposal)->orderBy('id')->get();
            abort_unless($items->isNotEmpty(), 422, 'Đề xuất chưa có vật tư.');
            $defaultWarehouseId = Schema::hasTable('crm_warehouses')
                ? (int) DB::table('crm_warehouses')->orderBy('id')->value('id')
                : 0;
            abort_unless($defaultWarehouseId > 0, 422, 'Chưa có kho để tiếp nhận đơn vật tư.');

            $requestId = DB::table('material_requests')->insertGetId($this->filterColumns('material_requests', [
                'site_id' => (int) $site->id,
                'warehouse_id' => $defaultWarehouseId,
                'created_by' => (int) $user->id,
                'status' => 'ADMIN_APPROVED',
                'note' => 'Đề xuất dự án #'.$proposal.' · Admin đã duyệt, chờ Kho chọn SKU và xuất kho.',
                'total_cost' => 0,
                'company_id' => $site->company_id ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]));

            foreach ($items as $item) {
                DB::table('material_request_items')->insert($this->filterColumns('material_request_items', [
                    'material_request_id' => $requestId,
                    'product_id' => null,
                    'warehouse_id' => $defaultWarehouseId,
                    'qty' => (float) $item->requested_qty,
                    'unit' => $item->requested_unit,
                    'unit_cost' => 0, 'vat_percent' => 0, 'line_total' => 0,
                    'note' => '[Đề xuất dự án] '.$item->requested_name
                        .($item->requested_spec ? ' | Model: '.$item->requested_spec : '')
                        .' | ĐVT: '.$item->requested_unit
                        .($item->request_note ? ' | '.$item->request_note : ''),
                    'created_at' => now(), 'updated_at' => now(),
                ]));
                DB::table('project_material_proposal_items')->where('id', $item->id)->update([
                    'material_request_id' => $requestId,
                    'warehouse_status' => 'WAITING_WAREHOUSE',
                    'updated_at' => now(),
                ]);
            }

            DB::table('project_material_proposals')->where('id', $proposal)->update($this->filterColumns('project_material_proposals', [
                'status' => 'READY_FOR_EXPORT',
                'admin_approved_by' => (int) $user->id,
                'admin_approved_at' => now(),
                'admin_approval_note' => $data['approval_note'] ?? null,
                'approval_note' => $data['approval_note'] ?? null,
                'updated_at' => now(),
            ]));
            $this->history($site, 'material_request_created', null, (string) $requestId, 'Admin duyệt đề xuất #'.$proposal.' và tự động chuyển sang Kho.');

            return $requestId;
        });

        $fragment = $request->input('return_to') === 'maintenance' ? 'round-incident' : 'materials';

        return back()->with('success', 'Đã duyệt và chuyển thành đơn vật tư #'.$materialRequestId.' bên Kho.')->withFragment($fragment);
    }

    public function returnMaterialProposal(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAdminMaterialApproval($user), 403);
        $data = $request->validate(['revision_note' => ['required', 'string', 'max:2000']]);

        $updated = DB::table('project_material_proposals')
            ->where('id', $proposal)
            ->where('site_id', $site->id)
            ->update([
                'status' => 'NEEDS_REVISION',
                'approval_note' => $data['revision_note'],
                'updated_at' => now(),
            ]);
        abort_unless($updated > 0, 404);
        $this->history($site, 'material_proposal_revision', null, (string) $proposal, $data['revision_note']);

        $fragment = $request->input('return_to') === 'maintenance' ? 'round-incident' : 'materials';

        return back()->with('success', 'Đã trả đề xuất để kỹ thuật bổ sung.')->withFragment($fragment);
    }

    public function allocateMaterialProposal(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWarehouse($user), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.product_id' => ['required', 'integer', 'exists:crm_product_catalog,id'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'items.*.selected_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.substitution_reason' => ['nullable', 'string', 'max:1000'],
            'items.*.serial_required' => ['nullable', 'boolean'],
            'warehouse_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $proposalRow = DB::table('project_material_proposals')->where('id', $proposal)->where('site_id', $site->id)->first();
        abort_unless($proposalRow && in_array((string) $proposalRow->status, ['ADMIN_APPROVED', 'PARTIALLY_ALLOCATED', 'WAREHOUSE_ALLOCATED'], true), 422);

        DB::transaction(function () use ($data, $proposal, $site, $user): void {
            foreach ($data['items'] as $item) {
                DB::table('project_material_proposal_items')
                    ->where('id', (int) $item['id'])
                    ->where('proposal_id', $proposal)
                    ->update([
                        'selected_product_id' => (int) $item['product_id'],
                        'selected_warehouse_id' => (int) $item['warehouse_id'],
                        'selected_qty' => (float) $item['selected_qty'],
                        'substitution_reason' => $item['substitution_reason'] ?? null,
                        'serial_required' => ! empty($item['serial_required']),
                        'warehouse_status' => 'ALLOCATED',
                        'warehouse_selected_by' => (int) $user->id,
                        'warehouse_selected_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $pending = DB::table('project_material_proposal_items')
                ->where('proposal_id', $proposal)
                ->where(function ($query): void {
                    $query->whereNull('selected_product_id')->orWhereNull('selected_warehouse_id');
                })
                ->count();

            DB::table('project_material_proposals')->where('id', $proposal)->update([
                'status' => $pending > 0 ? 'PARTIALLY_ALLOCATED' : 'WAREHOUSE_ALLOCATED',
                'warehouse_note' => $data['warehouse_note'] ?? null,
                'updated_at' => now(),
            ]);

            $this->history($site, 'material_warehouse_allocated', null, (string) $proposal, 'Kho đã chọn sản phẩm và kho xuất thực tế.');
        });

        return back()->with('success', 'Đã lưu phương án ghép hàng của Kho.')->withFragment('materials');
    }

    public function createMaterialRequestFromProposal(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWarehouse($user), 403);
        abort_unless(Schema::hasTable('material_requests') && Schema::hasTable('material_request_items'), 503);

        $proposalRow = DB::table('project_material_proposals')->where('id', $proposal)->where('site_id', $site->id)->first();
        abort_unless($proposalRow && in_array((string) $proposalRow->status, ['ADMIN_APPROVED', 'PARTIALLY_ALLOCATED', 'WAREHOUSE_ALLOCATED', 'READY_FOR_EXPORT'], true), 422);
        $items = DB::table('project_material_proposal_items')->where('proposal_id', $proposal)->orderBy('id')->get();
        abort_unless($items->isNotEmpty(), 422);

        $materialRequestId = DB::transaction(function () use ($proposalRow, $items, $proposal, $site, $user): int {
            $warehouseId = (int) ($items->pluck('selected_warehouse_id')->filter()->first()
                ?: DB::table('crm_warehouses')->orderBy('id')->value('id'));
            abort_unless($warehouseId > 0, 422, 'Chưa có kho để tiếp nhận đơn vật tư.');
            $requestRow = $this->filterColumns('material_requests', [
                'site_id' => (int) $site->id,
                'warehouse_id' => $warehouseId,
                'created_by' => (int) $user->id,
                'status' => 'ADMIN_APPROVED',
                'note' => 'Tạo từ đề xuất vật tư dự án #'.$proposal.($proposalRow->warehouse_note ? ' · '.$proposalRow->warehouse_note : ''),
                'total_cost' => 0,
                'company_id' => $site->company_id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $requestId = DB::table('material_requests')->insertGetId($requestRow);

            $totalCost = 0.0;
            foreach ($items as $item) {
                $quantity = (float) ($item->selected_qty ?: $item->requested_qty);
                $productId = (int) ($item->selected_product_id ?? 0);
                $unitCost = $productId > 0 ? $this->productCostForId($productId) : 0.0;
                $lineTotal = $quantity * $unitCost;
                $totalCost += $lineTotal;
                DB::table('material_request_items')->insert($this->filterColumns('material_request_items', [
                    'material_request_id' => $requestId,
                    'product_id' => $productId > 0 ? $productId : null,
                    'warehouse_id' => (int) ($item->selected_warehouse_id ?: $warehouseId),
                    'qty' => $quantity,
                    'unit' => $item->requested_unit,
                    'unit_cost' => $unitCost,
                    'vat_percent' => 0,
                    'line_total' => $lineTotal,
                    'note' => '[Đề xuất dự án] '.$item->requested_name.' | ĐVT: '.$item->requested_unit
                        .($item->requested_spec ? ' · '.$item->requested_spec : '')
                        .($item->substitution_reason ? ' · Thay thế: '.$item->substitution_reason : ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                DB::table('project_material_proposal_items')->where('id', $item->id)->update([
                    'material_request_id' => $requestId,
                    'warehouse_status' => 'READY_FOR_EXPORT',
                    'updated_at' => now(),
                ]);
            }

            DB::table('material_requests')->where('id', $requestId)->update($this->filterColumns('material_requests', [
                'total_cost' => $totalCost,
                'updated_at' => now(),
            ]));

            DB::table('project_material_proposals')->where('id', $proposal)->update([
                'status' => 'READY_FOR_EXPORT',
                'updated_at' => now(),
            ]);
            $this->history($site, 'material_request_created', null, (string) $requestId, 'Kho tạo đơn vật tư sau khi Admin duyệt đề xuất #'.$proposal.'.');

            return $requestId;
        });

        if (Route::has('material-requests.show')) {
            return redirect()->route('material-requests.show', $materialRequestId)->with('success', 'Đã tạo đơn vật tư chờ xuất kho.');
        }

        return back()->with('success', 'Đã tạo đơn vật tư #'.$materialRequestId.' chờ xuất kho.')->withFragment('materials');
    }

    public function updateAdminProjectFinance(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWriteFinance($user), 403);
        $this->abortIfCannotView($site, $user);

        $data = $request->validate([
            'contract_amount' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'other_cost_note' => ['nullable', 'string', 'max:2000'],
            'finance_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $revenue = (float) $data['contract_amount'];
        $financePayload = [
            'contract_amount' => $revenue,
            'contract_amount_after_vat' => $revenue,
            'labor_cost' => (float) ($data['labor_cost'] ?? 0),
            'transport_cost' => (float) ($data['transport_cost'] ?? 0),
            'other_cost_note' => $data['other_cost_note'] ?? null,
            'finance_note' => $data['finance_note'] ?? null,
        ];
        if (array_key_exists('other_cost', $data)) {
            $financePayload['other_cost'] = (float) $data['other_cost'];
        }
        $site->forceFill($this->filterColumns('sites', $financePayload))->save();

        $this->history($site, 'admin_finance_updated', null, null, 'Admin đã cập nhật doanh thu và chi phí công trình.');

        return redirect()
            ->route('projects-unified.show', ['site' => $site->id, 'step' => 'finance'])
            ->with('success', 'Đã lưu tài chính công trình. Giá vốn kho tự động lấy từ phiếu đã xuất.');
    }

    public function storeProjectPaymentTerm(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canRecordPayment($user), 403);
        $this->abortIfCannotView($site, $user);
        abort_unless(Schema::hasTable('site_payment_terms'), 503);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('site_payment_terms')->insert($this->filterColumns('site_payment_terms', [
            'site_id' => (int) $site->id,
            'name' => $data['name'],
            'percent' => $data['percent'] ?? null,
            'amount' => (float) $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'status' => 'pending',
            'note' => $data['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->history($site, 'payment_term_created', null, $data['name'], 'Đã tạo đợt thanh toán dự án.');

        return back()->with('success', 'Đã thêm đợt thanh toán.')->withFragment('materials');
    }

    public function recordProjectPayment(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canRecordPayment($user), 403);
        $this->abortIfCannotView($site, $user);
        abort_unless(Schema::hasTable('project_payment_records'), 503);

        $data = $request->validate([
            'payment_term_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'account_id' => ['nullable', 'integer'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        if (! empty($data['payment_term_id'])) {
            abort_unless(DB::table('site_payment_terms')->where('id', $data['payment_term_id'])->where('site_id', $site->id)->exists(), 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('project-payments/'.$site->id, 'public');
        }

        DB::transaction(function () use ($data, $attachmentPath, $site, $user): void {
            $recordId = DB::table('project_payment_records')->insertGetId([
                'site_id' => (int) $site->id,
                'payment_term_id' => $data['payment_term_id'] ?? null,
                'amount' => (float) $data['amount'],
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'account_id' => $data['account_id'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_name' => $data['payer_name'] ?? ($site->contact_name ?? $site->name),
                'note' => $data['note'] ?? null,
                'attachment_path' => $attachmentPath,
                'created_by' => (int) $user->id,
                'confirmed_by' => (int) $user->id,
                'status' => 'CONFIRMED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $receiptId = $this->syncProjectReceipt($site, $data, $attachmentPath, $user);
            if ($receiptId) {
                DB::table('project_payment_records')->where('id', $recordId)->update(['receipt_id' => $receiptId, 'updated_at' => now()]);
            }

            if (! empty($data['payment_term_id'])) {
                $term = DB::table('site_payment_terms')->where('id', $data['payment_term_id'])->lockForUpdate()->first();
                if ($term) {
                    $paid = (float) DB::table('project_payment_records')->where('payment_term_id', $term->id)->where('status', 'CONFIRMED')->sum('amount');
                    $status = $paid >= (float) $term->amount ? 'paid' : ($paid > 0 ? 'partial' : 'pending');
                    DB::table('site_payment_terms')->where('id', $term->id)->update(['status' => $status, 'updated_at' => now()]);
                }
            }

            $this->history($site, 'project_payment_recorded', null, (string) $recordId, 'Admin ghi nhận thanh toán '.number_format((float) $data['amount'], 0, ',', '.').' đ.');
        });

        return redirect()
            ->route('projects-unified.show', ['site' => $site->id, 'step' => 'finance'])
            ->with('success', 'Đã ghi nhận thanh toán, tạo phiếu thu và cập nhật công nợ công trình.')
            ->withFragment('project-payments');
    }

    public function updateProjectPayment(Request $request, Site $site, string $source, int $payment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWriteFinance($user), 403);
        $this->abortIfCannotView($site, $user);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'account_id' => ['nullable', 'integer'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'change_reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ]);

        $table = $source === 'record' ? 'project_payment_records' : 'receipts';
        abort_unless(Schema::hasTable($table), 404);
        $siteColumn = 'site_id';
        $row = DB::table($table)->where('id', $payment)->where($siteColumn, $site->id)->first();
        abort_unless($row, 404);

        $oldAmount = (float) ($row->amount ?? 0);
        $oldAccountId = (int) ($row->account_id ?? 0);
        $attachmentPath = $row->attachment_path ?? null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('project-payments/'.$site->id, 'public');
        }

        DB::transaction(function () use ($table, $source, $payment, $site, $data, $row, $oldAmount, $oldAccountId, $attachmentPath, $user): void {
            $newAccountId = (int) ($data['account_id'] ?? 0);
            $dateColumn = $source === 'record' ? 'paid_at' : 'receipt_date';
            $payload = $this->filterColumns($table, [
                'amount' => (float) $data['amount'],
                $dateColumn => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'account_id' => $data['account_id'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'note' => $data['note'] ?? null,
                'attachment_path' => $attachmentPath,
                'updated_at' => now(),
            ]);
            DB::table($table)->where('id', $payment)->update($payload);

            if ($source === 'record' && ! empty($row->receipt_id) && Schema::hasTable('receipts')) {
                DB::table('receipts')->where('id', (int) $row->receipt_id)->update($this->filterColumns('receipts', [
                    'amount' => (float) $data['amount'], 'receipt_date' => $data['paid_at'],
                    'payment_method' => $data['payment_method'], 'account_id' => $data['account_id'] ?? null,
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                    'payer_name' => $data['payer_name'] ?? null, 'note' => $data['note'] ?? null,
                    'attachment_path' => $attachmentPath, 'updated_at' => now(),
                ]));
            }

            if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'current_balance')) {
                if ($oldAccountId > 0) {
                    DB::table('accounts')->where('id', $oldAccountId)->decrement('current_balance', $oldAmount);
                }
                if ($newAccountId > 0) {
                    DB::table('accounts')->where('id', $newAccountId)->increment('current_balance', (float) $data['amount']);
                }
            }

            $this->financeAudit($site, 'payment', $source, $payment, (array) $row, $payload, $data['change_reason'], (int) $user->id);
            $this->history($site, 'project_payment_updated', null, (string) $payment, 'Admin đã sửa một khoản thanh toán.');
        });

        return redirect()->route('projects-unified.show', ['site' => $site->id, 'step' => 'finance'])
            ->with('success', 'Đã sửa thanh toán và lưu lịch sử thay đổi.')->withFragment('project-payments');
    }

    public function storeProjectFinanceExpense(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWriteFinance($user), 403);
        $this->abortIfCannotView($site, $user);
        abort_unless(Schema::hasTable('project_finance_expenses'), 503);
        $data = $this->validateProjectExpense($request);
        $id = DB::table('project_finance_expenses')->insertGetId([
            'site_id' => (int) $site->id, 'expense_date' => $data['expense_date'],
            'category' => $data['category'], 'amount' => (float) $data['amount'],
            'payee' => $data['payee'] ?? null, 'description' => $data['description'],
            'note' => $data['note'] ?? null, 'status' => 'ACTIVE',
            'created_by' => (int) $user->id, 'updated_by' => (int) $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->syncProjectOtherExpenseTotal($site);
        $this->financeAudit($site, 'expense', 'expense', $id, [], $data, 'Tạo khoản chi phí khác', (int) $user->id);

        return redirect()->route('projects-unified.show', ['site' => $site->id, 'step' => 'finance'])->with('success', 'Đã thêm chi phí khác.')->withFragment('project-expenses');
    }

    public function updateProjectFinanceExpense(Request $request, Site $site, int $expense): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canWriteFinance($user), 403);
        $this->abortIfCannotView($site, $user);
        $row = Schema::hasTable('project_finance_expenses') ? DB::table('project_finance_expenses')->where('id', $expense)->where('site_id', $site->id)->first() : null;
        abort_unless($row, 404);
        $data = $this->validateProjectExpense($request, true);
        $payload = ['expense_date' => $data['expense_date'], 'category' => $data['category'], 'amount' => (float) $data['amount'], 'payee' => $data['payee'] ?? null, 'description' => $data['description'], 'note' => $data['note'] ?? null, 'updated_by' => (int) $user->id, 'updated_at' => now()];
        DB::table('project_finance_expenses')->where('id', $expense)->update($payload);
        $this->syncProjectOtherExpenseTotal($site);
        $this->financeAudit($site, 'expense', 'expense', $expense, (array) $row, $payload, $data['change_reason'], (int) $user->id);

        return redirect()->route('projects-unified.show', ['site' => $site->id, 'step' => 'finance'])->with('success', 'Đã sửa chi phí và lưu lịch sử thay đổi.')->withFragment('project-expenses');
    }

    public function submitPhaseForApproval(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canSubmitProjectPhase($site, $user), 403);
        abort_unless(Schema::hasTable('project_phase_reviews'), 503);

        $data = $request->validate([
            'submit_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $phase = $this->phaseOf($site);
        abort_if($phase === 'om', 422, 'Dự án đã hoàn tất triển khai và đang ở giai đoạn bảo hành.');
        $review = $this->currentPhaseReview($site, $phase);
        abort_if(strtolower((string) ($review->status ?? '')) === 'pending', 422, 'Giai đoạn đang chờ phê duyệt.');

        $support = $this->phaseSupportData($site);
        $checklist = $this->buildPhaseChecklist(
            $site,
            $phase,
            $support['documents'],
            $support['tasks'],
            $support['materials'],
            $support['maintenance'],
            $support['legacy'],
            $review,
        );
        abort_unless((bool) ($checklist['ready_to_submit'] ?? false), 422, 'Chưa đủ điều kiện gửi duyệt giai đoạn.');

        $progress = $this->calculateProjectProgress($site, $phase, $checklist);

        DB::transaction(function () use ($site, $phase, $checklist, $progress, $data, $user): void {
            DB::table('project_phase_reviews')->insert([
                'site_id' => (int) $site->id,
                'phase' => $phase,
                'status' => 'pending',
                'phase_percent' => (int) $progress['phase_percent'],
                'calculated_progress' => (int) $progress['calculated'],
                'approved_progress' => (int) $progress['approved'],
                'checklist_snapshot' => json_encode($checklist['items'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'submitted_by' => (int) $user->id,
                'submitted_at' => now(),
                'submit_note' => $data['submit_note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->updateSiteFields($site, [
                'project_phase_status' => 'pending_approval',
                'phase_progress_percent' => (int) $progress['phase_percent'],
                'calculated_progress_percent' => (int) $progress['calculated'],
                'progress_percent' => (int) $progress['calculated'],
            ]);
            $this->history($site, 'phase_submitted', $phase, $phase, $data['submit_note'] ?? 'Kỹ sư phụ trách gửi duyệt giai đoạn.');
        });

        return back()->with('success', 'Đã gửi giai đoạn cho Giám đốc phê duyệt.');
    }

    public function approvePhase(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canApproveProjectPhase($user), 403);
        abort_unless(Schema::hasTable('project_phase_reviews'), 503);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $phase = $this->phaseOf($site);
        $review = DB::table('project_phase_reviews')
            ->where('site_id', $site->id)
            ->where('phase', $phase)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
        abort_unless($review, 422, 'Không có yêu cầu duyệt giai đoạn đang chờ xử lý.');

        $nextPhase = $this->nextPhase($phase);
        $approvedProgress = $this->phaseApprovedEndpoint($phase);

        DB::transaction(function () use ($site, $phase, $nextPhase, $approvedProgress, $review, $data, $user): void {
            DB::table('project_phase_reviews')->where('id', $review->id)->update([
                'status' => 'approved',
                'reviewed_by' => (int) $user->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
                'approved_progress' => $approvedProgress,
                'updated_at' => now(),
            ]);

            $update = [
                'approved_progress_percent' => $approvedProgress,
                'calculated_progress_percent' => $approvedProgress,
                'phase_progress_percent' => $nextPhase === 'om' ? 100 : 0,
                'progress_percent' => $approvedProgress,
                'project_phase_status' => 'in_progress',
                'progress_override_percent' => null,
                'progress_override_reason' => null,
                'progress_override_by' => null,
                'progress_override_at' => null,
            ];

            if ($nextPhase !== $phase) {
                $update['project_phase'] = $nextPhase;
                $update['stage'] = $nextPhase;
            }
            if ($nextPhase === 'om') {
                $update['completed_at'] = $site->completed_at ?: now();
                $update['approved_progress_percent'] = 100;
                $update['calculated_progress_percent'] = 100;
                $update['phase_progress_percent'] = 100;
                $update['progress_percent'] = 100;
            }

            $this->updateSiteFields($site, $update);
            $this->history($site, 'phase_approved', $phase, $nextPhase, $data['review_note'] ?? 'Giám đốc duyệt chuyển bước.');
            if ($nextPhase !== $phase) {
                $this->history($site, 'phase_changed', $phase, $nextPhase, 'Chuyển bước sau phê duyệt.');
            }
        });

        return back()->with('success', $nextPhase === 'om'
            ? 'Đã duyệt bàn giao. Tiến độ triển khai được khóa 100% và mở giai đoạn Bảo hành.'
            : 'Đã duyệt giai đoạn và mở bước tiếp theo.');
    }

    public function requestPhaseRevision(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canApproveProjectPhase($user), 403);
        abort_unless(Schema::hasTable('project_phase_reviews'), 503);

        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $phase = $this->phaseOf($site);
        $review = DB::table('project_phase_reviews')
            ->where('site_id', $site->id)
            ->where('phase', $phase)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
        abort_unless($review, 422, 'Không có yêu cầu duyệt giai đoạn đang chờ xử lý.');

        DB::transaction(function () use ($site, $phase, $review, $data, $user): void {
            DB::table('project_phase_reviews')->where('id', $review->id)->update([
                'status' => 'revision',
                'reviewed_by' => (int) $user->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'],
                'updated_at' => now(),
            ]);
            $this->updateSiteFields($site, ['project_phase_status' => 'revision']);
            $this->history($site, 'phase_revision_requested', $phase, $phase, $data['review_note']);
        });

        return back()->with('success', 'Đã trả giai đoạn để kỹ sư bổ sung.');
    }

    /**
     * Điều chỉnh ngoại lệ chỉ dành cho Ban Giám đốc/Admin.
     * Không thay đổi giai đoạn và bắt buộc lưu lý do audit.
     */
    public function updatePhase(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canApproveProjectPhase($user), 403);

        $data = $request->validate([
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'clear_override' => ['nullable', 'boolean'],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $clear = ! empty($data['clear_override']);
        abort_if(! $clear && $data['progress_percent'] === null, 422, 'Vui lòng nhập tỷ lệ điều chỉnh.');

        $old = Schema::hasColumn('sites', 'progress_override_percent')
            ? $site->progress_override_percent
            : null;
        $new = $clear ? null : (int) $data['progress_percent'];

        DB::transaction(function () use ($site, $user, $data, $old, $new, $clear): void {
            $this->updateSiteFields($site, [
                'progress_override_percent' => $new,
                'progress_override_reason' => $clear ? null : $data['note'],
                'progress_override_by' => $clear ? null : (int) $user->id,
                'progress_override_at' => $clear ? null : now(),
                'calculated_progress_percent' => $new ?? (int) ($site->calculated_progress_percent ?? $site->progress_percent ?? 0),
                'progress_percent' => $new ?? (int) ($site->calculated_progress_percent ?? $site->progress_percent ?? 0),
            ]);
            $this->history($site, 'progress_override', $old !== null ? (string) $old : null, $new !== null ? (string) $new : null, $data['note']);
        });

        return back()->with('success', $clear ? 'Đã bỏ điều chỉnh tiến độ thủ công.' : 'Đã lưu điều chỉnh tiến độ ngoại lệ.');
    }

    public function assignEngineer(Request $request, Site $site): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canManage($user), 403);

        $data = $request->validate([
            'lead_engineer_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldId = (int) ($site->lead_engineer_id ?? 0);
        $newId = (int) $data['lead_engineer_id'];

        DB::transaction(function () use ($site, $oldId, $newId, $data): void {
            $site->forceFill($this->filterColumns('sites', [
                'lead_engineer_id' => $newId,
            ]))->save();

            $this->history(
                $site,
                'lead_engineer_changed',
                $oldId > 0 ? (string) $oldId : null,
                (string) $newId,
                $data['note'] ?? null,
            );
        });

        return back()->with('success', 'Đã cập nhật kỹ sư phụ trách dự án.');
    }

    /** @return array<string,mixed> */
    private function materialWorkflowData(Site $site, $user): array
    {
        $empty = [
            'can_propose' => $this->canProposeMaterials($user),
            'can_approve' => $this->canApproveMaterialProposal($site, $user),
            'can_warehouse' => $this->canWarehouse($user),
            'proposals' => collect(),
            'items' => collect(),
            'kpis' => ['total' => 0, 'awaiting' => 0, 'warehouse' => 0, 'ready' => 0],
            'products' => collect(),
            'warehouses' => collect(),
        ];

        if (! Schema::hasTable('project_material_proposals') || ! Schema::hasTable('project_material_proposal_items')) {
            return $empty;
        }

        $proposals = DB::table('project_material_proposals as p')
            ->leftJoin('users as creator', 'creator.id', '=', 'p.created_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'p.technical_approved_by')
            ->where('p.site_id', $site->id)
            ->select('p.*', 'creator.name as creator_name', 'approver.name as approver_name')
            ->orderByDesc('p.id')
            ->get();

        $proposalIds = $proposals->pluck('id')->map(fn ($id) => (int) $id)->all();
        $items = collect();
        if ($proposalIds) {
            $items = DB::table('project_material_proposal_items as i')
                ->leftJoin('crm_product_catalog as product', 'product.id', '=', 'i.selected_product_id')
                ->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'i.selected_warehouse_id')
                ->whereIn('i.proposal_id', $proposalIds)
                ->select('i.*', 'product.name as selected_product_name', 'product.sku as selected_product_sku', 'warehouse.name as selected_warehouse_name')
                ->orderBy('i.id')
                ->get()
                ->groupBy('proposal_id');
        }

        $products = collect();
        $warehouses = collect();
        if ($empty['can_warehouse'] || $this->canManage($user)) {
            if (Schema::hasTable('crm_product_catalog')) {
                $products = DB::table('crm_product_catalog')
                    ->select(array_values(array_filter(['id', 'name', Schema::hasColumn('crm_product_catalog', 'sku') ? 'sku' : null])))
                    ->orderBy('name')
                    ->limit(1000)
                    ->get();
            }
            if (Schema::hasTable('crm_warehouses')) {
                $warehouses = DB::table('crm_warehouses')->select('id', 'name', 'location')->orderBy('name')->get();
            }
        }

        return [
            ...$empty,
            'proposals' => $proposals,
            'items' => $items,
            'products' => $products,
            'warehouses' => $warehouses,
            'kpis' => [
                'total' => $proposals->count(),
                'awaiting' => $proposals->whereIn('status', ['SUBMITTED', 'NEEDS_REVISION'])->count(),
                'warehouse' => $proposals->whereIn('status', ['TECHNICAL_APPROVED', 'PARTIALLY_ALLOCATED', 'WAREHOUSE_ALLOCATED', 'ADMIN_APPROVED'])->count(),
                'ready' => $proposals->whereIn('status', ['READY_FOR_EXPORT', 'EXPORTED'])->count(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function paymentWorkflowData(Site $site): array
    {
        $terms = collect();
        if (Schema::hasTable('site_payment_terms')) {
            $terms = DB::table('site_payment_terms')->where('site_id', $site->id)->orderBy('id')->get();
        }

        $records = collect();
        $linkedReceiptIds = collect();

        if (Schema::hasTable('project_payment_records')) {
            $records = DB::table('project_payment_records as r')
                ->leftJoin('site_payment_terms as term', 'term.id', '=', 'r.payment_term_id')
                ->leftJoin('users as creator', 'creator.id', '=', 'r.created_by')
                ->where('r.site_id', $site->id)
                ->select('r.*', 'term.name as term_name', 'creator.name as creator_name')
                ->get()
                ->map(function ($row) {
                    $row->finance_source = 'project_payment_record';

                    return $row;
                });

            $linkedReceiptIds = $records->pluck('receipt_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        }

        // Dữ liệu lịch sử trước khi có project_payment_records nằm trực tiếp ở receipts.
        // Bản ghi mới đồng bộ sang receipts và có receipt_id, vì vậy phải loại các receipt
        // đã được record mới trỏ tới để tránh cộng hai lần.
        if (Schema::hasTable('receipts') && Schema::hasColumn('receipts', 'site_id')) {
            $legacyQuery = DB::table('receipts as r')
                ->leftJoin('site_payment_terms as term', 'term.id', '=', 'r.site_payment_term_id')
                ->leftJoin('users as creator', 'creator.id', '=', 'r.created_by')
                ->where('r.site_id', $site->id);

            if ($linkedReceiptIds->isNotEmpty()) {
                $legacyQuery->whereNotIn('r.id', $linkedReceiptIds->all());
            }

            $legacyReceipts = $legacyQuery
                ->select([
                    'r.id',
                    'r.site_id',
                    DB::raw('r.site_payment_term_id as payment_term_id'),
                    DB::raw('r.id as receipt_id'),
                    'r.amount',
                    DB::raw('r.receipt_date as paid_at'),
                    'r.payment_method',
                    'r.account_id',
                    'r.transaction_reference',
                    'r.payer_name',
                    'r.note',
                    'r.attachment_path',
                    'r.created_by',
                    DB::raw("'CONFIRMED' as status"),
                    'term.name as term_name',
                    'creator.name as creator_name',
                ])
                ->get()
                ->map(function ($row) {
                    $row->finance_source = 'legacy_receipt';

                    return $row;
                });

            $records = $records->concat($legacyReceipts);
        }

        $records = $records
            ->sortByDesc(fn ($row) => (string) ($row->paid_at ?? '').'-'.str_pad((string) ($row->id ?? 0), 12, '0', STR_PAD_LEFT))
            ->values();

        $accounts = collect();
        if (Schema::hasTable('accounts')) {
            $accountQuery = DB::table('accounts')->select('id', 'name');
            if (Schema::hasColumn('accounts', 'current_balance')) {
                $accountQuery->addSelect('current_balance');
            }
            if (Schema::hasColumn('accounts', 'is_active')) {
                $accountQuery->where('is_active', 1);
            }
            $accounts = $accountQuery->orderBy('name')->get();
        }

        $confirmedRecords = $records->where('status', 'CONFIRMED');
        $validTermIds = $terms->pluck('id')->map(fn ($id) => (int) $id)->all();
        $termPaid = $confirmedRecords
            ->filter(fn ($row) => ! empty($row->payment_term_id) && in_array((int) $row->payment_term_id, $validTermIds, true))
            ->groupBy('payment_term_id')
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        // Phiếu thu legacy có thể chưa có site_payment_term_id hoặc đang trỏ tới id đợt cũ
        // đã bị tạo lại. Coi là chưa phân bổ rồi phân bổ FIFO vào các đợt hiện tại.
        $unallocated = (float) $confirmedRecords
            ->filter(fn ($row) => empty($row->payment_term_id) || ! in_array((int) $row->payment_term_id, $validTermIds, true))
            ->sum('amount');
        if ($unallocated > 0 && $terms->isNotEmpty()) {
            $orderedTerms = $terms->sortBy(function ($term) {
                return ($term->due_date ?: '9999-12-31').'-'.str_pad((string) $term->id, 12, '0', STR_PAD_LEFT);
            });
            foreach ($orderedTerms as $term) {
                if ($unallocated <= 0) {
                    break;
                }
                $already = (float) ($termPaid[(int) $term->id] ?? 0);
                $remaining = max(0, (float) $term->amount - $already);
                $applied = min($remaining, $unallocated);
                if ($applied > 0) {
                    $termPaid[(int) $term->id] = $already + $applied;
                    $unallocated -= $applied;
                }
            }
        }

        return [
            'terms' => $terms,
            'records' => $records,
            'accounts' => $accounts,
            'term_paid' => $termPaid,
        ];
    }

    private function syncProjectReceipt(Site $site, array $data, ?string $attachmentPath, $user): ?int
    {
        if (! Schema::hasTable('receipts')) {
            return null;
        }

        $row = $this->filterColumns('receipts', [
            'account_id' => $data['account_id'] ?? null,
            'site_id' => (int) $site->id,
            'site_payment_term_id' => $data['payment_term_id'] ?? null,
            'code' => $this->nextReceiptCode(),
            'receipt_date' => $data['paid_at'],
            'payer_name' => $data['payer_name'] ?? ($site->contact_name ?? $site->name ?? 'Chủ đầu tư'),
            'payer_phone' => $site->contact_phone ?? null,
            'category' => 'thu_khach_hang',
            'payment_method' => $data['payment_method'],
            'amount' => (float) $data['amount'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'attachment_path' => $attachmentPath,
            'note' => '[Dự án '.$site->project_code.'] '.($data['note'] ?? 'Ghi nhận thanh toán dự án'),
            'created_by' => (int) $user->id,
            'confirmed_by' => (int) $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receiptId = DB::table('receipts')->insertGetId($row);
        if (! empty($data['account_id']) && Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'current_balance')) {
            DB::table('accounts')->where('id', (int) $data['account_id'])->increment('current_balance', (float) $data['amount']);
        }

        return $receiptId;
    }

    private function nextReceiptCode(): string
    {
        $prefix = 'PT'.now()->format('Ymd');
        $last = Schema::hasTable('receipts') ? DB::table('receipts')->where('code', 'like', $prefix.'-%')->orderByDesc('id')->value('code') : null;
        $number = 1;
        if ($last && preg_match('/-(\\d+)$/', (string) $last, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    private function productCostForId(int $productId): float
    {
        if ($productId <= 0 || ! Schema::hasTable('crm_product_catalog')) {
            return 0.0;
        }

        foreach (['cost_price', 'purchase_price', 'unit_cost', 'avg_cost', 'price_cost'] as $column) {
            if (Schema::hasColumn('crm_product_catalog', $column)) {
                return (float) (DB::table('crm_product_catalog')->where('id', $productId)->value($column) ?? 0);
            }
        }

        if (Schema::hasTable('crm_product_stock')) {
            foreach (['avg_cost', 'unit_cost', 'cost_price'] as $column) {
                if (Schema::hasColumn('crm_product_stock', $column)) {
                    return (float) (DB::table('crm_product_stock')->where('product_id', $productId)->where($column, '>', 0)->avg($column) ?? 0);
                }
            }
        }

        return 0.0;
    }

    private function canProposeMaterials($user): bool
    {
        return $this->canManage($user) || count(array_intersect($this->roles($user), [
            'technical', 'technician', 'engineer', 'engineering',
            'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
        ])) > 0;
    }

    private function canApproveMaterialProposal(Site $site, $user): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return count(array_intersect($this->roles($user), [
            'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
            'ky_thuat_truong', 'manager_technical',
        ])) > 0;
    }

    private function canAdminMaterialApproval($user): bool
    {
        return ((int) ($user->is_admin ?? 0) === 1)
            || count(array_intersect($this->roles($user), [
                'admin', 'super_admin', 'management', 'director', 'general_director',
                'ban_giam_doc', 'giam_doc',
            ])) > 0;
    }

    private function canWarehouse($user): bool
    {
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }
        if (count(array_intersect($this->roles($user), [
            'admin', 'super_admin', 'warehouse', 'kho', 'warehouse_manager', 'inventory', 'inventory_manager', 'thu_kho',
        ])) > 0) {
            return true;
        }
        if (method_exists($user, 'can')) {
            foreach (['warehouse.view', 'warehouse.manage', 'material_requests.warehouse'] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $exception) {
                }
            }
        }

        return false;
    }

    private function canRecordPayment($user): bool
    {
        return $this->canWriteFinance($user);
    }

    private function applyExistingProjectScope(Builder $query): void
    {
        if (Schema::hasColumn('sites', 'deleted_at')) {
            $query->whereNull('sites.deleted_at');
        }

        if (Schema::hasColumn('sites', 'status')) {
            $query->where(function (Builder $status): void {
                $status->whereNull('sites.status')
                    ->orWhereNotIn('sites.status', ['deleted', 'removed', 'trashed', 'archived']);
            });
        }

        // Canonical visibility no longer depends on a live legacy row.
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->boolean('mine')) {
            if (app(UnifiedProjectAccess::class)->isSalesScoped($request->user())) {
                $query->where(function (Builder $owner) use ($request): void {
                    $owner->where('sites.created_by', (int) $request->user()->id)
                        ->orWhere('sites.sales_user_id', (int) $request->user()->id);
                });
            } else {
                $query->where('sites.lead_engineer_id', (int) $request->user()->id);
            }
        }
        if (in_array($request->query('source'), ['sales', 'technical', 'internal', 'cskh', 'warranty'], true)) {
            $query->where('sites.request_source', $request->query('source'));
        }
        $keyword = trim((string) $request->input('q', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $sub) use ($keyword): void {
                $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_phone', 'like', "%{$keyword}%");

                if (Schema::hasColumn('sites', 'project_code')) {
                    $sub->orWhere('project_code', 'like', "%{$keyword}%");
                }
            });
        }

        $type = trim((string) $request->input('type', ''));
        if ($type !== '' && Schema::hasColumn('sites', 'project_type')) {
            $query->where('project_type', $type);
        }

        $phase = trim((string) $request->input('phase', ''));
        if ($phase !== '' && isset(self::phases()[$phase])) {
            if (Schema::hasColumn('sites', 'project_phase')) {
                $query->where('project_phase', $phase);
            } elseif (Schema::hasColumn('sites', 'stage')) {
                $query->where('stage', $phase);
            }
        }

        $engineerId = (int) $request->input('engineer_id', 0);
        if ($engineerId > 0 && Schema::hasColumn('sites', 'lead_engineer_id')) {
            $query->where('lead_engineer_id', $engineerId);
        }

        if ($request->boolean('overdue')) {
            $dateColumn = Schema::hasColumn('sites', 'target_completion_at')
                ? 'target_completion_at'
                : (Schema::hasColumn('sites', 'completed_at') ? 'completed_at' : null);

            if ($dateColumn) {
                $query->whereDate($dateColumn, '<', today())
                    ->where(function (Builder $sub): void {
                        if (Schema::hasColumn('sites', 'project_phase')) {
                            $sub->where('project_phase', '!=', 'om')->orWhereNull('project_phase');
                        } else {
                            $sub->whereRaw('1 = 1');
                        }
                    });
            }
        }
    }

    private function applyDashboardStatusFilter(Builder $query, Request $request): void
    {
        $group = trim((string) $request->input('status_group', ''));
        $groups = [
            'survey_proposal' => ['survey', 'proposal'],
            'contract_legal' => ['contract', 'legal'],
            'construction' => ['construction'],
            'acceptance' => ['acceptance'],
            'warranty' => ['warranty'],
        ];

        if ($group === 'materials') {
            if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'site_id')) {
                $query->whereExists(function ($materials): void {
                    $materials->selectRaw('1')
                        ->from('material_requests as project_material_filter')
                        ->whereColumn('project_material_filter.site_id', 'sites.id')
                        ->whereNotIn('project_material_filter.status', [
                            MaterialRequestStatus::EXPORTED->value,
                            MaterialRequestStatus::REJECTED->value,
                        ]);
                });
            }

            return;
        }

        if (! isset($groups[$group])) {
            return;
        }

        $codes = $groups[$group];
        if (Schema::hasColumn('sites', 'workflow_current_step')) {
            $query->whereIn('workflow_current_step', $codes);

            return;
        }

        if (Schema::hasTable('project_workflow_steps')) {
            $query->whereExists(function ($steps) use ($codes): void {
                $steps->selectRaw('1')
                    ->from('project_workflow_steps as dashboard_step_filter')
                    ->whereColumn('dashboard_step_filter.site_id', 'sites.id')
                    ->whereIn('dashboard_step_filter.step_code', $codes)
                    ->where('dashboard_step_filter.status', '!=', 'approved');
            });
        }
    }

    private function applyCompanyScope(Builder $query, Request $request): void
    {
        if (! Schema::hasColumn('sites', 'company_id')) {
            return;
        }

        $companyId = (int) $request->input('company_id', 0);
        if ($companyId <= 0) {
            $companyId = $this->activeCompanyId($request);
        }

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }
    }

    private function applyUserScope(Builder $query, $user): void
    {
        if (app(UnifiedProjectAccess::class)->isSalesScoped($user)) {
            app(UnifiedProjectAccess::class)->applySalesScope($query, $user);
            return;
        }
        if ($this->canManage($user) || $this->hasGlobalFinanceScope($user) || $this->canWarehouse($user)) {
            return;
        }

        $userId = (int) $user->id;
        $name = trim((string) ($user->name ?? ''));

        $query->where(function (Builder $sub) use ($userId, $name): void {
            $hasAny = false;

            if (Schema::hasColumn('sites', 'lead_engineer_id')) {
                $sub->where('lead_engineer_id', $userId);
                $hasAny = true;
            }

            if (Schema::hasColumn('sites', 'created_by')) {
                $hasAny ? $sub->orWhere('created_by', $userId) : $sub->where('created_by', $userId);
                $hasAny = true;
            }

            if ($name !== '' && Schema::hasColumn('sites', 'technician_name')) {
                $hasAny ? $sub->orWhere('technician_name', 'like', "%{$name}%") : $sub->where('technician_name', 'like', "%{$name}%");
                $hasAny = true;
            }

            if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'site_id')) {
                $method = $hasAny ? 'orWhereExists' : 'whereExists';
                $sub->{$method}(function ($taskQuery) use ($userId): void {
                    $taskQuery->selectRaw('1')
                        ->from('tasks')
                        ->whereColumn('tasks.site_id', 'sites.id')
                        ->where('tasks.assignee_id', $userId);
                });
                $hasAny = true;
            }

            if (Schema::hasTable('project_workflow_steps') && Schema::hasTable('project_workflow_assignments')) {
                $method = $hasAny ? 'orWhereExists' : 'whereExists';
                $sub->{$method}(function ($workflowQuery) use ($userId): void {
                    $workflowQuery->selectRaw('1')
                        ->from('project_workflow_assignments as wf_scope_assignment')
                        ->join('project_workflow_steps as wf_scope_step', 'wf_scope_step.id', '=', 'wf_scope_assignment.workflow_step_id')
                        ->whereColumn('wf_scope_step.site_id', 'sites.id')
                        ->where('wf_scope_assignment.user_id', $userId)
                        ->where('wf_scope_assignment.is_active', 1);
                });
                $hasAny = true;
            }

            if (! $hasAny) {
                $sub->whereRaw('1 = 0');
            }
        });
    }

    private function phaseOf(Site $site): string
    {
        $phase = strtolower(trim((string) ($site->project_phase ?? $site->stage ?? '')));
        if (isset(self::phases()[$phase])) {
            return $phase;
        }

        $status = strtolower(trim((string) ($site->status ?? '')));
        $text = $phase.' '.$status;

        if (str_contains($text, 'warranty') || str_contains($text, 'bao_hanh') || str_contains($text, 'bảo hành')) {
            return 'om';
        }
        if (str_contains($text, 'install') || str_contains($text, 'thi_cong') || str_contains($text, 'thi công') || str_contains($text, 'accept')) {
            return 'execute';
        }
        if (str_contains($text, 'material') || str_contains($text, 'prepare') || str_contains($text, 'vat_tu') || str_contains($text, 'vật tư')) {
            return 'prepare';
        }
        if (str_contains($text, 'survey') || str_contains($text, 'design') || str_contains($text, 'khao_sat') || str_contains($text, 'khảo sát')) {
            return 'design';
        }

        if (! empty($site->warranty_to) || ! empty($site->completed_at)) {
            return 'om';
        }

        return 'init';
    }

    /** @return array<string, mixed> */
    private function presentProject(Site $site, $engineer, $maintenance, $task, $material, ?array $progressEngine = null): array
    {
        $phase = $this->phaseOf($site);
        $phaseInfo = self::phases()[$phase];
        $progress = (int) ($progressEngine['calculated'] ?? (Schema::hasColumn('sites', 'calculated_progress_percent')
            ? ($site->calculated_progress_percent ?? $site->progress_percent ?? $phaseInfo['progress'])
            : ($site->progress_percent ?? $phaseInfo['progress'])));
        $progress = max(0, min(100, $progress));
        $approvedProgress = (int) ($progressEngine['approved'] ?? ($site->approved_progress_percent ?? $this->approvedBaseForPhase($phase)));
        $phaseProgress = (int) ($progressEngine['phase_percent'] ?? ($site->phase_progress_percent ?? 0));

        $target = $site->target_completion_at ?? $site->completed_at ?? $site->installed_at ?? null;
        $targetDate = $target ? Carbon::parse($target) : null;
        $overdue = $targetDate && $targetDate->isBefore(today()) && $phase !== 'om';

        $alerts = collect();
        if (empty($site->lead_engineer_id)) {
            $alerts->push(['key' => 'unassigned', 'label' => 'Chưa phân công', 'tone' => 'orange']);
        }
        if ($overdue) {
            $alerts->push(['key' => 'overdue', 'label' => 'Trễ '.today()->diffInDays($targetDate).' ngày', 'tone' => 'red']);
        }
        if ((int) ($material->pending ?? 0) > 0) {
            $alerts->push(['key' => 'material', 'label' => 'Vật tư đang chờ', 'tone' => 'orange']);
        }
        if ((int) ($task->overdue ?? 0) > 0) {
            $alerts->push(['key' => 'task_overdue', 'label' => 'Có việc quá hạn', 'tone' => 'red']);
        }
        if ((int) ($maintenance->upcoming ?? 0) > 0) {
            $alerts->push(['key' => 'maintenance', 'label' => 'Sắp bảo trì', 'tone' => 'blue']);
        }

        return [
            'id' => (int) $site->id,
            'code' => trim((string) ($site->project_code ?? '')) ?: 'DA-SITE-'.str_pad((string) $site->id, 5, '0', STR_PAD_LEFT),
            'name' => (string) ($site->name ?? 'Dự án chưa đặt tên'),
            'address' => (string) ($site->address ?? ''),
            'customer' => trim((string) ($site->contact_name ?? $site->customer_name ?? '')),
            'capacity_kwp' => (float) ($site->system_kwp ?? 0),
            'note' => trim((string) ($site->note ?? '')),
            'project_type' => (string) ($site->project_type ?? 'other'),
            'phase' => $phase,
            'phase_info' => $phaseInfo,
            'progress' => $progress,
            'approved_progress' => max(0, min(100, $approvedProgress)),
            'phase_progress' => max(0, min(100, $phaseProgress)),
            'phase_status' => $this->phaseApprovalStatus($site, $this->currentPhaseReview($site, $phase)),
            'priority' => (string) ($site->priority ?? 'normal'),
            'lead_engineer' => $engineer?->name ?? ($site->technician_name ?? 'Chưa phân công'),
            'lead_engineer_id' => (int) ($site->lead_engineer_id ?? 0),
            'target_date' => $targetDate,
            'is_overdue' => (bool) $overdue,
            'alerts' => $alerts->values(),
            'next_action' => $this->nextAction($phase, $site, $task, $material, $maintenance),
            'legacy_source' => (string) ($site->legacy_source ?? ''),
            'legacy_source_id' => (int) ($site->legacy_source_id ?? 0),
            'task_total' => (int) ($task->total ?? 0),
            'task_open' => (int) ($task->open ?? 0),
            'material_total' => (int) ($material->total ?? 0),
            'maintenance_total' => (int) ($maintenance->total ?? 0),
        ];
    }

    private function nextAction(string $phase, Site $site, $task, $material, $maintenance): string
    {
        if (empty($site->lead_engineer_id)) {
            return 'Phân công kỹ sư phụ trách';
        }

        return match ($phase) {
            'init' => 'Bổ sung hồ sơ và lập kế hoạch khảo sát',
            'design' => 'Hoàn thiện phương án kỹ thuật để trình duyệt',
            'prepare' => ((int) ($material->pending ?? 0) > 0)
                ? 'Xử lý các yêu cầu vật tư đang chờ'
                : 'Chốt vật tư, nhân sự và lịch thi công',
            'execute' => ((int) ($task->open ?? 0) > 0)
                ? 'Theo dõi công việc và chuẩn bị nghiệm thu'
                : 'Tạo công việc thi công hoặc hồ sơ nghiệm thu',
            'om' => ((int) ($maintenance->upcoming ?? 0) > 0)
                ? 'Thực hiện lịch bảo trì sắp đến hạn'
                : 'Theo dõi bảo hành và tạo lịch bảo trì',
            default => 'Mở hồ sơ dự án',
        };
    }

    private function isOverdue(Site $site): bool
    {
        $date = $site->target_completion_at ?? null;
        if (! $date || $this->phaseOf($site) === 'om') {
            return false;
        }

        return Carbon::parse($date)->isBefore(today());
    }

    /** @return array<int, string> */
    private function summaryColumns(): array
    {
        $wanted = [
            'id', 'project_phase', 'stage', 'status', 'target_completion_at',
            'completed_at', 'warranty_to', 'contract_amount', 'company_id',
            'contract_amount_after_vat', 'quote_grand_total',
        ];

        return array_values(array_filter($wanted, fn (string $column) => Schema::hasColumn('sites', $column)));
    }

    private function maintenanceSummary(Collection $siteIds): array
    {
        if ($siteIds->isEmpty()
            || ! Schema::hasTable('solar_maintenance_schedules')
            || ! Schema::hasColumn('solar_maintenance_schedules', 'site_id')) {
            return [];
        }

        return DB::table('solar_maintenance_schedules')
            ->whereIn('site_id', $siteIds->all())
            ->whereNull('deleted_at')
            ->select([
                'site_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as upcoming"),
                DB::raw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue"),
            ])
            ->groupBy('site_id')
            ->get()
            ->keyBy('site_id')
            ->all();
    }

    private function taskSummary(Collection $siteIds): array
    {
        if ($siteIds->isEmpty() || ! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'site_id')) {
            return [];
        }

        return DB::table('tasks')
            ->whereIn('site_id', $siteIds->all())
            ->select([
                'site_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status NOT IN ('approved','completed','done','cancelled') THEN 1 ELSE 0 END) as open"),
                DB::raw("SUM(CASE WHEN status NOT IN ('approved','completed','done','cancelled') AND due_at < NOW() THEN 1 ELSE 0 END) as overdue"),
            ])
            ->groupBy('site_id')
            ->get()
            ->keyBy('site_id')
            ->all();
    }

    private function materialSummary(Collection $siteIds, bool $includeCost = false): array
    {
        if ($siteIds->isEmpty() || ! Schema::hasTable('material_requests') || ! Schema::hasColumn('material_requests', 'site_id')) {
            return [];
        }

        $select = [
            'site_id',
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN LOWER(status) NOT IN ('exported','issued','completed','cancelled') THEN 1 ELSE 0 END) as pending"),
        ];

        if ($includeCost && Schema::hasColumn('material_requests', 'total_cost')) {
            $select[] = DB::raw('COALESCE(SUM(total_cost),0) as total_cost');
        }

        return DB::table('material_requests')
            ->whereIn('site_id', $siteIds->all())
            ->select($select)
            ->groupBy('site_id')
            ->get()
            ->keyBy('site_id')
            ->all();
    }

    private function userMap(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $ids->unique()->all())->get(['id', 'name', 'email'])->keyBy('id')->all();
    }

    private function companies(): Collection
    {
        if (! Schema::hasTable('companies')) {
            return collect();
        }

        $query = DB::table('companies')->select(['id', 'code', 'name']);
        if (Schema::hasColumn('companies', 'is_active')) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('name')->get();
    }

    private function engineers(): Collection
    {
        $query = User::query()->select(['id', 'name', 'email']);

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }

        $users = $query->orderBy('name')->get();

        if (! method_exists(User::class, 'roles')) {
            return $users;
        }

        try {
            $users->load('roles:id,name');

            $technicalRoles = [
                'admin', 'management', 'director', 'general_director',
                'technical_manager', 'technical_leader', 'technical', 'technician', 'engineer', 'engineering'];

            $filtered = $users->filter(function ($user) use ($technicalRoles): bool {
                $roles = $user->roles?->pluck('name')->map(fn ($role) => strtolower((string) $role))->all() ?? [];

                return count(array_intersect($roles, $technicalRoles)) > 0;
            })->values();

            return $filtered->isNotEmpty() ? $filtered : $users;
        } catch (\Throwable $e) {
            return $users;
        }
    }

    private function financeSummary(Collection $siteIds, Collection $sites): array
    {
        return app(UnifiedProjectRevenue::class)->summary($sites);
    }

    private function singleFinanceSummary(Site $site, ?Collection $exportedRequests = null, ?Collection $financeExpenses = null): array
    {
        $received = $this->projectReceivedAmount(collect([(int) $site->id]));

        $contract = app(UnifiedProjectRevenue::class)->contract($site);

        $laborCost = (float) ($site->labor_cost ?? 0);
        $transportCost = (float) ($site->transport_cost ?? 0);
        $otherCost = $financeExpenses !== null && Schema::hasTable('project_finance_expenses')
            ? (float) $financeExpenses->sum('amount')
            : (float) ($site->other_cost ?? 0);
        $manualExpense = $laborCost + $transportCost + $otherCost;
        $warehouseCost = (float) ($exportedRequests ?? $this->exportedMaterialRequests($site))->sum('total_cost');
        $cost = $manualExpense + $warehouseCost;

        return [
            'contract' => $contract,
            'received' => $received,
            'debt' => max(0, $contract - $received),
            'cost' => $cost,
            'warehouse_cost' => $warehouseCost,
            'manual_expense' => $manualExpense,
            'labor_cost' => $laborCost,
            'transport_cost' => $transportCost,
            'other_cost' => $otherCost,
            'profit' => $contract - $cost,
            'profit_margin' => $contract > 0 ? (($contract - $cost) / $contract) * 100 : 0.0,
        ];
    }

    private function exportedMaterialRequests(Site $site): Collection
    {
        if (! Schema::hasTable('material_requests')
            || ! Schema::hasColumn('material_requests', 'site_id')
            || ! Schema::hasColumn('material_requests', 'status')) {
            return collect();
        }

        $query = DB::table('material_requests as mr')
            ->where('mr.site_id', $site->id)
            ->where('mr.status', MaterialRequestStatus::EXPORTED->value)
            ->orderByDesc('mr.id');

        $select = ['mr.id', 'mr.status'];
        foreach (['note', 'created_at', 'updated_at', 'warehouse_id', 'total_cost'] as $column) {
            if (Schema::hasColumn('material_requests', $column)) {
                $select[] = 'mr.'.$column;
            }
        }

        if (Schema::hasTable('crm_warehouses') && Schema::hasColumn('material_requests', 'warehouse_id')) {
            $query->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'mr.warehouse_id');
            $select[] = 'warehouse.name as warehouse_name';
        }

        $rows = $query->select($select)->get();
        if ($rows->isEmpty() || ! Schema::hasTable('material_request_items')) {
            return $rows;
        }

        $lineQuery = DB::table('material_request_items')
            ->whereIn('material_request_id', $rows->pluck('id')->all())
            ->selectRaw('material_request_id, COUNT(*) as item_count');

        if (Schema::hasColumn('material_request_items', 'line_total')) {
            $lineQuery->selectRaw('COALESCE(SUM(line_total), 0) as line_cost');
        }

        $lineSummary = $lineQuery->groupBy('material_request_id')->get()->keyBy('material_request_id');

        return $rows->map(function ($row) use ($lineSummary) {
            $summary = $lineSummary->get($row->id);
            $row->item_count = (int) ($summary->item_count ?? 0);
            $savedCost = (float) ($row->total_cost ?? 0);
            $row->total_cost = $savedCost > 0 ? $savedCost : (float) ($summary->line_cost ?? 0);

            return $row;
        });
    }

    private function projectFinanceExpenses(Site $site): Collection
    {
        if (! Schema::hasTable('project_finance_expenses')) {
            return collect();
        }

        return DB::table('project_finance_expenses as e')
            ->leftJoin('users as creator', 'creator.id', '=', 'e.created_by')
            ->leftJoin('users as editor', 'editor.id', '=', 'e.updated_by')
            ->where('e.site_id', $site->id)
            ->where('e.status', 'ACTIVE')
            ->select('e.*', 'creator.name as creator_name', 'editor.name as editor_name')
            ->orderByDesc('e.expense_date')->orderByDesc('e.id')->get();
    }

    private function validateProjectExpense(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(['supplier', 'service', 'travel', 'rental', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'change_reason' => [$editing ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);
    }

    private function syncProjectOtherExpenseTotal(Site $site): void
    {
        if (! Schema::hasTable('project_finance_expenses') || ! Schema::hasColumn('sites', 'other_cost')) {
            return;
        }
        $total = (float) DB::table('project_finance_expenses')->where('site_id', $site->id)->where('status', 'ACTIVE')->sum('amount');
        DB::table('sites')->where('id', $site->id)->update(['other_cost' => $total, 'updated_at' => now()]);
        $site->setAttribute('other_cost', $total);
    }

    private function financeAudit(Site $site, string $entityType, string $source, int $entityId, array $before, array $after, string $reason, int $userId): void
    {
        if (! Schema::hasTable('project_finance_audits')) {
            return;
        }
        DB::table('project_finance_audits')->insert([
            'site_id' => (int) $site->id, 'entity_type' => $entityType, 'source' => $source,
            'entity_id' => $entityId, 'before_data' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE), 'reason' => $reason,
            'changed_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Tổng thực thu của dự án, gộp dữ liệu mới + dữ liệu lịch sử mà không trùng receipt.
     */
    private function projectReceivedAmount(Collection $siteIds): float
    {
        return array_sum(app(UnifiedProjectRevenue::class)->receivedBySite($siteIds));
    }

    private function legacyContext(Site $site): array
    {
        $legacyId = (int) ($site->legacy_source_id ?? 0);
        if (($site->legacy_source ?? null) !== 'project_test' || $legacyId <= 0 || ! Schema::hasTable('project_test_projects')) {
            return [];
        }

        $project = DB::table('project_test_projects')->where('id', $legacyId)->first();
        if (! $project) {
            return [];
        }

        $context = ['project' => $project];
        foreach ([
            'survey' => ['project_test_surveys', false],
            'proposal' => ['project_test_proposals', false],
            'acceptance' => ['project_test_acceptances', false],
            'warranty' => ['project_test_warranties', false],
            'logs' => ['project_test_daily_logs', true],
            'material_requests' => ['project_test_material_requests', true],
            'assignments' => ['project_test_assignments', true],
            'histories' => ['project_test_histories', true],
        ] as $key => [$table, $many]) {
            if (! Schema::hasTable($table)) {
                $context[$key] = $many ? collect() : null;

                continue;
            }

            $query = DB::table($table)->where('project_id', $legacyId)->orderByDesc('id');
            $context[$key] = $many ? $query->limit(50)->get() : $query->first();
        }

        return $context;
    }

    /** @return array<string,int> */
    private function phaseWeights(): array
    {
        return ['init' => 10, 'design' => 20, 'prepare' => 20, 'execute' => 50, 'om' => 0];
    }

    private function approvedBaseForPhase(string $phase): int
    {
        $base = 0;
        foreach ($this->phaseWeights() as $key => $weight) {
            if ($key === $phase) {
                break;
            }
            $base += $weight;
        }

        return min(100, $base);
    }

    private function phaseApprovedEndpoint(string $phase): int
    {
        return min(100, $this->approvedBaseForPhase($phase) + ($this->phaseWeights()[$phase] ?? 0));
    }

    private function nextPhase(string $phase): string
    {
        $keys = array_keys(self::phases());
        $index = array_search($phase, $keys, true);
        if ($index === false || ! isset($keys[$index + 1])) {
            return $phase;
        }

        return $keys[$index + 1];
    }

    /** @return array{phase_percent:int,calculated:int,approved:int,base:int,weight:int,overridden:bool} */
    private function calculateProjectProgress(Site $site, string $phase, array $checklist): array
    {
        $phasePercent = max(0, min(100, (int) ($checklist['percent'] ?? 0)));
        $base = $this->approvedBaseForPhase($phase);
        $weight = (int) ($this->phaseWeights()[$phase] ?? 0);
        $approved = Schema::hasColumn('sites', 'approved_progress_percent')
            ? max($base, (int) ($site->approved_progress_percent ?? $base))
            : $base;

        $calculated = $phase === 'om'
            ? 100
            : (int) round($base + (($weight * $phasePercent) / 100));
        $calculated = max($approved, min(100, $calculated));

        $overridden = false;
        if (Schema::hasColumn('sites', 'progress_override_percent') && $site->progress_override_percent !== null) {
            $calculated = max(0, min(100, (int) $site->progress_override_percent));
            $overridden = true;
        }

        return [
            'phase_percent' => $phasePercent,
            'calculated' => $calculated,
            'approved' => min(100, $approved),
            'base' => $base,
            'weight' => $weight,
            'overridden' => $overridden,
        ];
    }

    private function syncCalculatedProgress(Site $site, array $progress): void
    {
        $data = $this->filterColumns('sites', [
            'phase_progress_percent' => (int) $progress['phase_percent'],
            'calculated_progress_percent' => (int) $progress['calculated'],
            'progress_percent' => (int) $progress['calculated'],
        ]);
        if ($data === []) {
            return;
        }

        $changed = array_filter($data, fn ($value, string $key): bool => (string) $site->getAttribute($key) !== (string) $value, ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            return;
        }

        DB::table('sites')->where('id', $site->id)->update($changed);
        foreach ($changed as $key => $value) {
            $site->setAttribute($key, $value);
        }
    }

    private function updateSiteFields(Site $site, array $data): void
    {
        $filtered = $this->filterColumns('sites', $data);
        if ($filtered === []) {
            return;
        }
        DB::table('sites')->where('id', $site->id)->update([...$filtered, 'updated_at' => now()]);
        foreach ($filtered as $key => $value) {
            $site->setAttribute($key, $value);
        }
    }

    private function currentPhaseReview(Site $site, string $phase): ?object
    {
        if (! Schema::hasTable('project_phase_reviews')) {
            return null;
        }

        return DB::table('project_phase_reviews as r')
            ->leftJoin('users as submitter', 'submitter.id', '=', 'r.submitted_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'r.reviewed_by')
            ->where('r.site_id', $site->id)
            ->where('r.phase', $phase)
            ->select('r.*', 'submitter.name as submitter_name', 'reviewer.name as reviewer_name')
            ->orderByDesc('r.id')
            ->first();
    }

    private function phaseApprovalStatus(Site $site, ?object $review): string
    {
        return match (strtolower((string) ($review->status ?? ''))) {
            'pending' => 'pending_approval',
            'revision' => 'revision',
            'approved' => 'approved',
            default => (string) ($site->project_phase_status ?? 'in_progress'),
        };
    }

    private function hasApprovedPhase(Site $site, string $phase): bool
    {
        if (Schema::hasTable('project_phase_reviews')) {
            return DB::table('project_phase_reviews')
                ->where('site_id', $site->id)
                ->where('phase', $phase)
                ->where('status', 'approved')
                ->exists();
        }

        return array_search($this->phaseOf($site), array_keys(self::phases()), true)
            > array_search($phase, array_keys(self::phases()), true);
    }

    /** @return array<string,mixed> */
    private function phaseSupportData(Site $site): array
    {
        $documents = collect();
        if (Schema::hasTable('solar_site_documents') && Schema::hasColumn('solar_site_documents', 'site_id')) {
            $documents = DB::table('solar_site_documents')->where('site_id', $site->id)
                ->when(Schema::hasColumn('solar_site_documents', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->get();
        }
        $tasks = collect();
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'site_id')) {
            $tasks = DB::table('tasks')->where('site_id', $site->id)->get();
        }
        $materials = collect();
        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'site_id')) {
            $materials = DB::table('material_requests')->where('site_id', $site->id)->get();
        }
        $maintenance = collect();
        if (Schema::hasTable('solar_maintenance_schedules') && Schema::hasColumn('solar_maintenance_schedules', 'site_id')) {
            $maintenance = DB::table('solar_maintenance_schedules')->where('site_id', $site->id)
                ->when(Schema::hasColumn('solar_maintenance_schedules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->get();
        }

        return [
            'documents' => $documents,
            'tasks' => $tasks,
            'materials' => $materials,
            'maintenance' => $maintenance,
            'legacy' => $this->legacyContext($site),
        ];
    }

    private function canSubmitProjectPhase(Site $site, $user): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return (int) ($site->lead_engineer_id ?? 0) === (int) ($user->id ?? 0);
    }

    private function canApproveProjectPhase($user): bool
    {
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }
        $roles = $this->roles($user);
        if (count(array_intersect($roles, [
            'admin', 'super_admin', 'management', 'director', 'general_director',
            'ban_giam_doc', 'giam_doc',
        ])) > 0) {
            return true;
        }

        if (method_exists($user, 'can')) {
            foreach (['projects.phase.approve', 'projects.approve'] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return false;
    }

    private function nextProjectCode(): string
    {
        $year = now()->format('Y');
        $prefix = 'DA-'.$year.'-';
        $last = 0;

        if (Schema::hasColumn('sites', 'project_code')) {
            $latest = DB::table('sites')
                ->where('project_code', 'like', $prefix.'%')
                ->orderByDesc('project_code')
                ->value('project_code');

            if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
                $last = (int) $matches[1];
            }
        }

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function history(Site $site, string $action, ?string $from, ?string $to, ?string $note): void
    {
        if (! Schema::hasTable('project_unified_histories')) {
            return;
        }

        DB::table('project_unified_histories')->insert([
            'site_id' => $site->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function filterColumns(string $table, array $data): array
    {
        return array_filter(
            $data,
            fn ($value, string $column) => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function activeCompanyId(Request $request): int
    {
        foreach (['company_id', 'ego_company_id', 'active_company_id', 'selected_company_id'] as $key) {
            $value = $key === 'company_id'
                ? (int) $request->input($key, 0)
                : (int) session($key, 0);
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function roles($user): array
    {
        $roles = collect();
        if (method_exists($user, 'getRoleNames')) {
            try {
                $roles = $user->getRoleNames();
            } catch (\Throwable $e) {
            }
        }

        foreach (['role', 'type', 'position'] as $field) {
            if (! empty($user->{$field}) && is_scalar($user->{$field})) {
                $roles->push((string) $user->{$field});
            }
        }

        return $roles
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function canManage($user): bool
    {
        $roles = $this->roles($user);

        return ((int) ($user->is_admin ?? 0) === 1)
            || count(array_intersect($roles, [
                'admin', 'super_admin', 'management', 'director', 'general_director',
                'ban_giam_doc', 'giam_doc', 'technical_manager', 'technical_leader',
                'truong_phong_ky_thuat',
            ])) > 0;
    }

    private function canCreate($user): bool
    {
        if (app(UnifiedProjectAccess::class)->isSalesScoped($user)) { return true; }
        if ($this->canManage($user)) {
            return true;
        }

        return count(array_intersect($this->roles($user), [
            'technical', 'technician', 'engineer', 'engineering',
        ])) > 0;
    }

    private function canViewFinance($user): bool
    {
        if (! $user) {
            return false;
        }

        // Finance/management keep their existing company-wide scope.
        if ($this->hasGlobalFinanceScope($user)) {
            return true;
        }

        // Sales may read finance only after canonical project ownership has been
        // validated by abortIfCannotView / EnsureUnifiedProjectAccess.
        return app(UnifiedProjectAccess::class)->isSalesScoped($user);
    }

    private function canWriteFinance($user): bool
    {
        if (! $user) {
            return false;
        }

        // Sales access to project finance is deliberately read-only.
        if (app(UnifiedProjectAccess::class)->isSalesScoped($user)) {
            return false;
        }

        return $this->hasGlobalFinanceScope($user);
    }

    private function hasGlobalFinanceScope($user): bool
    {
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }

        $roles = $this->roles($user);
        $financeRoles = [
            'admin', 'super_admin',
            'management', 'director', 'general_director', 'ban_giam_doc', 'giam_doc',
            'accounting', 'ketoan', 'ke_toan', 'chief_accountant', 'ke_toan_truong',
            'accounting_manager', 'finance', 'finance_manager',
        ];

        if (count(array_intersect($roles, $financeRoles)) > 0) {
            return true;
        }

        // Permission tài chính chuyên biệt được xem là quyền phạm vi toàn công ty.
        if (method_exists($user, 'can')) {
            foreach (['page.finance', 'finance.view', 'projects.finance.view'] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $exception) {
                    // Không làm gián đoạn request nếu permission chưa tồn tại.
                }
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function financialDocumentCategories(): array
    {
        return ['invoice', 'payment', 'financial', 'accounting'];
    }

    private function scopeLabel($user): string
    {
        if (app(UnifiedProjectAccess::class)->isSalesScoped($user)) {
            return app(UnifiedProjectAccess::class)->isSalesManager($user)
                ? 'Công trình do Sales tạo trong công ty'
                : 'Công trình do bạn tạo hoặc phụ trách Sales';
        }
        if ($this->canManage($user)) {
            return 'Toàn bộ dự án theo công ty đang chọn';
        }
        if ($this->hasGlobalFinanceScope($user)) {
            return 'Dự án theo quyền tài chính';
        }
        if ($this->canWarehouse($user)) {
            return 'Dự án có nhu cầu vật tư cần Kho phối hợp';
        }

        return 'Dự án bạn phụ trách hoặc được giao việc';
    }

    private function abortIfCannotView(Site $site, $user): void
    {
        if (app(UnifiedProjectAccess::class)->isSalesScoped($user)) {
            abort_unless(app(UnifiedProjectAccess::class)->ownsSalesSite($site, $user), 403);
            return;
        }
        if ($this->canManage($user) || $this->hasGlobalFinanceScope($user) || $this->canWarehouse($user)) {
            return;
        }

        $userId = (int) $user->id;
        $allowed = (int) ($site->lead_engineer_id ?? 0) === $userId
            || (int) ($site->created_by ?? 0) === $userId;

        if (! $allowed && Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'site_id')) {
            $allowed = DB::table('tasks')
                ->where('site_id', $site->id)
                ->where('assignee_id', $userId)
                ->exists();
        }

        if (! $allowed && Schema::hasTable('project_workflow_steps') && Schema::hasTable('project_workflow_assignments')) {
            $allowed = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as s', 's.id', '=', 'a.workflow_step_id')
                ->where('s.site_id', $site->id)
                ->where('a.user_id', $userId)
                ->where('a.is_active', 1)
                ->exists();
        }

        abort_unless($allowed, 403, 'Bạn chưa được phân công vào dự án này.');
    }
}
