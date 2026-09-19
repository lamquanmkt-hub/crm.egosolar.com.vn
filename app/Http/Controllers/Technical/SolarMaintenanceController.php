<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technical\StoreSolarMaintenanceRequest;
use App\Http\Requests\Technical\UpdateSolarMaintenanceRequest;
use App\Http\Requests\Technical\UpdateSolarMaintenanceStatusRequest;
use App\Models\SolarMaintenanceProfile;
use App\Models\SolarMaintenanceSchedule;
use App\Services\Technical\MaintenanceProjectHandoffService;
use App\Services\Technical\SolarMaintenanceQueryService;
use App\Services\Technical\SolarMaintenanceService;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
    ) {}

    /**
     * Hiển thị trang danh sách lịch bảo trì kèm bộ lọc, thống kê và quyền thao tác.
     */

    /**
     * Xóa hồ sơ khỏi Bảo hành & O&M.
     * Không xóa project/công trình CRM gốc.
     */
    public function destroyMaintenanceProfile(
        Request $request,
        SolarMaintenanceProfile $profile
    ) {
        $user = $request->user();

        abort_unless(
            $user
            && (
                $user->hasAnyRole([
                    'admin',
                    'management',
                    'manager',
                    'technical_manager',
                    'technical_leader',
                    'truong_phong_ky_thuat',
                ])
                || \App\Support\SolarMaintenanceAccess::isManager($user)
            ),
            403,
            'Bạn không có quyền xóa hồ sơ bảo hành.'
        );

        DB::transaction(function () use ($profile) {

            /*
             * Soft delete toàn bộ các đợt thuộc profile.
             */
            $scheduleQuery = DB::table(
                'solar_maintenance_schedules'
            )->where(
                'maintenance_profile_id',
                $profile->id
            );

            if (
                Schema::hasColumn(
                    'solar_maintenance_schedules',
                    'deleted_at'
                )
            ) {
                $scheduleQuery->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $scheduleQuery->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
            }

            /*
             * Ẩn profile khỏi O&M.
             */
            $payload = [
                'status' => 'deleted',
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'solar_maintenance_profiles',
                    'deleted_at'
                )
            ) {
                $payload['deleted_at'] = now();
            }

            DB::table(
                'solar_maintenance_profiles'
            )
                ->where('id', $profile->id)
                ->update($payload);

            /*
             * Xóa event lịch kỹ thuật được sync từ O&M nếu có.
             */
            if (
                Schema::hasTable(
                    'technical_schedule_events'
                )
            ) {
                $scheduleIds = DB::table(
                    'solar_maintenance_schedules'
                )
                    ->where(
                        'maintenance_profile_id',
                        $profile->id
                    )
                    ->pluck('id');

                $eventIds = DB::table(
                    'technical_schedule_events'
                )
                    ->where(
                        'source_type',
                        'maintenance'
                    )
                    ->whereIn(
                        'source_id',
                        $scheduleIds
                    )
                    ->pluck('id');

                if (
                    $eventIds->isNotEmpty()
                    && Schema::hasTable(
                        'technical_schedule_event_users'
                    )
                ) {
                    DB::table(
                        'technical_schedule_event_users'
                    )
                        ->whereIn(
                            'event_id',
                            $eventIds
                        )
                        ->delete();
                }

                DB::table(
                    'technical_schedule_events'
                )
                    ->whereIn(
                        'id',
                        $eventIds
                    )
                    ->delete();
            }
        });

        return back()->with(
            'success',
            'Đã xóa hồ sơ khỏi Bảo hành & O&M. Công trình CRM gốc vẫn được giữ nguyên.'
        );
    }
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);
        app(MaintenanceProjectHandoffService::class)->syncExistingCompletedThrottled();

        $allowedViews = ['all', 'needs_action', 'upcoming', 'overdue', 'completed'];
        $currentView = (string) $request->input('view', 'all');
        if (! in_array($currentView, $allowedViews, true)) {
            $currentView = 'all';
        }
        $request->merge(['view' => $currentView]);

        $user = $request->user();
        $schedules = $this->queryService->schedules($request, $user);
        $summary = $this->queryService->summary($user);
        $sites = $this->queryService->recentSites($user);
        $users = $this->queryService->technicalUsers();

        $profileQuery = SolarMaintenanceProfile::query();
        $companyId = EgoCompanyScope::currentId();
        if (
            $companyId > 0
            && ! SolarMaintenanceAccess::isAdmin($user)
            && ! SolarMaintenanceAccess::isTechnician($user)
        ) {
            $profileQuery->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $waitingProfiles = (clone $profileQuery)
            ->where('status', 'waiting_plan')
            ->orderByDesc('source_completed_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $profileCount = (clone $profileQuery)->where('status', 'waiting_plan')->count();
        $summary['waiting_plan'] = $profileCount;

        /*
         * Dropdown lập kế hoạch trước đây chỉ lấy bảng `sites`, trong khi
         * "Danh sách công trình tổng" còn có công trình Kỹ thuật tự tạo
         * nằm ở project_test/maintenance profile và có thể không có bản ghi site.
         * Bổ sung các profile chưa xuất hiện trong $sites để không mất công trình.
         */
        $loadedSiteIds = $sites->pluck('id')->map(fn ($id) => (int) $id)->all();
        $profileOptionQuery = clone $profileQuery;
        if ($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            // Dropdown tạo kế hoạch phải bám công ty đang làm việc, giống danh sách sites.
            $profileOptionQuery->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $profileOptions = $profileOptionQuery
            ->where('status', '!=', 'deleted')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->filter(function (SolarMaintenanceProfile $profile) use ($loadedSiteIds) {
                $siteId = (int) ($profile->site_id ?? 0);
                return $siteId <= 0 || ! in_array($siteId, $loadedSiteIds, true);
            })
            ->values();

        $types = SolarMaintenanceSchedule::TYPES;
        $statuses = SolarMaintenanceSchedule::STATUSES;
        $priorities = SolarMaintenanceSchedule::PRIORITIES;
        $filters = $request->only([
            'q', 'month', 'date_from', 'date_to', 'status', 'type',
            'priority', 'assignee_id', 'overdue', 'view', 'section',
        ]);
        $filters['view'] = $currentView;
        $filters['month'] = $filters['month'] ?? '';

        $permissions = [
            'create' => $user->can('create', SolarMaintenanceSchedule::class),
            'manage' => SolarMaintenanceAccess::canPlan($user),
            'assign' => SolarMaintenanceAccess::canAssign($user),
            'approve' => SolarMaintenanceAccess::canApprove($user),
            'admin' => SolarMaintenanceAccess::isAdmin($user),
        ];

        return view('technical.maintenance.index', compact(
            'schedules','summary','sites','users','types','statuses','priorities',
            'filters','permissions','waitingProfiles','profileOptions'
        ));
    }

    /**
     * Hàng đợi phê duyệt dành cho Admin / Trưởng phòng kỹ thuật.
     * Mỗi dòng là một đợt bảo hành độc lập, không gộp theo công trình.
     */
    public function approvalQueue(Request $request): View
    {
        $this->authorize('viewAny', SolarMaintenanceSchedule::class);
        abort_unless(SolarMaintenanceAccess::canApprove($request->user()), 403);

        $allowedViews = ['pending', 'revision', 'approved'];
        $approvalView = (string) $request->input('approval_view', 'pending');
        if (! in_array($approvalView, $allowedViews, true)) {
            $approvalView = 'pending';
        }
        $request->merge(['approval_view' => $approvalView]);

        $user = $request->user();
        $approvalQueue = $this->queryService->approvalQueue($request, $user);
        $approvalSummary = $this->queryService->approvalSummary($user);
        $selectedId = (int) $request->input('selected', $approvalQueue->first()?->id ?? 0);
        $selectedSchedule = $selectedId > 0
            ? $this->queryService->approvalItem($selectedId, $user)
            : null;

        return view('technical.maintenance.approvals', [
            'approvalQueue' => $approvalQueue,
            'approvalSummary' => $approvalSummary,
            'approvalView' => $approvalView,
            'selectedSchedule' => $selectedSchedule,
            'statuses' => SolarMaintenanceSchedule::STATUSES,
            'types' => SolarMaintenanceSchedule::TYPES,
            'priorities' => SolarMaintenanceSchedule::PRIORITIES,
            'approvalStatuses' => SolarMaintenanceSchedule::APPROVAL_STATUSES,
            'reportConclusions' => \App\Services\Technical\SolarMaintenanceWorkflowService::CONCLUSIONS,
        ]);
    }

    /**
     * Tạo chuỗi các đợt bảo trì / bảo hành mới từ dữ liệu đã validate.
     */

    public function destroyProfile(
        Request $request,
        SolarMaintenanceProfile $profile
    ) {
        $user = $request->user();

        abort_unless(
            $user
            && $user->hasAnyRole([
                'admin',
                'management',
                'manager',
                'technical_manager',
                'technical_leader',
                'truong_phong_ky_thuat',
            ]),
            403,
            'Bạn không có quyền xóa hồ sơ bảo hành.'
        );

        DB::transaction(function () use ($profile) {

            /*
             * Soft delete toàn bộ lịch thuộc hồ sơ.
             */
            DB::table('solar_maintenance_schedules')
                ->where('maintenance_profile_id', $profile->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            /*
             * Profile hiện không có deleted_at trong một số bản schema.
             * Vì vậy ưu tiên set inactive; nếu có deleted_at thì soft delete.
             */
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing(
                'solar_maintenance_profiles'
            );

            $payload = [
                'status' => 'deleted',
                'updated_at' => now(),
            ];

            if (in_array('deleted_at', $columns, true)) {
                $payload['deleted_at'] = now();
            }

            DB::table('solar_maintenance_profiles')
                ->where('id', $profile->id)
                ->update($payload);
        });

        return redirect()
            ->route('ky-thuat.maintenance.index')
            ->with(
                'success',
                'Đã xóa hồ sơ bảo hành/O&M khỏi danh sách điều hành.'
            );
    }
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
                ? route('ky-thuat.maintenance.site', ['site' => $schedule->site_id])
                : null,
            'schedule_detail_url' => route('ky-thuat.maintenance.show', ['schedule' => $schedule->id]),
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

        $this->service->update($schedule, $request->validated(), $request->user());

        return back()->with('success', 'Đã cập nhật lịch và ghi nhận lịch sử thay đổi.');
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
