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

        if (!Schema::hasColumn('sales_work_reports', 'call_1_at')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->dateTime('call_1_at')->nullable()->after('first_call_at');
            });
        }

        if (!Schema::hasColumn('sales_work_reports', 'call_1_result')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->string('call_1_result', 30)->nullable()->after('call_1_at');
            });
        }

        if (!Schema::hasColumn('sales_work_reports', 'call_2_at')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->dateTime('call_2_at')->nullable()->after('call_1_result');
            });
        }

        if (!Schema::hasColumn('sales_work_reports', 'call_2_result')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->string('call_2_result', 30)->nullable()->after('call_2_at');
            });
        }

        if (!Schema::hasColumn('sales_work_reports', 'quote_status')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->string('quote_status', 30)->nullable()->after('quoted_products');
            });
        }

        if (!Schema::hasColumn('sales_work_reports', 'quote_sent_at')) {
            Schema::table('sales_work_reports', function (Blueprint $table) {
                $table->dateTime('quote_sent_at')->nullable()->after('quote_status');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_work_reports')) {
            return;
        }

        Schema::table('sales_work_reports', function (Blueprint $table) {
            foreach (['call_1_at', 'call_1_result', 'call_2_at', 'call_2_result', 'quote_status', 'quote_sent_at'] as $col) {
                if (Schema::hasColumn('sales_work_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
