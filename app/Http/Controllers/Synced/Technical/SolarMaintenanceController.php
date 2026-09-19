<?php

namespace App\Http\Controllers\Synced\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Synced\Technical\StoreSolarMaintenanceRequest;
use App\Http\Requests\Synced\Technical\UpdateSolarMaintenanceRequest;
use App\Http\Requests\Synced\Technical\UpdateSolarMaintenanceStatusRequest;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarWarrantyClaim;
use App\Models\SolarWarrantyStockMovement;
use App\Services\Synced\Technical\SolarMaintenanceQueryService;
use App\Services\Synced\Technical\SolarMaintenanceService;
use App\Services\Technical\SolarWarrantyQueryService;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Quản lý lịch bảo trì / bảo hành hệ thống điện mặt trời (danh sách, tạo, cập nhật, đổi trạng thái, xóa).
 */
class SolarMaintenanceController extends Controller
{
    /**
     * Khởi tạo controller với service truy vấn và service nghiệp vụ bảo trì.
     */
    public function __construct(
        private readonly SolarMaintenanceQueryService $queryService,
        private readonly SolarMaintenanceService $service,
        private readonly SolarWarrantyQueryService $warrantyQuery,
    ) {}

    /**
     * Hiển thị trang danh sách lịch bảo trì kèm bộ lọc, thống kê và quyền thao tác.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);

        $user = $request->user();
        $schedules = $this->queryService->schedules($request, $user);
        $summary = $this->queryService->summary($user);
        $overviewSchedules = $this->queryService->overviewSchedules($user, 8);
        $technicianWorkload = $this->queryService->technicianWorkload($user, 6);
        $maintenanceRounds = $this->queryService->roundOverview($user, 4);
        $sites = $this->queryService->recentSites($user);
        $users = $this->queryService->technicalUsers();
        $activeView = in_array($request->input('view'), ['overview', 'maintenance', 'claims', 'stock', 'files'], true)
            ? (string) $request->input('view')
            : 'overview';
        $warrantySummary = $this->warrantyQuery->summary($user);
        $claims = $this->warrantyQuery->claims($request, $user);
        $stockMovements = $this->warrantyQuery->stockMovements($request, $user);
        $openClaims = $this->warrantyQuery->openClaimOptions($user);
        $warehouses = $this->warrantyQuery->warehouses($user);
        $documentSites = $this->warrantyQuery->documentSites($user);
        $recentClaims = $this->warrantyQuery->recentClaims($user);
        $recentStock = $this->warrantyQuery->recentStock($user);

        $types = SolarMaintenanceSchedule::TYPES;
        $statuses = SolarMaintenanceSchedule::STATUSES;
        $priorities = SolarMaintenanceSchedule::PRIORITIES;
        $filters = $request->only([
            'q', 'month', 'date_from', 'date_to', 'status', 'type',
            'priority', 'assignee_id', 'overdue',
        ]);

        $filters['month'] = $filters['month'] ?? now()->format('Y-m');

        $permissions = [
            'create' => $user->can('create', SolarMaintenanceSchedule::class),
            'manage' => SolarMaintenanceAccess::canManage($user),
            'admin' => SolarMaintenanceAccess::isAdmin($user),
            'approve' => SolarMaintenanceAccess::canApprove($user),
            'claim_create' => SolarMaintenanceAccess::canCreateWarrantyClaim($user),
            'stock_manage' => SolarMaintenanceAccess::canHandleWarrantyStock($user),
            'manager' => SolarMaintenanceAccess::isManager($user),
            'warehouse' => SolarMaintenanceAccess::isWarehouse($user),
            'technician_only' => SolarMaintenanceAccess::isTechnicianOnly($user),
        ];

        return view('synced.technical.maintenance.index', compact(
            'schedules',
            'summary',
            'overviewSchedules',
            'technicianWorkload',
            'maintenanceRounds',
            'sites',
            'users',
            'types',
            'statuses',
            'priorities',
            'filters',
            'permissions',
            'activeView',
            'warrantySummary',
            'claims',
            'stockMovements',
            'openClaims',
            'warehouses',
            'documentSites',
            'recentClaims',
            'recentStock',
        ) + [
            'claimTypes' => SolarWarrantyClaim::TYPES,
            'claimStatuses' => SolarWarrantyClaim::STATUSES,
            'claimPriorities' => SolarWarrantyClaim::PRIORITIES,
            'stockTypes' => SolarWarrantyStockMovement::TYPES,
            'stockStatuses' => SolarWarrantyStockMovement::STATUSES,
        ]);
    }

    /**
     * Tạo chuỗi các đợt bảo trì / bảo hành mới từ dữ liệu đã validate.
     */
    public function store(StoreSolarMaintenanceRequest $request): RedirectResponse
    {
        $this->authorize('create', SolarMaintenanceSchedule::class);

        $created = $this->service->createSeries($request->validated(), $request->user());

        return back()->with(
            'success',
            'Đã tạo '.$created->count().' đợt bảo trì / bảo hành và ghi lịch sử đầy đủ.'
        );
    }

