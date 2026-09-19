<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Services\Synced\Technical\SolarMaintenanceService;
use App\Support\SolarMaintenanceAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectWorkflowV2Controller extends Controller
{
    public function __construct(private readonly ProjectWorkflowV2Service $workflow) {}

    public function activateMaintenance(Request $request, Site $site): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->workflow->isAdmin($user) || SolarMaintenanceAccess::isManager($user), 403, 'Chỉ Admin hoặc quản lý kỹ thuật được kích hoạt bảo hành.');
        abort_unless(Schema::hasTable('solar_maintenance_schedules'), 503, 'Chưa khởi tạo dữ liệu Bảo trì/Bảo hành.');

        $acceptance = $this->workflow->stepRow($site, 'acceptance');
        abort_unless((string) $acceptance->status === 'approved', 422, 'Công trình phải được duyệt nghiệm thu trước khi kích hoạt bảo hành.');

        $existing = SolarMaintenanceSchedule::query()
            ->where('site_id', $site->id)
            ->whereNotIn('status', ['cancelled'])
            ->oldest('id')
            ->first();

        if (! $existing) {
            $candidateIds = DB::table('project_workflow_assignments')
                ->where('workflow_step_id', $acceptance->id)
                ->where('is_active', 1)
                ->pluck('user_id')
                ->push((int) ($site->lead_engineer_id ?? 0))
                ->filter(fn ($id) => (int) $id > 0)
                ->unique()
                ->values();

            $technicianIds = User::query()
                ->whereIn('id', $candidateIds->all())
                ->get()
                ->filter(fn (User $candidate) => SolarMaintenanceAccess::isSelectableTechnician($candidate))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $existing = app(SolarMaintenanceService::class)->createSeries([
                'site_id' => (int) $site->id,
                'customer_name' => (string) ($site->contact_name ?? ''),
                'site_name' => (string) ($site->name ?? ''),
                'address' => (string) ($site->address ?? ''),
                'type' => 'periodic',
                'priority' => 'normal',
                'scheduled_date' => now()->toDateString(),
                'rounds_count' => 1,
                'round_interval_months' => 3,
                'assigned_user_ids' => $technicianIds,
                'system_kwp' => $site->system_kwp,
                'technical_note' => 'Tự động tạo sau khi nghiệm thu công trình và kích hoạt Bảo trì/Bảo hành.',
            ], $user)->first();
        }

        $siteUpdate = [];
        $candidateUpdates = [
            'project_phase' => 'om',
            'stage' => 'om',
            'workflow_current_step' => 'warranty',
            'project_phase_status' => 'in_progress',
            'completed_at' => $site->completed_at ?: now(),
            'handover_at' => $site->handover_at ?: now()->toDateString(),
            'warranty_started_at' => $site->warranty_started_at ?: now()->toDateString(),
            'updated_at' => now(),
        ];
        foreach ($candidateUpdates as $column => $value) {
            if ($column === 'updated_at' || Schema::hasColumn('sites', $column)) {
                $siteUpdate[$column] = $value;
            }
        }
        if ($siteUpdate !== []) {
            DB::table('sites')->where('id', $site->id)->update($siteUpdate);
        }

        $this->workflow->recordEvent($site, $acceptance, $user, 'maintenance_activated', 'approved', 'warranty', 'Kích hoạt Bảo trì/Bảo hành và tạo lịch '.($existing?->schedule_code ?: '#'.($existing?->id ?? '')), [
            'maintenance_schedule_id' => (int) ($existing?->id ?? 0),
            'schedule_code' => $existing?->schedule_code,
        ]);

        return redirect()->route('projects-unified.maintenance.index', [
            'view' => 'maintenance',
            'month' => '',
            'q' => (string) ($site->name ?? ''),
        ])->with('success', 'Đã kích hoạt Bảo trì/Bảo hành cho công trình '.($site->name ?? '').' và tạo lịch theo dõi.');
    }

    public function assign(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $definition = $this->workflow->validateStepCode($step);
        if (! $this->workflow->isAdmin($user)) {
            $this->assertStepUnlocked($site, $step);
        }
        abort_unless($this->workflow->canAssign($user, $definition), 403);

        $data = $request->validate([
            'primary_assignee_id' => ['required', 'integer', 'exists:users,id'],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'due_at' => ['required', 'date'],
            'requirement' => ['required', 'string', 'max:10000'],
            'approved_action' => ['nullable', Rule::in(['preserve', 'reopen'])],
        ]);

        DB::transaction(function () use ($site, $step, $user, $data): void {
            $row = $this->workflow->stepRow($site, $step, true);
            abort_if((string) $row->status === 'approved' && ! $this->workflow->isAdmin($user), 422, 'Bước đã được duyệt, chỉ Admin được phân công lại.');

            $primaryAssigneeId = (int) $data['primary_assignee_id'];
            $assigneeIds = collect([$primaryAssigneeId])
                ->merge($data['collaborator_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            DB::table('project_workflow_assignments')
                ->where('workflow_step_id', $row->id)
                ->whereNotIn('user_id', $assigneeIds->all())
                ->update(['is_active' => 0, 'updated_at' => now()]);

            foreach ($assigneeIds as $index => $assigneeId) {
                $existing = DB::table('project_workflow_assignments')
                    ->where('workflow_step_id', $row->id)
                    ->where('user_id', $assigneeId)
                    ->first();

                $values = [
                    'assignment_role' => $index === 0 ? 'primary' : 'collaborator',
                    'status' => in_array((string) ($existing->status ?? ''), ['in_progress', 'submitted'], true)
                        ? (string) $existing->status
                        : 'assigned',
                    'progress_percent' => (int) ($existing->progress_percent ?? 0),
                    'is_active' => 1,
                    'assigned_by' => (int) $user->id,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('project_workflow_assignments')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('project_workflow_assignments')->insert([
                        'workflow_step_id' => $row->id,
                        'user_id' => $assigneeId,
                        ...$values,
                        'created_at' => now(),
                    ]);
                }
            }

            $old = (string) $row->status;
            $keepApproved = $old === 'approved' && ($data['approved_action'] ?? 'preserve') === 'preserve';
            $newStatus = $keepApproved ? 'approved' : (in_array($old, ['in_progress', 'submitted'], true) ? $old : 'assigned');
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'assigned_by' => (int) $user->id,
                'due_at' => $data['due_at'],
                'requirement' => trim((string) $data['requirement']),
                'status' => $newStatus,
                'returned_reason' => null,
                'updated_at' => now(),
            ]);

            $this->workflow->recordEvent(
                $site,
                $row,
                $user,
                'step_assigned',
                $old,
                $newStatus,
                'Giao bước cho '.count($assigneeIds).' nhân sự, hạn '.$data['due_at'].'.',
                ['assignee_ids' => $assigneeIds->all()]
            );
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $step, 'Đã giao việc và đặt hạn hoàn thành.');
    }

    public function accept(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        if (! $this->workflow->isAdmin($user)) {
            $this->assertStepUnlocked($site, $step);
        }
        $row = $this->workflow->stepRow($site, $step);
        $assignment = $this->assignmentForUser($row, $user);
        abort_unless(in_array((string) $assignment->status, ['assigned', 'accepted'], true), 422);

        DB::transaction(function () use ($site, $row, $assignment, $user): void {
            DB::table('project_workflow_assignments')->where('id', $assignment->id)->update([
                'status' => 'accepted',
                'accepted_at' => $assignment->accepted_at ?: now(),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'assignment_accepted', (string) $assignment->status, 'accepted', 'Nhân viên xác nhận đã hiểu và nhận việc.');
        });

        return $this->back($site, $step, 'Đã xác nhận nhận việc.');
    }

    public function start(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertStepUnlocked($site, $step);
        $row = $this->workflow->stepRow($site, $step);
        $assignment = $this->assignmentForUser($row, $user);
        abort_unless(in_array((string) $assignment->status, ['assigned', 'accepted', 'in_progress'], true), 422);

        DB::transaction(function () use ($site, $row, $assignment, $user): void {
            DB::table('project_workflow_assignments')->where('id', $assignment->id)->update([
                'status' => 'in_progress',
                'accepted_at' => $assignment->accepted_at ?: now(),
                'started_at' => $assignment->started_at ?: now(),
                'updated_at' => now(),
            ]);
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'status' => 'in_progress',
                'started_at' => $row->started_at ?: now(),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'assignment_started', (string) $row->status, 'in_progress', 'Bắt đầu thực hiện phần việc được giao.');
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $step, 'Đã bắt đầu thực hiện công việc.');
    }

    public function progress(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertStepUnlocked($site, $step);
        $row = $this->workflow->stepRow($site, $step);
        $assignment = $this->assignmentForUser($row, $user);
        $dueSource = $row->recommitted_due_at ?? $row->due_at ?? null;
        if ($dueSource && Carbon::parse($dueSource)->isPast()) {
            $lateDays = (int) floor(Carbon::parse($dueSource)->diffInDays(now()));
            abort_if($lateDays > 15 && empty($row->recovery_plan), 422, 'Bước chậm trên 15 ngày. Trưởng phòng Kỹ thuật phải lập phương án khắc phục trước khi cập nhật tiếp.');
        }
        $data = $request->validate([
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'submission_summary' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($site, $row, $assignment, $user, $data): void {
            DB::table('project_workflow_assignments')->where('id', $assignment->id)->update([
                'status' => (int) $data['progress_percent'] > 0 ? 'in_progress' : (string) $assignment->status,
                'progress_percent' => (int) $data['progress_percent'],
                'submission_summary' => $data['submission_summary'] ?? $assignment->submission_summary,
                'started_at' => $assignment->started_at ?: ((int) $data['progress_percent'] > 0 ? now() : null),
                'updated_at' => now(),
            ]);
            if ((int) $data['progress_percent'] > 0 && ! in_array((string) $row->status, ['submitted', 'approved'], true)) {
                DB::table('project_workflow_steps')->where('id', $row->id)->update([
                    'status' => 'in_progress',
                    'started_at' => $row->started_at ?: now(),
                    'updated_at' => now(),
                ]);
            }
            $this->workflow->recordEvent($site, $row, $user, 'assignment_progress', null, (string) $data['progress_percent'], $data['submission_summary'] ?? null);
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $step, 'Đã cập nhật tiến độ phần việc.');
    }

    public function updateDelayPlan(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertStepUnlocked($site, $step);
        $definition = $this->workflow->validateStepCode($step);
        $row = $this->workflow->stepRow($site, $step);
        $dueSource = $row->recommitted_due_at ?? $row->due_at ?? null;
        abort_unless($dueSource && Carbon::parse($dueSource)->isPast(), 422, 'Bước chưa quá hạn.');

        $lateDays = max(1, (int) floor(Carbon::parse($dueSource)->diffInDays(now())));
        $isAssigned = DB::table('project_workflow_assignments')
            ->where('workflow_step_id', $row->id)
            ->where('user_id', $user->id)
            ->where('is_active', 1)
            ->exists();
        $canManage = $this->workflow->canAssign($user, $definition) || $this->workflow->isAdmin($user);

        if ($lateDays <= 15) {
            abort_unless($isAssigned || $canManage, 403);
        } elseif ($lateDays <= 30) {
            abort_unless($canManage, 403, 'Chậm 16–30 ngày phải do Trưởng phòng Kỹ thuật lập phương án khắc phục.');
        } else {
            abort_unless($this->workflow->isAdmin($user), 403, 'Chậm trên 30 ngày phải do Admin/Ban lãnh đạo xử lý.');
        }

        $data = $request->validate([
            'delay_reason' => ['required', 'string', 'min:5', 'max:10000'],
            'recommitted_due_at' => ['required', 'date', 'after:now'],
            'recovery_plan' => [$lateDays > 15 ? 'required' : 'nullable', 'string', 'max:10000'],
        ]);

        $risk = $lateDays > 30 ? 'high' : ($lateDays > 15 ? 'medium' : 'watch');
        DB::transaction(function () use ($site, $row, $user, $data, $risk, $lateDays): void {
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'delay_reason' => $data['delay_reason'],
                'recommitted_due_at' => $data['recommitted_due_at'],
                'recovery_plan' => $data['recovery_plan'] ?? null,
                'risk_level' => $risk,
                'delay_updated_by' => (int) $user->id,
                'delay_updated_at' => now(),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent(
                $site,
                $row,
                $user,
                'delay_plan_updated',
                'late_'.$lateDays.'_days',
                $risk,
                $data['delay_reason'],
                ['recommitted_due_at' => $data['recommitted_due_at'], 'recovery_plan' => $data['recovery_plan'] ?? null]
            );
        });

        return $this->back($site, $step, 'Đã lưu lý do chậm, ngày cam kết mới và phương án khắc phục.');
    }

    public function uploadDocument(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $definition = $this->workflow->validateStepCode($step);
        if (! $this->workflow->isAdmin($user)) {
            $this->assertStepUnlocked($site, $step);
        }
        $row = $this->workflow->stepRow($site, $step);
        $wasApproved = (string) $row->status === 'approved';
        $assignments = DB::table('project_workflow_assignments')->where('workflow_step_id', $row->id)->where('is_active', 1)->get();
        $canUpload = $assignments->contains(fn ($assignment) => (int) $assignment->user_id === (int) $user->id)
            || $this->workflow->canAssign($user, $definition)
            || $this->workflow->isAdmin($user);
        abort_unless($canUpload, 403);

        $documentDefinitions = $this->workflow->documentDefinitions($site, $step);
        $documentCodes = array_keys($documentDefinitions);
        $data = $request->validate([
            'document_code' => ['required', Rule::in($documentCodes)],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:102400'],
        ]);

        $documentDefinition = (array) ($documentDefinitions[$data['document_code']] ?? []);
        $maximum = (int) ($documentDefinition['max'] ?? 0);
        if ($maximum > 0) {
            $uploadedCount = DB::table('project_workflow_documents')
                ->where('workflow_step_id', $row->id)
                ->where('document_code', $data['document_code'])
                ->count();
            abort_if($uploadedCount >= $maximum, 422, 'Hồ sơ này chỉ cho phép tối đa '.$maximum.' tệp.');
        }
        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());
        $allowed = array_map('strtolower', (array) ($documentDefinition['extensions'] ?? []));
        abort_if($allowed !== [] && ! in_array($extension, $allowed, true), 422, 'Định dạng file không hợp lệ. Cho phép: '.implode(', ', $allowed).'.');

        $storedPath = $request->file('file')->store(
            'project-workflow/'.$site->id.'/'.$step.'/'.$data['document_code'],
            'public'
        );

        DB::transaction(function () use ($site, $step, $row, $user, $data, $documentDefinition, $storedPath, $request): void {
            $latest = DB::table('project_workflow_documents')
                ->where('workflow_step_id', $row->id)
                ->where('document_code', $data['document_code'])
                ->orderByDesc('version')
                ->first();
            $version = (int) ($latest->version ?? 0) + 1;

            DB::table('project_workflow_documents')->insert([
                'workflow_step_id' => $row->id,
                'site_id' => (int) $site->id,
                'document_code' => $data['document_code'],
                'title' => trim((string) (($data['title'] ?? null) ?: ($documentDefinition['label'] ?? $data['document_code']))),
                'path' => $storedPath,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType(),
                'file_size' => (int) $request->file('file')->getSize(),
                'version' => $version,
                'uploaded_by' => (int) $user->id,
                'replaced_document_id' => $latest?->id,
                'metadata' => json_encode(['step' => $step], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->workflow->recordEvent($site, $row, $user, 'document_uploaded', null, $data['document_code'], 'Tải hồ sơ phiên bản '.$version.': '.$request->file('file')->getClientOriginalName());
        });

        $submitted = $wasApproved ? false : $this->workflow->refreshSubmissionState($site, $step, $user);

        return $this->back($site, $step, $wasApproved
            ? 'Đã tải bổ sung hồ sơ; bước vẫn giữ trạng thái Đã duyệt.'
            : ($submitted ? 'Đã tải hồ sơ và tự động chuyển bước sang chờ duyệt.' : 'Đã tải hồ sơ; phiên bản cũ vẫn được lưu trong lịch sử.'));
    }

    public function saveDocumentSettings(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->workflow->validateStepCode($step);
        abort_unless($this->workflow->canManageDocumentSettings($user), 403);
        abort_unless(Schema::hasTable('project_workflow_document_settings'), 503, 'Chưa chạy migration cấu hình hồ sơ.');

        $data = $request->validate([
            'scope' => ['required', Rule::in(['project', 'global'])],
            'documents' => ['nullable', 'array', 'max:100'],
            'documents.*.code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'documents.*.label' => ['required', 'string', 'max:255'],
            'documents.*.required' => ['nullable', 'boolean'],
            'documents.*.minimum' => ['required', 'integer', 'min:1', 'max:100'],
            'documents.*.maximum' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'documents.*.extensions' => ['nullable', 'string', 'max:500'],
            'documents.*.responsible_group' => ['nullable', 'string', 'max:100'],
            'documents.*.conditional_key' => ['nullable', 'string', 'max:120'],
            'documents.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'documents.*.is_active' => ['nullable', 'boolean'],
        ]);

        $scope = (string) $data['scope'];
        if ($scope === 'global') {
            abort_unless($this->workflow->isAdmin($user), 403, 'Chỉ Admin được thay đổi mẫu hồ sơ dùng chung.');
        }

        $scopeKey = $scope === 'global' ? 'global' : 'site:'.(int) $site->id;
        $siteId = $scope === 'global' ? null : (int) $site->id;
        $documents = collect($data['documents'] ?? []);
        $usedCodes = [];

        DB::transaction(function () use ($site, $step, $user, $scope, $scopeKey, $siteId, $documents, &$usedCodes): void {
            DB::table('project_workflow_document_settings')
                ->where('scope_key', $scopeKey)
                ->where('step_code', $step)
                ->delete();

            foreach ($documents->values() as $index => $document) {
                $label = trim((string) ($document['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $rawCode = trim((string) ($document['code'] ?? ''));
                if ($rawCode === '' || str_starts_with($rawCode, '__new__')) {
                    $baseCode = Str::slug($label, '_');
                    $rawCode = 'custom_'.($baseCode !== '' ? $baseCode : 'document');
                }
                $code = substr(preg_replace('/[^A-Za-z0-9_-]+/', '_', $rawCode) ?: 'custom_document', 0, 80);
                $candidate = $code;
                $suffix = 2;
                while (isset($usedCodes[$candidate])) {
                    $candidate = substr($code, 0, 74).'_'.$suffix;
                    $suffix++;
                }
                $code = $candidate;
                $usedCodes[$code] = true;

                $extensions = collect(preg_split('/[\s,;|]+/', (string) ($document['extensions'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn ($extension) => strtolower(ltrim(trim((string) $extension), '.')))
                    ->filter(fn ($extension) => $extension !== '' && preg_match('/^[a-z0-9]+$/', $extension))
                    ->unique()
                    ->values()
                    ->all();

                $minimum = max(1, (int) ($document['minimum'] ?? 1));
                $maximum = ! empty($document['maximum']) ? (int) $document['maximum'] : null;
                abort_if($maximum !== null && $maximum < $minimum, 422, 'Số file tối đa phải lớn hơn hoặc bằng số file tối thiểu: '.$label.'.');

                $settingValues = [
                    'scope_key' => $scopeKey,
                    'site_id' => $siteId,
                    'step_code' => $step,
                    'document_code' => $code,
                    'label' => $label,
                    'is_required' => ! empty($document['required']),
                    'min_files' => $minimum,
                    'extensions' => json_encode($extensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'responsible_group' => trim((string) ($document['responsible_group'] ?? '')) ?: null,
                    'conditional_key' => trim((string) ($document['conditional_key'] ?? '')) ?: null,
                    'sort_order' => (int) ($document['sort_order'] ?? (($index + 1) * 10)),
                    'is_active' => ! array_key_exists('is_active', $document) || ! empty($document['is_active']),
                    'created_by' => (int) $user->id,
                    'updated_by' => (int) $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('project_workflow_document_settings', 'max_files')) {
                    $settingValues['max_files'] = $maximum;
                }
                DB::table('project_workflow_document_settings')->insert($settingValues);
            }

            $row = $this->workflow->stepRow($site, $step);
            $this->workflow->recordEvent(
                $site,
                $row,
                $user,
                'document_settings_updated',
                null,
                $scope,
                'Cập nhật cấu hình '.count($usedCodes).' loại hồ sơ cho bước.',
                ['scope' => $scope, 'document_codes' => array_keys($usedCodes)]
            );
        });

        return $this->back($site, $step, $scope === 'global'
            ? 'Đã lưu mẫu hồ sơ dùng chung cho bước này.'
            : 'Đã lưu cấu hình hồ sơ riêng của dự án này.');
    }

    public function resetDocumentSettings(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->workflow->validateStepCode($step);
        abort_unless($this->workflow->canManageDocumentSettings($user), 403);
        abort_unless(Schema::hasTable('project_workflow_document_settings'), 503);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['project', 'global'])],
        ]);
        $scope = (string) $data['scope'];
        if ($scope === 'global') {
            abort_unless($this->workflow->isAdmin($user), 403, 'Chỉ Admin được khôi phục mẫu dùng chung.');
        }

        $scopeKey = $scope === 'global' ? 'global' : 'site:'.(int) $site->id;
        DB::transaction(function () use ($site, $step, $scope, $scopeKey, $user): void {
            DB::table('project_workflow_document_settings')
                ->where('scope_key', $scopeKey)
                ->where('step_code', $step)
                ->delete();

            $row = $this->workflow->stepRow($site, $step);
            $this->workflow->recordEvent($site, $row, $user, 'document_settings_reset', $scope, 'default', 'Khôi phục cấu hình hồ sơ về mặc định.');
        });

        return $this->back($site, $step, $scope === 'global'
            ? 'Đã khôi phục mẫu hồ sơ dùng chung.'
            : 'Đã bỏ cấu hình riêng và trở về mẫu hồ sơ mặc định.');
    }

    public function submitAssignment(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertStepUnlocked($site, $step);
        $row = $this->workflow->stepRow($site, $step);
        $assignment = $this->assignmentForUser($row, $user);
        $data = $request->validate([
            'submission_summary' => ['required', 'string', 'max:10000'],
        ]);

        $documents = DB::table('project_workflow_documents')->where('workflow_step_id', $row->id)->get();
        $documentState = $this->workflow->documentState($site, $step, $row, $documents);
        abort_unless(
            $documentState['files_complete'] ?? $documentState['complete'],
            422,
            'Chưa đủ file bắt buộc: '.implode('; ', $documentState['file_missing'] ?? $documentState['missing']).'.'
        );

        DB::transaction(function () use ($site, $row, $assignment, $user, $data): void {
            DB::table('project_workflow_assignments')->where('id', $assignment->id)->update([
                'status' => 'submitted',
                'progress_percent' => 100,
                'submission_summary' => trim((string) $data['submission_summary']),
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'assignment_submitted', (string) $assignment->status, 'submitted', $data['submission_summary']);
        });

        $submitted = $this->workflow->refreshSubmissionState($site, $step, $user);
        if ($submitted) {
            return $this->back($site, $step, 'Tất cả nhân sự đã nộp và hồ sơ đã đủ. Bước đã chuyển sang chờ duyệt.');
        }

        $documents = DB::table('project_workflow_documents')->where('workflow_step_id', $row->id)->get();
        $state = $this->workflow->documentState($site, $step, $row, $documents);
        $message = ($state['files_complete'] ?? $state['complete'])
            ? 'Đã nộp phần việc; đang chờ những người được giao còn lại nộp kết quả.'
            : 'Đã nộp phần việc nhưng bước chưa thể gửi duyệt vì còn thiếu file: '.implode('; ', $state['file_missing'] ?? $state['missing']).'.';

        return $this->back($site, $step, $message);
    }

    public function approve(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $definition = $this->workflow->validateStepCode($step);
        $row = $this->workflow->stepRow($site, $step);
        $oldStatus = (string) $row->status;
        $isAdminOverride = $this->workflow->isAdmin($user) && $oldStatus !== 'submitted';
        abort_unless($oldStatus === 'submitted' || $this->workflow->isAdmin($user), 422, 'Bước chưa ở trạng thái chờ duyệt.');

        $tracks = (array) ($definition['approval_tracks'] ?? []);
        $data = $request->validate([
            'approval_type' => ['required', Rule::in(array_keys($tracks))],
            'note' => ['nullable', 'string', 'max:10000'],
        ]);
        $assignments = DB::table('project_workflow_assignments')->where('workflow_step_id', $row->id)->where('is_active', 1)->get();
        abort_unless($this->workflow->isAdmin($user) || $this->workflow->canApproveTrack($user, (array) $tracks[$data['approval_type']], $assignments), 403, 'Người thực hiện không được tự duyệt kết quả của mình.');

        $documents = DB::table('project_workflow_documents')->where('workflow_step_id', $row->id)->get();
        $documentState = $this->workflow->documentState($site, $step, $row, $documents);
        $missingDocuments = (array) ($documentState['file_missing'] ?? $documentState['missing'] ?? []);
        $approvalNote = $data['note'] ?? null;
        if ($this->workflow->isAdmin($user) && $missingDocuments !== []) {
            $approvalNote = 'Admin duyệt ngoại lệ khi hồ sơ còn thiếu: '.implode('; ', $missingDocuments).'.'.($approvalNote ? ' '.$approvalNote : '');
        }

        DB::transaction(function () use ($site, $step, $row, $user, $data, $oldStatus, $isAdminOverride, $missingDocuments, $approvalNote): void {
            DB::table('project_workflow_approvals')->updateOrInsert(
                ['workflow_step_id' => $row->id, 'approval_type' => $data['approval_type']],
                [
                    'status' => 'approved',
                    'reviewed_by' => (int) $user->id,
                    'reviewed_at' => now(),
                    'note' => $approvalNote,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $this->workflow->recordEvent($site, $row, $user, 'approval_granted', $oldStatus, 'approved', $approvalNote, ['approval_type' => $data['approval_type'], 'admin_override' => $isAdminOverride || $missingDocuments !== [], 'missing_documents' => $missingDocuments]);

            if ($this->workflow->approvalsComplete($row, $step)) {
                DB::table('project_workflow_steps')->where('id', $row->id)->update([
                    'status' => 'approved',
                    'approved_by' => (int) $user->id,
                    'approved_at' => now(),
                    'returned_reason' => null,
                    'updated_at' => now(),
                ]);
                $this->applyApprovalSideEffects($site, $step);
                $this->workflow->recordEvent($site, $row, $user, 'step_approved', $oldStatus, 'approved', $missingDocuments === [] ? 'Đủ các cấp duyệt; hệ thống mở bước tiếp theo.' : 'Admin duyệt ngoại lệ khi hồ sơ chưa đầy đủ; hệ thống mở bước tiếp theo.', ['admin_override' => $missingDocuments !== [], 'missing_documents' => $missingDocuments]);
            }

            $this->workflow->syncProject($site);
        });

        $latest = $this->workflow->stepRow($site, $step);
        $message = (string) $latest->status === 'approved'
            ? ($missingDocuments === [] ? 'Đã duyệt bước và mở bước tiếp theo.' : 'Đã duyệt ngoại lệ; hệ thống đã ghi nhận danh sách hồ sơ còn thiếu và mở bước tiếp theo.')
            : 'Đã ghi nhận cấp duyệt; bước còn chờ cấp duyệt khác.';

        return $this->back($site, $step, $message);
    }

    public function revise(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $definition = $this->workflow->validateStepCode($step);
        $row = $this->workflow->stepRow($site, $step);
        $previousStatus = (string) $row->status;
        $isAdmin = $this->workflow->isAdmin($user);
        abort_unless($previousStatus === 'submitted' || $isAdmin, 422, 'Chỉ Admin được trả hồ sơ khi bước chưa ở trạng thái chờ duyệt.');

        $tracks = (array) ($definition['approval_tracks'] ?? []);
        $data = $request->validate([
            'approval_type' => ['required', Rule::in(array_keys($tracks))],
            'reason' => ['required', 'string', 'max:10000'],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'revision_due_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);
        $assignments = DB::table('project_workflow_assignments')->where('workflow_step_id', $row->id)->where('is_active', 1)->get();
        abort_unless($isAdmin || $this->workflow->canApproveTrack($user, (array) $tracks[$data['approval_type']], $assignments), 403);
        abort_unless($isAdmin || $assignments->contains(fn ($assignment) => (int) $assignment->user_id === (int) $data['recipient_id']), 422, 'Người nhận xử lý phải thuộc danh sách nhân sự đang được phân công.');

        $attachment = $request->file('attachment');
        $attachmentPath = $attachment?->store(
            'project-workflow/'.$site->id.'/'.$step.'/revision-feedback',
            'public'
        );

        DB::transaction(function () use ($site, $step, $row, $user, $data, $attachment, $attachmentPath, $previousStatus): void {
            DB::table('project_workflow_approvals')->updateOrInsert(
                ['workflow_step_id' => $row->id, 'approval_type' => $data['approval_type']],
                [
                    'status' => 'revision',
                    'reviewed_by' => (int) $user->id,
                    'reviewed_at' => now(),
                    'note' => $data['reason'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'status' => 'revision',
                'submitted_at' => null,
                'approved_at' => null,
                'returned_reason' => $data['reason'],
                'recommitted_due_at' => $data['revision_due_at'] ?: ($row->recommitted_due_at ?? $row->due_at),
                'updated_at' => now(),
            ]);
            $recipientAssignment = DB::table('project_workflow_assignments')
                ->where('workflow_step_id', $row->id)
                ->where('user_id', (int) $data['recipient_id'])
                ->first();
            $assignmentValues = [
                'status' => 'in_progress',
                'submitted_at' => null,
                'is_active' => 1,
                'assigned_by' => (int) $user->id,
                'updated_at' => now(),
            ];
            if ($recipientAssignment) {
                DB::table('project_workflow_assignments')->where('id', $recipientAssignment->id)->update($assignmentValues);
            } else {
                $hasPrimary = DB::table('project_workflow_assignments')
                    ->where('workflow_step_id', $row->id)
                    ->where('assignment_role', 'primary')
                    ->where('is_active', 1)
                    ->exists();
                DB::table('project_workflow_assignments')->insert([
                    'workflow_step_id' => $row->id,
                    'user_id' => (int) $data['recipient_id'],
                    'assignment_role' => $hasPrimary ? 'collaborator' : 'primary',
                    'progress_percent' => 0,
                    ...$assignmentValues,
                    'created_at' => now(),
                ]);
            }

            $attachmentId = null;
            if ($attachment && $attachmentPath) {
                $attachmentId = DB::table('project_workflow_documents')->insertGetId([
                    'workflow_step_id' => $row->id,
                    'site_id' => (int) $site->id,
                    'document_code' => 'revision_feedback',
                    'title' => 'File phản hồi hoàn trả',
                    'path' => $attachmentPath,
                    'original_name' => $attachment->getClientOriginalName(),
                    'mime_type' => $attachment->getMimeType(),
                    'file_size' => (int) $attachment->getSize(),
                    'version' => 1,
                    'uploaded_by' => (int) $user->id,
                    'replaced_document_id' => null,
                    'metadata' => json_encode(['step' => $step, 'recipient_id' => (int) $data['recipient_id'], 'kind' => 'revision_feedback'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->workflow->recordEvent($site, $row, $user, 'step_revision_requested', $previousStatus, 'revision', $data['reason'], [
                'approval_type' => $data['approval_type'],
                'recipient_id' => (int) $data['recipient_id'],
                'revision_due_at' => $data['revision_due_at'] ?? null,
                'attachment_id' => $attachmentId,
                'attachment_name' => $attachment?->getClientOriginalName(),
            ]);
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $step, 'Đã trả hồ sơ yêu cầu chỉnh sửa, giao người xử lý và ghi vào lịch sử công trình.');
    }

    public function saveData(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        $definition = $this->workflow->validateStepCode($step);
        if (! $this->workflow->isAdmin($user)) {
            $this->assertStepUnlocked($site, $step);
        }
        $row = $this->workflow->stepRow($site, $step);
        $assignments = DB::table('project_workflow_assignments')->where('workflow_step_id', $row->id)->where('is_active', 1)->get();
        $canSave = $assignments->contains(fn ($assignment) => (int) $assignment->user_id === (int) $user->id)
            || $this->workflow->canAssign($user, $definition)
            || $this->workflow->isAdmin($user);
        abort_unless($canSave, 403);

        $data = $request->validate([
            'customer_feedback_note' => ['nullable', 'string', 'max:10000'],
            'technical_feasibility_confirmed' => ['nullable', 'boolean'],
            'contract_signed_at' => ['nullable', 'date'],
            'contract_amount_before_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contract_amount_after_vat' => ['nullable', 'numeric', 'min:0'],
            'target_completion_at' => ['nullable', 'date'],
            'requires_grid_connection' => ['nullable', 'boolean'],
            'requires_insurance' => ['nullable', 'boolean'],
            'has_incident' => ['nullable', 'boolean'],
            'handover_at' => ['nullable', 'date'],
            'warranty_started_at' => ['nullable', 'date'],
            'warranty_to' => ['nullable', 'date'],
            'uses_replacement_material' => ['nullable', 'boolean'],
            'warranty_scope' => ['nullable', Rule::in(['in_scope', 'out_of_scope', 'pending'])],
            'step_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $booleanKeys = [
            'technical_feasibility_confirmed', 'requires_grid_connection',
            'requires_insurance', 'has_incident', 'uses_replacement_material',
        ];
        foreach ($booleanKeys as $key) {
            $data[$key] = $request->boolean($key);
        }

        DB::transaction(function () use ($site, $step, $row, $user, $data): void {
            $current = $this->workflow->decodeData($row->data ?? null);
            $merged = array_merge($current, array_filter($data, fn ($value) => $value !== null));
            DB::table('project_workflow_steps')->where('id', $row->id)->update([
                'data' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

            $siteUpdate = [];
            foreach ([
                'contract_signed_at', 'contract_amount_before_vat', 'vat_rate',
                'contract_amount_after_vat', 'target_completion_at', 'handover_at',
                'warranty_started_at', 'warranty_to',
            ] as $column) {
                if (array_key_exists($column, $data) && $data[$column] !== null && Schema::hasColumn('sites', $column)) {
                    $siteUpdate[$column] = $data[$column];
                }
            }
            if (isset($siteUpdate['contract_amount_after_vat']) && Schema::hasColumn('sites', 'contract_amount')) {
                $siteUpdate['contract_amount'] = $siteUpdate['contract_amount_after_vat'];
            }
            if ($siteUpdate !== []) {
                $siteUpdate['updated_at'] = now();
                DB::table('sites')->where('id', $site->id)->update($siteUpdate);
            }

            $this->workflow->recordEvent($site, $row, $user, 'step_data_updated', null, $step, $data['step_note'] ?? 'Cập nhật dữ liệu nghiệp vụ của bước.');
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $step, 'Đã lưu dữ liệu nghiệp vụ của bước.');
    }

    public function requestException(Request $request, Site $site, string $step): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->workflow->isAdmin($user), 403);
        $definitions = $this->workflow->definitions();
        $data = $request->validate([
            'to_step' => ['required', Rule::in(array_keys($definitions))],
            'reason' => ['required', 'string', 'max:10000'],
        ]);
        abort_unless(Schema::hasTable('project_workflow_exceptions'), 503);

        DB::transaction(function () use ($site, $step, $user, $data, $definitions): void {
            $row = $this->workflow->stepRow($site, $step, true);
            DB::table('project_workflow_exceptions')->insert([
                'site_id' => (int) $site->id,
                'from_step' => $step,
                'to_step' => $data['to_step'],
                'status' => 'approved',
                'reason' => $data['reason'],
                'requested_by' => (int) $user->id,
                'approved_by' => (int) $user->id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $targetSequence = (int) ($definitions[$data['to_step']]['sequence'] ?? 1);
            DB::table('project_workflow_steps')->where('site_id', $site->id)->where('sequence', '<', $targetSequence)->update([
                'status' => 'approved',
                'approved_by' => (int) $user->id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', $data['to_step'])->update([
                'status' => 'not_assigned',
                'updated_at' => now(),
            ]);
            $this->workflow->recordEvent($site, $row, $user, 'workflow_exception_approved', $step, $data['to_step'], $data['reason']);
            $this->workflow->syncProject($site);
        });

        return $this->back($site, $data['to_step'], 'Đã duyệt ngoại lệ chuyển bước và lưu đầy đủ lý do.');
    }

    public function approveMaterialAdmin(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->workflow->isAdmin($user), 403);
        abort_unless(Schema::hasTable('project_material_proposals'), 503);
        $data = $request->validate(['admin_approval_note' => ['nullable', 'string', 'max:5000']]);

        $updated = DB::table('project_material_proposals')
            ->where('id', $proposal)
            ->where('site_id', $site->id)
            ->whereIn('status', ['WAREHOUSE_ALLOCATED', 'ADMIN_APPROVAL_PENDING'])
            ->update([
                'status' => 'ADMIN_APPROVED',
                'admin_approved_by' => (int) $user->id,
                'admin_approved_at' => now(),
                'admin_approval_note' => $data['admin_approval_note'] ?? null,
                'updated_at' => now(),
            ]);
        abort_unless($updated > 0, 422, 'Phiếu chưa được Kho đối chiếu đầy đủ hoặc đã được xử lý.');

        return back()->with('success', 'Admin đã duyệt phiếu vật tư; Kho được phép tạo đơn xuất.')->withFragment('materials');
    }

    public function confirmMaterialReceipt(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->workflow->matchesAnyGroup($user, ['technical', 'technical_manager', 'supervisor']) || $this->workflow->isAdmin($user), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $updated = DB::table('project_material_proposals')->where('id', $proposal)->where('site_id', $site->id)->whereIn('status', ['READY_FOR_EXPORT', 'EXPORTED'])->update([
            'recipient_confirmed_by' => (int) $user->id,
            'recipient_confirmed_at' => now(),
            'updated_at' => now(),
        ]);
        abort_unless($updated > 0, 422);

        return back()->with('success', 'Đội thi công đã xác nhận nhận và kiểm đếm vật tư.')->withFragment('materials');
    }

    public function confirmMaterialSupervisor(Request $request, Site $site, int $proposal): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->workflow->matchesAnyGroup($user, ['supervisor', 'technical_manager']) || $this->workflow->isAdmin($user), 403);
        $updated = DB::table('project_material_proposals')->where('id', $proposal)->where('site_id', $site->id)->whereNotNull('recipient_confirmed_at')->update([
            'supervisor_confirmed_by' => (int) $user->id,
            'supervisor_confirmed_at' => now(),
            'status' => 'EXPORTED',
            'updated_at' => now(),
        ]);
        abort_unless($updated > 0, 422, 'Đội thi công chưa xác nhận nhận vật tư.');

        return back()->with('success', 'Giám sát đã xác nhận vật tư tập kết đúng chủng loại và số lượng.')->withFragment('materials');
    }

    private function applyApprovalSideEffects(Site $site, string $step): void
    {
        $update = [];
        if ($step === 'acceptance') {
            foreach (['completed_at', 'handover_at', 'warranty_started_at'] as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $update[$column] = now()->toDateString();
                }
            }
        }
        if ($step === 'warranty' && Schema::hasColumn('sites', 'workflow_status')) {
            $update['workflow_status'] = 'completed';
        }
        if ($update !== []) {
            $update['updated_at'] = now();
            DB::table('sites')->where('id', $site->id)->update($update);
        }
    }

    private function assertStepUnlocked(Site $site, string $step): void
    {
        abort_unless(
            $this->workflow->isStepUnlocked($site, $step),
            422,
            'Bước này chưa được mở. Phải duyệt hoàn tất bước trước hoặc dùng ngoại lệ do Admin phê duyệt.'
        );
    }

    private function assignmentForUser(object $step, User $user): object
    {
        $assignment = DB::table('project_workflow_assignments')
            ->where('workflow_step_id', $step->id)
            ->where('user_id', $user->id)
            ->where('is_active', 1)
            ->first();
        abort_unless($assignment, 403, 'Bạn chưa được giao bước này.');

        return $assignment;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(Site $site, string $step, string $message): RedirectResponse
    {
        return redirect()
            ->route('projects-unified.show', ['site' => $site->id, 'step' => $step])
            ->with('success', $message)
            ->withFragment('workflow');
    }
}
