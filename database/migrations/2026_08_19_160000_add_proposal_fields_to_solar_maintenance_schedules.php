<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solar_maintenance_schedules')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('solar_maintenance_schedules', 'plan_checklist')) {
                    $table->json('plan_checklist')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'external_labor_enabled')) {
                    $table->boolean('external_labor_enabled')->default(false);
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'external_labor_name')) {
                    $table->string('external_labor_name')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'external_labor_contact')) {
                    $table->string('external_labor_contact')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'external_labor_estimated_cost')) {
                    $table->decimal('external_labor_estimated_cost', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'execution_fault_note')) {
                    $table->text('execution_fault_note')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'incident_kind')) {
                    $table->string('incident_kind', 40)->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'incident_material_note')) {
                    $table->text('incident_material_note')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'incident_replacement_reason')) {
                    $table->text('incident_replacement_reason')->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'incident_estimated_cost')) {
                    $table->decimal('incident_estimated_cost', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'completion_actual_cost')) {
                    $table->decimal('completion_actual_cost', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('solar_maintenance_schedules', 'completion_state')) {
                    $table->string('completion_state', 30)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_maintenance_schedules')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                $table->dropColumn([
                    'plan_checklist', 'external_labor_enabled', 'external_labor_name',
                    'external_labor_contact', 'external_labor_estimated_cost',
                    'execution_fault_note', 'incident_kind', 'incident_material_note',
                    'incident_replacement_reason', 'incident_estimated_cost',
                    'completion_actual_cost', 'completion_state',
                ]);
            });
        }
    }
};
