<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\ProjectTest\Project;
use App\Models\SolarMaintenanceSchedule;
use App\Models\Technical\TechnicalScheduleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TechnicalScheduleSyncService
{
    public function ready(): bool
    {
        return Schema::hasTable('technical_schedule_events')
            && Schema::hasTable('technical_schedule_event_users');
    }

    public function syncProject(Project $project): void
    {
        if (! $this->ready() || ! Schema::hasTable('project_test_projects')) {
            return;
        }

        $project->loadMissing(['survey', 'assignments']);

        $surveyAt = $project->survey?->scheduled_at
            ?: $project->survey_confirmed_at
            ?: $project->proposed_survey_at;

        $surveyUsers = collect([
            $project->survey?->surveyed_by,
            $project->lead_technician_id,
        ])->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        $this->syncSourceEvent(
            sourceType: 'project',
            sourceId: (int) $project->id,
            eventType: 'survey',
            projectId: (int) $project->id,
            title: 'Khảo sát · '.$project->code.' · '.$project->name,
            address: (string) ($project->address ?? ''),
            startsAt: $surveyAt,
            endsAt: $surveyAt ? Carbon::parse($surveyAt)->copy()->addHours(2) : null,
            status: $this->projectEventStatus((string) $project->status, 'survey'),
            priority: (string) ($project->priority ?: 'normal'),
            note: (string) ($project->note ?? ''),
            companyId: (int) ($project->company_id ?? 0),
            createdBy: (int) ($project->created_by ?? 0),
            users: $surveyUsers,
        );

        $installationAt = $project->installation_confirmed_at ?: $project->proposed_installation_at;
        $installationUsers = $project->assignments
            ->pluck('user_id')
            ->push($project->lead_technician_id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $this->syncSourceEvent(
            sourceType: 'project',
            sourceId: (int) $project->id,
            eventType: 'installation',
            projectId: (int) $project->id,
            title: 'Thi công · '.$project->code.' · '.$project->name,
            address: (string) ($project->address ?? ''),
            startsAt: $installationAt,
            endsAt: $installationAt ? Carbon::parse($installationAt)->copy()->addHours(9) : null,
            status: $this->projectEventStatus((string) $project->status, 'installation'),
            priority: (string) ($project->priority ?: 'normal'),
            note: (string) ($project->note ?? ''),
            companyId: (int) ($project->company_id ?? 0),
            createdBy: (int) ($project->created_by ?? 0),
            users: $installationUsers,
        );
    }

    public function removeProject(Project|int $project): void
    {
        if (! $this->ready()) {
            return;
        }

        $projectId = $project instanceof Project ? (int) $project->id : (int) $project;
        TechnicalScheduleEvent::query()
            ->where('source_type', 'project')
            ->where('source_id', $projectId)
            ->delete();
    }

    public function syncMaintenance(SolarMaintenanceSchedule $schedule): void
    {
        if (! $this->ready() || ! Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        $schedule->loadMissing('assignees');

        $startsAt = $schedule->scheduled_start_at ?? null;
        if (! $startsAt && $schedule->scheduled_date) {
            $startsAt = Carbon::parse($schedule->scheduled_date)->setTime(8, 30);
        }

        $endsAt = $schedule->scheduled_end_at ?? null;
        if (! $endsAt && $startsAt) {
            $endsAt = Carbon::parse($startsAt)->copy()->addHours(3);
        }

        $userIds = $schedule->assignees->pluck('user_id')
            ->push($schedule->assigned_to)
            ->merge(is_array($schedule->assigned_user_ids) ? $schedule->assigned_user_ids : [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $this->syncSourceEvent(
            sourceType: 'maintenance',
            sourceId: (int) $schedule->id,
            eventType: 'maintenance',
            projectId: (int) ($schedule->project_id ?? 0) ?: null,
            title: 'Bảo trì · '.($schedule->schedule_code ?: 'BT-'.$schedule->id).' · '.($schedule->site_name ?: $schedule->customer_name ?: 'Công trình'),
            address: (string) ($schedule->address ?? ''),
            startsAt: $startsAt,
            endsAt: $endsAt,
            status: (string) ($schedule->status ?: 'scheduled'),
            priority: (string) ($schedule->priority ?: 'normal'),
            note: (string) ($schedule->technical_note ?: $schedule->issue_note ?: ''),
            companyId: (int) ($schedule->company_id ?? 0),
            createdBy: (int) ($schedule->created_by ?? 0),
            users: $userIds,
        );
    }

    public function removeMaintenance(SolarMaintenanceSchedule|int $schedule): void
    {
        if (! $this->ready()) {
            return;
        }

        $scheduleId = $schedule instanceof SolarMaintenanceSchedule ? (int) $schedule->id : (int) $schedule;
        TechnicalScheduleEvent::query()
            ->where('source_type', 'maintenance')
            ->where('source_id', $scheduleId)
            ->delete();
    }

    public function ensureInitialMaintenance(Project $project, Carbon $acceptedAt, ?int $creatorId = null): ?SolarMaintenanceSchedule
    {
        if (! Schema::hasTable('solar_maintenance_schedules')
            || ! Schema::hasColumn('solar_maintenance_schedules', 'project_id')) {
            return null;
        }

        $nextDate = $acceptedAt->copy()->addMonths(6)->startOfDay();
        $lookup = ['project_id' => $project->id, 'type' => 'periodic', 'scheduled_date' => $nextDate->toDateString()];

        $schedule = SolarMaintenanceSchedule::query()->firstOrNew($lookup);
        $schedule->fill([
            'company_id' => $project->company_id,
            'site_id' => $project->legacy_site_id ?? null,
            'customer_name' => $project->contact_name,
            'site_name' => $project->name,
            'address' => $project->address,
            'status' => $project->lead_technician_id ? 'assigned' : 'unassigned',
            'priority' => $project->priority ?: 'normal',
            'scheduled_start_at' => $nextDate->copy()->setTime(8, 30),
            'scheduled_end_at' => $nextDate->copy()->setTime(11, 30),
            'assigned_to' => $project->lead_technician_id,
            'assigned_user_ids' => $project->lead_technician_id ? [(int) $project->lead_technician_id] : [],
            'system_kwp' => $project->estimated_kwp,
            'technical_note' => 'Lịch bảo trì đầu tiên được tạo tự động sau nghiệm thu công trình '.$project->code.'.',
            'created_by' => $creatorId,
        ]);
        $schedule->save();

        if (Schema::hasTable('solar_maintenance_assignees') && $project->lead_technician_id) {
            DB::table('solar_maintenance_assignees')->updateOrInsert(
                ['maintenance_schedule_id' => $schedule->id, 'user_id' => $project->lead_technician_id],
                [
                    'role' => 'leader',
                    'assignment_role' => 'leader',
                    'is_leader' => 1,
                    'assigned_by' => $creatorId,
                    'assigned_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->syncMaintenance($schedule->fresh());

        return $schedule;
    }

    public function syncAll(?int $companyId = null): array
    {
        $result = ['projects' => 0, 'maintenance' => 0];

        if (Schema::hasTable('project_test_projects')) {
            Project::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->with(['survey', 'assignments'])
                ->orderBy('id')
                ->chunkById(100, function ($projects) use (&$result): void {
                    foreach ($projects as $project) {
                        $this->syncProject($project);
                        $result['projects']++;
                    }
                });
        }

        if (Schema::hasTable('solar_maintenance_schedules')) {
            SolarMaintenanceSchedule::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->with('assignees')
                ->orderBy('id')
                ->chunkById(100, function ($schedules) use (&$result): void {
                    foreach ($schedules as $schedule) {
                        $this->syncMaintenance($schedule);
                        $result['maintenance']++;
                    }
                });
        }

        return $result;
    }

    private function syncSourceEvent(
        string $sourceType,
        int $sourceId,
        string $eventType,
        ?int $projectId,
        string $title,
        string $address,
        mixed $startsAt,
        mixed $endsAt,
        string $status,
        string $priority,
        string $note,
        int $companyId,
        int $createdBy,
        Collection $users,
    ): void {
        $query = TechnicalScheduleEvent::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('event_type', $eventType);

        if (! $startsAt) {
            $query->delete();
            return;
        }

        $event = TechnicalScheduleEvent::query()->updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'event_type' => $eventType,
            ],
            [
                'company_id' => $companyId ?: null,
                'project_id' => $projectId,
                'title' => $title,
                'address' => $address ?: null,
                'starts_at' => Carbon::parse($startsAt),
                'ends_at' => $endsAt ? Carbon::parse($endsAt) : null,
                'all_day' => false,
                'status' => $status,
                'priority' => $priority,
                'note' => $note ?: null,
                'created_by' => $createdBy ?: null,
            ]
        );

        $ids = $users->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        DB::table('technical_schedule_event_users')
            ->where('event_id', $event->id)
            ->whereNotIn('user_id', $ids->all() ?: [0])
            ->delete();

        foreach ($ids as $index => $userId) {
            DB::table('technical_schedule_event_users')->updateOrInsert(
                ['event_id' => $event->id, 'user_id' => $userId],
                [
                    'assignment_role' => $index === 0 ? 'leader' : 'member',
                    'status' => 'assigned',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function projectEventStatus(string $projectStatus, string $eventType): string
    {
        if (in_array($projectStatus, ['cancelled'], true)) {
            return 'cancelled';
        }

        if ($eventType === 'survey') {
            if (in_array($projectStatus, ['survey_in_progress'], true)) {
                return 'in_progress';
            }
            if (! in_array($projectStatus, ['request_new', 'request_accepted', 'survey_pending', 'survey_reschedule', 'survey_confirmed'], true)) {
                return 'completed';
            }
        }

        if ($eventType === 'installation') {
            if ($projectStatus === 'installing') {
                return 'in_progress';
            }
            if (in_array($projectStatus, ['acceptance_pending', 'warranty_active', 'completed'], true)) {
                return 'completed';
            }
        }

        return 'scheduled';
    }
}
