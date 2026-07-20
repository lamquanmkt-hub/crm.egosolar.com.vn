<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarSiteDocument;
use App\Services\SolarMaintenanceQueryService;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Hiển thị trang chi tiết công trình và chi tiết đợt bảo trì điện mặt trời.
 */
class SolarMaintenanceDetailController extends Controller
{
    /**
     * Khởi tạo controller với service truy vấn bảo trì.
     */
    public function __construct(private readonly SolarMaintenanceQueryService $queryService) {}

    /**
     * Trang hồ sơ công trình: các chu kỳ bảo trì, tài liệu, serial và nhật ký hoạt động.
     */
    public function site(Request $request, int $site): View|RedirectResponse
    {
        if (! SolarMaintenanceAccess::canViewAny($request->user())) {
            return $this->deny('Bạn chưa có quyền xem hồ sơ công trình bảo trì.');
        }

        $siteModel = Site::withoutGlobalScopes()->find($site);
        if (! $siteModel) {
            return redirect()
                ->route('ky-thuat.maintenance.index')
                ->with('error', 'Không tìm thấy công trình. Có thể lịch cũ đang liên kết tới một công trình đã bị xóa.');
        }

        if (! $this->canAccessSite($request, $siteModel)) {
            return $this->deny('Công trình không thuộc phạm vi công ty bạn đang làm việc.');
        }

        $scheduleQuery = SolarMaintenanceSchedule::query()
            ->where('site_id', $siteModel->id)
            ->with([
                'site:id,name,contact_name,contact_phone,address,company_id',
                'assignees.user:id,name,email,phone_number',
                'approver:id,name',
                'attachments:id,maintenance_schedule_id,category,file_size',
            ]);

        $this->queryService->scopeVisibleTo($scheduleQuery, $request->user());

        $schedules = $scheduleQuery
            ->orderBy('round_group')
            ->orderBy('round_no')
            ->orderBy('scheduled_date')
            ->get();

        if (SolarMaintenanceAccess::isTechnicianOnly($request->user()) && $schedules->isEmpty()) {
            return $this->deny('Bạn chưa được phân công vào đợt bảo trì nào của công trình này.');
        }

        $cycles = $schedules
            ->groupBy(fn (SolarMaintenanceSchedule $item) => $item->round_group ?: 'single-'.$item->id)
            ->map(function (Collection $items, string $key) {
                $first = $items->first();
                $planned = max(1, (int) ($first?->total_rounds ?? $items->count()));
                $completed = $items->where('status', 'completed')->count();

                return [
                    'key' => $key,
                    'title' => $planned > 1 ? 'Chu kỳ '.$planned.' đợt' : 'Lịch đơn lẻ',
                    'total' => $items->count(),
                    'planned' => $planned,
                    'completed' => $completed,
                    'approved' => $items->whereIn('status', ['approved', 'completed'])->count(),
                    'percent' => min(100, (int) round(($completed / $planned) * 100)),
                    'next' => $items->first(fn ($item) => ! in_array($item->status, ['completed', 'cancelled'], true)),
                    'items' => $items,
                ];
            })
            ->values();

        $documents = SolarSiteDocument::query()
            ->where('site_id', $siteModel->id)
            ->with('uploader:id,name')
            ->latest('id')
            ->get();

        return view('technical.maintenance.site-show', [
            'site' => $siteModel,
            'schedules' => $schedules,
            'cycles' => $cycles,
            'documents' => $documents,
            'serials' => $this->serialsForSite($siteModel->id),
            'activity' => $this->activityForSchedules($schedules->pluck('id')->all()),
            'statuses' => SolarMaintenanceSchedule::STATUSES,
            'types' => SolarMaintenanceSchedule::TYPES,
            'approvalStatuses' => SolarMaintenanceSchedule::APPROVAL_STATUSES,
            'permissions' => [
                'create' => $request->user()->can('create', SolarMaintenanceSchedule::class),
                'upload' => SolarMaintenanceAccess::isTechnician($request->user())
                    || SolarMaintenanceAccess::isManager($request->user()),
                'manage' => SolarMaintenanceAccess::isManager($request->user()),
            ],
        ]);
    }

