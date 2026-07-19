<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_daily_kpis', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_daily_kpis', 'extra_metrics')) {
                $table->longText('extra_metrics')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('sales_daily_kpis', 'manager_note')) {
                $table->text('manager_note')->nullable()->after('extra_metrics');
            }

            if (!Schema::hasColumn('sales_daily_kpis', 'approval_status')) {
                $table->string('approval_status', 30)->default('draft')->after('manager_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_daily_kpis', function (Blueprint $table) {
            if (Schema::hasColumn('sales_daily_kpis', 'approval_status')) {
                $table->dropColumn('approval_status');
            }

            if (Schema::hasColumn('sales_daily_kpis', 'manager_note')) {
                $table->dropColumn('manager_note');
            }

            if (Schema::hasColumn('sales_daily_kpis', 'extra_metrics')) {
                $table->dropColumn('extra_metrics');
            }
        });
    }
};
