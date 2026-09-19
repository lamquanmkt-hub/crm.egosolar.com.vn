<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // V13 có thể đã dừng giữa chừng do tên index tự sinh của MySQL > 64 ký tự.
        // Vì vậy migration V13.1 phải idempotent: cột nào đã có thì giữ nguyên,
        // cột nào thiếu thì bổ sung, sau đó tạo index bằng tên ngắn an toàn.
        if (Schema::hasTable('solar_maintenance_schedules')) {
            $tableName = 'solar_maintenance_schedules';

            $columns = [
                'assignment_approval_status' => fn (Blueprint $table) => $table->string('assignment_approval_status', 30)->default('not_required'),
                'assignment_submitted_at' => fn (Blueprint $table) => $table->timestamp('assignment_submitted_at')->nullable(),
                'assignment_submitted_by' => fn (Blueprint $table) => $table->unsignedBigInteger('assignment_submitted_by')->nullable(),
                'assignment_approved_at' => fn (Blueprint $table) => $table->timestamp('assignment_approved_at')->nullable(),
                'assignment_approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('assignment_approved_by')->nullable(),
                'assignment_revision_requested_at' => fn (Blueprint $table) => $table->timestamp('assignment_revision_requested_at')->nullable(),
                'assignment_revision_requested_by' => fn (Blueprint $table) => $table->unsignedBigInteger('assignment_revision_requested_by')->nullable(),
                'assignment_approval_note' => fn (Blueprint $table) => $table->text('assignment_approval_note')->nullable(),
                'external_labor_enabled' => fn (Blueprint $table) => $table->boolean('external_labor_enabled')->default(false),
                'external_labor_name' => fn (Blueprint $table) => $table->string('external_labor_name', 255)->nullable(),
                'external_labor_phone' => fn (Blueprint $table) => $table->string('external_labor_phone', 80)->nullable(),
                'external_labor_headcount' => fn (Blueprint $table) => $table->unsignedInteger('external_labor_headcount')->nullable(),
                'external_labor_total_cost' => fn (Blueprint $table) => $table->decimal('external_labor_total_cost', 15, 2)->default(0),
                'external_labor_advance_amount' => fn (Blueprint $table) => $table->decimal('external_labor_advance_amount', 15, 2)->default(0),
                'external_labor_bank_info' => fn (Blueprint $table) => $table->text('external_labor_bank_info')->nullable(),
                'external_labor_note' => fn (Blueprint $table) => $table->text('external_labor_note')->nullable(),
                'external_labor_payment_request_id' => fn (Blueprint $table) => $table->unsignedBigInteger('external_labor_payment_request_id')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }

            $this->ensureIndex($tableName, 'assignment_approval_status', 'sms_asg_approval_status_idx');
            $this->ensureIndex($tableName, 'assignment_submitted_by', 'sms_asg_submitted_by_idx');
            $this->ensureIndex($tableName, 'assignment_approved_by', 'sms_asg_approved_by_idx');
            $this->ensureIndex($tableName, 'assignment_revision_requested_by', 'sms_asg_revision_by_idx');
            $this->ensureIndex($tableName, 'external_labor_enabled', 'sms_ext_labor_enabled_idx');
            $this->ensureIndex($tableName, 'external_labor_payment_request_id', 'sms_ext_payreq_idx');
        }

        if (Schema::hasTable('payment_requests')) {
            if (! Schema::hasColumn('payment_requests', 'maintenance_schedule_id')) {
                Schema::table('payment_requests', function (Blueprint $table): void {
                    $table->unsignedBigInteger('maintenance_schedule_id')->nullable();
                });
            }

            $this->ensureIndex('payment_requests', 'maintenance_schedule_id', 'pr_maint_schedule_idx');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_requests')
            && Schema::hasColumn('payment_requests', 'maintenance_schedule_id')) {
            Schema::table('payment_requests', function (Blueprint $table): void {
                $table->dropColumn('maintenance_schedule_id');
            });
        }

        if (Schema::hasTable('solar_maintenance_schedules')) {
            $columns = [
                'assignment_approval_status',
                'assignment_submitted_at',
                'assignment_submitted_by',
                'assignment_approved_at',
                'assignment_approved_by',
                'assignment_revision_requested_at',
                'assignment_revision_requested_by',
                'assignment_approval_note',
                'external_labor_enabled',
                'external_labor_name',
                'external_labor_phone',
                'external_labor_headcount',
                'external_labor_total_cost',
                'external_labor_advance_amount',
                'external_labor_bank_info',
                'external_labor_note',
                'external_labor_payment_request_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('solar_maintenance_schedules', $column)) {
                    Schema::table('solar_maintenance_schedules', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }

    private function ensureIndex(string $tableName, string $column, string $indexName): void
    {
        if (! Schema::hasColumn($tableName, $column) || $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $indexName): void {
            $table->index($column, $indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
