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
                if (!Schema::hasColumn('solar_maintenance_schedules', 'assigned_user_ids')) {
                    $table->text('assigned_user_ids')->nullable()->after('assigned_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_maintenance_schedules')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('solar_maintenance_schedules', 'assigned_user_ids')) {
                    $table->dropColumn('assigned_user_ids');
                }
            });
        }
    }
};
