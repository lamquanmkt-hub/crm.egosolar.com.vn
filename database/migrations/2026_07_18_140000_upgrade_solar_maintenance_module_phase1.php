<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        $this->addScheduleColumns();
        $this->createAssigneesTable();
        $this->createStatusHistoryTable();
        $this->createAuditLogTable();
        $this->backfillSchedules();
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_maintenance_audit_logs');
        Schema::dropIfExists('solar_maintenance_status_histories');
        Schema::dropIfExists('solar_maintenance_assignees');

        if (!Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        $columns = [
            'company_id',
            'schedule_code',
            'started_at',
            'completed_at',
            'cancelled_at',
            'cancellation_reason',
            'reopened_at',
            'reopened_by',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('solar_maintenance_schedules', $column)) {
                Schema::table('solar_maintenance_schedules', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function addScheduleColumns(): void
    {
        if (!Schema::hasColumn('solar_maintenance_schedules', 'company_id')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('site_id');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'schedule_code')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->string('schedule_code', 40)->nullable()->unique()->after('id');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'started_at')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('scheduled_date');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'completed_at')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('completed_date');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'cancelled_at')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'cancellation_reason')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'reopened_at')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->timestamp('reopened_at')->nullable()->after('cancellation_reason');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'reopened_by')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('reopened_by')->nullable()->index()->after('reopened_at');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_schedules', 'deleted_at')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    private function createAssigneesTable(): void
    {
        if (Schema::hasTable('solar_maintenance_assignees')) {
            return;
        }

        Schema::create('solar_maintenance_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_schedule_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 30)->default('member')->index();
            $table->unsignedBigInteger('assigned_by')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['maintenance_schedule_id', 'user_id'], 'sm_assignees_schedule_user_unique');
        });
    }

    private function createStatusHistoryTable(): void
    {
        if (Schema::hasTable('solar_maintenance_status_histories')) {
            return;
        }

        Schema::create('solar_maintenance_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_schedule_id')->index();
            $table->string('from_status', 80)->nullable()->index();
            $table->string('to_status', 80)->index();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->index();
            $table->json('metadata')->nullable();
        });
    }

    private function createAuditLogTable(): void
    {
        if (Schema::hasTable('solar_maintenance_audit_logs')) {
            return;
        }

        Schema::create('solar_maintenance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_schedule_id')->index();
            $table->string('action', 80)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    private function backfillSchedules(): void
    {
        DB::table('solar_maintenance_schedules')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $update = [];

                    if (empty($row->schedule_code)) {
                        $year = $row->created_at ? date('Y', strtotime((string) $row->created_at)) : date('Y');
                        $update['schedule_code'] = sprintf('SM-%s-%06d', $year, $row->id);
                    }

                    if (empty($row->company_id) && !empty($row->site_id) && Schema::hasTable('sites')) {
                        $companyId = DB::table('sites')->where('id', $row->site_id)->value('company_id');
                        if ($companyId) {
                            $update['company_id'] = (int) $companyId;
                        }
                    }

                    if (($row->status ?? null) === 'processing') {
                        $update['status'] = 'in_progress';
                    }

                    if (($row->status ?? null) === 'completed' && empty($row->completed_at)) {
                        $date = $row->completed_date ?: $row->updated_at ?: now();
                        $update['completed_at'] = $date;
                    }

                    if ($update) {
                        DB::table('solar_maintenance_schedules')->where('id', $row->id)->update($update);
                    }

                    $ids = [];
                    $decoded = json_decode((string) ($row->assigned_user_ids ?? ''), true);

                    if (is_array($decoded)) {
                        $ids = $decoded;
                    }

                    if (!$ids && !empty($row->assigned_to)) {
                        $ids = [(int) $row->assigned_to];
                    }

                    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

                    foreach ($ids as $index => $userId) {
                        DB::table('solar_maintenance_assignees')->insertOrIgnore([
                            'maintenance_schedule_id' => $row->id,
                            'user_id' => $userId,
                            'role' => $index === 0 ? 'leader' : 'member',
                            'assigned_by' => $row->created_by ?: null,
                            'assigned_at' => $row->created_at ?: now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $hasHistory = DB::table('solar_maintenance_status_histories')
                        ->where('maintenance_schedule_id', $row->id)
                        ->exists();

                    if (!$hasHistory) {
                        DB::table('solar_maintenance_status_histories')->insert([
                            'maintenance_schedule_id' => $row->id,
                            'from_status' => null,
                            'to_status' => ($row->status ?? 'scheduled') === 'processing'
                                ? 'in_progress'
                                : ($row->status ?? 'scheduled'),
                            'reason' => 'Dữ liệu được chuyển từ module cũ',
                            'note' => null,
                            'changed_by' => $row->created_by ?: null,
                            'changed_at' => $row->created_at ?: now(),
                            'metadata' => json_encode(['source' => 'legacy_backfill'], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            });
    }
};