    /**
     * Trang chi tiết một đợt bảo trì: các đợt cùng chu kỳ, phê duyệt, file đính kèm và quyền thao tác.
     */
    public function show(Request $request, SolarMaintenanceSchedule $schedule): View|RedirectResponse
    {
        $schedule->loadMissing([
            'site',
            'assignees.user:id,name,email,phone_number,department_id,position_id',
        ]);

        if (! $request->user()->can('view', $schedule)) {
            return $this->deny('Bạn chưa có quyền xem đợt bảo trì này hoặc đợt không thuộc công ty đang làm việc.');
        }

        $schedule->load([
            'site',
            'creator:id,name',
            'submitter:id,name',
            'approver:id,name',
            'assignees.user:id,name,email,phone_number,department_id,position_id',
            'approvals.approver:id,name',
            'approvals.submitter:id,name',
            'attachments.uploader:id,name',
            'statusHistories.user:id,name',
        ]);

        $siblingQuery = SolarMaintenanceSchedule::query()
            ->when($schedule->round_group, fn ($q) => $q->where('round_group', $schedule->round_group))
            ->when(! $schedule->round_group, fn ($q) => $q->whereKey($schedule->id));

        $this->queryService->scopeVisibleTo($siblingQuery, $request->user());

        $siblings = $siblingQuery
            ->orderBy('round_no')
            ->orderBy('scheduled_date')
            ->get();

        $technicalUsers = $this->queryService->technicalUsers();
        $leaderId = optional($schedule->leader)->user_id;
        $memberIds = $schedule->assignees
            ->reject(fn ($item) => (int) $item->user_id === (int) $leaderId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $previous = $siblings->filter(fn ($item) => (int) $item->round_no < (int) $schedule->round_no)->last();
        $next = $siblings->first(fn ($item) => (int) $item->round_no > (int) $schedule->round_no);

        return view('technical.maintenance.show', [
            'schedule' => $schedule,
            'siblings' => $siblings,
            'previousSchedule' => $previous,
            'nextSchedule' => $next,
            'technicalUsers' => $technicalUsers,
            'leaderId' => $leaderId,
            'memberIds' => $memberIds,
            'statuses' => SolarMaintenanceSchedule::STATUSES,
            'approvalStatuses' => SolarMaintenanceSchedule::APPROVAL_STATUSES,
            'types' => SolarMaintenanceSchedule::TYPES,
            'priorities' => SolarMaintenanceSchedule::PRIORITIES,
            'permissions' => [
                'update' => $request->user()->can('update', $schedule),
                'upload' => $request->user()->can('uploadAttachment', $schedule),
                'submit' => $request->user()->can('submitForApproval', $schedule),
                'approve' => $request->user()->can('approve', $schedule),
                'revision' => $request->user()->can('requestRevision', $schedule),
                'reject' => $request->user()->can('reject', $schedule),
                'reopen' => $request->user()->can('reopen', $schedule),
            ],
        ]);
    }

    /**
     * Kiểm tra người dùng có được truy cập công trình theo phạm vi công ty hay không.
     */
    private function canAccessSite(Request $request, Site $site): bool
    {
        if (SolarMaintenanceAccess::isAdmin($request->user())) {
            return true;
        }

        $companyId = EgoCompanyScope::currentId();
        if ($companyId <= 0) {
            return true;
        }

        $siteCompanyId = (int) ($site->company_id ?? 0);
        if ($siteCompanyId <= 0) {
            return SolarMaintenanceAccess::isManager($request->user());
        }

        return $siteCompanyId === $companyId;
    }

    /**
     * Chuyển hướng về trang danh sách bảo trì kèm thông báo lỗi.
     */
    private function deny(string $message): RedirectResponse
    {
        return redirect()
            ->route('ky-thuat.maintenance.index')
            ->with('error', $message);
    }

    /**
     * Lấy danh sách serial/bảo hành thiết bị của công trình (trả rỗng nếu thiếu bảng).
     */
    private function serialsForSite(int $siteId): Collection
    {
        $tables = [
            'crm_serial_warranties',
            'crm_serial_units',
            'crm_product_catalog',
            'crm_serial_unit_identifiers',
            'crm_serial_identifiers',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return collect();
            }
        }

        return DB::table('crm_serial_warranties as w')
            ->join('crm_serial_units as u', 'u.id', '=', 'w.serial_unit_id')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'u.product_id')
            ->leftJoin('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'u.id')->where('sui.is_primary', 1);
            })
            ->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('w.site_id', $siteId)
            ->select([
                'w.id', 'w.serial_unit_id', 'w.warranty_start_at', 'w.warranty_end_at',
                'w.status', 'w.note', 'p.name as product_name', 'p.sku', 'si.code as serial_code',
            ])
            ->orderByDesc('w.id')
            ->get();
    }

    /**
     * Gộp nhật ký đổi trạng thái và lịch sử phê duyệt của các đợt bảo trì (tối đa 100 dòng mới nhất).
     */
    private function activityForSchedules(array $scheduleIds): Collection
    {
        if (! $scheduleIds) {
            return collect();
        }

        $histories = collect();
        if (Schema::hasTable('solar_maintenance_status_histories')) {
            $histories = DB::table('solar_maintenance_status_histories as h')
                ->leftJoin('users as u', 'u.id', '=', 'h.changed_by')
                ->whereIn('h.maintenance_schedule_id', $scheduleIds)
                ->selectRaw("h.changed_at as activity_at, 'status' as kind, h.maintenance_schedule_id, h.from_status, h.to_status, h.reason, u.name as actor")
                ->get();
        }

        $approvals = collect();
        if (Schema::hasTable('solar_maintenance_approvals')) {
            $approvals = DB::table('solar_maintenance_approvals as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.approver_id')
                ->whereIn('a.maintenance_schedule_id', $scheduleIds)
                ->selectRaw("COALESCE(a.reviewed_at, a.submitted_at, a.created_at) as activity_at, 'approval' as kind, a.maintenance_schedule_id, NULL as from_status, a.status as to_status, a.comment as reason, u.name as actor")
                ->get();
        }

        return $histories->concat($approvals)
            ->sortByDesc('activity_at')
            ->take(100)
            ->values();
    }
}
