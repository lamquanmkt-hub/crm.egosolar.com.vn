<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solar_maintenance_profiles')) {
            Schema::create('solar_maintenance_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('source_type', 40)->default('manual')->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('source_status', 100)->nullable();
                $table->string('status', 40)->default('waiting_plan')->index();
                $table->string('site_name')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 80)->nullable();
                $table->string('address', 500)->nullable();
                $table->decimal('system_kwp', 12, 2)->nullable();
                $table->text('inverter_info')->nullable();
                $table->date('handover_date')->nullable()->index();
                $table->date('warranty_start_date')->nullable();
                $table->date('warranty_end_date')->nullable()->index();
                $table->timestamp('planned_at')->nullable();
                $table->timestamp('source_completed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->unique(['source_type', 'source_id'], 'sm_profiles_source_unique');
            });
        }

        if (Schema::hasTable('solar_maintenance_schedules')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('solar_maintenance_schedules', 'maintenance_profile_id')) {
                    $table->unsignedBigInteger('maintenance_profile_id')->nullable()->index()->after('project_id');
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'report_conclusion')) {
                    $table->string('report_conclusion', 50)->nullable()->index()->after('result_note');
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'execution_finished_at')) {
                    $table->timestamp('execution_finished_at')->nullable()->after('started_at');
                }
            });
        }

        if (! Schema::hasTable('solar_maintenance_checklist_items')) {
            Schema::create('solar_maintenance_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('maintenance_schedule_id')->index();
                $table->string('item_key', 80);
                $table->string('label', 255);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_done')->default(false)->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['maintenance_schedule_id', 'item_key'], 'sm_checklist_schedule_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_maintenance_checklist_items');
        Schema::dropIfExists('solar_maintenance_profiles');

        if (Schema::hasTable('solar_maintenance_schedules')) {
            foreach (['maintenance_profile_id', 'report_conclusion', 'execution_finished_at'] as $column) {
                if (Schema::hasColumn('solar_maintenance_schedules', $column)) {
                    Schema::table('solar_maintenance_schedules', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
