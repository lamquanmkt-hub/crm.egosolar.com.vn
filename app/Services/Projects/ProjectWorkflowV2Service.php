<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectWorkflowV2Service
{
    /** @return array<string,array<string,mixed>> */
    public function definitions(): array
    {
        return (array) config('project_workflow_v2.steps', []);
    }

    /** @return array<string,string> */
    public function statusLabels(): array
    {
        return (array) config('project_workflow_v2.status_labels', []);
    }

    public function isAvailable(): bool
    {
        return Schema::hasTable('project_workflow_steps');
    }

    public function ensure(Site $site): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $definitions = $this->definitions();
        if ($definitions === []) {
            return;
        }

        $existing = DB::table('project_workflow_steps')
            ->where('site_id', $site->id)
            ->pluck('step_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        if (count($existing) === count($definitions)) {
            return;
        }

        $legacyPhase = strtolower(trim((string) ($site->project_phase ?? $site->stage ?? 'init')));
        $legacyMap = (array) config('project_workflow_v2.legacy_phase_map', []);
        $current = (string) ($site->workflow_current_step ?? ($legacyMap[$legacyPhase] ?? 'survey'));
        if (! isset($definitions[$current])) {
            $current = array_key_first($definitions) ?: 'survey';
        }
        $currentSequence = (int) ($definitions[$current]['sequence'] ?? 1);

        DB::transaction(function () use ($site, $definitions, $existing, $currentSequence): void {
            foreach ($definitions as $code => $definition) {
                if (in_array($code, $existing, true)) {
                    continue;
                }

                $sequence = (int) ($definition['sequence'] ?? 0);
                $status = $sequence < $currentSequence
                    ? 'approved'
                    : 'not_assigned';

                DB::table('project_workflow_steps')->insert([
                    'site_id' => (int) $site->id,
                    'step_code' => $code,
                    'sequence' => $sequence,
                    'status' => $status,
                    'started_at' => $status === 'in_progress' ? now() : null,
                    'submitted_at' => $status === 'approved' ? now() : null,
                    'approved_at' => $status === 'approved' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->syncProject($site);
        });
    }

    public function applyListFilter(Builder $query, ?string $step): void
    {
        $step = trim((string) $step);
        if ($step === '' || ! isset($this->definitions()[$step])) {
            return;
        }

        if (Schema::hasColumn('sites', 'workflow_current_step')) {
            $query->where('workflow_current_step', $step);

            return;
        }

        if ($this->isAvailable()) {
            $query->whereExists(function ($subQuery) use ($step): void {
                $subQuery->selectRaw('1')
                    ->from('project_workflow_steps as wf_filter')
                    ->whereColumn('wf_filter.site_id', 'sites.id')
                    ->where('wf_filter.step_code', $step)
                    ->where('wf_filter.status', '!=', 'approved');
            });
        }
    }

    /**
     * @param  Collection<int,int|string>  $siteIds
     * @return array<int,array<string,mixed>>
     */
    public function listPresentation(Collection $siteIds): array
    {
        $ids = $siteIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $definitions = $this->definitions();
        $statusLabels = $this->statusLabels();

        $steps = $this->isAvailable()
            ? DB::table('project_workflow_steps')
                ->whereIn('site_id', $ids->all())
                ->orderBy('sequence')
                ->get()
                ->groupBy('site_id')
            : collect();

        $sites = DB::table('sites')
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($ids as $siteId) {
            $site = $sites->get($siteId);
            $rows = $steps->get($siteId, collect());
            $current = $rows->first(fn ($row) => (string) $row->status !== 'approved') ?: $rows->last();
            $code = (string) ($current->step_code ?? $site->workflow_current_step ?? 'survey');
            $definition = $definitions[$code] ?? reset($definitions) ?: [];
            $status = (string) ($current->status ?? 'not_assigned');
            $dueSource = $current->recommitted_due_at ?? $current->due_at ?? null;
            $dueAt = ! empty($dueSource) ? Carbon::parse($dueSource) : null;
            $delay = $this->delayState($dueAt, $status);
            $isOverdue = $delay['days'] > 0;
            $progress = (int) ($site->workflow_progress_percent ?? $site->progress_percent ?? 0);

            $result[(int) $siteId] = [
                'code' => $code,
                'label' => (string) ($definition['label'] ?? $code),
                'short' => (string) ($definition['short'] ?? $code),
                'tone' => (string) ($definition['tone'] ?? 'blue'),
                'icon' => (string) ($definition['icon'] ?? 'bi-diagram-3'),
                'status' => $status,
                'status_label' => (string) ($statusLabels[$status] ?? $status),
                'due_at' => $dueAt,
                'is_overdue' => (bool) $isOverdue,
                'delay_level' => $delay['level'],
                'delay_days' => $delay['days'],
                'progress' => max(0, min(100, $progress)),
                'next_action' => $this->nextAction($status, $definition, $isOverdue),
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int,int|string>  $siteIds
     * @return array{counts:array<string,int>,overdue:int,total:int}
     */
    public function summary(Collection $siteIds): array
    {
        $ids = $siteIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $counts = collect(array_keys($this->definitions()))->mapWithKeys(fn ($code) => [$code => 0])->all();
        if ($ids->isEmpty()) {
            return ['counts' => $counts, 'overdue' => 0, 'total' => 0];
        }

        $presentation = $this->listPresentation($ids);
        $overdue = 0;
        foreach ($presentation as $row) {
            if (isset($counts[$row['code']])) {
                $counts[$row['code']]++;
            }
            if (! empty($row['is_overdue'])) {
                $overdue++;
            }
        }

        return ['counts' => $counts, 'overdue' => $overdue, 'total' => $ids->count()];
    }

    /** @return array<string,mixed> */
    public function present(Site $site, User $user, ?string $selectedStep = null): array
    {
        $this->ensure($site);
        $definitions = $this->definitions();
        $statusLabels = $this->statusLabels();

        if (! $this->isAvailable()) {
            return $this->fallbackPresentation($site, $definitions, $statusLabels);
        }

        $steps = DB::table('project_workflow_steps')
            ->where('site_id', $site->id)
            ->orderBy('sequence')
            ->get()
            ->keyBy('step_code');

        $currentRow = $steps->first(fn ($row) => (string) $row->status !== 'approved') ?: $steps->last();
        $currentCode = (string) ($currentRow->step_code ?? array_key_first($definitions));
        $selectedStep = isset($definitions[(string) $selectedStep]) ? (string) $selectedStep : $currentCode;
        $selectedRow = $steps->get($selectedStep);
        if (! $selectedRow) {
            $selectedStep = $currentCode;
            $selectedRow = $currentRow;
        }

        $stepIds = $steps->pluck('id')->map(fn ($id) => (int) $id)->all();
        $assignments = collect();
        $documents = collect();
        $approvals = collect();
        $events = collect();

        if ($stepIds !== []) {
            $assignments = DB::table('project_workflow_assignments as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->whereIn('a.workflow_step_id', $stepIds)
                ->where('a.is_active', 1)
                ->select('a.*', 'u.name as user_name', 'u.email as user_email')
                ->orderBy('a.assignment_role')
                ->orderBy('u.name')
                ->get()
                ->groupBy('workflow_step_id');

            $documents = DB::table('project_workflow_documents as d')
                ->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')
                ->whereIn('d.workflow_step_id', $stepIds)
                ->select('d.*', 'u.name as uploader_name')
                ->orderByDesc('d.version')
                ->orderByDesc('d.id')
                ->get()
                ->groupBy('workflow_step_id');

            $approvals = DB::table('project_workflow_approvals as a')
                ->leftJoin('users as reviewer', 'reviewer.id', '=', 'a.reviewed_by')
                ->whereIn('a.workflow_step_id', $stepIds)
                ->select('a.*', 'reviewer.name as reviewer_name')
                ->get()
                ->groupBy('workflow_step_id');
        }

        if (Schema::hasTable('project_workflow_events')) {
            $events = DB::table('project_workflow_events as e')
                ->leftJoin('users as u', 'u.id', '=', 'e.actor_id')
                ->where('e.site_id', $site->id)
                ->where(function ($query) use ($selectedRow): void {
                    $query->whereNull('e.workflow_step_id');
                    if ($selectedRow) {
                        $query->orWhere('e.workflow_step_id', $selectedRow->id);
                    }
                })
                ->select('e.*', 'u.name as actor_name')
                ->orderByDesc('e.id')
                ->limit(80)
                ->get();
        }

        $stepPresentations = [];
        foreach ($definitions as $code => $definition) {
            $definition['documents'] = $this->documentDefinitions($site, (string) $code);
            $row = $steps->get($code);
            $rowAssignments = $row ? $assignments->get((int) $row->id, collect()) : collect();
            $rowDocuments = $row ? $documents->get((int) $row->id, collect()) : collect();
            $state = $this->documentState($site, $code, $row, $rowDocuments);
            $status = (string) ($row->status ?? 'not_assigned');
            $dueSource = $row->recommitted_due_at ?? $row->due_at ?? null;
            $dueAt = ! empty($dueSource) ? Carbon::parse($dueSource) : null;
            $delay = $this->delayState($dueAt, $status);
            $overdueDays = $delay['days'];

            $stepPresentations[$code] = [
                'row' => $row,
                'definition' => $definition,
                'is_unlocked' => $this->isStepUnlocked($site, $code),
                'status' => $status,
                'status_label' => (string) ($statusLabels[$status] ?? $status),
                'status_percent' => $this->statusPercent($status),
                'assignments' => $rowAssignments,
                'documents' => $rowDocuments,
                'document_state' => $state,
                'approvals' => $row ? $approvals->get((int) $row->id, collect()) : collect(),
                'due_at' => $dueAt,
                'overdue_days' => $overdueDays,
                'delay_level' => $delay['level'],
                'delay_label' => $delay['label'],
                'is_current' => $code === $currentCode,
                'is_selected' => $code === $selectedStep,
            ];
        }

        $selected = $stepPresentations[$selectedStep];
        $selectedRow = $selected['row'];
        $selectedAssignments = $selected['assignments'];
        $roles = $this->roles($user);
        $isAssigned = $selectedAssignments->contains(fn ($row) => (int) $row->user_id === (int) $user->id);
        $isUnlocked = ! empty($selected['is_unlocked']);
        $permissions = [
            'can_assign' => $this->isAdmin($user) || ($isUnlocked && $this->canAssign($user, $selected['definition'])),
            'can_work' => $this->isAdmin($user) || ($isUnlocked && $isAssigned),
            'can_upload' => $this->isAdmin($user) || ($isUnlocked && ($isAssigned || $this->canAssign($user, $selected['definition']))),
            'can_save_data' => $this->isAdmin($user) || ($isUnlocked && ($isAssigned || $this->canAssign($user, $selected['definition']))),
            'can_request_exception' => $this->isAdmin($user),
            'can_manage_documents' => $this->canManageDocumentSettings($user),
            'can_manage_global_documents' => $this->isAdmin($user),
            'is_admin' => $this->isAdmin($user),
            'is_assigned' => $isAssigned,
            'roles' => $roles,
        ];

        $approvalPermissions = [];
        foreach ((array) ($selected['definition']['approval_tracks'] ?? []) as $type => $track) {
            $approvalPermissions[$type] = $this->canApproveTrack($user, $track, $selectedAssignments);
        }

        return [
            'available' => true,
            'version' => (string) config('project_workflow_v2.version', '2.0'),
            'definitions' => $definitions,
            'steps' => $stepPresentations,
            'current_code' => $currentCode,
            'selected_code' => $selectedStep,
            'selected' => $selected,
            'current' => $stepPresentations[$currentCode] ?? $selected,
            'status_labels' => $statusLabels,
            'permissions' => $permissions,
            'approval_permissions' => $approvalPermissions,
            'document_settings' => $this->documentSettingsForEditor($site, $selectedStep),
            'assignee_options' => $this->assigneeOptions($selected['definition']),
            'events' => $events,
            'progress' => $this->calculateProgress($steps),
            'material_types' => [
                'INITIAL' => 'Vật tư ban đầu',
                'ADDITIONAL' => 'Vật tư phát sinh thi công',
                'REPLACEMENT' => 'Vật tư thay thế bảo hành',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function fallbackPresentation(Site $site, array $definitions, array $statusLabels): array
    {
        $legacyMap = (array) config('project_workflow_v2.legacy_phase_map', []);
        $legacy = strtolower(trim((string) ($site->project_phase ?? $site->stage ?? 'init')));
        $current = (string) ($legacyMap[$legacy] ?? 'survey');
        $steps = [];
        foreach ($definitions as $code => $definition) {
            $status = $code === $current ? 'in_progress' : 'not_assigned';
            $steps[$code] = [
                'row' => null,
                'definition' => $definition,
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'status_percent' => $this->statusPercent($status),
                'assignments' => collect(),
                'documents' => collect(),
                'document_state' => ['items' => [], 'missing' => [], 'complete' => false],
                'approvals' => collect(),
                'due_at' => null,
                'overdue_days' => 0,
                'is_current' => $code === $current,
                'is_selected' => $code === $current,
            ];
        }

        return [
            'available' => false,
            'version' => '2.0',
            'definitions' => $definitions,
            'steps' => $steps,
            'current_code' => $current,
            'selected_code' => $current,
            'selected' => $steps[$current] ?? null,
            'current' => $steps[$current] ?? null,
            'status_labels' => $statusLabels,
            'permissions' => ['can_assign' => false, 'can_work' => false, 'can_upload' => false, 'can_save_data' => false, 'can_request_exception' => false, 'can_manage_documents' => false, 'can_manage_global_documents' => false, 'is_assigned' => false, 'roles' => []],
            'approval_permissions' => [],
            'document_settings' => [],
            'assignee_options' => collect(),
            'events' => collect(),
            'progress' => (int) ($site->progress_percent ?? 0),
            'material_types' => [],
        ];
    }

    /** @return array{items:array<int,array<string,mixed>>,missing:array<int,string>,complete:bool} */
    public function documentState(Site $site, string $stepCode, ?object $step, Collection $documents): array
    {
        $definition = $this->definitions()[$stepCode] ?? [];
        $definition['documents'] = $this->documentDefinitions($site, $stepCode);
        $data = $this->decodeData($step->data ?? null);
        $items = [];
        $missing = [];
        $fileMissing = [];
        $informationMissing = [];

        foreach ((array) ($definition['documents'] ?? []) as $code => $docDefinition) {
            $required = (bool) ($docDefinition['required'] ?? false);
            $conditionalKey = (string) ($docDefinition['conditional_key'] ?? '');
            if ($conditionalKey !== '') {
                $required = ! empty($data[$conditionalKey]);
            }
            $minimum = max(1, (int) ($docDefinition['min'] ?? 1));
            $count = $documents->where('document_code', $code)->count();
            $complete = ! $required || $count >= $minimum;
            $label = (string) ($docDefinition['label'] ?? $code);

            $items[] = [
                'code' => $code,
                'label' => $label,
                'required' => $required,
                'minimum' => $minimum,
                'maximum' => ! empty($docDefinition['max']) ? (int) $docDefinition['max'] : null,
                'count' => $count,
                'complete' => $complete,
                'extensions' => (array) ($docDefinition['extensions'] ?? []),
            ];

            if (! $complete) {
                $missingLabel = $minimum > 1 ? $label.' (cần '.$minimum.', hiện có '.$count.')' : $label;
                $missing[] = $missingLabel;
                $fileMissing[] = $missingLabel;
            }
        }

        foreach ((array) ($definition['data_requirements'] ?? []) as $key => $label) {
            $value = $data[$key] ?? $site->getAttribute($key);
            $complete = ! ($value === null || $value === '' || $value === false || (is_numeric($value) && (float) $value <= 0));
            $items[] = [
                'code' => 'data:'.$key,
                'label' => (string) $label,
                'required' => true,
                'minimum' => 1,
                'count' => $complete ? 1 : 0,
                'complete' => $complete,
                'extensions' => [],
                'is_data_requirement' => true,
            ];
            if (! $complete) {
                $missing[] = (string) $label;
                $informationMissing[] = (string) $label;
            }
        }

        foreach ((array) ($definition['system_requirements'] ?? []) as $key => $label) {
            $complete = $this->systemRequirementComplete($site, $stepCode, $key);
            $items[] = [
                'code' => 'system:'.$key,
                'label' => (string) $label,
                'required' => true,
                'minimum' => 1,
                'count' => $complete ? 1 : 0,
                'complete' => $complete,
                'extensions' => [],
                'is_system_requirement' => true,
            ];
            if (! $complete) {
                $missing[] = (string) $label;
                $informationMissing[] = (string) $label;
            }
        }

        return [
            'items' => $items,
            'missing' => $missing,
            'file_missing' => $fileMissing,
            'information_missing' => $informationMissing,
            'files_complete' => $fileMissing === [],
            'complete' => $missing === [],
        ];
    }

    public function canManageDocumentSettings(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /** @return array<string,array<string,mixed>> */
    public function documentDefinitions(Site $site, string $stepCode): array
    {
        $items = $this->documentSettingsForEditor($site, $stepCode);
        $definitions = [];

        foreach ($items as $item) {
            if (empty($item['is_active'])) {
                continue;
            }

            $code = (string) ($item['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $definitions[$code] = [
                'label' => (string) ($item['label'] ?? $code),
                'required' => (bool) ($item['required'] ?? false),
                'min' => max(1, (int) ($item['minimum'] ?? 1)),
                'max' => ! empty($item['maximum']) ? max(1, (int) $item['maximum']) : null,
                'extensions' => array_values((array) ($item['extensions'] ?? [])),
                'responsible_group' => $item['responsible_group'] ?? null,
                'conditional_key' => $item['conditional_key'] ?? null,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ];
        }

        return $definitions;
    }

    /** @return array<int,array<string,mixed>> */
    public function documentSettingsForEditor(Site $site, string $stepCode): array
    {
        $baseDefinitions = (array) (($this->definitions()[$stepCode]['documents'] ?? []));
        $items = [];
        $order = 10;

        foreach ($baseDefinitions as $code => $definition) {
            $items[(string) $code] = [
                'code' => (string) $code,
                'label' => (string) ($definition['label'] ?? $code),
                'required' => (bool) ($definition['required'] ?? false),
                'minimum' => max(1, (int) ($definition['min'] ?? 1)),
                'maximum' => ! empty($definition['max']) ? (int) $definition['max'] : null,
                'extensions' => array_values((array) ($definition['extensions'] ?? [])),
                'responsible_group' => $definition['responsible_group'] ?? null,
                'conditional_key' => $definition['conditional_key'] ?? null,
                'sort_order' => (int) ($definition['sort_order'] ?? $order),
                'is_active' => true,
                'source' => 'default',
                'scope_key' => 'default',
            ];
            $order += 10;
        }

        if (! Schema::hasTable('project_workflow_document_settings')) {
            return $this->sortDocumentSettings($items);
        }

        $siteScope = 'site:'.(int) $site->id;
        $rows = DB::table('project_workflow_document_settings')
            ->where('step_code', $stepCode)
            ->whereIn('scope_key', ['global', $siteScope])
            ->get()
            ->sortBy(fn ($row) => (string) $row->scope_key === 'global' ? 0 : 1);

        foreach ($rows as $row) {
            $code = (string) $row->document_code;
            $current = $items[$code] ?? [
                'code' => $code,
                'label' => $code,
                'required' => false,
                'minimum' => 1,
                'maximum' => null,
                'extensions' => [],
                'responsible_group' => null,
                'conditional_key' => null,
                'sort_order' => $order,
                'is_active' => true,
                'source' => 'custom',
                'scope_key' => (string) $row->scope_key,
            ];

            $decodedExtensions = json_decode((string) ($row->extensions ?? '[]'), true);
            $items[$code] = array_merge($current, [
                'code' => $code,
                'label' => (string) $row->label,
                'required' => (bool) $row->is_required,
                'minimum' => max(1, (int) $row->min_files),
                'maximum' => ! empty($row->max_files) ? (int) $row->max_files : null,
                'extensions' => is_array($decodedExtensions) ? array_values($decodedExtensions) : [],
                'responsible_group' => $row->responsible_group ?: null,
                'conditional_key' => $row->conditional_key ?: null,
                'sort_order' => (int) $row->sort_order,
                'is_active' => (bool) $row->is_active,
                'source' => (string) $row->scope_key === 'global' ? 'global' : 'project',
                'scope_key' => (string) $row->scope_key,
            ]);
            $order += 10;
        }

        return $this->sortDocumentSettings($items);
    }

    /** @param array<string,array<string,mixed>> $items */
    private function sortDocumentSettings(array $items): array
    {
        uasort($items, function (array $left, array $right): int {
            $orderCompare = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array_values($items);
    }

    private function systemRequirementComplete(Site $site, string $stepCode, string $key): bool
    {
        if ($key === 'approved_material') {
            if (Schema::hasTable('project_material_proposals')) {
                return DB::table('project_material_proposals')
                    ->where('site_id', $site->id)
                    ->where(function ($query): void {
                        $query->whereNotNull('admin_approved_at')
                            ->orWhereIn('status', ['ADMIN_APPROVED', 'READY_FOR_EXPORT', 'EXPORTED']);
                    })
                    ->exists();
            }

            return Schema::hasTable('material_requests')
                && Schema::hasColumn('material_requests', 'site_id')
                && DB::table('material_requests')->where('site_id', $site->id)->whereIn('status', ['ADMIN_APPROVED', 'READY_FOR_EXPORT', 'EXPORTED', 'ISSUED', 'COMPLETED'])->exists();
        }

        if ($key === 'maintenance_schedule') {
            return Schema::hasTable('solar_maintenance_schedules')
                && Schema::hasColumn('solar_maintenance_schedules', 'site_id')
                && DB::table('solar_maintenance_schedules')->where('site_id', $site->id)
                    ->when(Schema::hasColumn('solar_maintenance_schedules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                    ->exists();
        }

        return false;
    }

    public function isStepUnlocked(Site $site, string $stepCode): bool
    {
        $definitions = $this->definitions();
        $definition = $definitions[$stepCode] ?? null;
        if (! is_array($definition) || ! $this->isAvailable()) {
            return $stepCode === array_key_first($definitions);
        }

        $sequence = (int) ($definition['sequence'] ?? 1);
        if ($sequence <= 1) {
            return true;
        }

        $pendingPrevious = DB::table('project_workflow_steps')
            ->where('site_id', $site->id)
            ->where('sequence', '<', $sequence)
            ->where('status', '!=', 'approved')
            ->exists();

        return ! $pendingPrevious;
    }

    public function canAssign(User $user, array $definition): bool
    {
        return $this->isAdmin($user) || $this->matchesAnyGroup($user, (array) ($definition['assign_groups'] ?? []));
    }

    public function canApproveTrack(User $user, array $track, Collection $assignments): bool
    {
        if ($assignments->contains(fn ($row) => (int) $row->user_id === (int) $user->id)) {
            return false;
        }

        return $this->isAdmin($user) || $this->matchesAnyGroup($user, (array) ($track['groups'] ?? []));
    }

    public function isAdmin(User $user): bool
    {
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }

        return $this->matchesAnyGroup($user, ['admin']);
    }

    /** @return array<int,string> */
    public function roles(User $user): array
    {
        try {
            if (method_exists($user, 'getRoleNames')) {
                return $user->getRoleNames()->map(fn ($role) => strtolower((string) $role))->values()->all();
            }
        } catch (\Throwable $exception) {
            // Continue with fallbacks.
        }

        $roles = [];
        foreach (['role', 'user_role', 'role_name'] as $column) {
            if (! empty($user->{$column})) {
                $roles[] = strtolower((string) $user->{$column});
            }
        }

        return array_values(array_unique($roles));
    }

    public function matchesAnyGroup(User $user, array $groups): bool
    {
        $roles = $this->roles($user);
        $roleGroups = (array) config('project_workflow_v2.role_groups', []);
        foreach ($groups as $group) {
            $aliases = (array) ($roleGroups[$group] ?? [$group]);
            if (array_intersect($roles, array_map('strtolower', $aliases)) !== []) {
                return true;
            }
        }

        return false;
    }

    public function validateStepCode(string $stepCode): array
    {
        $definition = $this->definitions()[$stepCode] ?? null;
        abort_unless(is_array($definition), 404, 'Bước quy trình không tồn tại.');

        return $definition;
    }

    public function stepRow(Site $site, string $stepCode, bool $lock = false): object
    {
        $this->ensure($site);
        $query = DB::table('project_workflow_steps')
            ->where('site_id', $site->id)
            ->where('step_code', $stepCode);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        abort_unless($row, 404, 'Chưa khởi tạo bước quy trình.');

        return $row;
    }

    public function recordEvent(Site $site, ?object $step, ?User $actor, string $action, ?string $from, ?string $to, ?string $note = null, array $payload = []): void
    {
        if (! Schema::hasTable('project_workflow_events')) {
            return;
        }

        DB::table('project_workflow_events')->insert([
            'site_id' => (int) $site->id,
            'workflow_step_id' => $step?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    public function refreshSubmissionState(Site $site, string $stepCode, ?User $actor = null): bool
    {
        $step = $this->stepRow($site, $stepCode);
        $activeAssignments = DB::table('project_workflow_assignments')
            ->where('workflow_step_id', $step->id)
            ->where('is_active', 1)
            ->get();

        if ($activeAssignments->isEmpty() || $activeAssignments->contains(fn ($row) => (string) $row->status !== 'submitted')) {
            return false;
        }

        $documents = DB::table('project_workflow_documents')
            ->where('workflow_step_id', $step->id)
            ->get();
        $documentState = $this->documentState($site, $stepCode, $step, $documents);
        if (! ($documentState['files_complete'] ?? $documentState['complete'])) {
            return false;
        }

        $old = (string) $step->status;
        if ($old === 'approved') {
            return false;
        }
        if ($old === 'submitted') {
            return true;
        }

        DB::transaction(function () use ($site, $step, $stepCode, $actor, $old): void {
            DB::table('project_workflow_steps')->where('id', $step->id)->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'returned_reason' => null,
                'updated_at' => now(),
            ]);

            foreach ((array) ($this->definitions()[$stepCode]['approval_tracks'] ?? []) as $type => $track) {
                DB::table('project_workflow_approvals')->updateOrInsert(
                    ['workflow_step_id' => $step->id, 'approval_type' => $type],
                    [
                        'status' => 'pending',
                        'submitted_by' => $actor?->id,
                        'submitted_at' => now(),
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $this->recordEvent($site, $step, $actor, 'step_submitted', $old, 'submitted', 'Tất cả người được giao đã nộp kết quả và hồ sơ bắt buộc đã đủ.');
            $this->syncProject($site);
        });

        return true;
    }

    public function approvalsComplete(object $step, string $stepCode): bool
    {
        $types = array_keys((array) ($this->definitions()[$stepCode]['approval_tracks'] ?? []));
        if ($types === []) {
            return true;
        }
        $approved = DB::table('project_workflow_approvals')
            ->where('workflow_step_id', $step->id)
            ->whereIn('approval_type', $types)
            ->where('status', 'approved')
            ->pluck('approval_type')
            ->unique();

        return $approved->count() === count($types);
    }

    public function syncProject(Site $site): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $steps = DB::table('project_workflow_steps')
            ->where('site_id', $site->id)
            ->orderBy('sequence')
            ->get();
        if ($steps->isEmpty()) {
            return;
        }

        $current = $steps->first(fn ($row) => (string) $row->status !== 'approved') ?: $steps->last();
        $currentCode = (string) $current->step_code;
        $progress = $this->calculateProgress($steps);
        $legacyMap = (array) config('project_workflow_v2.step_to_legacy_phase', []);
        $legacyPhase = (string) ($legacyMap[$currentCode] ?? 'design');
        $phaseStatus = match ((string) $current->status) {
            'submitted' => 'pending_approval',
            'revision' => 'revision',
            'approved' => 'approved',
            default => 'in_progress',
        };

        $update = [];
        $candidate = [
            'workflow_version' => (string) config('project_workflow_v2.version', '2.0'),
            'workflow_current_step' => $currentCode,
            'workflow_status' => $steps->every(fn ($row) => (string) $row->status === 'approved') ? 'completed' : 'active',
            'workflow_progress_percent' => $progress,
            'project_phase' => $legacyPhase,
            'stage' => $legacyPhase,
            'progress_percent' => $progress,
            'calculated_progress_percent' => $progress,
            'project_phase_status' => $phaseStatus,
            'updated_at' => now(),
        ];

        foreach ($candidate as $column => $value) {
            if ($column === 'updated_at' || Schema::hasColumn('sites', $column)) {
                $update[$column] = $value;
            }
        }

        DB::table('sites')->where('id', $site->id)->update($update);
        foreach ($update as $column => $value) {
            $site->setAttribute($column, $value);
        }
    }

    public function calculateProgress(Collection $steps): int
    {
        $definitions = $this->definitions();
        $progress = 0.0;
        foreach ($steps as $step) {
            $definition = $definitions[(string) $step->step_code] ?? null;
            if (! $definition) {
                continue;
            }
            $weight = (float) ($definition['weight'] ?? 0);
            $progress += $weight * ($this->statusPercent((string) $step->status) / 100);
        }

        return max(0, min(100, (int) round($progress)));
    }

    public function statusPercent(string $status): int
    {
        $map = (array) config('project_workflow_v2.status_progress', []);

        return max(0, min(100, (int) ($map[$status] ?? 0)));
    }

    /** @return Collection<int,User> */
    private function assigneeOptions(array $definition): Collection
    {
        $query = User::query();
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }
        $users = $query->with('roles')->orderBy('name')->limit(500)->get(['id', 'name', 'email']);
        $groups = (array) ($definition['assignee_groups'] ?? []);
        if ($groups === []) {
            return $users;
        }

        $filtered = $users->filter(fn (User $user) => $this->matchesAnyGroup($user, $groups));

        return $filtered->isNotEmpty() ? $filtered->values() : $users;
    }

    public function decodeData(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{days:int,level:string,label:string} */
    private function delayState(?Carbon $dueAt, string $status): array
    {
        if (! $dueAt || ! $dueAt->isPast() || in_array($status, ['submitted', 'approved'], true)) {
            return ['days' => 0, 'level' => 'normal', 'label' => 'Đúng hạn'];
        }

        $days = max(1, (int) floor($dueAt->diffInDays(now())));
        if ($days <= 15) {
            return ['days' => $days, 'level' => 'late_1_15', 'label' => 'Chậm 1–15 ngày'];
        }
        if ($days <= 30) {
            return ['days' => $days, 'level' => 'late_16_30', 'label' => 'Chậm 16–30 ngày'];
        }

        return ['days' => $days, 'level' => 'late_over_30', 'label' => 'Chậm trên 30 ngày · Rủi ro cao'];
    }

    private function nextAction(string $status, array $definition, bool $isOverdue): string
    {
        if ($isOverdue) {
            return 'Quá hạn: cần cập nhật lý do và ngày cam kết mới';
        }

        return match ($status) {
            'not_assigned' => 'Giao việc cho '.($definition['main_role'] ?? 'người thực hiện'),
            'assigned' => 'Người được giao xác nhận nhận việc',
            'in_progress' => 'Bổ sung hồ sơ và nộp kết quả',
            'revision' => 'Khắc phục nội dung bị trả lại',
            'submitted' => 'Người có thẩm quyền duyệt hoặc trả lại',
            'approved' => 'Mở bước tiếp theo',
            default => 'Cập nhật quy trình',
        };
    }
}