    /**
     * Trả về chi tiết một đợt bảo trì dạng JSON (kèm lịch sử trạng thái và quyền).
     */
    public function showJson(Request $request, SolarMaintenanceSchedule $schedule): JsonResponse
    {
        $schedule->load(['site', 'assignees.user', 'statusHistories.user']);

        if (! $request->user()->can('view', $schedule)) {
            return response()->json([
                'message' => 'Bạn chưa có quyền xem đợt bảo trì này.',
            ], 403);
        }

        $hasValidSite = (bool) ($schedule->site_id && $schedule->site);

        return response()->json([
            'id' => $schedule->id,
            'schedule_code' => $schedule->schedule_code,
            'company_id' => $schedule->company_id,
            'site_id' => $schedule->site_id,
            'customer_name' => $schedule->customer_name,
            'site_name' => $schedule->site_name,
            'address' => $schedule->address,
            'type' => $schedule->type,
            'status' => $schedule->status,
            'priority' => $schedule->priority,
            'scheduled_date' => optional($schedule->scheduled_date)->format('Y-m-d'),
            'completed_date' => optional($schedule->completed_date)->format('Y-m-d'),
            'assigned_user_ids' => $schedule->assignees->pluck('user_id')->map(fn ($id) => (int) $id)->values(),
            'assignee_names' => $schedule->assignee_names,
            'system_kwp' => $schedule->system_kwp,
            'inverter_info' => $schedule->inverter_info,
            'issue_note' => $schedule->issue_note,
            'technical_note' => $schedule->technical_note,
            'result_note' => $schedule->result_note,
            'round_no' => $schedule->round_no,
            'total_rounds' => $schedule->total_rounds,
            'site' => $schedule->site,
            'has_valid_site' => $hasValidSite,
            'site_detail_url' => $hasValidSite
                ? route('projects-unified.maintenance.site', ['site' => $schedule->site_id])
                : null,
            'schedule_detail_url' => route('projects-unified.maintenance.show', ['schedule' => $schedule->id]),
            'round_label' => 'Đợt '.((int) ($schedule->round_no ?: 1)).'/'.((int) ($schedule->total_rounds ?: 1)),
            'history' => $schedule->statusHistories->take(20)->map(fn ($item) => [
                'from_status' => $item->from_status,
                'to_status' => $item->to_status,
                'reason' => $item->reason,
                'note' => $item->note,
                'changed_by' => $item->user?->name,
                'changed_at' => optional($item->changed_at)->format('d/m/Y H:i'),
            ])->values(),
            'permissions' => [
                'update' => $request->user()->can('update', $schedule),
                'change_status' => $request->user()->can('changeStatus', $schedule),
                'delete' => $request->user()->can('delete', $schedule),
            ],
        ]);
    }

    /**
     * Tìm kiếm công trình theo từ khóa, trả JSON cho ô chọn công trình.
     */
    public function sitesSearch(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);

        $keyword = trim((string) $request->input('q', ''));
        $sites = $this->queryService->searchSites($keyword, $request->user());

