<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarMaintenanceProfile;
use App\Models\ProjectTest\Project;
use App\Support\EgoCompanyScope;
use Illuminate\Http\JsonResponse;
use App\Services\Technical\SolarMaintenanceService;
use App\Services\Technical\SolarMaintenanceWorkflowService;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SolarMaintenanceWorkflowController extends Controller
{
    public function __construct(
        private readonly SolarMaintenanceWorkflowService $workflow,
        private readonly SolarMaintenanceService $maintenance,
    ) {}

    public function issues(): RedirectResponse
    {
        return redirect()->route('ky-thuat.maintenance.index', [
            'section' => 'work',
            'type' => 'incident',
        ]);
    }


    /**
     * Tạo nhanh công trình ngay trong popup O&M.
     *
     * Tạo:
     * - project_test_projects
     * - solar_maintenance_profiles
     *
     * Không cần tạo site trung gian.
     */
    public function quickCreateProject(
        Request $request
    ): JsonResponse {
        abort_unless(
            SolarMaintenanceAccess::canPlan(
                $request->user()
            )
            || SolarMaintenanceAccess::isManager(
                $request->user()
            )
            || SolarMaintenanceAccess::isAdmin(
                $request->user()
            ),
            403,
            'Bạn không có quyền tạo công trình bảo hành.'
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:190',
            ],

            'customer_name' => [
                'nullable',
                'string',
                'max:190',
            ],

            'customer_phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'system_kwp' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999',
            ],

            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $companyId =
            EgoCompanyScope::currentId();

        return DB::transaction(
            function () use (
                $data,
                $companyId,
                $request
            ): JsonResponse {

                /*
                 * Sinh code công trình riêng cho O&M.
                 */
                $nextId =
                    (int) Project::withTrashed()
                        ->max('id')
                    + 1;

                $code =
                    'CT-OM-'
                    .now()->format('ymd')
                    .'-'
                    .str_pad(
                        (string) $nextId,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );

                $project = Project::create([
                    'code' =>
                        $code,

                    'company_id' =>
                        $companyId > 0
                            ? $companyId
                            : null,

                    'created_by' =>
                        $request->user()->id,

                    'request_source' =>
                        'maintenance',

                    'project_type' =>
                        'maintenance',

                    'customer_confirmation_required' =>
                        0,

                    'customer_confirmation_status' =>
                        'confirmed',

                    'name' =>
                        $data['name'],

                    'contact_name' =>
                        $data['customer_name']
                        ?? null,

                    'contact_phone' =>
                        $data['customer_phone']
                        ?? null,

                    'address' =>
                        $data['address']
                        ?? null,

                    'estimated_kwp' =>
                        $data['system_kwp']
                        ?? null,

                    /*
                     * Công trình tạo ngay từ O&M
                     * coi như đã vào giai đoạn bảo hành.
                     */
                    'status' =>
                        'warranty_active',

                    'current_owner_role' =>
                        'technical',

                    'progress' =>
                        100,

                    'installed_at' =>
                        now()->toDateString(),

                    'note' =>
                        $data['note']
                        ?? 'Tạo nhanh từ module Bảo hành & O&M.',

                    'imported_from_legacy_at' =>
                        now(),
                ]);

                /*
                 * Tạo profile ngay.
                 * Không phụ thuộc observer vì project mới này
                 * chưa đi qua workflow nghiệm thu.
                 */
                $profile =
                    SolarMaintenanceProfile::query()
                        ->where(
                            'project_id',
                            $project->id
                        )
                        ->first();

                if (! $profile) {

                    $profile =
                        SolarMaintenanceProfile::create([
                            'company_id' =>
                                $project->company_id,

                            'source_type' =>
                                'project_test',

                            'source_id' =>
                                $project->id,

                            'project_id' =>
                                $project->id,

                            'site_id' =>
                                null,

                            'source_status' =>
                                'warranty_active',

                            'status' =>
                                'waiting_plan',

                            'site_name' =>
                                $project->name,

                            'customer_name' =>
                                $project->contact_name,

                            'customer_phone' =>
                                $project->contact_phone,

                            'address' =>
                                $project->address,

                            'system_kwp' =>
                                $project->estimated_kwp,

                            'handover_date' =>
                                now()->toDateString(),

                            'warranty_start_date' =>
                                now()->toDateString(),

                            'source_completed_at' =>
                                now(),

                            'created_by' =>
                                $request->user()->id,
                        ]);
                }

                return response()->json([
                    'success' => true,

                    'project' => [
                        'id' =>
                            $project->id,

                        'code' =>
                            $project->code,

                        'name' =>
                            $project->name,

                        'customer_name' =>
                            $project->contact_name,

                        'customer_phone' =>
                            $project->contact_phone,

                        'address' =>
                            $project->address,

                        'system_kwp' =>
                            $project->estimated_kwp,

                        'maintenance_profile_id' =>
                            $profile->id,
                    ],
                ]);
            }
        );
    }
    public function storeIncident(Request $request): RedirectResponse
    {
        $this->authorize('create', SolarMaintenanceSchedule::class);
        $data = $request->validate([
            'maintenance_profile_id' => ['nullable','integer','exists:solar_maintenance_profiles,id'],
            'site_id' => ['nullable','integer','exists:sites,id'],
            'site_name' => ['nullable','string','max:190'],
            'customer_name' => ['nullable','string','max:190'],
            'address' => ['nullable','string','max:255'],
            'priority' => ['required', Rule::in(array_keys(SolarMaintenanceSchedule::PRIORITIES))],
            'scheduled_date' => ['required','date'],
            'issue_note' => ['required','string','max:5000'],
        ]);

        $data += [
            'type' => 'incident',
            'rounds_count' => 1,
            'round_interval_months' => 1,
        ];

        $created = $this->maintenance->createSeries($data, $request->user());
        $schedule = $created->first();

        return redirect()->route('ky-thuat.maintenance.show', $schedule)
            ->with('success', 'Đã tạo công việc xử lý sự cố trong cùng luồng O&M.');
    }

    public function assignTeam(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            SolarMaintenanceAccess::canAssign($request->user())
            || SolarMaintenanceAccess::isAdmin($request->user()),
            403
        );
        $data = $request->validate([
            'leader_user_id' => ['required','integer','exists:users,id'],
            'member_user_ids' => ['nullable','array'],
            'member_user_ids.*' => ['integer','distinct','exists:users,id'],
            'external_labor_enabled' => ['nullable','boolean'],
            'external_labor_name' => ['nullable','string','max:255'],
            'external_labor_phone' => ['nullable','string','max:80'],
            'external_labor_headcount' => ['nullable','integer','min:1','max:999'],
            'external_labor_total_cost' => ['nullable','numeric','min:0','max:999999999999'],
            'external_labor_advance_amount' => ['nullable','numeric','min:0','max:999999999999'],
            'external_labor_bank_info' => ['nullable','string','max:3000'],
            'external_labor_note' => ['nullable','string','max:5000'],
        ]);
        $isAdmin = SolarMaintenanceAccess::isAdmin($request->user());
        $this->workflow->assignTeam($schedule, $data, $request->user(), $isAdmin);

        return back()->with(
            'success',
            $isAdmin && in_array((string) $schedule->status, [
                'in_progress',
                'waiting_material',
                'waiting_submission',
                'pending_approval',
                'revision_requested',
                'approved',
                'completed',
            ], true)
                ? 'Admin đã cập nhật lại phân công của bước đã qua. Trạng thái công việc hiện tại được giữ nguyên.'
                : 'Đã lưu phân công. Hãy gửi sếp duyệt trước khi bắt đầu thực hiện.'
        );
    }

    /**
     * V13.3 - Admin chỉnh lại thông tin Kế hoạch của bước đã qua.
     * Chỉ cập nhật dữ liệu kế hoạch, tuyệt đối không lùi trạng thái workflow.
     */
    public function adminUpdatePlan(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            SolarMaintenanceAccess::isAdmin($request->user()),
            403,
            'Chỉ Admin được chỉnh lại bước Kế hoạch đã qua.'
        );

        $data = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'priority' => ['required', Rule::in(array_keys(SolarMaintenanceSchedule::PRIORITIES))],
            'type' => ['required', Rule::in(array_keys(SolarMaintenanceSchedule::TYPES))],
            'system_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'inverter_info' => ['nullable', 'string', 'max:2000'],
            'issue_note' => ['nullable', 'string', 'max:5000'],
            'technical_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->workflow->adminUpdatePlan(
            $schedule,
            $data,
            $request->user()
        );

        return redirect()
            ->route('ky-thuat.maintenance.show', [
                'schedule' => $schedule->id,
                'tab' => 'work',
                'admin_step' => 1,
            ])
            ->with('success', 'Admin đã cập nhật lại bước Kế hoạch. Tiến trình hiện tại được giữ nguyên.');
    }


    public function updateRounds(
        Request $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {

        abort_unless(
            SolarMaintenanceAccess::canPlan(
                $request->user()
            )
            || SolarMaintenanceAccess::isAdmin(
                $request->user()
            ),
            403
        );

        $data = $request->validate([
            'total_rounds' => [
                'required',
                'integer',
                'min:1',
                'max:36',
            ],

            'interval_months' => [
                'nullable',
                'integer',
                'min:1',
                'max:24',
            ],
            'round_dates' => [
                'nullable',
                'array',
            ],

            'round_dates.*' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $newTotal =
            (int) $data['total_rounds'];

        $intervalMonths =
            max(
                1,
                (int) (
                    $data['interval_months']
                    ?? 3
                )
            );

        $group =
            $schedule->round_group;

        if (! $group) {

            $group =
                'OM-'
                .now()->format('YmdHis')
                .'-'
                .$schedule->id;

            $schedule->update([
                'round_group' => $group,
            ]);
        }

                /*
         * EGO_REAL_ROUND_COUNT_V2
         * Lấy toàn bộ đợt thật của cùng hồ sơ.
         * Không chỉ dựa vào round_group vì dữ liệu cũ có thể thiếu group.
         */
        $roundsQuery =
            SolarMaintenanceSchedule::withoutGlobalScopes()
                ->whereNull('deleted_at');

        if ($schedule->maintenance_profile_id) {

            $roundsQuery->where(
                'maintenance_profile_id',
                $schedule->maintenance_profile_id
            );

        } elseif ($schedule->project_id) {

            $roundsQuery->where(
                'project_id',
                $schedule->project_id
            );

        } elseif ($schedule->round_group) {

            $roundsQuery->where(
                'round_group',
                $schedule->round_group
            );

        } else {

            $roundsQuery->where(
                'site_name',
                $schedule->site_name
            );
        }

        $rounds = $roundsQuery
            ->orderByRaw(
                'COALESCE(round_no, 999999)'
            )
            ->orderBy('scheduled_date')
            ->get();

        DB::transaction(
            function () use (
                $rounds,
                $schedule,
                $group,
                $newTotal,
                $intervalMonths,
                $request,
                $data
            ): void {

                $current =
                    $rounds->count();


                /*
                 * EGO_SAVE_ROUND_DATES_FINAL
                 * Cập nhật ngày riêng từng đợt.
                 */
                foreach (
                    ($data['round_dates'] ?? [])
                    as $roundId => $dateValue
                ) {
                    if (! $dateValue) {
                        continue;
                    }

                    $round = $rounds->firstWhere(
                        'id',
                        (int) $roundId
                    );

                    if (! $round) {
                        continue;
                    }

                    /*
                     * Đợt completed/approved không đổi lịch sử.
                     */
                    if (
                        $round->completed_at
                        || $round->approved_at
                        || in_array(
                            (string) $round->status,
                            ['completed', 'approved'],
                            true
                        )
                    ) {
                        continue;
                    }

                    $newDate = \Carbon\Carbon::parse(
                        $dateValue
                    )->format('Y-m-d');

                    $startTime = $round->scheduled_start_at
                        ? \Carbon\Carbon::parse(
                            $round->scheduled_start_at
                        )->format('H:i:s')
                        : '08:30:00';

                    $endTime = $round->scheduled_end_at
                        ? \Carbon\Carbon::parse(
                            $round->scheduled_end_at
                        )->format('H:i:s')
                        : '11:30:00';

                    $round->update([
                        'scheduled_date' =>
                            $newDate,

                        'scheduled_start_at' =>
                            $newDate.' '.$startTime,

                        'scheduled_end_at' =>
                            $newDate.' '.$endTime,
                    ]);
                }
                /*
                 * GIẢM
                 */

                if ($newTotal < $current) {

                    $remove = $rounds
                        ->filter(
                            fn ($item) =>
                                (int) ($item->round_no ?: 0)
                                > $newTotal
                        );

                    foreach ($remove as $item) {

                        /*
                         * Không hard delete lịch sử.
                         * Chỉ loại khỏi chu kỳ hiện hành.
                         */
                        if (
                            \Illuminate\Support\Facades\Schema::hasColumn(
                                'solar_maintenance_schedules',
                                'deleted_at'
                            )
                        ) {
                            $item->deleted_at = now();
                            $item->save();
                        } else {
                            $item->status = 'cancelled';
                            $item->save();
                        }
                    }
                }

                /*
                 * TĂNG
                 */
                if ($newTotal > $current) {

                    $last =
                        $rounds->last()
                        ?: $schedule;

                    $lastDate =
                        $last->scheduled_date
                        ? \Carbon\Carbon::parse(
                            $last->scheduled_date
                        )
                        : now();

                    for (
                        $i = $current + 1;
                        $i <= $newTotal;
                        $i++
                    ) {

                        $lastDate =
                            $lastDate
                                ->copy()
                                ->addMonthsNoOverflow(
                                    $intervalMonths
                                );

                        SolarMaintenanceSchedule::withoutGlobalScopes()
                            ->create([

                                'schedule_code' =>
                                    'SM-'
                                    .now()->format(
                                        'YmdHis'
                                    )
                                    .'-'
                                    .$i,

                                'site_id' =>
                                    $schedule->site_id,

                                'project_id' =>
                                    $schedule->project_id,

                                'maintenance_profile_id' =>
                                    $schedule
                                        ->maintenance_profile_id,

                                'company_id' =>
                                    $schedule->company_id,

                                'customer_name' =>
                                    $schedule->customer_name,

                                'site_name' =>
                                    $schedule->site_name,

                                'address' =>
                                    $schedule->address,

                                'type' =>
                                    $schedule->type,

                                'status' =>
                                    'scheduled',

                                'approval_status' =>
                                    'not_submitted',

                                'priority' =>
                                    $schedule->priority,

                                'round_no' =>
                                    $i,

                                'total_rounds' =>
                                    $newTotal,

                                'round_group' =>
                                    $group,

                                'scheduled_date' =>
                                    $lastDate
                                        ->toDateString(),

                                'scheduled_start_at' =>
                                    $lastDate
                                        ->copy()
                                        ->setTime(8, 30),

                                'scheduled_end_at' =>
                                    $lastDate
                                        ->copy()
                                        ->setTime(11, 30),

                                'system_kwp' =>
                                    $schedule->system_kwp,

                                'inverter_info' =>
                                    $schedule->inverter_info,

                                'created_by' =>
                                    $request->user()->id,
                            ]);
                    }
                }

                /*
                 * UPDATE TOTAL
                 */
                $all =
                    SolarMaintenanceSchedule::withoutGlobalScopes()
                        ->where(
                            'round_group',
                            $group
                        )
                        ->orderBy('round_no')
                        ->get();

                foreach (
                    $all->values()
                    as $index => $item
                ) {
                    $item->update([
                        'round_no' =>
                            $index + 1,

                        'total_rounds' =>
                            $newTotal,
                    ]);
                }
            }
        );

        return back()->with(
            'success',
            'Đã cập nhật chu kỳ thành '
            .$newTotal
            .' đợt.'
        );
    }
    public function start(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            \App\Support\SolarMaintenanceAccess::isTechnician($request->user())
            || \App\Support\SolarMaintenanceAccess::isManager($request->user())
            || $request->user()->can('changeStatus', $schedule),
            403,
            'Bạn không có quyền bắt đầu công việc này.'
        );

        $this->workflow->start(
            $schedule,
            $request->user()
        );
        return back()->with('success', 'Đã bắt đầu thực hiện công việc.');
    }

    public function saveChecklist(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            \App\Support\SolarMaintenanceAccess::isTechnician($request->user())
            || \App\Support\SolarMaintenanceAccess::isManager($request->user())
            || $request->user()->can('update', $schedule),
            403,
            'Bạn không có quyền cập nhật checklist.'
        );

        $data = $request->validate([
            'checked' => ['nullable','array'],
            'checked.*' => ['integer'],
            'notes' => ['nullable','array'],
            'notes.*' => ['nullable','string','max:1000'],
        ]);
        $this->workflow->saveChecklist($schedule, $data, $request->user());
        return back()->with('success', 'Đã lưu checklist thực hiện.');
    }

    public function finishExecution(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            \App\Support\SolarMaintenanceAccess::isTechnician($request->user())
            || \App\Support\SolarMaintenanceAccess::isManager($request->user())
            || $request->user()->can('changeStatus', $schedule),
            403,
            'Bạn không có quyền hoàn tất phần thực hiện.'
        );

        $this->workflow->finishExecution(
            $schedule,
            $request->user()
        );
        return back()->with('success', 'Đã hoàn tất đợt bảo trì. Checklist và minh chứng đã được lưu, không cần gửi duyệt.');
    }

    public function saveReport(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(
            \App\Support\SolarMaintenanceAccess::isTechnician($request->user())
            || \App\Support\SolarMaintenanceAccess::isManager($request->user())
            || $request->user()->can('update', $schedule),
            403,
            'Bạn không có quyền cập nhật checklist.'
        );

        $data = $request->validate([
            'report_conclusion' => ['required', Rule::in(array_keys(SolarMaintenanceWorkflowService::CONCLUSIONS))],
            'result_note' => ['required','string','max:10000'],
            'technical_note' => ['nullable','string','max:5000'],
        ]);
        $this->workflow->saveReport($schedule, $data, $request->user());
        return back()->with('success', 'Đã lưu báo cáo bảo trì.');
    }
}
