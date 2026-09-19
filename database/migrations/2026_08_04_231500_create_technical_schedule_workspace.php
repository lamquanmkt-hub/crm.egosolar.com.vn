<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_schedule_events')) {
            Schema::create('technical_schedule_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('event_type', 40)->index();
                $table->string('source_type', 40)->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->string('title');
                $table->string('address', 700)->nullable();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->nullable()->index();
                $table->boolean('all_day')->default(false);
                $table->string('status', 40)->default('scheduled')->index();
                $table->string('priority', 30)->default('normal')->index();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->unique(['source_type', 'source_id', 'event_type'], 'tech_events_source_unique');
            });
        }

        if (! Schema::hasTable('technical_schedule_event_users')) {
            Schema::create('technical_schedule_event_users', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('assignment_role', 30)->default('member')->index();
                $table->string('status', 30)->default('assigned')->index();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('check_in_at')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['event_id', 'user_id'], 'tech_event_users_unique');
            });
        }

        if (Schema::hasTable('solar_maintenance_schedules')) {
            foreach ([
                'project_id' => fn (Blueprint $table) => $table->unsignedBigInteger('project_id')->nullable()->index(),
                'scheduled_start_at' => fn (Blueprint $table) => $table->dateTime('scheduled_start_at')->nullable()->index(),
                'scheduled_end_at' => fn (Blueprint $table) => $table->dateTime('scheduled_end_at')->nullable()->index(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('solar_maintenance_schedules', $column)) {
                    Schema::table('solar_maintenance_schedules', $definition);
                }
            }
        }

        $this->backfillMaintenanceProjectLinks();
        $this->backfillProjectEvents();
        $this->backfillMaintenanceEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_schedule_event_users');
        Schema::dropIfExists('technical_schedule_events');

        if (Schema::hasTable('solar_maintenance_schedules')) {
            foreach (['project_id', 'scheduled_start_at', 'scheduled_end_at'] as $column) {
                if (Schema::hasColumn('solar_maintenance_schedules', $column)) {
                    Schema::table('solar_maintenance_schedules', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }

    private function backfillMaintenanceProjectLinks(): void
    {
        if (! Schema::hasTable('solar_maintenance_schedules')
            || ! Schema::hasTable('project_test_projects')
            || ! Schema::hasColumn('project_test_projects', 'legacy_site_id')) {
            return;
        }

        DB::table('solar_maintenance_schedules')
            ->whereNull('project_id')
            ->whereNotNull('site_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $projectId = DB::table('project_test_projects')
                        ->where('legacy_site_id', $row->site_id)
                        ->value('id');

                    if ($projectId) {
                        DB::table('solar_maintenance_schedules')->where('id', $row->id)->update([
                            'project_id' => (int) $projectId,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    private function backfillProjectEvents(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        DB::table('project_test_projects')->orderBy('id')->chunkById(100, function ($projects): void {
            foreach ($projects as $project) {
                $survey = Schema::hasTable('project_test_surveys')
                    ? DB::table('project_test_surveys')->where('project_id', $project->id)->first()
                    : null;

                $surveyAt = $survey->scheduled_at ?? $project->survey_confirmed_at ?? $project->proposed_survey_at ?? null;
                if ($surveyAt) {
                    $eventId = $this->upsertEvent([
                        'company_id' => $project->company_id ?? null,
                        'event_type' => 'survey',
                        'source_type' => 'project',
                        'source_id' => $project->id,
                        'project_id' => $project->id,
                        'title' => 'Khảo sát · '.$project->code.' · '.$project->name,
                        'address' => $project->address ?? null,
                        'starts_at' => Carbon::parse($surveyAt),
                        'ends_at' => Carbon::parse($surveyAt)->addHours(2),
                        'all_day' => 0,
                        'status' => $this->projectEventStatus((string) $project->status, 'survey'),
                        'priority' => $project->priority ?? 'normal',
                        'note' => $project->note ?? null,
                        'created_by' => $project->created_by ?? null,
                    ]);

                    $surveyUserId = $survey->surveyed_by ?? $project->lead_technician_id ?? null;
                    if ($surveyUserId) {
                        $this->upsertEventUser($eventId, (int) $surveyUserId, 'leader');
                    }
                }

                $installationAt = $project->installation_confirmed_at ?? $project->proposed_installation_at ?? null;
                if ($installationAt) {
                    $eventId = $this->upsertEvent([
                        'company_id' => $project->company_id ?? null,
                        'event_type' => 'installation',
                        'source_type' => 'project',
                        'source_id' => $project->id,
                        'project_id' => $project->id,
                        'title' => 'Thi công · '.$project->code.' · '.$project->name,
                        'address' => $project->address ?? null,
                        'starts_at' => Carbon::parse($installationAt),
                        'ends_at' => Carbon::parse($installationAt)->addHours(9),
                        'all_day' => 0,
                        'status' => $this->projectEventStatus((string) $project->status, 'installation'),
                        'priority' => $project->priority ?? 'normal',
                        'note' => $project->note ?? null,
                        'created_by' => $project->created_by ?? null,
                    ]);

                    $userIds = collect();
                    if (Schema::hasTable('project_test_assignments')) {
                        $userIds = DB::table('project_test_assignments')->where('project_id', $project->id)->pluck('user_id');
                    }
                    if ($project->lead_technician_id) {
                        $userIds->push($project->lead_technician_id);
                    }
                    foreach ($userIds->filter()->unique()->values() as $index => $userId) {
                        $this->upsertEventUser($eventId, (int) $userId, $index === 0 ? 'leader' : 'member');
                    }
                }
            }
        });
    }

    private function backfillMaintenanceEvents(): void
    {
        if (! Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        DB::table('solar_maintenance_schedules')->orderBy('id')->chunkById(100, function ($schedules): void {
            foreach ($schedules as $schedule) {
                if (! $schedule->scheduled_date && ! $schedule->scheduled_start_at) {
                    continue;
                }

                $start = $schedule->scheduled_start_at
                    ? Carbon::parse($schedule->scheduled_start_at)
                    : Carbon::parse($schedule->scheduled_date)->setTime(8, 30);
                $end = $schedule->scheduled_end_at
                    ? Carbon::parse($schedule->scheduled_end_at)
                    : $start->copy()->addHours(3);

                DB::table('solar_maintenance_schedules')->where('id', $schedule->id)->update([
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'updated_at' => now(),
                ]);

                $eventId = $this->upsertEvent([
                    'company_id' => $schedule->company_id ?? null,
                    'event_type' => 'maintenance',
                    'source_type' => 'maintenance',
                    'source_id' => $schedule->id,
                    'project_id' => $schedule->project_id ?? null,
                    'title' => 'Bảo trì · '.($schedule->schedule_code ?: 'BT-'.$schedule->id).' · '.($schedule->site_name ?: $schedule->customer_name ?: 'Công trình'),
                    'address' => $schedule->address ?? null,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'all_day' => 0,
                    'status' => $schedule->status ?: 'scheduled',
                    'priority' => $schedule->priority ?: 'normal',
                    'note' => $schedule->technical_note ?: $schedule->issue_note ?: null,
                    'created_by' => $schedule->created_by ?? null,
                ]);

                $userIds = collect();
                if (Schema::hasTable('solar_maintenance_assignees')) {
                    $userIds = DB::table('solar_maintenance_assignees')->where('maintenance_schedule_id', $schedule->id)->pluck('user_id');
                }
                $decoded = json_decode((string) ($schedule->assigned_user_ids ?? ''), true);
                if (is_array($decoded)) {
                    $userIds = $userIds->merge($decoded);
                }
                if ($schedule->assigned_to) {
                    $userIds->push($schedule->assigned_to);
                }
                foreach ($userIds->filter()->unique()->values() as $index => $userId) {
                    $this->upsertEventUser($eventId, (int) $userId, $index === 0 ? 'leader' : 'member');
                }
            }
        });
    }

    private function upsertEvent(array $data): int
    {
        $key = [
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'event_type' => $data['event_type'],
        ];

        $existing = DB::table('technical_schedule_events')->where($key)->value('id');
        $payload = array_merge($data, ['updated_at' => now()]);

        if ($existing) {
            DB::table('technical_schedule_events')->where('id', $existing)->update($payload);
            return (int) $existing;
        }

        $payload['created_at'] = now();
        return (int) DB::table('technical_schedule_events')->insertGetId($payload);
    }

    private function upsertEventUser(int $eventId, int $userId, string $role): void
    {
        DB::table('technical_schedule_event_users')->updateOrInsert(
            ['event_id' => $eventId, 'user_id' => $userId],
            [
                'assignment_role' => $role,
                'status' => 'assigned',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function projectEventStatus(string $status, string $type): string
    {
        if ($status === 'cancelled') {
            return 'cancelled';
        }
        if ($type === 'survey' && ! in_array($status, ['request_new', 'request_accepted', 'survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress'], true)) {
            return 'completed';
        }
        if ($type === 'installation' && in_array($status, ['acceptance_pending', 'warranty_active', 'completed'], true)) {
            return 'completed';
        }
        return in_array($status, ['survey_in_progress', 'installing'], true) ? 'in_progress' : 'scheduled';
    }
};
