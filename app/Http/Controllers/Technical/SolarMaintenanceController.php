<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technical\StoreSolarMaintenanceRequest;
use App\Http\Requests\Technical\UpdateSolarMaintenanceRequest;
use App\Http\Requests\Technical\UpdateSolarMaintenanceStatusRequest;
use App\Models\SolarMaintenanceSchedule;
use App\Services\SolarMaintenanceQueryService;
use App\Services\SolarMaintenanceService;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SolarMaintenanceController extends Controller
{
    public function __construct(
        private readonly SolarMaintenanceQueryService $queryService,
        private readonly SolarMaintenanceService $service,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);

        $user = $request->user();
        $schedules = $this->queryService->schedules($request, $user);
        $summary = $this->queryService->summary($user);
        $sites = $this->queryService->recentSites($user);
        $users = $this->queryService->technicalUsers();

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
        ];

        return view('technical.maintenance.index', compact(
            'schedules',
            'summary',
            'sites',
            'users',
            'types',
            'statuses',
            'priorities',
            'filters',
            'permissions',
        ));
    }

    public function store(StoreSolarMaintenanceRequest $request): RedirectResponse
    {
        $this->authorize('create', SolarMaintenanceSchedule::class);

        $created = $this->service->createSeries($request->validated(), $request->user());

        return back()->with(
            'success',
            'Đã tạo ' . $created->count() . ' đợt bảo trì / bảo hành và ghi lịch sử đầy đủ.'
        );
    }

    public function showJson(Request $request, SolarMaintenanceSchedule $schedule): JsonResponse
    {
        $schedule->load(['site', 'assignees.user', 'statusHistories.user']);

        if (!$request->user()->can('view', $schedule)) {
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
                ? route('ky-thuat.maintenance.site', ['site' => $schedule->site_id])
                : null,
            'schedule_detail_url' => route('ky-thuat.maintenance.show', ['schedule' => $schedule->id]),
            'round_label' => 'Đợt ' . ((int) ($schedule->round_no ?: 1)) . '/' . ((int) ($schedule->total_rounds ?: 1)),
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

    public function sitesSearch(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);

        $keyword = trim((string) $request->input('q', ''));
        $sites = $this->queryService->searchSites($keyword, $request->user());

        return response()->json([
            'items' => $sites->map(fn ($site) => [
                'value' => $site->id,
                'text' => $site->name
                    . ($site->contact_name ? ' — ' . $site->contact_name : '')
                    . ($site->contact_phone ? ' — ' . $site->contact_phone : ''),
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

    public function update(
        UpdateSolarMaintenanceRequest $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {
        $this->authorize('update', $schedule);

        $this->service->update($schedule, $request->validated(), $request->user());

        return back()->with('success', 'Đã cập nhật lịch và ghi nhận lịch sử thay đổi.');
    }

    public function updateStatus(
        UpdateSolarMaintenanceStatusRequest $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {
        $this->authorize('changeStatus', $schedule);

        $this->service->changeStatus($schedule, $request->validated(), $request->user());

        return back()->with('success', 'Đã cập nhật trạng thái và lưu nhật ký xử lý.');
    }

    public function destroy(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $this->service->softDelete($schedule, $request->user());

        return back()->with('success', 'Đã chuyển lịch vào thùng rác an toàn. Dữ liệu lịch sử vẫn được giữ.');
    }
}
