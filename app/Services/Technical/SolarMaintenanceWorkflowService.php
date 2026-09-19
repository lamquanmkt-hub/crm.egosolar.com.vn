<?php

namespace App\Services\Technical;

use App\Models\SolarMaintenanceChecklistItem;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolarMaintenanceWorkflowService
{
    public const CONCLUSIONS = [
        'normal' => 'Hệ thống hoạt động bình thường',
        'monitor' => 'Cần tiếp tục theo dõi',
        'repair' => 'Có lỗi cần xử lý / sửa chữa',
        'replace' => 'Cần thay thiết bị',
    ];

    public function ensureChecklist(
        SolarMaintenanceSchedule $schedule
    ): void {

        /*
         * Hồ sơ đã hoàn thành giữ snapshot lịch sử.
         */
        if (
            in_array(
                (string) $schedule->status,
                [
                    'completed',
                    'approved',
                    'cancelled',
                ],
                true
            )
        ) {
            return;
        }

        $templates = app(
            SolarMaintenanceChecklistTemplateService::class
        )->templatesForSchedule($schedule);

        $keys = $templates
            ->pluck('item_key')
            ->filter()
            ->map(
                fn ($key) =>
                (string) $key
            )
            ->values();

        DB::transaction(
            function () use (
                $schedule,
                $templates,
                $keys
            ): void {

                /*
                 * XÓA MỤC CŨ KHÔNG CÒN TRONG BUILDER
                 */
                $items =
                    SolarMaintenanceChecklistItem::query()
                        ->where(
                            'maintenance_schedule_id',
                            $schedule->id
                        )
                        ->get();

                foreach ($items as $item) {

                    if (
                        ! $keys->contains(
                            (string) $item->item_key
                        )
                    ) {
                        $item->delete();
                    }
                }

                /*
                 * UPDATE / INSERT ĐÚNG TEMPLATE
                 */
                foreach (
                    $templates as $template
                ) {

                    $item =
                        SolarMaintenanceChecklistItem::query()
                            ->where(
                                'maintenance_schedule_id',
                                $schedule->id
                            )
                            ->where(
                                'item_key',
                                $template->item_key
                            )
                            ->first();

                    $data = [
                        'checklist_template_id' =>
                            $template->id,

                        'label' =>
                            $template->label,

                        'sort_order' =>
                            $template->sort_order,

                        'is_required' =>
                            $template->is_required,

                        'requires_evidence' =>
                            $template->requires_evidence,

                        'min_evidence' =>
                            $template->requires_evidence
                                ? max(
                                    1,
                                    (int) $template->min_evidence
                                )
                                : 0,
                    ];

                    if ($item) {

                        $item->fill($data);
                        $item->save();

                    } else {

                        SolarMaintenanceChecklistItem::create(
                            $data + [
                                'maintenance_schedule_id' =>
                                    $schedule->id,

                                'item_key' =>
                                    $template->item_key,

                                'is_done' =>
                                    false,
                            ]
                        );
                    }
                }

                /*
                 * CHỐNG DUPLICATE
                 */
                foreach ($keys as $key) {

                    $duplicates =
                        SolarMaintenanceChecklistItem::query()
                            ->where(
                                'maintenance_schedule_id',
                                $schedule->id
                            )
                            ->where(
                                'item_key',
                                $key
                            )
                            ->orderBy('id')
                            ->get();

                    if (
                        $duplicates->count() > 1
                    ) {
                        $duplicates
                            ->slice(1)
                            ->each(
                                fn ($row) =>
                                    $row->delete()
                            );
                    }
                }
            }
        );

        /*
         * Bắt controller load lại relation mới.
         */
        $schedule->unsetRelation(
            'checklistItems'
        );
    }
    public function assignTeam(SolarMaintenanceSchedule $schedule, array $data, User $actor, bool $adminOverride = false): void
    {
        $hasExternalPaymentRequest =
            (int) ($schedule->external_labor_payment_request_id ?? 0) > 0;

        if ($hasExternalPaymentRequest && ! $adminOverride) {
            throw ValidationException::withMessages([
                'team' => 'Phân công này đã sinh ĐNTT nhân công ngoài. Để tránh lệch tài chính, không sửa phân công trực tiếp sau khi đã tạo phiếu.',
            ]);
        }

        $passedAssignmentStep = in_array((string) $schedule->status, [
            'in_progress',
            'waiting_material',
            'waiting_submission',
            'pending_approval',
            'revision_requested',
            'approved',
            'completed',
        ], true);

        if (in_array($schedule->status, [
            'in_progress',
            'waiting_material',
            'waiting_submission',
            'pending_approval',
            'revision_requested',
            'approved',
            'completed',
            'cancelled',
        ], true) && ! $adminOverride) {
            throw ValidationException::withMessages([
                'team' => 'Chỉ được thay đổi phân công trước khi bắt đầu thực hiện. Admin có thể xem lại bước đã qua từ thanh tiến trình.',
            ]);
        }

        if ((string) $schedule->status === 'cancelled' && $adminOverride) {
            throw ValidationException::withMessages([
                'team' => 'Hồ sơ đã hủy không được chỉnh phân công. Hãy mở lại hồ sơ trước.',
            ]);
        }

        DB::transaction(function () use (
            $schedule,
            $data,
            $actor,
            $adminOverride,
            $hasExternalPaymentRequest,
            $passedAssignmentStep
        ): void {
            $leaderId = (int) ($data['leader_user_id'] ?? 0);
            $memberIds = collect($data['member_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $ids = collect(array_merge([$leaderId], $memberIds))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! $leaderId || ! count($ids)) {
                throw ValidationException::withMessages([
                    'team' => 'Phải chọn ít nhất 1 kỹ thuật viên và người phụ trách chính.',
                ]);
            }

            $users = User::query()
                ->with(['roles', 'department', 'position'])
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            foreach ($ids as $id) {
                $user = $users->get($id);
                if (! $user || ! SolarMaintenanceAccess::isSelectableTechnician($user)) {
                    throw ValidationException::withMessages([
                        'team' => 'Danh sách có nhân sự không thuộc nhóm kỹ thuật đang hoạt động.',
                    ]);
                }
            }

            /*
             * Nếu đã sinh ĐNTT thuê ngoài, Admin vẫn được sửa lại nhân sự nội bộ
             * của bước đã qua nhưng không được làm lệch dữ liệu tài chính đã phát hành.
             * Toàn bộ thông tin thuê ngoài được giữ nguyên.
             */
            if ($adminOverride && $hasExternalPaymentRequest) {
                $externalEnabled = (bool) $schedule->external_labor_enabled;
                $headcount = $externalEnabled ? (int) $schedule->external_labor_headcount : null;
                $totalCost = $externalEnabled ? (float) $schedule->external_labor_total_cost : 0;
                $advanceAmount = $externalEnabled ? (float) $schedule->external_labor_advance_amount : 0;
            } else {
                $externalEnabled = (bool) ($data['external_labor_enabled'] ?? false);
                $headcount = $externalEnabled ? (int) ($data['external_labor_headcount'] ?? 0) : null;
                $totalCost = $externalEnabled ? (float) ($data['external_labor_total_cost'] ?? 0) : 0;
                $advanceAmount = $externalEnabled ? (float) ($data['external_labor_advance_amount'] ?? 0) : 0;
            }

            if ($externalEnabled) {
                $errors = [];
                if ($headcount < 1) {
                    $errors[] = 'Số lượng nhân công ngoài phải từ 1 người.';
                }
                if ($totalCost <= 0) {
                    $errors[] = 'Phải nhập tổng tiền công nhân công ngoài.';
                }
                if ($advanceAmount < 0 || $advanceAmount > $totalCost) {
                    $errors[] = 'Số tiền đề nghị/tạm ứng phải từ 0 đến tổng tiền công.';
                }
                if ($errors) {
                    throw ValidationException::withMessages(['external_labor' => $errors]);
                }
            }

            $schedule->assignees()->whereNotIn('user_id', $ids)->delete();
            foreach ($ids as $id) {
                $isLeader = $id === $leaderId;
                $schedule->assignees()->updateOrCreate([
                    'user_id' => $id,
                ], [
                    'role' => $isLeader ? 'leader' : 'member',
                    'assignment_role' => $isLeader ? 'leader' : 'member',
                    'is_leader' => $isLeader,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);
            }

            $leader = $users->get($leaderId);
            $oldStatus = (string) $schedule->status;

            $schedule->forceFill([
                'assigned_to' => $leaderId,
                'assigned_name' => $leader?->name,
                'assigned_user_ids' => $ids,
                'status' => in_array($oldStatus, ['draft', 'scheduled', 'unassigned', 'postponed'], true)
                    ? 'assigned'
                    : $oldStatus,

                /*
                 * Nếu Admin đang xem lại Bước 2 đã qua, giữ nguyên workflow hiện tại
                 * và coi lần chỉnh sửa này là đã được Admin xác nhận.
                 * Trường hợp bước 2 chưa qua thì vẫn quay về draft để gửi duyệt đúng luồng.
                 */
                'assignment_approval_status' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? 'approved'
                        : 'draft',

                'assignment_submitted_at' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? ($schedule->assignment_submitted_at ?: now())
                        : null,

                'assignment_submitted_by' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? ($schedule->assignment_submitted_by ?: $actor->id)
                        : null,

                'assignment_approved_at' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? now()
                        : null,

                'assignment_approved_by' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? $actor->id
                        : null,

                'assignment_revision_requested_at' => null,
                'assignment_revision_requested_by' => null,

                'assignment_approval_note' =>
                    ($adminOverride && $passedAssignmentStep)
                        ? 'Admin chỉnh sửa lại phân công của bước đã qua; trạng thái hiện tại được giữ nguyên.'
                        : null,

                'external_labor_enabled' => $externalEnabled,

                'external_labor_name' =>
                    ($adminOverride && $hasExternalPaymentRequest)
                        ? $schedule->external_labor_name
                        : ($externalEnabled ? trim((string) ($data['external_labor_name'] ?? '')) ?: null : null),

                'external_labor_phone' =>
                    ($adminOverride && $hasExternalPaymentRequest)
                        ? $schedule->external_labor_phone
                        : ($externalEnabled ? trim((string) ($data['external_labor_phone'] ?? '')) ?: null : null),

                'external_labor_headcount' => $headcount,
                'external_labor_total_cost' => $totalCost,
                'external_labor_advance_amount' => $advanceAmount,

                'external_labor_bank_info' =>
                    ($adminOverride && $hasExternalPaymentRequest)
                        ? $schedule->external_labor_bank_info
                        : ($externalEnabled ? trim((string) ($data['external_labor_bank_info'] ?? '')) ?: null : null),

                'external_labor_note' =>
                    ($adminOverride && $hasExternalPaymentRequest)
                        ? $schedule->external_labor_note
                        : ($externalEnabled ? trim((string) ($data['external_labor_note'] ?? '')) ?: null : null),

                'external_labor_payment_request_id' =>
                    $hasExternalPaymentRequest
                        ? $schedule->external_labor_payment_request_id
                        : null,
            ])->save();

            $reason =
                ($adminOverride && $passedAssignmentStep)
                    ? 'Admin chỉnh sửa lại Bước 2 - Phân công sau khi bước này đã qua; không lùi trạng thái công việc.'
                    : 'Đã cập nhật phân công nhóm kỹ thuật '.count($ids).' người; yêu cầu sếp duyệt trước khi thực hiện.';

            if ($externalEnabled) {
                $reason .= ' Có thuê ngoài '.$headcount.' người, tổng '.number_format($totalCost, 0, ',', '.').'đ.';
            }

            if ($adminOverride && $hasExternalPaymentRequest) {
                $reason .= ' Dữ liệu nhân công ngoài được khóa theo ĐNTT #'.$schedule->external_labor_payment_request_id.'.';
            }

            $this->history(
                $schedule,
                $actor,
                $oldStatus,
                (string) $schedule->status,
                $reason
            );

            if (
                $adminOverride
                && $passedAssignmentStep
                && $externalEnabled
                && ! $hasExternalPaymentRequest
            ) {
                app(SolarMaintenanceAssignmentApprovalService::class)
                    ->ensureExternalLaborPaymentRequestForAdmin(
                        $schedule,
                        $actor
                    );
            }
        });
    }

    /**
     * V13.3 - Admin chỉnh lại Bước 1 (Kế hoạch) sau khi bước đã qua.
     * Không thay đổi status/approval của workflow; mọi chỉnh sửa được ghi lịch sử.
     */
    public function adminUpdatePlan(
        SolarMaintenanceSchedule $schedule,
        array $data,
        User $actor
    ): void {
        DB::transaction(function () use ($schedule, $data, $actor): void {
            $oldStatus = (string) $schedule->status;

            $schedule->forceFill([
                'scheduled_date' => $data['scheduled_date'],
                'priority' => $data['priority'],
                'type' => $data['type'],
                'system_kwp' => $data['system_kwp'] ?? null,
                'inverter_info' => trim((string) ($data['inverter_info'] ?? '')) ?: null,
                'issue_note' => trim((string) ($data['issue_note'] ?? '')) ?: null,
                'technical_note' => trim((string) ($data['technical_note'] ?? '')) ?: null,
                'scheduled_start_at' =>
                    \Carbon\Carbon::parse($data['scheduled_date'])->setTime(8, 30),
                'scheduled_end_at' =>
                    \Carbon\Carbon::parse($data['scheduled_date'])->setTime(11, 30),
            ])->save();

            $this->history(
                $schedule,
                $actor,
                $oldStatus,
                $oldStatus,
                'Admin chỉnh sửa lại Bước 1 - Kế hoạch của hồ sơ đã qua bước này; tiến trình hiện tại được giữ nguyên.'
            );
        });
    }

    public function start(SolarMaintenanceSchedule $schedule, User $actor): void
    {
        if (! in_array($schedule->status, ['assigned','scheduled','customer_confirmed','travelling'], true)) {
            throw ValidationException::withMessages(['workflow' => 'Công việc chưa ở trạng thái có thể bắt đầu.']);
        }
        if (! $schedule->assignees()->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Phải phân công nhóm kỹ thuật trước khi bắt đầu.']);
        }

        $assignmentApproval = (string) ($schedule->assignment_approval_status ?? 'not_required');
        if (! in_array($assignmentApproval, ['approved', 'not_required'], true)) {
            $message = match ($assignmentApproval) {
                'pending' => 'Phân công đang chờ sếp duyệt. Chưa thể bắt đầu công việc.',
                'revision_requested' => 'Sếp đã yêu cầu chỉnh sửa phân công. Hãy cập nhật và gửi duyệt lại.',
                'rejected' => 'Phân công đã bị từ chối. Hãy chỉnh sửa và gửi duyệt lại.',
                default => 'Phân công chưa được sếp duyệt. Hãy gửi duyệt trước khi bắt đầu.',
            };

            throw ValidationException::withMessages(['workflow' => $message]);
        }

        $old = $schedule->status;
        $schedule->forceFill(['status' => 'in_progress', 'started_at' => $schedule->started_at ?: now()])->save();
        $schedule->assignees()->whereNull('started_at')->update(['started_at' => now(), 'updated_at' => now()]);
        $this->ensureChecklist($schedule);
        $this->history($schedule, $actor, $old, 'in_progress', 'Bắt đầu thực hiện công việc.');
    }

    public function saveChecklist(SolarMaintenanceSchedule $schedule, array $data, User $actor): void
    {
        if (! in_array($schedule->status, ['in_progress', 'waiting_material', 'revision_requested'], true)) {
            throw ValidationException::withMessages([
                'checklist' => 'Checklist chỉ được cập nhật khi đang thực hiện hoặc đang bổ sung hồ sơ.',
            ]);
        }

        $this->ensureChecklist($schedule);
        $checked = collect($data['checked'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $notes = $data['notes'] ?? [];

        $items = $schedule->checklistItems()->withCount('attachments')->get();
        $missingEvidence = [];

        foreach ($items as $item) {
            if ($checked->contains((int) $item->id)
                && $item->requires_evidence
                && (int) $item->attachments_count < max(1, (int) $item->min_evidence)) {
                $missingEvidence[] = $item->label.' ('.(int) $item->attachments_count.'/'.max(1, (int) $item->min_evidence).' file)';
            }
        }

        if ($missingEvidence) {
            throw ValidationException::withMessages([
                'checklist' => 'Chưa đủ minh chứng cho: '.implode('; ', $missingEvidence).'.',
            ]);
        }

        foreach ($items as $item) {
            $done = $checked->contains((int) $item->id);
            $item->update([
                'is_done' => $done,
                'note' => isset($notes[$item->id]) ? trim((string) $notes[$item->id]) : $item->note,
                'completed_by' => $done ? $actor->id : null,
                'completed_at' => $done ? ($item->completed_at ?: now()) : null,
            ]);
        }
    }

    /**
     * V3: Checklist + minh chứng đủ là hoàn tất luôn đợt bảo trì.
     * Không còn bước gửi duyệt/phê duyệt cuối cùng.
     */
    public function finishExecution(SolarMaintenanceSchedule $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $schedule->refresh();
            $this->ensureChecklist($schedule);
            $readiness = $this->checklistReadiness($schedule);

            if ($readiness['required_total'] === 0 || $readiness['required_done'] < $readiness['required_total']) {
                throw ValidationException::withMessages([
                    'checklist' => "Checklist bắt buộc chưa hoàn tất ({$readiness['required_done']}/{$readiness['required_total']}).",
                ]);
            }

            if ($readiness['missing_evidence']) {
                throw ValidationException::withMessages([
                    'checklist' => 'Chưa đủ file minh chứng cho: '.implode('; ', $readiness['missing_evidence']).'.',
                ]);
            }

            if (! $schedule->assignees()->exists()) {
                throw ValidationException::withMessages([
                    'workflow' => 'Chưa có nhóm kỹ thuật thực hiện.',
                ]);
            }

            $evidence = $schedule->attachments()
                ->whereIn('category', ['before','during','after','report','fault','serial','checklist'])
                ->count();

            if ($evidence <= 0) {
                throw ValidationException::withMessages([
                    'checklist' => 'Chưa có ảnh hoặc file minh chứng.',
                ]);
            }

            if (! in_array($schedule->status, [
                'in_progress',
                'waiting_material',
                'waiting_submission',
                'revision_requested',
                'pending_approval',
            ], true)) {
                throw ValidationException::withMessages([
                    'workflow' => 'Trạng thái hiện tại không thể hoàn tất đợt bảo trì.',
                ]);
            }

            $old = (string) $schedule->status;
            $now = now();

            $schedule->forceFill([
                'status' => 'completed',
                'approval_status' => 'not_required',
                'execution_finished_at' => $schedule->execution_finished_at ?: $now,
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'revision_requested_at' => null,
                'revision_requested_by' => null,
                'approval_note' => null,
                'completed_at' => $now,
                'completed_date' => $now->toDateString(),
            ])->save();

            $schedule->assignees()->whereNull('completed_at')->update([
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

            if ($schedule->maintenance_profile_id && $schedule->maintenanceProfile) {
                $schedule->maintenanceProfile->forceFill(['status' => 'active'])->save();
            }

            $this->history(
                $schedule,
                $actor,
                $old,
                'completed',
                'Đã hoàn tất checklist và minh chứng. Đợt bảo trì hoàn thành ngay, không yêu cầu phê duyệt.'
            );

            $this->audit($schedule, $actor, 'maintenance_completed_without_approval');
        });
    }

    public function saveReport(SolarMaintenanceSchedule $schedule, array $data, User $actor): void
    {
        if (! in_array($schedule->status, ['waiting_submission', 'revision_requested'], true)) {
            throw ValidationException::withMessages([
                'report_conclusion' => 'Báo cáo chỉ được cập nhật sau khi hoàn tất thực hiện hoặc khi Admin yêu cầu bổ sung.',
            ]);
        }

        $conclusion = (string) ($data['report_conclusion'] ?? '');
        if ($conclusion !== '' && ! array_key_exists($conclusion, self::CONCLUSIONS)) {
            throw ValidationException::withMessages(['report_conclusion' => 'Kết luận báo cáo không hợp lệ.']);
        }

        $schedule->forceFill([
            'report_conclusion' => $conclusion ?: $schedule->report_conclusion,
            'result_note' => trim((string) ($data['result_note'] ?? $schedule->result_note)),
            'technical_note' => trim((string) ($data['technical_note'] ?? $schedule->technical_note)),
        ])->save();
    }

    public function assertReadyForApproval(SolarMaintenanceSchedule $schedule): void
    {
        $this->ensureChecklist($schedule);
        $errors = [];

        if (! $schedule->assignees()->exists()) {
            $errors[] = 'Chưa có nhóm kỹ thuật thực hiện.';
        }

        if (! $schedule->execution_finished_at) {
            $errors[] = 'Chưa xác nhận hoàn tất phần thực hiện.';
        }

        $readiness = $this->checklistReadiness($schedule);
        if ($readiness['required_total'] === 0 || $readiness['required_done'] < $readiness['required_total']) {
            $errors[] = "Checklist bắt buộc chưa hoàn tất ({$readiness['required_done']}/{$readiness['required_total']}).";
        }
        if ($readiness['missing_evidence']) {
            $errors[] = 'Thiếu file minh chứng theo checklist: '.implode('; ', $readiness['missing_evidence']).'.';
        }

        // V2: không yêu cầu nhập lại Kết luận/Nội dung báo cáo ở một bước riêng.
        // Nội dung thực hiện đã nằm trong checklist; biên bản/ảnh nằm trong minh chứng.

        $evidence = $schedule->attachments()->whereIn('category', ['before','during','after','report','fault','serial','checklist'])->count();
        if ($evidence <= 0) {
            $errors[] = 'Chưa có ảnh hoặc file minh chứng.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['approval' => $errors]);
        }
    }

    private function checklistReadiness(SolarMaintenanceSchedule $schedule): array
    {
        $items = $schedule->checklistItems()
            ->withCount('attachments')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $required = $items->where('is_required', true);
        $missingEvidence = $required
            ->filter(fn ($item) => $item->requires_evidence
                && (int) $item->attachments_count < max(1, (int) $item->min_evidence))
            ->map(fn ($item) => $item->label.' ('.(int) $item->attachments_count.'/'.max(1, (int) $item->min_evidence).' file)')
            ->values()
            ->all();

        return [
            'required_total' => $required->count(),
            'required_done' => $required->where('is_done', true)->count(),
            'missing_evidence' => $missingEvidence,
        ];
    }

    private function history(SolarMaintenanceSchedule $schedule, User $actor, ?string $from, string $to, string $reason): void
    {
        $schedule->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'metadata' => ['source' => 'v10_workflow'],
        ]);
    }

    private function audit(SolarMaintenanceSchedule $schedule, User $actor, string $action): void
    {
        $schedule->auditLogs()->create([
            'action' => $action,
            'old_values' => null,
            'new_values' => $schedule->fresh()->toArray(),
            'user_id' => $actor->id,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