        return response()->json([
            'items' => $sites->map(fn ($site) => [
                'value' => $site->id,
                'text' => $site->name
                    .($site->contact_name ? ' — '.$site->contact_name : '')
                    .($site->contact_phone ? ' — '.$site->contact_phone : ''),
                'name' => $site->name,
                'contact_name' => $site->contact_name,
                'contact_phone' => $site->contact_phone,
                'address' => $site->address,
                'system_kwp' => $site->system_kwp,
                'system_kw_ac' => $site->system_kw_ac,
                'battery_kwh' => $site->battery_kwh,
                'system_type' => $site->system_type,
                'phase' => $site->phase,
                'installed_at' => $site->installed_at,
                'warranty_to' => $site->warranty_to,
                'monitoring_link' => $site->monitoring_link,
                'monitoring_account' => $site->monitoring_account,
            ])->values(),
        ]);
    }

    /**
     * Cập nhật thông tin một đợt bảo trì và ghi lịch sử thay đổi.
     */
    public function update(
        UpdateSolarMaintenanceRequest $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {
        $this->authorize('update', $schedule);

        $data = $request->validated();
        $assignmentChanged = array_key_exists('leader_user_id', $data)
            || array_key_exists('member_user_ids', $data)
            || array_key_exists('assigned_user_ids', $data);

        if ($assignmentChanged) {
            abort_unless(SolarMaintenanceAccess::isManager($request->user()), 403);
        }

        $updated = $this->service->update($schedule, $data, $request->user());

        if ($assignmentChanged && $updated->assignees->isNotEmpty()) {
            $updated->assignees()->update(['accepted_at' => null, 'started_at' => null]);
            $updated->approvals()->create([
                'approval_level' => 'assignment',
                'submitted_by' => $request->user()->id,
                'action' => 'assignment_submitted',
                'status' => 'pending',
                'comment' => 'Đã chọn nhóm kỹ thuật, chờ Admin duyệt phân công.',
                'submitted_at' => now(),
                'metadata' => ['assignee_ids' => $updated->assignees->pluck('user_id')->all()],
            ]);

            return back()->with('success', 'Đã chọn nhân sự. Chờ Admin duyệt phân công.');
        }

        return back()->with('success', 'Đã cập nhật lịch và ghi nhận lịch sử thay đổi.');
    }

    public function approveAssignment(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('view', $schedule);
        abort_unless(SolarMaintenanceAccess::isAdmin($request->user()), 403, 'Chỉ Admin được duyệt phân công.');

        if (! $schedule->assignees()->exists()) {
            return back()->with('error', 'Cần chọn kỹ thuật viên trước khi duyệt.');
        }

        DB::transaction(function () use ($request, $schedule): void {
            if (in_array($schedule->status, ['draft', 'scheduled', 'unassigned'], true)) {
                $this->service->changeStatus($schedule, [
                    'status' => 'assigned',
                    'reason' => 'Admin duyệt phân công nhóm kỹ thuật.',
                ], $request->user());
                $schedule->refresh();
            }

            $schedule->approvals()->create([
                'approval_level' => 'assignment',
                'submitted_by' => $request->user()->id,
                'approver_id' => $request->user()->id,
                'action' => 'assignment_approved',
                'status' => 'approved',
                'comment' => 'Admin đã duyệt danh sách kỹ thuật viên được phân công.',
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'metadata' => ['assignee_ids' => $schedule->assignees()->pluck('user_id')->all()],
            ]);
        });

        return back()->with('success', 'Đã duyệt phân công. Kỹ thuật viên có thể nhận việc.');
    }

    public function acceptAssignment(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('view', $schedule);
        $assignment = $schedule->assignees()->where('user_id', $request->user()->id)->first();
        abort_unless($assignment, 403, 'Chỉ người được phân công mới được nhận việc.');

        $approval = $schedule->approvals()->where('approval_level', 'assignment')->latest('id')->first();
        if ($approval && $approval->status !== 'approved') {
            return back()->with('error', 'Admin chưa duyệt phân công.');
        }

        DB::transaction(function () use ($request, $schedule, $assignment): void {
            $assignment->forceFill([
                'accepted_at' => $assignment->accepted_at ?: now(),
                'started_at' => $assignment->started_at ?: now(),
            ])->save();

            if (in_array($schedule->status, ['assigned', 'customer_confirmed', 'travelling'], true)) {
                $this->service->changeStatus($schedule, [
                    'status' => 'in_progress',
                    'reason' => $request->user()->name.' đã nhận việc.',
                ], $request->user());
            }
        });

        return back()->with('success', 'Đã nhận việc. Bạn có thể bắt đầu thực hiện.');
    }

    /**
     * Đổi trạng thái đợt bảo trì và lưu nhật ký xử lý.
     */
    public function updateStatus(
        UpdateSolarMaintenanceStatusRequest $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {
        $this->authorize('changeStatus', $schedule);

        $this->service->changeStatus($schedule, $request->validated(), $request->user());

        return back()->with('success', 'Đã cập nhật trạng thái và lưu nhật ký xử lý.');
    }

    /**
     * Xóa mềm một đợt bảo trì (chuyển vào thùng rác, giữ lại lịch sử).
     */
    public function destroy(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $this->service->softDelete($schedule, $request->user());

        return back()->with('success', 'Đã chuyển lịch vào thùng rác an toàn. Dữ liệu lịch sử vẫn được giữ.');
    }
}
