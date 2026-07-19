<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_work_reports')) {
            return;
        }

        if (!Schema::hasColumn('sales_work_reports', 'customer_stage')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->string('customer_stage', 40)->nullable()->after('customer_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_work_reports')) {
            return;
        }

        if (Schema::hasColumn('sales_work_reports', 'customer_stage')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->dropColumn('customer_stage');
            });
        }
    }
};
