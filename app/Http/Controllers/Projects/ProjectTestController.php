<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use App\Models\ProjectTest\Acceptance;
use App\Models\ProjectTest\Assignment;
use App\Models\ProjectTest\DailyLog;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\MaterialRequest;
use App\Models\ProjectTest\Project;
use App\Models\ProjectTest\Proposal;
use App\Models\ProjectTest\Survey;
use App\Models\ProjectTest\Warranty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectTestController extends Controller
{
    public const REQUEST_SOURCES = [
        'sales' => 'Kinh doanh bàn giao',
        'technical' => 'Phòng Kỹ thuật tự tạo',
        'cskh' => 'CSKH chuyển yêu cầu',
        'internal' => 'Nội bộ công ty',
        'warranty' => 'Bảo trì / Bảo hành',
    ];

    public const PROJECT_TYPES = [
        'commercial' => 'Công trình thương mại',
        'survey' => 'Khảo sát / Đo kiểm',
        'internal' => 'Công trình nội bộ',
        'maintenance' => 'Bảo trì định kỳ',
        'warranty' => 'Bảo hành / Sự cố',
        'inspection' => 'Kiểm tra chất lượng',
        'repair' => 'Sửa chữa kỹ thuật',
        'support' => 'Hỗ trợ hiện trường',
    ];

    public const STATUSES = [
        'request_new' => ['label' => 'Yêu cầu mới · Chờ Kỹ thuật tiếp nhận', 'group' => 'Tiếp nhận', 'progress' => 4, 'owner' => 'technical_manager'],
        'request_accepted' => ['label' => 'Đã tiếp nhận · Chờ lập lịch khảo sát', 'group' => 'Tiếp nhận', 'progress' => 8, 'owner' => 'technical_manager'],
        'survey_pending' => ['label' => 'Chờ xác nhận lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 12, 'owner' => 'technical_manager'],
        'survey_reschedule' => ['label' => 'Chờ cập nhật lại lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 14, 'owner' => 'technical_manager'],
        'survey_confirmed' => ['label' => 'Đã xác nhận lịch khảo sát', 'group' => 'Khảo sát', 'progress' => 18, 'owner' => 'technical'],
        'survey_in_progress' => ['label' => 'Đang khảo sát', 'group' => 'Khảo sát', 'progress' => 25, 'owner' => 'technical'],
        'customer_confirmation' => ['label' => 'Chờ xác nhận phương án / triển khai', 'group' => 'Phương án', 'progress' => 35, 'owner' => 'technical_manager'],
        'sales_review' => ['label' => 'Chờ xác nhận phương án / triển khai', 'group' => 'Phương án', 'progress' => 38, 'owner' => 'technical_manager'],
        'proposal_revision' => ['label' => 'Phương án cần điều chỉnh', 'group' => 'Phương án', 'progress' => 32, 'owner' => 'technical'],
        'installation_pending' => ['label' => 'Chờ Kỹ thuật sắp lịch thi công', 'group' => 'Phương án', 'progress' => 43, 'owner' => 'technical_manager'],
        'installation_reschedule' => ['label' => 'Chờ cập nhật lại lịch thi công', 'group' => 'Phương án', 'progress' => 41, 'owner' => 'technical_manager'],
        'materials_pending' => ['label' => 'Chờ Kỹ thuật đề xuất vật tư', 'group' => 'Vật tư', 'progress' => 50, 'owner' => 'technical'],
        'materials_admin_review' => ['label' => 'Chờ Admin duyệt vật tư', 'group' => 'Vật tư', 'progress' => 56, 'owner' => 'admin'],
        'materials_revision' => ['label' => 'Vật tư cần điều chỉnh', 'group' => 'Vật tư', 'progress' => 53, 'owner' => 'technical'],
        'warehouse_preparing' => ['label' => 'Kho đang chuẩn bị hàng', 'group' => 'Vật tư', 'progress' => 63, 'owner' => 'warehouse'],
        'warehouse_issued' => ['label' => 'Đã xuất kho công trình', 'group' => 'Thi công', 'progress' => 70, 'owner' => 'technical_manager'],
        'assignment_pending' => ['label' => 'Chờ Trưởng phòng Kỹ thuật phân công', 'group' => 'Thi công', 'progress' => 72, 'owner' => 'technical_manager'],
        'ready_install' => ['label' => 'Sẵn sàng thi công', 'group' => 'Thi công', 'progress' => 76, 'owner' => 'technical'],
        'installing' => ['label' => 'Đang thi công', 'group' => 'Thi công', 'progress' => 84, 'owner' => 'technical'],
        'acceptance_pending' => ['label' => 'Chờ nghiệm thu', 'group' => 'Nghiệm thu', 'progress' => 93, 'owner' => 'technical_manager'],
        'warranty_active' => ['label' => 'Đã nghiệm thu · Đang bảo hành', 'group' => 'Bảo hành', 'progress' => 100, 'owner' => 'technical'],
        'completed' => ['label' => 'Đã kết thúc', 'group' => 'Bảo hành', 'progress' => 100, 'owner' => 'none'],
        'cancelled' => ['label' => 'Đã hủy', 'group' => 'Dừng', 'progress' => 0, 'owner' => 'none'],
    ];


    public const PHASES = [
        'survey' => [
            'label' => 'Lịch khảo sát',
            'description' => 'Tiếp nhận yêu cầu, xác nhận lịch, check-in hiện trường và hoàn tất biên bản khảo sát.',
            'statuses' => ['request_new', 'request_accepted', 'survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress'],
            'icon' => 'bi-geo-alt',
        ],
        'design' => [
            'label' => 'Khảo sát & phương án',
            'description' => 'Theo dõi hồ sơ khảo sát, file 3D/CAD, phương án kỹ thuật và bước xác nhận triển khai.',
            'statuses' => ['customer_confirmation', 'sales_review', 'proposal_revision'],
            'icon' => 'bi-badge-3d',
        ],
        'materials' => [
            'label' => 'Đề xuất vật tư',
            'description' => 'Bóc tách vật tư, trình duyệt, chuẩn bị kho và bàn giao vật tư theo công trình.',
            'statuses' => ['installation_pending', 'installation_reschedule', 'materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing', 'warehouse_issued'],
            'icon' => 'bi-box-seam',
        ],
        'assignment' => [
            'label' => 'Phân công kỹ thuật',
            'description' => 'Trưởng phòng phân công người/ca, xác nhận lịch và điều phối đội thi công.',
            'statuses' => ['warehouse_issued', 'assignment_pending', 'ready_install'],
            'icon' => 'bi-people',
        ],
        'installation' => [
            'label' => 'Thi công',
            'description' => 'Theo dõi nhật ký, tiến độ, file hiện trường, vật tư sử dụng và phát sinh.',
            'statuses' => ['ready_install', 'installing'],
            'icon' => 'bi-tools',
        ],
        'acceptance' => [
            'label' => 'Nghiệm thu',
            'description' => 'Hoàn thiện biên bản nghiệm thu, hồ sơ hoàn công, chữ ký và bàn giao khách hàng.',
            'statuses' => ['acceptance_pending'],
            'icon' => 'bi-clipboard-check',
        ],
        'warranty' => [
            'label' => 'Bảo hành',
            'description' => 'Công trình đã bàn giao, đang theo dõi O&M và bảo hành sau bán.',
            'statuses' => ['warranty_active', 'completed'],
            'icon' => 'bi-shield-check',
        ],
    ];

    public const WORKSPACE_STAGES = [
        // EGO_PROJECT_WORKFLOW_360_V2_STAGES
        'flow-intake' => [
            'label' => 'Tiếp nhận',
            'description' => 'Yêu cầu mới, kiểm tra đầu vào, tiếp nhận và phân công khảo sát.',
            'statuses' => ['request_new', 'request_accepted'],
            'icon' => 'bi-inbox',
        ],
        'flow-survey' => [
            'label' => 'Khảo sát',
            'description' => 'Lên lịch, khảo sát hiện trường, nộp và duyệt báo cáo khảo sát.',
            'statuses' => ['survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress'],
            'icon' => 'bi-calendar-check',
        ],
        'flow-proposal' => [
            'label' => 'Phương án',
            'description' => 'Lập phương án, Sales theo dõi khách hàng và xác nhận lịch triển khai dự kiến.',
            'statuses' => ['customer_confirmation', 'sales_review', 'proposal_revision', 'installation_pending', 'installation_reschedule'],
            'icon' => 'bi-badge-3d',
        ],
        'flow-materials' => [
            'label' => 'Chuẩn bị vật tư',
            'description' => 'Kỹ thuật lập nhu cầu, Admin duyệt và Kho ghép hàng thực tế.',
            'statuses' => ['materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing'],
            'icon' => 'bi-box-seam',
        ],
        'flow-installation' => [
            'label' => 'Thi công',
            'description' => 'Xuất vật tư, phân công đội, chốt lịch và triển khai thi công tại hiện trường.',
            'statuses' => ['warehouse_issued', 'assignment_pending', 'ready_install', 'installing'],
            'icon' => 'bi-tools',
        ],
        'flow-acceptance' => [
            'label' => 'Nghiệm thu',
            'description' => 'Hoàn thiện hồ sơ, ký nghiệm thu và bàn giao công trình.',
            'statuses' => ['acceptance_pending'],
            'icon' => 'bi-clipboard-check',
        ],
        'flow-warranty' => [
            'label' => 'Bảo hành',
            'description' => 'Theo dõi O&M, sự cố và bảo hành sau bàn giao.',
            'statuses' => ['warranty_active', 'completed'],
            'icon' => 'bi-shield-check',
        ],        'new-requests' => [
            'label' => 'Yêu cầu mới',
            'description' => 'Yêu cầu Kỹ thuật mới từ Sales, CSKH, nội bộ hoặc do Phòng Kỹ thuật tự tạo đang chờ tiếp nhận.',
            'statuses' => ['request_new', 'request_accepted'],
            'icon' => 'bi-inbox',
        ],
        'waiting-survey' => [
            'label' => 'Chờ khảo sát',
            'description' => 'Các công trình đang chờ xác nhận lịch, đổi lịch hoặc đã có lịch nhưng chưa bắt đầu khảo sát.',
            'statuses' => ['request_accepted', 'survey_pending', 'survey_reschedule', 'survey_confirmed'],
            'icon' => 'bi-calendar2-check',
        ],
        'waiting-design' => [
            'label' => 'Chờ phương án kỹ thuật',
            'description' => 'Các công trình đang khảo sát, chờ hoàn thiện phương án hoặc cần chỉnh sửa file kỹ thuật.',
            'statuses' => ['survey_in_progress', 'customer_confirmation', 'sales_review', 'proposal_revision'],
            'icon' => 'bi-badge-3d',
        ],
        'waiting-materials' => [
            'label' => 'Chờ vật tư',
            'description' => 'Các công trình đang bóc tách, trình duyệt, điều chỉnh hoặc chờ Kho chuẩn bị vật tư.',
            'statuses' => ['materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing'],
            'icon' => 'bi-box-seam',
        ],
        'waiting-installation' => [
            'label' => 'Chờ thi công',
            'description' => 'Các công trình đã bước vào giai đoạn chuẩn bị lịch, xuất vật tư và phân công đội thi công.',
            'statuses' => ['installation_pending', 'installation_reschedule', 'warehouse_issued', 'assignment_pending', 'ready_install'],
            'icon' => 'bi-calendar2-week',
        ],
        'installing' => [
            'label' => 'Đang thi công',
            'description' => 'Các công trình đang triển khai tại hiện trường và cần cập nhật nhật ký, tiến độ, phát sinh.',
            'statuses' => ['installing'],
            'icon' => 'bi-tools',
        ],
        'waiting-acceptance' => [
            'label' => 'Chờ nghiệm thu',
            'description' => 'Các công trình đã hoàn thành thi công và đang chờ hồ sơ nghiệm thu, bàn giao khách hàng.',
            'statuses' => ['acceptance_pending'],
            'icon' => 'bi-clipboard-check',
        ],
        'warranty' => [
            'label' => 'Đang bảo hành',
            'description' => 'Các công trình đã bàn giao và đang theo dõi lịch O&M, sự cố và bảo hành sau bán.',
            'statuses' => ['warranty_active'],
            'icon' => 'bi-shield-check',
        ],
        'survey-schedule' => [
            'label' => 'Lịch khảo sát',
            'description' => 'Điều phối toàn bộ lịch khảo sát: chờ duyệt, đổi lịch, đã xác nhận và đang thực hiện.',
            'statuses' => ['request_accepted', 'survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress'],
            'icon' => 'bi-geo-alt',
        ],
        'installation-schedule' => [
            'label' => 'Lịch thi công',
            'description' => 'Theo dõi công trình chờ lịch, đổi lịch, chờ phân công, sẵn sàng và đang thi công.',
            'statuses' => ['installation_pending', 'installation_reschedule', 'assignment_pending', 'ready_install', 'installing'],
            'icon' => 'bi-calendar-week',
        ],
        'assignments' => [
            'label' => 'Phân công nhân sự',
            'description' => 'Các công trình đã có vật tư hoặc đang chờ trưởng phòng phân công người, ca và đội thi công.',
            'statuses' => ['warehouse_issued', 'assignment_pending', 'ready_install'],
            'icon' => 'bi-people',
        ],
    ];


    public function index(Request $request)
    {
        // EGO_WORKSPACE_PROJECT_REDIRECT_V1
        $workspace = (string) session('ego.workspace.active.v1', '');
        if ($workspace === 'sales') {
            return redirect()->route('sales-projects.index', $request->query());
        }
        if ($workspace === 'technical') {
            return redirect()->route('technical-projects.all', $request->query());
        }

        return $this->renderIndex($request);
    }

    public function salesIndex(Request $request)
    {
        $this->authorizeSalesRole();
        $request->attributes->set('page_mode', 'sales');
        $request->query->set('source_scope', 'sales');

        return $this->renderIndex($request);
    }

    public function technicalFromSalesIndex(Request $request)
    {
        $this->authorizeTechnicalWorkspaceRole();
        $request->attributes->set('page_mode', 'technical-from-sales');
        $request->query->set('source_scope', 'sales');

        return $this->renderIndex($request);
    }

    public function technicalCreatedIndex(Request $request)
    {
        $this->authorizeTechnicalWorkspaceRole();
        $request->attributes->set('page_mode', 'technical-created');
        $request->query->set('source_scope', 'non-sales');

        return $this->renderIndex($request);
    }

    public function technicalAllIndex(Request $request)
    {
        $this->authorizeTechnicalWorkspaceRole();
        $request->attributes->set('page_mode', 'technical-all');

        return $this->renderIndex($request);
    }

    public function deploymentPlanIndex(Request $request)
    {
        $this->authorizeTechnicalWorkspaceRole();
        $request->attributes->set('page_mode', 'deployment-plan');

        return $this->renderIndex($request);
    }

    public function acceptanceHandoverIndex(Request $request)
    {
        $this->authorizeTechnicalWorkspaceRole();
        $request->attributes->set('page_mode', 'acceptance-handover');

        return $this->renderIndex($request);
    }

    private function renderIndex(Request $request)
    {
        $this->authorizePermission('project-test.access');

        $user = $request->user();
        if ($user->hasAnyRole(['warehouse', 'kho']) && ! $user->hasAnyRole(['admin', 'management', 'technical_manager', 'sales_manager', 'sales', 'ky_thuat'])) {
            return redirect()->route('project-test.warehouse.index');
        }

        $pageMode = (string) $request->attributes->get('page_mode', 'all');
        $sourceScope = trim((string) $request->query('source_scope'));

        $query = Project::query()
            ->visibleTo($user);

        // EGO_TECHNICAL_CROSS_COMPANY_VISIBILITY_V2
        // Kỹ thuật được nhìn công trình của tất cả pháp nhân.
        // Các phòng ban khác vẫn giữ company scope cũ.
        $technicalCrossCompany = $user->hasAnyRole([
            'ky_thuat',
            'technical',
            'technician',
            'technical_staff',
            'technical_leader',
            'technical_manager',
            'truong_phong_ky_thuat',
            'maintenance',
            'bao_hanh',
        ]);

        if (! $technicalCrossCompany) {
            $query->where('company_id', EgoCompanyLock::id());
        }

        $query
            ->with([
                'salesUser:id,name',
                'technicalManager:id,name',
                'leadTechnician:id,name',
                'salesOrder:id,order_code,total_amount,created_by',
                'latestMaterialRequest' => function ($query): void {
                    $query->select([
                        'project_test_material_requests.id',
                        'project_test_material_requests.project_id',
                        'project_test_material_requests.status',
                        'project_test_material_requests.code',
                    ]);
                },
            ])
            ->latest('id');

        $this->applySourceScope($query, $sourceScope);
        $this->applyPageModeScope($query, $pageMode);

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_phone', 'like', "%{$keyword}%")
                    ->orWhere('contract_reference', 'like', "%{$keyword}%");
            });
        }

        $phase = trim((string) $request->query('phase'));
        $phaseInfo = self::PHASES[$phase] ?? null;
        $workspaceStage = trim((string) $request->query('workspace_stage'));
        $workspaceStageInfo = self::WORKSPACE_STAGES[$workspaceStage] ?? null;

        if ($workspaceStageInfo) {
            $query->whereIn('status', $workspaceStageInfo['statuses']);
            $phaseInfo = $workspaceStageInfo;
            $phase = '';
        } elseif ($phaseInfo) {
            $query->whereIn('status', $phaseInfo['statuses']);
        }

        if ($status = $request->query('status')) {
            if (isset(self::STATUSES[$status])) {
                $query->where('status', $status);
            }
        }

        $projects = $query->paginate(18)->withQueryString();
        $visible = Project::query()->visibleTo($user);

        if (! $technicalCrossCompany) {
            $visible->where('company_id', EgoCompanyLock::id());
        }
        $this->applySourceScope($visible, $sourceScope);
        $this->applyPageModeScope($visible, $pageMode);

        $statusCounts = (clone $visible)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $stageCounts = collect(self::WORKSPACE_STAGES)->mapWithKeys(function (array $stage, string $key) use ($statusCounts): array {
            $total = collect($stage['statuses'])->sum(fn (string $status): int => (int) ($statusCounts[$status] ?? 0));
            return [$key => $total];
        })->all();

        $kpis = [
            'total' => (clone $visible)->count(),
            'mine' => (clone $visible)->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('sales_user_id', $user->id)
                    ->orWhere('lead_technician_id', $user->id)
                    ->orWhereHas('assignments', fn (Builder $a) => $a->where('user_id', $user->id));
            })->count(),
            'waiting' => (clone $visible)->whereIn('status', ['request_new', 'request_accepted', 'survey_pending', 'customer_confirmation', 'sales_review', 'installation_pending', 'materials_admin_review', 'assignment_pending'])->count(),
            'installing' => (clone $visible)->whereIn('status', ['ready_install', 'installing', 'acceptance_pending'])->count(),
            'warranty' => (clone $visible)->where('status', 'warranty_active')->count(),
            'from_sales' => (clone $visible)->where('request_source', 'sales')->count(),
            'technical_created' => (clone $visible)->where('request_source', '!=', 'sales')->count(),
        ];

        // EGO_SALES_FINANCE_LIST_V1
        $showFinancialsList = $pageMode === 'sales'
            && $user->hasAnyRole(['admin', 'management', 'manager', 'sales_manager', 'sales', 'sales_staff']);
        $financeKpis = [
            'revenue' => 0.0,
            'collected' => 0.0,
            'receivable' => 0.0,
        ];
        if ($showFinancialsList) {
            $financeRow = (clone $visible)
                ->selectRaw('COALESCE(SUM(COALESCE(contract_amount,0) + COALESCE(extra_revenue,0)),0) as revenue')
                ->selectRaw('COALESCE(SUM(COALESCE(amount_collected,0)),0) as collected')
                ->first();
            $financeKpis['revenue'] = (float) ($financeRow->revenue ?? 0);
            $financeKpis['collected'] = (float) ($financeRow->collected ?? 0);
            $financeKpis['receivable'] = max(0, $financeKpis['revenue'] - $financeKpis['collected']);
        }

        return view('project-test.index', [
            'projects' => $projects,
            'kpis' => $kpis,
            'statuses' => self::STATUSES,
            'phases' => self::PHASES,
            'phase' => $phase,
            'phaseInfo' => $phaseInfo,
            'workspaceStages' => self::WORKSPACE_STAGES,
            'workspaceStage' => $workspaceStage,
            'workspaceStageInfo' => $workspaceStageInfo,
            'roleLabel' => $this->roleLabel($user),
            'requestSources' => self::REQUEST_SOURCES,
            'projectTypes' => self::PROJECT_TYPES,
            'pageMode' => $pageMode,
            'sourceScope' => $sourceScope,
            'stageCounts' => $stageCounts,            'showFinancialsList' => $showFinancialsList,
            'financeKpis' => $financeKpis,
        ]);
    }

    private function applySourceScope(Builder $query, string $sourceScope): void
    {
        if ($sourceScope === 'sales') {
            $query->where('request_source', 'sales');
        } elseif ($sourceScope === 'non-sales') {
            $query->where('request_source', '!=', 'sales');
        }
    }

    private function applyPageModeScope(Builder $query, string $pageMode): void
    {
        if ($pageMode === 'deployment-plan') {
            $query->whereIn('status', [
                'installation_pending', 'installation_reschedule', 'materials_pending',
                'materials_admin_review', 'materials_revision', 'warehouse_preparing',
                'warehouse_issued', 'assignment_pending', 'ready_install', 'installing',
            ]);
        } elseif ($pageMode === 'acceptance-handover') {
            $query->whereIn('status', ['acceptance_pending', 'warranty_active', 'completed']);
        }
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasAnyRole(['sales', 'sales_staff', 'sales_manager']) && ! $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader'])) {
            return redirect()->route('sales-projects.create');
        }

        if ($user->hasAnyRole(['admin', 'technical_manager', 'technical_leader'])) {
            return redirect()->route('technical-projects.create');
        }

        $this->authorizeCreateProject();

        return redirect()->route('sales-projects.create');
    }

    public function createSales(Request $request)
    {
        $this->authorizeSalesRole();
        $user = $request->user();

        return view('sales.projects.create', array_merge($this->formOptions(), [
            'defaultSalesId' => $user->id,
            'activeCompanyId' => (int) session('active_company_id', 0),
            'salesProjectTypes' => Arr::only(self::PROJECT_TYPES, ['commercial', 'survey']),
        ]));
    }

    public function createTechnical(Request $request)
    {
        $this->authorizeTechnicalCreator();
        $user = $request->user();

        return view('technical.projects.create', array_merge($this->formOptions(), [
            'defaultTechnicalManagerId' => $user->hasAnyRole(['technical_manager', 'technical_leader']) ? $user->id : null,
            'activeCompanyId' => (int) session('active_company_id', 0),
            'technicalRequestSources' => Arr::except(self::REQUEST_SOURCES, ['sales']),
            'technicalProjectTypes' => self::PROJECT_TYPES,
        ]));
    }

    public function storeSales(Request $request)
    {
        $this->authorizeSalesRole();
        $request->attributes->set('intake_mode', 'sales');
        $request->merge([
            'request_source' => 'sales',
            'sales_user_id' => $request->user()->id,
            'customer_confirmation_required' => true,
        ]);

        return $this->store($request);
    }

    public function storeTechnical(Request $request)
    {
        $this->authorizeTechnicalCreator();
        $request->attributes->set('intake_mode', 'technical');
        $request->merge([
            'sales_user_id' => null,
            'request_source' => in_array((string) $request->input('request_source'), ['technical', 'internal', 'warranty', 'cskh'], true)
                ? (string) $request->input('request_source')
                : 'technical',
        ]);

        return $this->store($request);
    }

    public function store(Request $request)
    {
        $this->authorizeCreateProject();

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'sales_order_id' => ['nullable', 'integer'],
            'contract_reference' => ['nullable', 'string', 'max:180'],
            'contract_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'extra_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'financial_note' => ['nullable', 'string', 'max:5000'],
            'handover_note' => ['nullable', 'string'],
            'request_source' => ['required', Rule::in(array_keys(self::REQUEST_SOURCES))],
            'project_type' => ['required', Rule::in(array_keys(self::PROJECT_TYPES))],
            'customer_confirmation_required' => ['nullable', 'boolean'],
            'sales_user_id' => ['nullable', 'integer'],
            'technical_manager_id' => ['nullable', 'integer'],
            'lead_technician_id' => ['nullable', 'integer', 'required_with:technician_ids'],
            'technician_ids' => ['nullable', 'array'],
            'technician_ids.*' => ['integer', 'distinct'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:700'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'customer_need' => ['required', 'string'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'estimated_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'proposed_survey_at' => ['nullable', 'date', 'after_or_equal:now'],
            'target_completion_at' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isSales = $user->hasAnyRole(['sales', 'sales_staff', 'sales_manager']);
        $isCustomerCare = $user->hasAnyRole(['cskh', 'customer_service', 'customer_care']);
        $isTechnicalManager = $user->hasAnyRole(['technical_manager', 'technical_leader']);
        $intakeMode = (string) $request->attributes->get('intake_mode', 'auto');

        $leadTechnicianId = ! empty($data['lead_technician_id'])
            ? (int) $data['lead_technician_id']
            : null;

        $collaboratorIds = collect($data['technician_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->reject(
                fn ($id) => $leadTechnicianId
                    && $id === $leadTechnicianId
            )
            ->unique()
            ->values();

        $technicianIds = collect([$leadTechnicianId])
            ->filter()
            ->merge($collaboratorIds)
            ->unique()
            ->values();

        $data['lead_technician_id'] = $leadTechnicianId;
        $data['technician_ids'] = $technicianIds->all();

        if ($intakeMode === 'sales' || ($isSales && ! $isAdmin && $intakeMode !== 'technical')) {
            $data['request_source'] = 'sales';
            if (! in_array($data['project_type'], ['commercial', 'survey'], true)) {
                $data['project_type'] = 'commercial';
            }
            $data['sales_user_id'] = $user->id;
            $data['customer_confirmation_required'] = true;
        } elseif ($intakeMode === 'technical') {
            // EGO_TECHNICAL_FINANCE_ZERO_V1
            $data['sales_user_id'] = null;
            $data['sales_order_id'] = null;
            $data['contract_reference'] = $data['contract_reference'] ?? null;
            $data['contract_amount'] = 0;
            $data['amount_collected'] = 0;
            $data['extra_revenue'] = 0;
            $data['labor_cost'] = 0;
            $data['transport_cost'] = 0;
            $data['other_cost'] = 0;
            $data['financial_note'] = null;
            if (! in_array($data['request_source'], ['technical', 'internal', 'warranty', 'cskh'], true)) {
                $data['request_source'] = 'technical';
            }
        } elseif ($isCustomerCare && ! $isAdmin && ! $isTechnicalManager) {
            $data['request_source'] = 'cskh';
        }

        if (! empty($data['sales_order_id'])) {
            $orderQuery = DB::table('crm_orders')
                ->where('id', (int) $data['sales_order_id'])
                ->where('company_id', EgoCompanyLock::id());
            if ($intakeMode === 'sales' && ! $user->hasAnyRole(['admin', 'sales_manager'])) {
                $orderQuery->where('created_by', $user->id);
            }
            abort_unless($orderQuery->exists(), 422, 'Đơn hàng không thuộc phạm vi tài khoản hoặc công ty hiện tại.');
        }

        if (! empty($data['sales_user_id'])) {
            $this->assertUserHasRole((int) $data['sales_user_id'], ['sales', 'sales_staff', 'sales_manager'], 'Người phụ trách khách hàng');
        }
        if (! empty($data['technical_manager_id'])) {
            $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager', 'technical_leader'], 'Trưởng phòng Kỹ thuật');
        }
        foreach ($data['technician_ids'] as $technicianId) {
            $this->assertUserHasRole((int) $technicianId, ['ky_thuat', 'technical', 'technician', 'technical_staff'], 'Kỹ thuật viên phụ trách');
        }

        // EGO_SALES_FINANCE_DEFAULTS_V1
        foreach (['contract_amount', 'amount_collected', 'extra_revenue', 'labor_cost', 'transport_cost', 'other_cost'] as $moneyField) {
            $data[$moneyField] = max(0, (float) ($data[$moneyField] ?? 0));
        }

        $confirmationRequired = $request->boolean('customer_confirmation_required');
        if (in_array($data['project_type'], ['internal', 'maintenance', 'inspection', 'repair', 'support'], true)) {
            $confirmationRequired = $request->has('customer_confirmation_required')
                ? $request->boolean('customer_confirmation_required')
                : false;
        }

        $project = DB::transaction(function () use ($request, $data, $user, $isAdmin, $isTechnicalManager, $confirmationRequired, $intakeMode) {
            $directTechnical = $intakeMode === 'technical' || $isAdmin || $isTechnicalManager;
            $hasConfirmedSchedule = $directTechnical
                && ! empty($data['proposed_survey_at'])
                && ! empty($data['lead_technician_id']);

            $initialStatus = $hasConfirmedSchedule
                ? 'survey_confirmed'
                : ($directTechnical && $data['request_source'] !== 'sales' ? 'request_accepted' : 'request_new');

            $project = Project::create(array_merge(Arr::except($data, ['customer_confirmation_required', 'technician_ids']), [
                'code' => $this->nextProjectCode(),
                'company_id' => ($data['company_id'] ?? null) ?: EgoCompanyLock::id(),
                'created_by' => $user->id,
                'sales_user_id' => ($data['sales_user_id'] ?? null) ?: ($intakeMode === 'sales' ? $user->id : null),
                'technical_manager_id' => ($data['technical_manager_id'] ?? null) ?: ($isTechnicalManager ? $user->id : null),
                'lead_technician_id' => $data['lead_technician_id'] ?? null,
                'customer_confirmation_required' => $confirmationRequired,
                'customer_confirmation_status' => $confirmationRequired ? 'pending' : 'not_required',
                'handover_at' => $data['request_source'] === 'sales' ? now() : null,
                'status' => $initialStatus,
                'current_owner_role' => self::STATUSES[$initialStatus]['owner'],
                'progress' => self::STATUSES[$initialStatus]['progress'],
                'survey_confirmed_at' => $hasConfirmedSchedule ? $data['proposed_survey_at'] : null,
            ]));

            Survey::create([
                'project_id' => $project->id,
                'reviewed_by' => $directTechnical ? $user->id : null,
                'surveyed_by' => $data['lead_technician_id'] ?? null,
                'schedule_status' => $hasConfirmedSchedule ? 'confirmed' : 'pending',
                'scheduled_at' => $data['proposed_survey_at'] ?? null,
            ]);

            foreach ($data['technician_ids'] as $index => $technicianId) {
                Assignment::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'user_id' => (int) $technicianId,
                    ],
                    [
                        'assigned_by' => $user->id,
                        'assignment_role' => $index === 0 ? 'lead' : 'member',
                        'work_date' => ! empty($data['proposed_survey_at'])
                            ? Carbon::parse($data['proposed_survey_at'])->toDateString()
                            : null,
                        'note' => 'Phân công ban đầu khi tạo công trình Kỹ thuật',
                    ]
                );
            }

            $sourceLabel = self::REQUEST_SOURCES[$data['request_source']] ?? $data['request_source'];
            $this->history(
                $project,
                $directTechnical && $data['request_source'] !== 'sales'
                    ? 'Phòng Kỹ thuật tạo và tiếp nhận yêu cầu: '.$sourceLabel
                    : 'Tạo yêu cầu Kỹ thuật từ nguồn: '.$sourceLabel,
                null,
                $initialStatus,
                [
                    'request_source' => $data['request_source'],
                    'project_type' => $data['project_type'],
                    'technician_ids' => $data['technician_ids'],
                ]
            );

            return $project;
        });

        return redirect()->route('project-test.show', $project)->with(
            'success',
            $project->status === 'survey_confirmed'
                ? 'Đã tạo yêu cầu và xác nhận lịch khảo sát.'
                : ($project->status === 'request_accepted'
                    ? 'Đã tạo và tiếp nhận yêu cầu Kỹ thuật.'
                    : 'Đã tạo yêu cầu Kỹ thuật, đang chờ Trưởng phòng tiếp nhận.')
        );
    }

    /**
     * Xóa mềm công trình khỏi danh sách.
     *
     * Chỉ Ban giám đốc / Admin / quản lý kỹ thuật được xóa. Dữ liệu nghiệp vụ
     * liên quan không bị xóa vật lý để có thể đối soát hoặc phục hồi khi cần.
     */
    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();

        $canDelete = $user && (
            $user->hasAnyRole([
                'admin',
                'management',
                'manager',
                'technical_manager',
                'technical_leader',
            ])
            || $user->can('project-test.admin')
        );

        abort_unless($canDelete, 403, 'Bạn không có quyền xóa công trình.');

        // Giữ cùng phạm vi nhìn thấy với màn Danh sách công trình.
        $canCrossCompany = $user->hasAnyRole([
            'technical_manager',
            'technical_leader',
        ]);

        $visible = Project::query()
            ->visibleTo($user)
            ->whereKey($project->getKey());

        if (! $canCrossCompany) {
            $visible->where('company_id', EgoCompanyLock::id());
        }

        abort_unless(
            $visible->exists(),
            403,
            'Bạn không có quyền xóa công trình ngoài phạm vi được phép xem.'
        );

        $projectCode = (string) ($project->code ?: ('#'.$project->id));
        $projectName = (string) ($project->name ?: 'Công trình');
        $projectStatus = (string) ($project->status ?: '');

        DB::transaction(function () use ($project, $projectCode, $projectName, $projectStatus): void {
            // Ghi lại lịch sử trước khi soft delete để vẫn truy vết được ai đã xóa.
            $this->history(
                $project,
                'Xóa mềm công trình',
                $projectStatus,
                $projectStatus,
                [
                    'project_code' => $projectCode,
                    'project_name' => $projectName,
                    'delete_mode' => 'soft_delete',
                    'deleted_at' => now()->toDateTimeString(),
                ]
            );

            // Không xóa hồ sơ con, vật tư, tài chính hay lịch sử O&M.
            // Chỉ ẩn hồ sơ công trình chính bằng SoftDeletes để bảo toàn dữ liệu nghiệp vụ.

            // Project dùng SoftDeletes nên thao tác này chỉ ghi deleted_at, không xóa vật lý.
            // Event deleted của model vẫn tự dọn các lịch kỹ thuật đồng bộ tương ứng.
            $project->delete();
        });

        return back()->with(
            'success',
            'Đã xóa công trình '.$projectCode.' - '.$projectName.'. Dữ liệu được lưu dạng xóa mềm để bảo toàn lịch sử.'
        );
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $project->load([
            'creator:id,name', 'salesUser:id,name', 'technicalManager:id,name', 'leadTechnician:id,name', 'salesOrder:id,order_code,total_amount,created_by',
            'survey.surveyor:id,name', 'proposal',
            'materialRequests.requester:id,name', 'materialRequests.reviewer:id,name', 'materialRequests.receiver:id,name',
            'materialRequests.items.product:id,name,sku,unit,is_serialized',
            'materialRequests.items.allocations.product:id,name,sku,unit,is_serialized',
            'materialRequests.items.allocations.warehouse:id,name,location',
            'materialAftercareRequests.requester:id,name', 'materialAftercareRequests.warehouseReviewer:id,name',
            'materialAftercareRequests.managerReviewer:id,name', 'materialAftercareRequests.processor:id,name',
            'materialAftercareRequests.items.materialItem.allocations', 'materialAftercareRequests.items.product:id,name,sku,unit,is_serialized',
            'materialAftercareRequests.items.warehouse:id,name,location',
            'materialAftercareRequests.items.replacementProduct:id,name,sku,unit,is_serialized',
            'materialAftercareRequests.items.replacementWarehouse:id,name,location',
            'materialAftercareRequests.linkedMaterialRequest:id,project_id,code,status,warehouse_status,request_kind,parent_request_id,aftercare_request_id',
            'assignments.user:id,name', 'dailyLogs.author:id,name', 'acceptance', 'warranty', 'histories.user:id,name',
            'expenses.creator:id,name', 'expenses.confirmer:id,name', 'expenses.rejecter:id,name', 'expenses.canceller:id,name',
        ]);

        // EGO_MATERIAL_FASTFLOW_V9: nạp tồn kho/giá vốn bằng số truy vấn cố định, không query từng dòng.
        $inventoryAware = $request->user()->hasAnyRole(['admin', 'management', 'manager', 'accounting', 'warehouse', 'kho']);
        if (! $inventoryAware) {
            try {
                $inventoryAware = $request->user()->can('finance.project_profit.view');
            } catch (\Throwable) {
                $inventoryAware = false;
            }
        }
        if ($inventoryAware) {
            $this->hydrateMaterialInventorySnapshots($project);
        }

        // EGO_PROJECT_FINANCE_UI_V2
        $showFinancials = $this->canViewProjectFinancials($request->user(), $project);
        $showProjectProfit = $showFinancials && $this->canViewProjectProfit($request->user());
        $canEditProjectValue = $showFinancials && $this->canEditProjectRevenue($request->user(), $project);
        $canEditProjectCosts = $showProjectProfit && $this->canEditProjectCosts($request->user());
        $showProjectExpenseLedger = ($project->request_source ?? 'sales') === 'sales'
            && $this->canManageProjectExpenses($request->user(), $project);
        $canReviewProjectExpenses = $showProjectExpenseLedger
            && $this->canReviewProjectExpenses($request->user());

        // Giá vốn chỉ được truy vấn khi người dùng có quyền xem lợi nhuận nội bộ.
        $materialCost = 0.0;
        if ($showProjectProfit) {
            $materialCost = (float) $project->materialRequests
                ->flatMap(fn ($materialRequest) => $materialRequest->items)
                ->flatMap(fn ($item) => $item->allocations)
                ->sum(function ($allocation): float {
                    $qty = (float) ($allocation->issued_quantity ?: $allocation->allocated_quantity ?: 0);

                    return $qty * (float) ($allocation->unit_cost ?? 0);
                });
        }

        $revenue = (float) $project->contract_amount + (float) $project->extra_revenue;
        $legacyManualCost = $showProjectProfit
            ? (float) $project->labor_cost + (float) $project->transport_cost + (float) $project->other_cost
            : 0.0;
        $expenseLedgerCost = $showProjectProfit
            ? (float) $project->expenses->where('status', 'confirmed')->sum('amount')
            : 0.0;
        $manualCost = $legacyManualCost + $expenseLedgerCost;
        $totalCost = $materialCost + $manualCost;
        $hasCostData = $showProjectProfit && ($materialCost > 0 || $manualCost > 0);

        $financialSummary = [
            'revenue' => $revenue,
            'collected' => (float) $project->amount_collected,
            'receivable' => max(0, $revenue - (float) $project->amount_collected),
            'material_cost' => $materialCost,
            'expense_cost' => $expenseLedgerCost,
            'legacy_manual_cost' => $legacyManualCost,
            'manual_cost' => $manualCost,
            'total_cost' => $totalCost,
            'gross_profit' => $hasCostData ? $revenue - $totalCost : null,
            'gross_margin' => $hasCostData && $revenue > 0
                ? (($revenue - $totalCost) / $revenue) * 100
                : null,
            'has_cost_data' => $hasCostData,
        ];

        $selectedProductIds = $project->materialRequests
            ->flatMap(fn ($materialRequest) => $materialRequest->items)
            ->flatMap(function ($item): array {
                return array_values(array_filter([
                    (int) ($item->product_id ?? 0),
                    ...collect($item->allocations ?? [])->pluck('product_id')->map(fn ($id): int => (int) $id)->all(),
                ]));
            })
            ->unique()
            ->values()
            ->all();

        return view('project-test.show', array_merge($this->formOptions(true, $selectedProductIds, (int) ($project->customer_id ?? 0)), [
            'project' => $project,
            'statuses' => self::STATUSES,
            'statusInfo' => self::STATUSES[$project->status] ?? [
                'label' => $project->status,
                'group' => 'Khác',
                'progress' => $project->progress,
            ],
            'can' => $this->actionAbilities($request->user(), $project),
            'requestSources' => self::REQUEST_SOURCES,
            'projectTypes' => self::PROJECT_TYPES,
            'showFinancials' => $showFinancials,
            'showProjectProfit' => $showProjectProfit,
            'canEditProjectValue' => $canEditProjectValue,
            'canEditProjectCosts' => $canEditProjectCosts,
            'showProjectExpenseLedger' => $showProjectExpenseLedger,
            'canReviewProjectExpenses' => $canReviewProjectExpenses,
            'financialSummary' => $financialSummary,
        ]));
    }

    public function updateFinancials(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);
        abort_unless(
            $this->canViewProjectFinancials($request->user(), $project),
            403,
            'Bạn không được xem hoặc cập nhật tài chính công trình này.'
        );

        $scope = $request->validate([
            'finance_scope' => ['required', Rule::in(['revenue', 'cost'])],
        ])['finance_scope'];

        $canEditRevenue = $this->canEditProjectRevenue($request->user(), $project);
        $canEditCosts = $this->canEditProjectCosts($request->user());

        if ($scope === 'revenue') {
            abort_unless($canEditRevenue, 403, 'Bạn không được chỉnh sửa giá trị công trình này.');

            $data = $request->validate([
                'contract_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
                'extra_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'sales_revenue_note' => ['nullable', 'string', 'max:5000'],
                'change_reason' => ['required', 'string', 'max:3000'],
            ]);

            $before = [
                'contract_amount' => (float) $project->contract_amount,
                'extra_revenue' => (float) $project->extra_revenue,
                'sales_revenue_note' => $project->sales_revenue_note,
            ];

            $project->contract_amount = max(0, (float) $data['contract_amount']);
            $project->extra_revenue = max(0, (float) ($data['extra_revenue'] ?? 0));
            $project->sales_revenue_note = $data['sales_revenue_note'] ?? null;
            $project->financial_updated_by = $request->user()->id;
            $project->financial_updated_at = now();
            $project->save();

            $this->history(
                $project,
                'Cập nhật doanh thu công trình',
                $project->status,
                $project->status,
                [
                    'scope' => 'revenue',
                    'reason' => $data['change_reason'],
                    'before' => $before,
                    'after' => [
                        'contract_amount' => (float) $project->contract_amount,
                        'extra_revenue' => (float) $project->extra_revenue,
                        'sales_revenue_note' => $project->sales_revenue_note,
                    ],
                ]
            );

            return back()->with('success', 'Đã cập nhật giá trị và ghi chú doanh thu công trình.');
        }

        abort_unless($canEditCosts, 403, 'Chỉ Admin/Giám đốc hoặc người có quyền quản trị lợi nhuận được chỉnh sửa chi phí.');

        $data = $request->validate([
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'internal_finance_note' => ['nullable', 'string', 'max:5000'],
            'change_reason' => ['required', 'string', 'max:3000'],
        ]);

        $before = [
            'labor_cost' => (float) $project->labor_cost,
            'transport_cost' => (float) $project->transport_cost,
            'other_cost' => (float) $project->other_cost,
            'internal_finance_note' => $project->internal_finance_note ?: $project->financial_note,
        ];

        $project->labor_cost = max(0, (float) ($data['labor_cost'] ?? 0));
        $project->transport_cost = max(0, (float) ($data['transport_cost'] ?? 0));
        $project->other_cost = max(0, (float) ($data['other_cost'] ?? 0));
        $project->internal_finance_note = $data['internal_finance_note'] ?? null;
        // Duy trì trường cũ để các báo cáo legacy không bị mất ghi chú.
        $project->financial_note = $project->internal_finance_note;
        $project->financial_updated_by = $request->user()->id;
        $project->financial_updated_at = now();
        $project->save();

        $this->history(
            $project,
            'Cập nhật chi phí nội bộ công trình',
            $project->status,
            $project->status,
            [
                'scope' => 'cost',
                'reason' => $data['change_reason'],
                'before' => $before,
                'after' => [
                    'labor_cost' => (float) $project->labor_cost,
                    'transport_cost' => (float) $project->transport_cost,
                    'other_cost' => (float) $project->other_cost,
                    'internal_finance_note' => $project->internal_finance_note,
                ],
            ]
        );

        return back()->with('success', 'Đã cập nhật chi phí nội bộ công trình.');
    }

    public function acceptRequest(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->authorizeProject($request, $project);
        $this->assertStatus($project, ['request_new', 'request_accepted']);

        $data = $request->validate([
            'technical_manager_id' => ['required', 'integer'],
            'surveyor_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'note' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager', 'technical_leader'], 'Trưởng phòng Kỹ thuật');
        if (! empty($data['surveyor_id'])) {
            $this->assertUserHasRole((int) $data['surveyor_id'], ['ky_thuat', 'technical', 'technician', 'technical_staff'], 'Người khảo sát');
        }

        DB::transaction(function () use ($request, $project, $data): void {
            $project->technical_manager_id = (int) $data['technical_manager_id'];
            if (! empty($data['surveyor_id'])) {
                $project->lead_technician_id = (int) $data['surveyor_id'];
            }
            if (! empty($data['scheduled_at'])) {
                $project->proposed_survey_at = $data['scheduled_at'];
            }
            $project->save();

            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->reviewed_by = $request->user()->id;
            $survey->surveyed_by = $data['surveyor_id'] ?? $survey->surveyed_by;
            $survey->scheduled_at = $data['scheduled_at'] ?? $survey->scheduled_at;
            $survey->schedule_status = (! empty($data['scheduled_at']) && ! empty($data['surveyor_id'])) ? 'confirmed' : 'pending';
            $survey->save();

            if (! empty($data['scheduled_at']) && ! empty($data['surveyor_id'])) {
                $project->survey_confirmed_at = $data['scheduled_at'];
                $this->transition($project, 'survey_confirmed', 'Trưởng phòng Kỹ thuật tiếp nhận, lập lịch và phân công khảo sát'.(!empty($data['note']) ? ': '.$data['note'] : ''));
            } else {
                $this->transition($project, 'request_accepted', 'Trưởng phòng Kỹ thuật đã tiếp nhận yêu cầu'.(!empty($data['note']) ? ': '.$data['note'] : ''));
            }
        });

        return back()->with('success', ! empty($data['scheduled_at']) && ! empty($data['surveyor_id'])
            ? 'Đã tiếp nhận, xác nhận lịch và phân công khảo sát.'
            : 'Đã tiếp nhận yêu cầu Kỹ thuật.');
    }

    public function reviewSurveySchedule(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['request_accepted', 'survey_pending', 'survey_reschedule']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'reschedule'])],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'technical_manager_id' => ['required', 'integer'],
            'surveyor_id' => ['nullable', 'required_if:decision,confirm', 'integer'],
            'reason' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager', 'technical_leader'], 'Trưởng phòng Kỹ thuật');
        if (! empty($data['surveyor_id'])) {
            $this->assertUserHasRole((int) $data['surveyor_id'], ['ky_thuat', 'technical', 'technician', 'technical_staff'], 'Người khảo sát');
        }

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->fill([
                'reviewed_by' => $request->user()->id,
                'surveyed_by' => $data['surveyor_id'] ?? $survey->surveyed_by,
                'schedule_status' => $data['decision'] === 'confirm' ? 'confirmed' : 'reschedule',
                'scheduled_at' => $data['scheduled_at'],
                'reschedule_reason' => $data['reason'] ?? null,
            ])->save();

            $project->technical_manager_id = (int) $data['technical_manager_id'];
            if (! empty($data['surveyor_id']) && ! $project->lead_technician_id) {
                $project->lead_technician_id = (int) $data['surveyor_id'];
            }
            $project->proposed_survey_at = $data['scheduled_at'];

            if ($data['decision'] === 'confirm') {
                $project->survey_confirmed_at = $data['scheduled_at'];
                $this->transition($project, 'survey_confirmed', 'Trưởng phòng Kỹ thuật xác nhận lịch và phân công người khảo sát');
            } else {
                $this->transition($project, 'survey_reschedule', 'Trưởng phòng Kỹ thuật ghi nhận cần cập nhật lại lịch khảo sát: '.($data['reason'] ?? 'Không ghi lý do'));
            }
        });

        return back()->with('success', $data['decision'] === 'confirm' ? 'Đã xác nhận lịch và phân công người khảo sát.' : 'Đã ghi nhận yêu cầu cập nhật lại lịch khảo sát.');
    }

    public function salesRescheduleSurvey(Request $request, Project $project)
    {
        $this->authorizeRequestCoordinator($project);
        $this->assertStatus($project, ['survey_reschedule']);
        $data = $request->validate(['proposed_survey_at' => ['required', 'date', 'after_or_equal:now'], 'note' => ['nullable', 'string']]);

        $project->proposed_survey_at = $data['proposed_survey_at'];
        $project->survey()->updateOrCreate(['project_id' => $project->id], ['schedule_status' => 'pending', 'scheduled_at' => $data['proposed_survey_at'], 'reschedule_reason' => $data['note'] ?? null]);
        $this->transition($project, 'survey_pending', 'Cập nhật lại lịch khảo sát'.(!empty($data['note']) ? ': '.$data['note'] : ''));

        return back()->with('success', 'Đã cập nhật lịch khảo sát để Trưởng phòng xác nhận.');
    }

    public function surveyCheckIn(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        $this->assertStatus($project, ['survey_confirmed', 'survey_in_progress', 'proposal_revision']);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($request, $project, $data): void {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->surveyed_by = $survey->surveyed_by ?: $request->user()->id;
            $survey->schedule_status = 'in_progress';
            $survey->check_in_at = $survey->check_in_at ?: now();
            $survey->check_in_lat = $data['latitude'] ?? $survey->check_in_lat;
            $survey->check_in_lng = $data['longitude'] ?? $survey->check_in_lng;
            $survey->save();

            if ($project->status === 'survey_confirmed') {
                $this->transition($project, 'survey_in_progress', 'Kỹ thuật check-in tại hiện trường khảo sát');
            } else {
                $this->history($project, 'Kỹ thuật cập nhật check-in khảo sát', $project->status, $project->status);
            }
        });

        return back()->with('success', 'Đã check-in khảo sát lúc '.now()->format('H:i d/m/Y').'.');
    }

    public function surveyCheckOut(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        $this->assertStatus($project, ['survey_in_progress', 'proposal_revision']);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $survey = $project->survey;
        abort_unless($survey && $survey->check_in_at, 422, 'Bạn cần check-in trước khi check-out.');
        $survey->check_out_at = now();
        $survey->check_out_lat = $data['latitude'] ?? null;
        $survey->check_out_lng = $data['longitude'] ?? null;
        $survey->save();
        $this->history($project, 'Kỹ thuật check-out hiện trường khảo sát', $project->status, $project->status);

        return back()->with('success', 'Đã check-out khảo sát lúc '.now()->format('H:i d/m/Y').'.');
    }

    public function submitSurvey(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['survey_confirmed', 'survey_in_progress', 'proposal_revision']);

        $data = $request->validate([
            'site_condition' => ['required', 'string'],
            'actual_measurements' => ['required', 'string'],
            'site_risks' => ['nullable', 'string'],
            'technical_notes' => ['nullable', 'string'],
            'proposed_kwp' => ['required', 'numeric', 'min:0.01'],
            'solution_summary' => ['required', 'string'],
            'preliminary_materials' => ['required', 'string'],
            'design_3d_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,dwg,dxf,skp', 'max:30720'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,doc,docx,xls,xlsx', 'max:30720'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->surveyed_by = $survey->surveyed_by ?: $request->user()->id;
            abort_unless($survey->check_in_at, 422, 'Bạn cần check-in hiện trường trước khi hoàn tất khảo sát.');
            $survey->schedule_status = 'completed';
            $survey->check_out_at = $survey->check_out_at ?: now();
            $survey->completed_at = now();
            $survey->site_condition = $data['site_condition'];
            $survey->actual_measurements = $data['actual_measurements'];
            $survey->site_risks = $data['site_risks'] ?? null;
            $survey->technical_notes = $data['technical_notes'] ?? null;
            if ($request->hasFile('design_3d_file')) {
                $this->deleteFile($survey->design_3d_file);
                $survey->design_3d_file = $request->file('design_3d_file')->store('project-test/designs', 'public');
            }
            if ($request->hasFile('attachment_file')) {
                $this->deleteFile($survey->attachment_file);
                $survey->attachment_file = $request->file('attachment_file')->store('project-test/surveys', 'public');
            }
            $survey->save();

            $confirmationRequired = (bool) $project->customer_confirmation_required;
            Proposal::updateOrCreate(['project_id' => $project->id], [
                'created_by' => $request->user()->id,
                'proposed_kwp' => $data['proposed_kwp'],
                'solution_summary' => $data['solution_summary'],
                'preliminary_materials_json' => $this->linesToArray($data['preliminary_materials']),
                'sales_decision' => $confirmationRequired ? 'pending' : 'not_required',
                'sales_feedback' => null,
                'sales_confirmed_at' => $confirmationRequired ? null : now(),
            ]);

            $project->estimated_kwp = $data['proposed_kwp'];
            $project->customer_confirmation_status = $confirmationRequired ? 'pending' : 'not_required';
            if (! $confirmationRequired) {
                $project->customer_confirmed_by = $request->user()->id;
                $project->customer_confirmed_at = now();
            }
            $project->save();

            if ($confirmationRequired) {
                $this->transition($project, 'customer_confirmation', 'Kỹ thuật hoàn tất khảo sát và phương án; chuyển bước xác nhận triển khai');
            } else {
                $this->transition($project, 'installation_pending', 'Kỹ thuật hoàn tất khảo sát và phương án; hồ sơ không yêu cầu xác nhận khách hàng');
            }
        });

        return back()->with('success', (bool) $project->fresh()->customer_confirmation_required
            ? 'Đã hoàn tất khảo sát và chuyển bước xác nhận phương án / triển khai.'
            : 'Đã hoàn tất khảo sát; chuyển sang sắp lịch thi công.');
    }

    public function salesReviewProposal(Request $request, Project $project)
    {
        $this->authorizeCustomerConfirmation($project);
        $this->assertStatus($project, ['customer_confirmation', 'sales_review']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'revision'])],
            'feedback' => ['nullable', 'string'],
            'proposed_installation_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($project, $data) {
            $project->proposal()->update([
                'sales_decision' => $data['decision'],
                'sales_feedback' => $data['feedback'] ?? null,
                'sales_confirmed_at' => now(),
            ]);

            $project->customer_confirmation_status = $data['decision'] === 'accept' ? 'confirmed' : 'revision';
            $project->customer_confirmed_by = auth()->id();
            $project->customer_confirmed_at = now();
            $project->customer_confirmation_note = $data['feedback'] ?? null;
            $project->save();

            if ($data['decision'] === 'revision') {
                $this->transition($project, 'proposal_revision', 'Phương án cần điều chỉnh: '.($data['feedback'] ?? 'Không ghi nội dung'));
                return;
            }

            $project->proposed_installation_at = $data['proposed_installation_at'];
            $this->transition($project, 'installation_pending', 'Đã xác nhận phương án và điều kiện triển khai');
        });

        return back()->with('success', $data['decision'] === 'accept' ? 'Đã xác nhận phương án; Kỹ thuật có thể sắp lịch thi công.' : 'Đã trả phương án để Kỹ thuật điều chỉnh.');
    }

    public function reviewInstallationSchedule(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['installation_pending', 'installation_reschedule']);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'reschedule'])],
            'installation_at' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string'],
        ]);

        $project->proposed_installation_at = $data['installation_at'];
        if ($data['decision'] === 'confirm') {
            $project->installation_confirmed_at = $data['installation_at'];
            $this->transition($project, 'materials_pending', 'Kỹ thuật xác nhận lịch thi công; chuyển lập vật tư chính thức');
        } else {
            $this->transition($project, 'installation_reschedule', 'Kỹ thuật ghi nhận cần cập nhật lại lịch thi công: '.($data['reason'] ?? 'Không ghi lý do'));
        }

        return back()->with('success', $data['decision'] === 'confirm' ? 'Đã xác nhận lịch thi công.' : 'Đã ghi nhận cần cập nhật lại lịch thi công.');
    }

    public function salesRescheduleInstallation(Request $request, Project $project)
    {
        $this->authorizeRequestCoordinator($project);
        $this->assertStatus($project, ['installation_reschedule']);
        $data = $request->validate(['proposed_installation_at' => ['required', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string']]);
        $project->proposed_installation_at = $data['proposed_installation_at'];
        $this->transition($project, 'installation_pending', 'Cập nhật lại lịch thi công'.(!empty($data['note']) ? ': '.$data['note'] : ''));
        return back()->with('success', 'Đã cập nhật lịch thi công để Trưởng phòng xác nhận.');
    }

    public function importMaterialExcel(Request $request, Project $project): \Illuminate\Http\JsonResponse
    {
        // EGO_MATERIAL_V11_EXCEL_PREVIEW: chỉ xem trước, chưa ghi database.
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv']]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = min((int) $sheet->getHighestDataRow(), 1000);
            $highestColumn = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 30);
            $normalize = static fn ($value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)), 'UTF-8');

            $headerRow = null;
            $columns = ['name' => 2, 'quantity' => 4, 'unit' => 5, 'needed' => 6, 'note' => 7];
            $aliases = [
                'name' => ['tên vật tư', 'tên sản phẩm', 'sản phẩm', 'vật tư', 'model', 'hàng hóa'],
                'quantity' => ['số lượng', 'sl', 'khối lượng'],
                'unit' => ['đơn vị', 'đvt', 'đơn vị tính'],
                'needed' => ['thời gian cần', 'ngày cần', 'ngày giao', 'thời gian'],
                'note' => ['ghi chú', 'thông số', 'lưu ý', 'mô tả'],
            ];

            for ($r = 1; $r <= min($highestRow, 30); $r++) {
                $found = [];
                for ($c = 1; $c <= $highestColumn; $c++) {
                    $cell = $normalize($sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r)->getFormattedValue());
                    if ($cell === '') continue;
                    foreach ($aliases as $key => $names) {
                        foreach ($names as $alias) {
                            if ($cell === $alias || str_contains($cell, $alias)) { $found[$key] = $c; break 2; }
                        }
                    }
                }
                if (isset($found['name'], $found['quantity'])) { $headerRow = $r; $columns = array_merge($columns, $found); break; }
            }

            $groupLabels = ['vật tư chính','tủ điện solar','dây dc/ac và phụ kiện','dây dc ac và phụ kiện','khung sắt đỡ tấm pin','vật tư khác','thiết bị chính','phụ kiện','tổng cộng'];
            $rows = []; $skipped = 0;
            for ($r = $headerRow ? $headerRow + 1 : 1; $r <= $highestRow; $r++) {
                $name = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['name']).$r)->getFormattedValue());
                $qtyRaw = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['quantity']).$r)->getCalculatedValue();
                $unit = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['unit']).$r)->getFormattedValue());
                $needed = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['needed']).$r)->getFormattedValue());
                $note = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['note']).$r)->getFormattedValue());
                if ($name === '') continue;
                $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', (string) $qtyRaw));
                $n = $normalize($name);
                if ($qty <= 0 || in_array($n, $groupLabels, true)) { $skipped++; continue; }
                if ($needed !== '' && !str_contains($note, $needed)) $note = trim($note.($note !== '' ? ' · ' : '').'Thời gian cần: '.$needed);
                $rows[] = ['name' => mb_substr($name, 0, 255), 'quantity' => $qty, 'unit' => mb_substr($unit ?: 'cái', 0, 40), 'note' => mb_substr($note, 0, 3000), 'source_row' => $r];
                if (count($rows) >= 300) break;
            }
            abort_if(empty($rows), 422, 'Không tìm thấy dòng vật tư hợp lệ trong file Excel.');
            return response()->json(['message' => 'Đã đọc '.count($rows).' dòng vật tư.', 'data' => $rows, 'meta' => ['imported' => count($rows), 'skipped' => $skipped, 'header_row' => $headerRow, 'file_name' => $request->file('file')->getClientOriginalName()]]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Không đọc được file Excel. Hãy kiểm tra file không bị khóa và đúng định dạng .xlsx, .xls hoặc .csv.'], 422);
        }
    }

    public function submitMaterials(Request $request, Project $project)
    {
        // EGO_MATERIAL_WORKFLOW_V41_TECHNICAL_SUBMIT
        // Kỹ thuật được nhập tay vật tư đề xuất. Product ID chỉ là gợi ý; Kho chịu trách nhiệm đối chiếu SKU thật.
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);

        abort_unless(
            in_array($project->status, ['materials_pending', 'materials_revision', 'materials_admin_review', 'warehouse_preparing'], true),
            422,
            'Công trình không còn ở bước đề nghị cấp vật tư.'
        );

        $data = $request->validate([
            'needed_at' => ['required', 'date'],
            'request_note' => ['nullable', 'string', 'max:3000'],
            'item_name' => ['required', 'array', 'min:1'],
            'item_name.*' => ['required', 'string', 'max:255'],
            'product_id' => ['nullable', 'array'],
            'product_id.*' => ['nullable', 'integer'],
            'quantity' => ['required', 'array'],
            'quantity.*' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'array'],
            'unit.*' => ['required', 'string', 'max:40'],
            'item_note' => ['nullable', 'array'],
            'item_note.*' => ['nullable', 'string', 'max:3000'],
        ]);

        $productIds = collect($data['product_id'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $products = collect();
        if ($productIds->isNotEmpty()) {
            $products = DB::table('crm_product_catalog as products')
                ->leftJoin('crm_brands as brands', 'brands.id', '=', 'products.brand_id')
                ->whereIn('products.id', $productIds->all())
                ->where('products.is_active', 1)
                ->select([
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.unit',
                    'products.description',
                    'brands.name as brand_name',
                ])
                ->get()
                ->keyBy(fn ($product) => (int) $product->id);

            abort_if(
                $products->count() !== $productIds->count(),
                422,
                'Có sản phẩm gợi ý không tồn tại hoặc đã ngừng sử dụng. Hãy nhập lại tên vật tư hoặc chọn lại gợi ý.'
            );
        }

        $materialRequest = DB::transaction(function () use ($request, $project, $data, $products, $productIds) {
            $project->loadMissing('materialRequests.items');

            $activeStatuses = ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'revision'];
            $officialActiveRequest = $project->materialRequests
                ->filter(fn ($existing) => in_array($existing->status, $activeStatuses, true))
                ->first(fn ($existing): bool => $existing->items->contains(
                    fn ($item): bool => trim((string) $item->item_name) !== ''
                ));

            abort_if(
                $officialActiveRequest,
                422,
                'Công trình đã có đề nghị cấp vật tư đang xử lý. Hãy mở phiếu hiện tại để chỉnh sửa hoặc chờ bộ phận đang giữ bước.'
            );

            $archivedLegacyIds = [];
            foreach ($project->materialRequests as $existing) {
                if (in_array($existing->status, ['issued', 'legacy_archived'], true)) {
                    continue;
                }

                $hasNamedItem = $existing->items->contains(fn ($item): bool => trim((string) $item->item_name) !== '');
                $belongsToCurrentFlow = $hasNamedItem && in_array($existing->status, $activeStatuses, true);

                if (! $belongsToCurrentFlow) {
                    $existing->update([
                        'status' => 'legacy_archived',
                        'warehouse_status' => 'legacy',
                    ]);
                    $archivedLegacyIds[] = (int) $existing->id;
                }
            }

            $materialRequest = MaterialRequest::create([
                'project_id' => $project->id,
                'code' => $this->nextMaterialCode(),
                'requested_by' => $request->user()->id,
                'needed_at' => $data['needed_at'],
                'status' => 'warehouse_check',
                'warehouse_status' => 'waiting_match',
                'request_note' => $data['request_note'] ?? null,
            ]);

            foreach ($data['item_name'] as $index => $submittedName) {
                $itemName = trim((string) $submittedName);
                abort_if($itemName === '', 422, 'Tên vật tư không được để trống.');

                $productId = (int) Arr::get($data, "product_id.{$index}", 0);
                $product = $productId > 0 ? $products->get($productId) : null;
                abort_if($productId > 0 && ! $product, 422, 'Sản phẩm gợi ý ở dòng '.($index + 1).' không hợp lệ.');

                $submittedUnit = trim((string) Arr::get($data, "unit.{$index}", ''));
                $catalogUnit = trim((string) ($product->unit ?? ''));

                $materialRequest->items()->create([
                    'product_id' => $product ? (int) $product->id : null,
                    'item_name' => $product ? (string) $product->name : $itemName,
                    'quantity' => (float) Arr::get($data, "quantity.{$index}", 1),
                    'unit' => $submittedUnit !== '' ? $submittedUnit : ($catalogUnit !== '' ? $catalogUnit : 'cái'),
                    'issued_quantity' => 0,
                    'serials' => null,
                    'note' => Arr::get($data, "item_note.{$index}"),
                ]);
            }

            $from = $project->status;
            $project->update([
                'status' => 'warehouse_preparing',
                'current_owner_role' => 'warehouse',
                'progress' => max((int) $project->progress, 60),
            ]);

            $this->history(
                $project,
                'Kỹ thuật gửi đề nghị cấp vật tư; chuyển Kho đối chiếu sản phẩm và kiểm tra tồn',
                $from,
                'warehouse_preparing',
                [
                    'material_request_id' => (int) $materialRequest->id,
                    'manual_item_name_allowed' => true,
                    'item_count' => count($data['item_name']),
                    'catalog_suggestion_count' => $productIds->count(),
                    'archived_legacy_request_ids' => $archivedLegacyIds,
                    'workflow_version' => 'v4.1',
                ]
            );

            return $materialRequest;
        });

        return redirect()->route('project-test.show', [
            'project' => $project,
            'tab' => 'materials',
            'material_view' => 'proposal',
            'material_request' => $materialRequest->id,
        ])->with('success', 'Đã gửi đề nghị cấp vật tư. Kho sẽ đối chiếu đúng sản phẩm/SKU và kiểm tra tồn.');
    }



    public function reviewMaterials(Request $request, Project $project, MaterialRequest $materialRequest)
    {
        // EGO_MATERIAL_WORKFLOW_V13_MANAGER_REVIEW
        $reviewer = $request->user();
        abort_unless($reviewer, 401, 'Phiên đăng nhập đã hết hạn.');

        $normalizedRoles = collect();
        try {
            if (method_exists($reviewer, 'roles')) {
                $normalizedRoles = $reviewer->roles()
                    ->pluck('name')
                    ->map(fn ($role) => mb_strtolower(trim((string) $role)))
                    ->values();
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $allowedRoles = collect([
            'admin', 'administrator', 'super admin', 'super_admin',
            'management', 'manager', 'technical manager', 'technical_manager',
            'technical leader', 'technical_leader', 'quản lý', 'quan ly',
            'giám đốc', 'giam doc',
        ]);

        $hasManagerRole = $normalizedRoles->intersect($allowedRoles)->isNotEmpty();
        try {
            $hasManagerRole = $hasManagerRole || $reviewer->hasAnyRole([
                'admin', 'Admin', 'administrator', 'Administrator',
                'super admin', 'Super Admin', 'management', 'Management',
                'manager', 'Manager', 'technical_manager', 'Technical Manager',
                'technical_leader', 'Technical Leader', 'Quản lý', 'Giám đốc',
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $hasAdminPermission = false;
        try {
            $hasAdminPermission = $reviewer->can('project-test.admin');
        } catch (\Throwable $exception) {
            report($exception);
        }

        $hasAdminFlag = (bool) data_get($reviewer, 'is_admin', false)
            || (bool) data_get($reviewer, 'is_super_admin', false)
            || (int) data_get($reviewer, 'level', 0) === 1;

        abort_unless(
            $hasManagerRole || $hasAdminPermission || $hasAdminFlag,
            403,
            'Tài khoản chưa có quyền Quản lý phê duyệt vật tư.'
        );

        $this->authorizeProject($request, $project);
        abort_unless((int) $materialRequest->project_id === (int) $project->id, 404);

        $materialRequest->refresh();
        if ((string) $materialRequest->status !== 'pending_manager') {
            throw ValidationException::withMessages([
                'materials' => 'Phiếu không còn ở trạng thái chờ Admin/Quản lý phê duyệt. Hãy tải lại trang.',
            ]);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return_warehouse', 'return_technical'])],
            'review_note' => ['nullable', 'string', 'max:3000'],
        ], [
            'decision.required' => 'Hãy chọn quyết định xử lý phiếu vật tư.',
        ]);

        $decision = (string) $data['decision'];
        $reviewNote = trim((string) ($data['review_note'] ?? ''));

        if ($decision !== 'approve' && $reviewNote === '') {
            throw ValidationException::withMessages([
                'review_note' => 'Bắt buộc nhập lý do khi trả phiếu về Kho hoặc Kỹ thuật.',
            ]);
        }

        $materialRequest->load(['items.allocations', 'project']);
        if ($materialRequest->items->isEmpty()) {
            throw ValidationException::withMessages(['materials' => 'Phiếu chưa có dòng vật tư.']);
        }

        $allRowsReady = true;
        $warehouseStateBeforeReview = (string) ($materialRequest->warehouse_status ?: 'waiting_match');

        if ($decision === 'approve') {
            $invalidRows = collect();

            foreach ($materialRequest->items as $item) {
                $allocation = $item->allocations->first();
                $reason = null;

                if ((int) $item->product_id <= 0) {
                    $reason = 'chưa gắn sản phẩm/SKU';
                } elseif (! $allocation) {
                    $reason = 'Kho chưa lưu đối chiếu';
                } elseif ((int) $allocation->product_id !== (int) $item->product_id) {
                    $reason = 'SKU Kho đối chiếu không khớp';
                } elseif (! in_array((string) $allocation->status, ['ready', 'shortage', 'transfer', 'waiting_purchase'], true)) {
                    $reason = 'chưa có kết quả hợp lệ';
                }

                if ($reason !== null) {
                    $invalidRows->push((string) $item->item_name.' ('.$reason.')');
                    continue;
                }

                if ((string) $allocation->status !== 'ready') {
                    $allRowsReady = false;
                }
            }

            if ($invalidRows->isNotEmpty()) {
                $preview = $invalidRows->take(4)->implode('; ');
                $more = $invalidRows->count() > 4 ? '; và '.($invalidRows->count() - 4).' dòng khác' : '';
                throw ValidationException::withMessages([
                    'materials' => 'Chưa thể duyệt vì còn '.$invalidRows->count().' dòng đối chiếu chưa hoàn chỉnh: '.$preview.$more.'.',
                ]);
            }

            if (! $allRowsReady && $reviewNote === '') {
                throw ValidationException::withMessages([
                    'review_note' => 'Phiếu còn thiếu hàng/cần điều chuyển/chờ nhập. Bắt buộc ghi rõ nguồn điều hàng hoặc mua/nhập bổ sung và thời gian dự kiến.',
                ]);
            }
        }

        DB::transaction(function () use (
            $request,
            $project,
            $materialRequest,
            $decision,
            $reviewNote,
            $allRowsReady,
            $warehouseStateBeforeReview
        ): void {
            $lockedRequest = MaterialRequest::query()->lockForUpdate()->findOrFail($materialRequest->id);
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ((string) $lockedRequest->status !== 'pending_manager') {
                throw ValidationException::withMessages([
                    'materials' => 'Phiếu vừa được người khác xử lý. Hãy tải lại trang.',
                ]);
            }

            $from = (string) $lockedProject->status;

            if ($decision === 'approve') {
                $lockedRequest->update([
                    'status' => 'approved',
                    'warehouse_status' => $allRowsReady ? 'ready' : 'waiting_replenishment',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $reviewNote !== ''
                        ? $reviewNote
                        : 'Phê duyệt chuyển Kho giữ hàng và xuất kho',
                ]);
                $lockedProject->update([
                    'status' => 'warehouse_preparing',
                    'current_owner_role' => 'warehouse',
                    'progress' => max((int) $lockedProject->progress, 64),
                ]);
                $this->history(
                    $lockedProject,
                    $allRowsReady
                        ? 'Admin/Quản lý phê duyệt phiếu vật tư; chuyển Kho giữ hàng và xuất kho'
                        : 'Admin/Quản lý phê duyệt phương án còn thiếu hàng; giao Kho điều hàng/nhập bổ sung trước khi xuất',
                    $from,
                    'warehouse_preparing',
                    [
                        'material_request_id' => (int) $lockedRequest->id,
                        'decision' => 'approve',
                        'all_lines_ready' => $allRowsReady,
                        'requires_replenishment' => ! $allRowsReady,
                        'manager_supply_note' => $reviewNote,
                        'workflow_version' => 'v13',
                    ]
                );
                return;
            }

            if ($decision === 'return_warehouse') {
                $lockedRequest->update([
                    'status' => 'warehouse_check',
                    'warehouse_status' => $warehouseStateBeforeReview,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $reviewNote,
                ]);
                $lockedProject->update([
                    'status' => 'warehouse_preparing',
                    'current_owner_role' => 'warehouse',
                    'progress' => 60,
                ]);
                $this->history($lockedProject, 'Admin/Quản lý trả Kho kiểm tra lại phiếu vật tư', $from, 'warehouse_preparing', [
                    'material_request_id' => (int) $lockedRequest->id,
                    'decision' => 'return_warehouse',
                    'reason' => $reviewNote,
                    'warehouse_mapping_preserved' => true,
                    'workflow_version' => 'v13',
                ]);
                return;
            }

            // Giữ nguyên phần Kho đã đối chiếu. Khi Kỹ thuật sửa, chỉ dòng thay đổi mới bị hủy đối chiếu.
            $lockedRequest->update([
                'status' => 'revision',
                'warehouse_status' => $warehouseStateBeforeReview,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'issued_by' => null,
                'receiver_id' => null,
                'reserved_at' => null,
                'issued_at' => null,
                'handed_over_at' => null,
            ]);

            $lockedProject->update([
                'status' => 'materials_revision',
                'current_owner_role' => 'technical',
                'progress' => 53,
            ]);

            $this->history($lockedProject, 'Admin/Quản lý trả Kỹ thuật điều chỉnh danh sách vật tư', $from, 'materials_revision', [
                'material_request_id' => (int) $lockedRequest->id,
                'decision' => 'return_technical',
                'reason' => $reviewNote,
                'warehouse_mapping_reset' => false,
                'warehouse_mapping_preserved' => true,
                'next_owner_role' => 'technical',
                'workflow_version' => 'v13',
            ]);
        });

        return redirect()->route('project-test.show', [
            'project' => $project,
            'tab' => 'materials',
            'material_view' => 'proposal',
            'material_request' => $materialRequest->id,
        ])->with('success', match ($decision) {
            'approve' => $allRowsReady
                ? 'Đã phê duyệt. Kho có thể giữ hàng và xuất kho.'
                : 'Đã phê duyệt phương án bổ sung hàng. Kho cập nhật tồn đến khi đủ rồi mới giữ và xuất.',
            'return_warehouse' => 'Đã trả Kho kiểm tra lại; kết quả đối chiếu cũ vẫn được giữ.',
            default => 'Đã trả Kỹ thuật điều chỉnh; chỉ dòng thay đổi phải Kho đối chiếu lại.',
        });
    }


    public function assignTeam(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->assertStatus($project, ['warehouse_issued', 'assignment_pending']);

        $data = $this->validateTeamAssignment($request);
        $this->persistTeamAssignment($request, $project, $data);
        $this->transition($project, 'ready_install', 'Trưởng phòng Kỹ thuật đã phân công đội thi công');

        return back()->with('success', 'Đã phân công đội thi công.');
    }

    public function startInstallation(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['ready_install']);
        $this->transition($project, 'installing', 'Đội kỹ thuật bắt đầu thi công');
        return back()->with('success', 'Đã bắt đầu thi công.');
    }

    public function storeDailyLog(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->assertStatus($project, ['installing']);

        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['working', 'blocked', 'waiting_customer', 'waiting_material', 'done'])],
            'content' => ['required', 'string'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar', 'max:30720'],
        ]);

        $path = $request->hasFile('attachment_file') ? $request->file('attachment_file')->store('project-test/logs', 'public') : null;
        DailyLog::create(array_merge($data, ['project_id' => $project->id, 'created_by' => $request->user()->id, 'attachment_file' => $path]));
        $project->progress = max($project->progress, min(90, (int) $data['progress']));
        if ($data['status'] === 'done' || (int) $data['progress'] >= 100) {
            $this->transition($project, 'acceptance_pending', 'Kỹ thuật hoàn tất thi công và gửi nghiệm thu');
        } else {
            $project->save();
            $this->history($project, 'Cập nhật nhật ký thi công: '.$data['content'], $project->status, $project->status, ['progress' => $data['progress']]);
        }

        return back()->with('success', 'Đã cập nhật nhật ký thi công.');
    }

    public function accept(Request $request, Project $project)
    {
        $this->authorizeAcceptanceRole();
        $this->assertStatus($project, ['acceptance_pending']);

        $data = $request->validate([
            'accepted_at' => ['required', 'date'],
            'device_serials' => ['required', 'string'],
            'monitoring_link' => ['nullable', 'url', 'max:700'],
            'monitoring_account' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'report_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:30720'],
            'checklist' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $path = $request->hasFile('report_file') ? $request->file('report_file')->store('project-test/acceptances', 'public') : null;
            Acceptance::updateOrCreate(['project_id' => $project->id], [
                'submitted_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'accepted_at' => $data['accepted_at'],
                'checklist_json' => $data['checklist'] ?? [],
                'device_serials' => $data['device_serials'],
                'monitoring_link' => $data['monitoring_link'] ?? null,
                'monitoring_account' => $data['monitoring_account'] ?? null,
                'report_file' => $path,
                'note' => $data['note'] ?? null,
                'status' => 'approved',
            ]);

            $starts = Carbon::parse($data['accepted_at']);
            Warranty::updateOrCreate(['project_id' => $project->id], [
                'starts_at' => $starts->toDateString(),
                'ends_at' => $starts->copy()->addMonths((int) $data['warranty_months'])->toDateString(),
                'next_maintenance_at' => $starts->copy()->addMonths(6)->toDateString(),
                'assigned_to' => $project->lead_technician_id,
                'status' => 'active',
                'note' => 'Tự động tạo sau nghiệm thu trong module Công trình.',
            ]);

            $this->transition($project, 'warranty_active', 'Đã nghiệm thu; hệ thống tự động tạo hồ sơ bảo hành');

            app(\App\Services\Technical\TechnicalScheduleSyncService::class)
                ->ensureInitialMaintenance($project->fresh(), $starts, (int) $request->user()->id);
        });

        return back()->with('success', 'Đã nghiệm thu, mở hồ sơ bảo hành và tạo lịch bảo trì đầu tiên.');
    }

    public function updateBasic(Request $request, Project $project)
    {
        $this->authorizeRequestCoordinator($project);
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'sales_order_id' => ['nullable', 'integer'],
            'contract_reference' => ['nullable', 'string', 'max:180'],
            'contract_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'extra_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'financial_note' => ['nullable', 'string', 'max:5000'],
            'handover_note' => ['nullable', 'string'],
            'request_source' => ['required', Rule::in(array_keys(self::REQUEST_SOURCES))],
            'project_type' => ['required', Rule::in(array_keys(self::PROJECT_TYPES))],
            'customer_confirmation_required' => ['nullable', 'boolean'],
            'sales_user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:700'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'customer_need' => ['required', 'string'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'estimated_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'proposed_survey_at' => ['nullable', 'date'],
            'proposed_installation_at' => ['nullable', 'date'],
            'target_completion_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if (! empty($data['sales_user_id'])) {
            $this->assertUserHasRole((int) $data['sales_user_id'], ['sales', 'sales_staff', 'sales_manager'], 'Người phụ trách khách hàng');
        }
        $data['customer_confirmation_required'] = $request->boolean('customer_confirmation_required');
        $data['customer_confirmation_status'] = $data['customer_confirmation_required']
            ? ($project->customer_confirmation_status === 'confirmed' ? 'confirmed' : 'pending')
            : 'not_required';

        DB::transaction(function () use ($project, $data) {
            $project->fill($data)->save();

            if (! empty($data['proposed_survey_at'])) {
                $project->survey()->updateOrCreate(
                    ['project_id' => $project->id],
                    ['scheduled_at' => $data['proposed_survey_at']]
                );
            }

            $this->history(
                $project,
                'Cập nhật thông tin yêu cầu Kỹ thuật và lịch dự kiến',
                $project->status,
                $project->status,
                ['edited_section' => 'basic', 'request_source' => $data['request_source'], 'project_type' => $data['project_type']]
            );
        });

        return back()->with('success', 'Đã cập nhật thông tin yêu cầu Kỹ thuật.');
    }

    public function updatePeople(Request $request, Project $project)
    {
        $this->authorizeTechnicalManagerRole();
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'technical_manager_id' => ['required', 'integer'],
            'surveyor_id' => ['nullable', 'integer'],
            'survey_at' => ['nullable', 'date'],
            'lead_technician_id' => ['nullable', 'integer', 'required_with:technician_ids'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'work_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'update_team' => ['nullable', 'boolean'],
        ]);

        $this->assertUserHasRole((int) $data['technical_manager_id'], ['technical_manager', 'technical_leader'], 'Trưởng phòng Kỹ thuật');
        if (! empty($data['surveyor_id'])) {
            $this->assertUserHasRole((int) $data['surveyor_id'], ['ky_thuat', 'technical', 'technician', 'technical_staff'], 'Người khảo sát');
        }

        DB::transaction(function () use ($request, $project, $data) {
            $project->technical_manager_id = (int) $data['technical_manager_id'];

            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            if (array_key_exists('surveyor_id', $data)) {
                $survey->surveyed_by = $data['surveyor_id'] ?: null;
            }
            if (! empty($data['survey_at'])) {
                $survey->scheduled_at = $data['survey_at'];
                $project->proposed_survey_at = $data['survey_at'];
                if ($project->survey_confirmed_at) {
                    $project->survey_confirmed_at = $data['survey_at'];
                }
            }
            $survey->save();

            if ($request->boolean('update_team')) {
                $teamData = [
                    'lead_technician_id' => $data['lead_technician_id'] ?? null,
                    'member_ids' => $data['member_ids'] ?? [],
                    'work_date' => $data['work_date'] ?? null,
                    'note' => $data['note'] ?? null,
                ];
                $this->persistTeamAssignment($request, $project, $teamData, true);
            } elseif (! empty($data['surveyor_id']) && ! $project->lead_technician_id) {
                $project->lead_technician_id = (int) $data['surveyor_id'];
            }

            $project->save();
            $this->history(
                $project,
                'Trưởng phòng Kỹ thuật cập nhật người phụ trách và phân công',
                $project->status,
                $project->status,
                ['edited_section' => 'people']
            );
        });

        return back()->with('success', 'Đã cập nhật người khảo sát, trưởng phòng và đội thi công.');
    }

    public function updateSurveyData(Request $request, Project $project)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'site_condition' => ['required', 'string'],
            'actual_measurements' => ['required', 'string'],
            'site_risks' => ['nullable', 'string'],
            'technical_notes' => ['nullable', 'string'],
            'proposed_kwp' => ['required', 'numeric', 'min:0.01'],
            'solution_summary' => ['required', 'string'],
            'preliminary_materials' => ['required', 'string'],
            'completed_at' => ['nullable', 'date'],
            'design_3d_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,dwg,dxf,skp', 'max:30720'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar,doc,docx,xls,xlsx', 'max:30720'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $survey = $project->survey ?: new Survey(['project_id' => $project->id]);
            $survey->surveyed_by = $survey->surveyed_by ?: $request->user()->id;
            $survey->schedule_status = 'completed';
            $survey->completed_at = $data['completed_at'] ?? $survey->completed_at ?? now();
            $survey->site_condition = $data['site_condition'];
            $survey->actual_measurements = $data['actual_measurements'];
            $survey->site_risks = $data['site_risks'] ?? null;
            $survey->technical_notes = $data['technical_notes'] ?? null;

            if ($request->hasFile('design_3d_file')) {
                $this->deleteFile($survey->design_3d_file);
                $survey->design_3d_file = $request->file('design_3d_file')->store('project-test/designs', 'public');
            }
            if ($request->hasFile('attachment_file')) {
                $this->deleteFile($survey->attachment_file);
                $survey->attachment_file = $request->file('attachment_file')->store('project-test/surveys', 'public');
            }
            $survey->save();

            $proposal = $project->proposal ?: new Proposal(['project_id' => $project->id]);
            $proposal->created_by = $proposal->created_by ?: $request->user()->id;
            $proposal->proposed_kwp = $data['proposed_kwp'];
            $proposal->solution_summary = $data['solution_summary'];
            $proposal->preliminary_materials_json = $this->linesToArray($data['preliminary_materials']);
            $proposal->save();

            $project->estimated_kwp = $data['proposed_kwp'];
            $project->save();
            $this->history(
                $project,
                'Kỹ thuật chỉnh sửa kết quả khảo sát, mô phỏng 3D và phương án',
                $project->status,
                $project->status,
                ['edited_section' => 'survey_proposal']
            );
        });

        return back()->with('success', 'Đã cập nhật kết quả khảo sát và phương án kỹ thuật.');
    }

    public function updateMaterialRequest(Request $request, Project $project, MaterialRequest $materialRequest)
    {
        // EGO_MATERIAL_FASTFLOW_V9_PARTIAL_RECHECK
        // Chỉ hủy đối chiếu Kho ở dòng Kỹ thuật thực sự thay đổi; các dòng giữ nguyên không phải làm lại.
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        abort_unless((int) $materialRequest->project_id === (int) $project->id, 404);
        abort_if($materialRequest->status === 'legacy_archived', 422, 'Danh sách cũ chỉ được lưu tham chiếu, không dùng để xuất kho.');
        abort_if(in_array($materialRequest->status, ['pending_manager', 'approved', 'preparing', 'issued'], true), 422, 'Phiếu đã chuyển bộ phận khác nên Kỹ thuật không thể sửa trực tiếp.');

        $materialRequest->loadMissing('items.allocations');
        $hasWarehouseWork = $materialRequest->items->contains(fn ($item): bool => $item->allocations->isNotEmpty());
        abort_if(
            $materialRequest->status === 'warehouse_check' && $hasWarehouseWork,
            422,
            'Kho đã bắt đầu đối chiếu phiếu. Hãy yêu cầu Kho hoặc Quản lý trả lại Kỹ thuật trước khi sửa.'
        );

        $data = $request->validate([
            'needed_at' => ['required', 'date'],
            'request_note' => ['nullable', 'string', 'max:3000'],
            'item_id' => ['nullable', 'array'],
            'item_id.*' => ['nullable', 'integer'],
            'item_name' => ['required', 'array', 'min:1'],
            'item_name.*' => ['required', 'string', 'max:255'],
            'product_id' => ['nullable', 'array'],
            'product_id.*' => ['nullable', 'integer'],
            'quantity' => ['required', 'array'],
            'quantity.*' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'array'],
            'unit.*' => ['required', 'string', 'max:40'],
            'item_note' => ['nullable', 'array'],
            'item_note.*' => ['nullable', 'string', 'max:3000'],
        ]);

        $productIds = collect($data['product_id'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $products = collect();
        if ($productIds->isNotEmpty()) {
            $products = DB::table('crm_product_catalog as products')
                ->leftJoin('crm_brands as brands', 'brands.id', '=', 'products.brand_id')
                ->whereIn('products.id', $productIds->all())
                ->where('products.is_active', 1)
                ->select(['products.id', 'products.name', 'products.sku', 'products.unit', 'products.description', 'brands.name as brand_name'])
                ->get()
                ->keyBy(fn ($product) => (int) $product->id);

            abort_if($products->count() !== $productIds->count(), 422, 'Có sản phẩm gợi ý không tồn tại hoặc đã ngừng sử dụng.');
        }

        $result = DB::transaction(function () use ($project, $materialRequest, $data, $products, $productIds): array {
            $lockedRequest = MaterialRequest::query()->with('items.allocations')->lockForUpdate()->findOrFail($materialRequest->id);
            $existingItems = $lockedRequest->items->keyBy(fn ($item): int => (int) $item->id);
            $keptIds = collect();
            $preservedRows = 0;
            $invalidatedRows = 0;
            $newRows = 0;

            foreach ($data['item_name'] as $index => $submittedName) {
                $itemId = (int) Arr::get($data, "item_id.{$index}", 0);
                $existing = $itemId > 0 ? $existingItems->get($itemId) : null;
                abort_if($itemId > 0 && ! $existing, 422, 'Dòng vật tư #'.($index + 1).' không còn tồn tại trong phiếu.');

                $itemName = trim((string) $submittedName);
                abort_if($itemName === '', 422, 'Tên vật tư không được để trống.');

                $productId = (int) Arr::get($data, "product_id.{$index}", 0);
                $product = $productId > 0 ? $products->get($productId) : null;
                abort_if($productId > 0 && ! $product, 422, 'Sản phẩm gợi ý ở dòng '.($index + 1).' không hợp lệ.');

                $submittedUnit = trim((string) Arr::get($data, "unit.{$index}", ''));
                $catalogUnit = trim((string) ($product->unit ?? ''));
                $resolvedName = $product ? (string) $product->name : $itemName;
                $resolvedUnit = $submittedUnit !== '' ? $submittedUnit : ($catalogUnit !== '' ? $catalogUnit : 'cái');
                $quantity = (float) Arr::get($data, "quantity.{$index}", 1);
                $note = trim((string) Arr::get($data, "item_note.{$index}", '')) ?: null;

                if (! $existing) {
                    $created = $lockedRequest->items()->create([
                        'product_id' => $product ? (int) $product->id : null,
                        'item_name' => $resolvedName,
                        'quantity' => $quantity,
                        'unit' => $resolvedUnit,
                        'issued_quantity' => 0,
                        'serials' => null,
                        'note' => $note,
                    ]);
                    $keptIds->push((int) $created->id);
                    $newRows++;
                    continue;
                }

                $changed = trim((string) $existing->item_name) !== trim($resolvedName)
                    || abs((float) $existing->quantity - $quantity) > 0.0001
                    || trim((string) $existing->unit) !== trim($resolvedUnit)
                    || trim((string) ($existing->note ?? '')) !== trim((string) ($note ?? ''));

                if ($changed) {
                    if (Schema::hasTable('project_test_material_allocations')) {
                        DB::table('project_test_material_allocations')->where('material_item_id', $existing->id)->delete();
                    }
                    $existing->update([
                        'product_id' => $product ? (int) $product->id : ($productId > 0 ? $productId : null),
                        'item_name' => $resolvedName,
                        'quantity' => $quantity,
                        'unit' => $resolvedUnit,
                        'issued_quantity' => 0,
                        'serials' => null,
                        'note' => $note,
                    ]);
                    $invalidatedRows++;
                } else {
                    $preservedRows++;
                }
                $keptIds->push((int) $existing->id);
            }

            $removedItems = $existingItems->reject(fn ($item): bool => $keptIds->contains((int) $item->id));
            foreach ($removedItems as $removedItem) {
                if (Schema::hasTable('project_test_material_allocations')) {
                    DB::table('project_test_material_allocations')->where('material_item_id', $removedItem->id)->delete();
                }
                $removedItem->delete();
            }

            $lockedRequest->refresh()->load('items.allocations');
            $allocations = $lockedRequest->items->map(fn ($item) => $item->allocations->first());
            $state = 'waiting_match';
            if ($allocations->isNotEmpty() && ! $allocations->contains(null)) {
                if ($allocations->contains(fn ($allocation): bool => $allocation->status === 'waiting_purchase')) {
                    $state = 'waiting_purchase';
                } elseif ($allocations->contains(fn ($allocation): bool => $allocation->status === 'transfer')) {
                    $state = 'transfer';
                } elseif ($allocations->contains(fn ($allocation): bool => $allocation->status === 'shortage')) {
                    $state = 'shortage';
                } elseif ($allocations->every(fn ($allocation): bool => in_array($allocation->status, ['ready', 'reserved'], true))) {
                    $state = 'ready';
                }
            }
            $warehouseIds = $allocations->filter()->pluck('warehouse_id')->map(fn ($id): int => (int) $id)->filter()->unique()->values();

            $lockedRequest->update([
                'needed_at' => $data['needed_at'],
                'request_note' => $data['request_note'] ?? null,
                'status' => 'warehouse_check',
                'warehouse_status' => $state,
                'warehouse_id' => $warehouseIds->count() === 1 ? $warehouseIds->first() : null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'issued_by' => null,
                'receiver_id' => null,
                'issue_note' => null,
                'reserved_at' => null,
                'issued_at' => null,
                'handed_over_at' => null,
            ]);

            $from = $project->status;
            if (($lockedRequest->request_kind ?? 'standard') === 'standard') {
                $project->update([
                    'status' => 'warehouse_preparing',
                    'current_owner_role' => 'warehouse',
                    'progress' => max((int) $project->progress, 60),
                ]);
            }

            $this->history(
                $project,
                'Kỹ thuật cập nhật đề nghị cấp vật tư; chỉ chuyển Kho kiểm tra lại các dòng thay đổi',
                $from,
                $project->status,
                [
                    'material_request_id' => (int) $lockedRequest->id,
                    'item_count' => count($data['item_name']),
                    'catalog_suggestion_count' => $productIds->count(),
                    'warehouse_mapping_reset' => false,
                    'preserved_rows' => $preservedRows,
                    'invalidated_rows' => $invalidatedRows,
                    'new_rows' => $newRows,
                    'removed_rows' => $removedItems->count(),
                    'warehouse_status' => $state,
                    'manual_item_name_allowed' => true,
                    'workflow_version' => 'v9',
                ]
            );

            return compact('preservedRows', 'invalidatedRows', 'newRows') + ['removedRows' => $removedItems->count()];
        });

        return redirect()->route('project-test.show', [
            'project' => $project,
            'tab' => 'materials',
            'material_view' => 'proposal',
            'material_request' => $materialRequest->id,
        ])->with(
            'success',
            'Đã gửi lại Kho: giữ nguyên '.$result['preservedRows'].' dòng đã đối chiếu, kiểm tra lại '.($result['invalidatedRows'] + $result['newRows']).' dòng.'
        );
    }




    public function updateDailyLog(Request $request, Project $project, DailyLog $dailyLog)
    {
        $this->authorizeTechnicalRole();
        $this->authorizeProject($request, $project);
        abort_unless((int) $dailyLog->project_id === (int) $project->id, 404);

        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader']) || (int) $dailyLog->created_by === (int) $user->id,
            403
        );

        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['working', 'blocked', 'waiting_customer', 'waiting_material', 'done'])],
            'content' => ['required', 'string'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,zip,rar', 'max:30720'],
        ]);

        if ($request->hasFile('attachment_file')) {
            $this->deleteFile($dailyLog->attachment_file);
            $data['attachment_file'] = $request->file('attachment_file')->store('project-test/logs', 'public');
        }
        $dailyLog->update($data);
        $this->history(
            $project,
            'Chỉnh sửa nhật ký thi công ngày '.$dailyLog->log_date->format('d/m/Y'),
            $project->status,
            $project->status,
            ['daily_log_id' => $dailyLog->id]
        );

        return back()->with('success', 'Đã cập nhật nhật ký thi công.');
    }

    public function updateAcceptanceData(Request $request, Project $project)
    {
        $this->authorizeAcceptanceRole();
        $this->authorizeProject($request, $project);
        abort_unless($project->acceptance, 422, 'Công trình chưa có hồ sơ nghiệm thu để chỉnh sửa.');

        $data = $request->validate([
            'accepted_at' => ['required', 'date'],
            'device_serials' => ['required', 'string'],
            'monitoring_link' => ['nullable', 'url', 'max:700'],
            'monitoring_account' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'next_maintenance_at' => ['nullable', 'date'],
            'report_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:30720'],
            'checklist' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $project, $data) {
            $acceptance = $project->acceptance;
            $acceptanceData = [
                'accepted_at' => $data['accepted_at'],
                'checklist_json' => $data['checklist'] ?? $acceptance->checklist_json ?? [],
                'device_serials' => $data['device_serials'],
                'monitoring_link' => $data['monitoring_link'] ?? null,
                'monitoring_account' => $data['monitoring_account'] ?? null,
                'note' => $data['note'] ?? null,
            ];
            if ($request->hasFile('report_file')) {
                $this->deleteFile($acceptance->report_file);
                $acceptanceData['report_file'] = $request->file('report_file')->store('project-test/acceptances', 'public');
            }
            $acceptance->update($acceptanceData);

            $starts = Carbon::parse($data['accepted_at']);
            Warranty::updateOrCreate(['project_id' => $project->id], [
                'starts_at' => $starts->toDateString(),
                'ends_at' => $starts->copy()->addMonths((int) $data['warranty_months'])->toDateString(),
                'next_maintenance_at' => $data['next_maintenance_at'] ?? $starts->copy()->addMonths(6)->toDateString(),
                'assigned_to' => $project->lead_technician_id,
                'status' => 'active',
                'note' => 'Đã cập nhật từ hồ sơ nghiệm thu Công trình.',
            ]);

            $this->history(
                $project,
                'Cập nhật hồ sơ nghiệm thu và lịch bảo hành',
                $project->status,
                $project->status,
                ['edited_section' => 'acceptance_warranty']
            );
        });

        return back()->with('success', 'Đã cập nhật nghiệm thu và bảo hành.');
    }

    public function download(Project $project, string $kind)
    {
        $this->authorizeProject(request(), $project);
        $project->loadMissing(['survey', 'acceptance']);
        $path = match ($kind) {
            'design-3d' => $project->survey?->design_3d_file,
            'survey' => $project->survey?->attachment_file,
            'acceptance' => $project->acceptance?->report_file,
            default => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);
        return Storage::disk('public')->download($path);
    }

    private function hydrateMaterialInventorySnapshots(Project $project): void
    {
        $allocations = $project->materialRequests
            ->flatMap(fn ($materialRequest) => $materialRequest->items)
            ->flatMap(fn ($item) => $item->allocations)
            ->filter(fn ($allocation): bool => (int) $allocation->product_id > 0 && (int) $allocation->warehouse_id > 0)
            ->values();

        if ($allocations->isEmpty()) {
            return;
        }

        $pairs = $allocations
            ->map(fn ($allocation): array => [
                'product_id' => (int) $allocation->product_id,
                'warehouse_id' => (int) $allocation->warehouse_id,
            ])
            ->unique(fn (array $pair): string => $pair['product_id'].':'.$pair['warehouse_id'])
            ->values();

        $applyPairs = function ($query) use ($pairs): void {
            $query->where(function ($where) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $where->orWhere(function ($row) use ($pair): void {
                        $row->where('product_id', $pair['product_id'])
                            ->where('warehouse_id', $pair['warehouse_id']);
                    });
                }
            });
        };

        $stockMap = collect();
        if (Schema::hasTable('crm_product_stock')) {
            $stockQuery = DB::table('crm_product_stock')
                ->selectRaw('product_id, warehouse_id, SUM(qty) as qty')
                ->groupBy('product_id', 'warehouse_id');
            $applyPairs($stockQuery);
            $stockMap = $stockQuery->get()->keyBy(fn ($row): string => $row->product_id.':'.$row->warehouse_id);
        }

        $reservedMap = collect();
        if (Schema::hasTable('project_test_material_allocations')) {
            $reservedQuery = DB::table('project_test_material_allocations')
                ->where('status', 'reserved')
                ->selectRaw('product_id, warehouse_id, SUM(reserved_quantity) as qty')
                ->groupBy('product_id', 'warehouse_id');
            $applyPairs($reservedQuery);
            $reservedMap = $reservedQuery->get()->keyBy(fn ($row): string => $row->product_id.':'.$row->warehouse_id);
        }

        $costMap = collect();
        if (Schema::hasTable('crm_product_stock_lots') && Schema::hasColumn('crm_product_stock_lots', 'qty_remaining')) {
            $costExpression = 'COALESCE(NULLIF(actual_cost_after_vat, 0), NULLIF(cost_after_vat, 0), NULLIF(cost_before_vat, 0), 0)';
            $costQuery = DB::table('crm_product_stock_lots')
                ->where('qty_remaining', '>', 0)
                ->selectRaw("product_id, warehouse_id, SUM(qty_remaining * {$costExpression}) / NULLIF(SUM(qty_remaining), 0) as unit_cost")
                ->groupBy('product_id', 'warehouse_id');
            $applyPairs($costQuery);
            $costMap = $costQuery->get()->keyBy(fn ($row): string => $row->product_id.':'.$row->warehouse_id);
        }

        foreach ($allocations as $allocation) {
            $key = (int) $allocation->product_id.':'.(int) $allocation->warehouse_id;
            $stock = (float) data_get($stockMap->get($key), 'qty', 0);
            $reservedTotal = (float) data_get($reservedMap->get($key), 'qty', 0);
            $ownReserved = $allocation->status === 'reserved' ? (float) $allocation->reserved_quantity : 0.0;
            $reservedOther = max(0, $reservedTotal - $ownReserved);
            $available = max(0, $stock - $reservedOther);
            $unitCost = (float) ($allocation->unit_cost ?? 0);
            if ($unitCost <= 0) {
                $unitCost = (float) data_get($costMap->get($key), 'unit_cost', 0);
            }
            $quantity = (float) ($allocation->issued_quantity ?: $allocation->allocated_quantity ?: 0);

            $allocation->setAttribute('live_stock_qty', $stock);
            $allocation->setAttribute('live_reserved_other_qty', $reservedOther);
            $allocation->setAttribute('live_available_qty', $available);
            $allocation->setAttribute('live_unit_cost', $unitCost > 0 ? round($unitCost, 2) : null);
            $allocation->setAttribute('live_line_cost', $unitCost > 0 ? round($unitCost * $quantity, 2) : null);
        }
    }

    private function formOptions(bool $compactShow = false, array $selectedProductIds = [], int $selectedCustomerId = 0): array
    {
        // EGO_MATERIAL_WORKFLOW_V4_PRODUCT_CATALOG
        $activeCompanyId = (int) session('active_company_id', 0);
        $companies = collect();
        $customers = collect();
        $products = collect();
        $warehouses = collect();
        $salesOrders = collect();

        if (Schema::hasTable('companies')) {
            $companies = DB::table('companies')->select('id', 'name')->where('id', EgoCompanyLock::id())->orderBy('name')->get();
        }
        if (Schema::hasTable('crm_customers')) {
            $customersQuery = DB::table('crm_customers')->select('id', 'name', 'phone', 'address', 'company_id')->orderByDesc('id')->limit($compactShow ? 250 : 1000);
            if ($activeCompanyId && Schema::hasColumn('crm_customers', 'company_id')) {
                $customersQuery->where(function ($q) use ($activeCompanyId) {
                    $q->whereNull('company_id')->orWhere('company_id', $activeCompanyId);
                });
            }
            $customers = $customersQuery->get();
            if ($compactShow && $selectedCustomerId > 0 && ! $customers->contains('id', $selectedCustomerId)) {
                $selectedCustomer = DB::table('crm_customers')
                    ->select('id', 'name', 'phone', 'address', 'company_id')
                    ->where('id', $selectedCustomerId)
                    ->first();
                if ($selectedCustomer) {
                    $customers->prepend($selectedCustomer);
                }
            }
        }
        if (Schema::hasTable('crm_product_catalog')) {
            $productQuery = DB::table('crm_product_catalog as products')
                ->leftJoin('crm_brands as brands', 'brands.id', '=', 'products.brand_id')
                ->leftJoin('crm_product_stock as stocks', 'stocks.product_id', '=', 'products.id')
                ->where('products.is_active', 1)
                ->select([
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.barcode',
                    'products.unit',
                    'products.description',
                    'products.image_url',
                    'products.is_serialized',
                    'products.company_id',
                    'brands.name as brand_name',
                ])
                ->selectRaw('COALESCE(SUM(stocks.qty), 0) as total_stock_qty')
                ->groupBy([
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.barcode',
                    'products.unit',
                    'products.description',
                    'products.image_url',
                    'products.is_serialized',
                    'products.company_id',
                    'brands.name',
                ]);

            if ($activeCompanyId > 0 && Schema::hasColumn('crm_product_catalog', 'company_id')) {
                $productQuery->where(function ($query) use ($activeCompanyId): void {
                    $query->where('products.company_id', $activeCompanyId)
                        ->orWhereNull('products.company_id');
                });
            }

            if ($compactShow) {
                $selectedProductIds = collect($selectedProductIds)
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($selectedProductIds === []) {
                    $products = collect();
                } else {
                    $products = $productQuery
                        ->whereIn('products.id', $selectedProductIds)
                        ->orderBy('products.name')
                        ->get();
                }
            } else {
                $products = $productQuery
                    ->orderByRaw('COALESCE(SUM(stocks.qty), 0) > 0 DESC')
                    ->orderBy('products.name')
                    ->limit(1500)
                    ->get();
            }
        }
        if (Schema::hasTable('crm_warehouses')) {
            $warehouses = DB::table('crm_warehouses')->select('id', 'name')->orderBy('name')->get();
        }
        if (! $compactShow && Schema::hasTable('crm_orders')) {
            $salesOrdersQuery = DB::table('crm_orders as o')
                ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
                ->select([
                    'o.id', 'o.order_code', 'o.order_date', 'o.total_amount', 'o.created_by',
                    'o.shipping_address', 'o.receiver_name', 'o.receiver_phone',
                    'c.id as customer_id', 'c.name as customer_name', 'c.phone as customer_phone', 'c.address as customer_address',
                ])
                ->where('o.company_id', EgoCompanyLock::id())
                ->whereNull('o.deleted_at')
                ->orderByDesc('o.id')
                ->limit(500);

            $formUser = auth()->user();
            if ($formUser && $formUser->hasAnyRole(['sales', 'sales_staff']) && ! $formUser->hasAnyRole(['admin', 'sales_manager'])) {
                $salesOrdersQuery->where('o.created_by', $formUser->id);
            }
            $salesOrders = $salesOrdersQuery->get();
        }

        $activeUsers = $compactShow
            ? collect()
            : User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $salesUsers = $this->usersByRoles(['sales', 'sales_staff', 'sales_manager']);
        $technicalManagers = $this->usersByRoles(['technical_manager', 'technical_leader']);
        $technicians = $this->usersByRoles(['ky_thuat', 'technical', 'technician', 'technical_staff']);

        return compact(
            'companies',
            'customers',
            'products',
            'warehouses',
            'salesOrders',
            'activeUsers',
            'salesUsers',
            'technicalManagers',
            'technicians'
        );
    }



    private function authorizeCreateProject(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->hasAnyRole([
                    'admin', 'sales', 'sales_staff', 'sales_manager', 'technical_manager', 'technical_leader',
                    'cskh', 'customer_service', 'customer_care',
                ]) || $user->can('project-test.create')
            ),
            403
        );
    }

    private function authorizeRequestCoordinator(Project $project): void
    {
        $user = auth()->user();
        $allowed = $user && (
            $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader', 'cskh', 'customer_service', 'customer_care'])
            || (int) $project->created_by === (int) $user->id
            || (int) $project->sales_user_id === (int) $user->id
            || ($project->request_source === 'sales' && $user->hasAnyRole(['sales', 'sales_staff', 'sales_manager']) && ((int) $project->sales_user_id === (int) $user->id || $user->hasRole('sales_manager')))
        );
        abort_unless($allowed, 403);
    }

    private function authorizeCustomerConfirmation(Project $project): void
    {
        $user = auth()->user();
        $allowed = $user && (
            $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader', 'cskh', 'customer_service', 'customer_care'])
            || (int) $project->sales_user_id === (int) $user->id
            || ($project->request_source === 'sales' && $user->hasAnyRole(['sales', 'sales_staff', 'sales_manager']) && ((int) $project->sales_user_id === (int) $user->id || $user->hasRole('sales_manager')))
        );
        abort_unless($allowed, 403);
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can($permission)), 403);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        $user = $request->user();

        /*
         * EGO_WAREHOUSE_PROJECT_MATERIAL_ACCESS_V1
         *
         * Kho chỉ được mở công trình khi:
         * - Đang vào tab vật tư.
         * - Công trình thuộc đúng công ty hiện tại.
         * - Công trình có phiếu vật tư đang thuộc luồng Kho.
         *
         * Không cấp project-test.access tổng quát cho role Kho.
         */
        $isWarehouseOnly = $user
            && $user->hasAnyRole(['warehouse', 'kho'])
            && ! $user->hasAnyRole([
                'admin',
                'management',
                'manager',
                'technical_manager',
                'technical_leader',
                'sales_manager',
                'sales',
                'sales_staff',
                'ky_thuat',
            ]);

        if (
            $isWarehouseOnly
            && $request->query('tab') === 'materials'
        ) {
            $allowedForWarehouse = Project::query()
                ->whereKey($project->getKey())
                ->where('company_id', EgoCompanyLock::id())
                ->whereHas('materialRequests', function ($query): void {
                    $query->whereIn('status', [
                        'warehouse_check',
                        'pending_manager',
                        'approved',
                        'preparing',
                        'issued',
                        'revision',
                    ]);
                })
                ->exists();

            abort_unless(
                $allowedForWarehouse,
                403,
                'Kho chỉ được truy cập công trình có phiếu vật tư đang xử lý.'
            );

            return;
        }

        $this->authorizePermission('project-test.access');

        $visible = Project::query()
            ->visibleTo($user)
            ->whereKey($project->getKey())
            ->exists();

        abort_unless($visible, 403);
    }

    private function canViewProjectProfit(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager'])
            || $user->can('finance.project_profit.view');
    }

    private function canEditProjectRevenue(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'management', 'manager', 'sales_manager'])) {
            return true;
        }

        return $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );
    }

    private function canEditProjectCosts(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager'])
            || $user->can('finance.project_profit.manage');
    }

    private function canManageProjectExpenses(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'management', 'manager', 'accounting', 'sales_manager', 'warehouse', 'kho'])) {
            return true;
        }

        return $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );
    }

    private function canReviewProjectExpenses(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager', 'accounting']);
    }
    /* EGO_PROJECT_WORKFLOW_360_V2_FINANCE_ACCESS */
    private function canViewProjectFinancials(User $user, Project $project): bool
    {
        if (($project->request_source ?? 'sales') !== 'sales') {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'management', 'manager'])) {
            return true;
        }

        if ($user->hasRole('sales_manager')) {
            return true;
        }

        return $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );
    }
    private function actionAbilities(User $user, Project $project): array
    {
        $isAdmin = $user->hasRole('admin');
        $isSales = $user->hasAnyRole(['sales', 'sales_staff', 'sales_manager']);
        $isCustomerCare = $user->hasAnyRole(['cskh', 'customer_service', 'customer_care']);
        $isTechnicalManager = $user->hasAnyRole(['technical_manager', 'technical_leader']);
        $isTechnical = $user->hasAnyRole(['ky_thuat', 'technical', 'technician', 'technical_staff']);
        $isProjectCoordinator = $isAdmin || $isTechnicalManager || $isCustomerCare
            || (int) $project->created_by === (int) $user->id
            || (int) $project->sales_user_id === (int) $user->id
            || ($project->request_source === 'sales' && $isSales && ((int) $project->sales_user_id === (int) $user->id || $user->hasRole('sales_manager')));

        return [
            'sales' => $isAdmin || $isSales,
            'customerCoordinator' => $isProjectCoordinator,
            'requestCoordinator' => $isProjectCoordinator,
            'technical' => $isAdmin || $isTechnicalManager || $isTechnical,
            'technicalManager' => $isAdmin || $isTechnicalManager,
            'admin' => $isAdmin || $user->can('project-test.admin'),
            'warehouse' => $isAdmin || $user->hasAnyRole(['warehouse', 'kho']),
            'acceptance' => $isAdmin || $isTechnicalManager || $user->can('project-test.acceptance'),
            'editBasic' => $isProjectCoordinator,
            'editPeople' => $isAdmin || $isTechnicalManager,
            'editSurvey' => $isAdmin || $isTechnicalManager || $isTechnical,
            'editMaterials' => $isAdmin || $isTechnicalManager || $isTechnical,
            'editAcceptance' => $isAdmin || $isTechnicalManager || $user->can('project-test.acceptance'),
        ];
    }

    private function usersByRoles(array $roles)
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles): void {
                $query->whereIn('name', $roles);
            })
            ->select('users.id', 'users.name', 'users.email')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    private function authorizeSalesRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'sales', 'sales_manager']), 403);
    }

    private function authorizeTechnicalCreator(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader']), 403);
    }

    private function authorizeTechnicalWorkspaceRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole([
            'admin', 'management', 'manager', 'technical_manager', 'technical_leader',
            'ky_thuat', 'technical', 'technician', 'technical_staff',
        ]), 403);
    }

    private function authorizeTechnicalRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader', 'ky_thuat', 'technical', 'technician', 'technical_staff']), 403);
    }

    private function authorizeTechnicalManagerRole(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader']), 403);
    }

    private function authorizeAcceptanceRole(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasAnyRole(['admin', 'technical_manager', 'technical_leader']) || $user->can('project-test.acceptance')),
            403
        );
    }

    private function assertUserHasRole(int $userId, array $roles, string $label): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles): void {
                $query->whereIn('name', $roles);
            })
            ->first();

        abort_unless($user, 422, $label.' không đúng vai trò hoặc tài khoản đã ngừng hoạt động.');

        return $user;
    }

    private function validateTeamAssignment(Request $request, bool $allowPastDate = false): array
    {
        $dateRules = ['required', 'date'];
        if (! $allowPastDate) {
            $dateRules[] = 'after_or_equal:today';
        }

        $data = $request->validate([
            'lead_technician_id' => ['required', 'integer'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'work_date' => $dateRules,
            'note' => ['nullable', 'string'],
        ]);

        $this->assertUserHasRole((int) $data['lead_technician_id'], ['ky_thuat'], 'Đội trưởng thi công');
        foreach (collect($data['member_ids'] ?? [])->unique() as $memberId) {
            $this->assertUserHasRole((int) $memberId, ['ky_thuat'], 'Thành viên kỹ thuật');
        }

        return $data;
    }

    private function persistTeamAssignment(Request $request, Project $project, array $data, bool $allowEmpty = false): void
    {
        $leadId = (int) ($data['lead_technician_id'] ?? 0);
        $memberIds = collect($data['member_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (! $leadId && $allowEmpty) {
            $project->assignments()->delete();
            $project->lead_technician_id = $project->survey?->surveyed_by;
            $project->save();
            return;
        }

        abort_unless($leadId, 422, 'Vui lòng chọn đội trưởng thi công.');
        $this->assertUserHasRole($leadId, ['ky_thuat'], 'Đội trưởng thi công');
        foreach ($memberIds as $memberId) {
            $this->assertUserHasRole($memberId, ['ky_thuat'], 'Thành viên kỹ thuật');
        }

        $project->assignments()->delete();
        $ids = $memberIds->push($leadId)->unique()->values();
        foreach ($ids as $id) {
            Assignment::create([
                'project_id' => $project->id,
                'user_id' => $id,
                'assigned_by' => $request->user()->id,
                'assignment_role' => (int) $id === $leadId ? 'leader' : 'member',
                'work_date' => $data['work_date'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        }

        $project->lead_technician_id = $leadId;
        $project->technical_manager_id = $project->technical_manager_id ?: $request->user()->id;
        $project->save();
    }

    private function roleLabel(User $user): string
    {
        return $user->getRoleNames()->map(fn ($role) => str_replace('_', ' ', mb_strtoupper($role)))->implode(' · ') ?: 'Tài khoản';
    }

    private function transition(Project $project, string $toStatus, string $note): void
    {
        $from = $project->status;
        $info = self::STATUSES[$toStatus] ?? null;
        abort_unless($info, 500, 'Trạng thái workflow không hợp lệ.');
        $project->status = $toStatus;
        $project->current_owner_role = $info['owner'];
        $project->progress = max((int) $project->progress, (int) $info['progress']);
        if (in_array($toStatus, ['cancelled'], true)) {
            $project->progress = 0;
        }
        $project->save();
        $this->history($project, $note, $from, $toStatus);
    }

    private function history(Project $project, string $action, ?string $from, ?string $to, array $meta = []): void
    {
        History::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $action,
            'meta' => $meta ?: null,
        ]);
    }

    private function assertStatus(Project $project, array $allowed): void
    {
        abort_unless(in_array($project->status, $allowed, true), 422, 'Công trình không còn ở bước phù hợp để thực hiện thao tác này.');
    }

    private function nextProjectCode(): string
    {
        $prefix = 'CT-'.now()->format('Ym').'-';
        $last = Project::withTrashed()->where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function nextMaterialCode(): string
    {
        $prefix = 'VT-'.now()->format('Ym').'-';
        $last = MaterialRequest::where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function linesToArray(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
