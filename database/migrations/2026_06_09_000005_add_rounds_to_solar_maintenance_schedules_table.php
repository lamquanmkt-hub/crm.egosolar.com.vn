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
                if (!Schema::hasColumn('solar_maintenance_schedules', 'round_no')) {
                    $table->unsignedInteger('round_no')->default(1)->after('priority');
                }

                if (!Schema::hasColumn('solar_maintenance_schedules', 'total_rounds')) {
                    $table->unsignedInteger('total_rounds')->default(1)->after('round_no');
                }

                if (!Schema::hasColumn('solar_maintenance_schedules', 'round_group')) {
                    $table->string('round_group', 80)->nullable()->index()->after('total_rounds');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_maintenance_schedules')) {
            Schema::table('solar_maintenance_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('solar_maintenance_schedules', 'round_no')) {
                    $table->dropColumn('round_no');
                }

                if (Schema::hasColumn('solar_maintenance_schedules', 'total_rounds')) {
                    $table->dropColumn('total_rounds');
                }

                if (Schema::hasColumn('solar_maintenance_schedules', 'round_group')) {
                    $table->dropColumn('round_group');
                }
            });
        }
    }
};
