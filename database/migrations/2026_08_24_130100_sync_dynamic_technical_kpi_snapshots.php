<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_kpi_payroll_items')) {
            return;
        }

        Schema::table('technical_kpi_payroll_items', function (Blueprint $table) {
            if (! Schema::hasColumn('technical_kpi_payroll_items', 'kpi_definition_id')) {
                $table->unsignedBigInteger('kpi_definition_id')->nullable()->after('payroll_id')->index();
            }

            if (! Schema::hasColumn('technical_kpi_payroll_items', 'calc_type')) {
                $table->string('calc_type', 50)->nullable()->after('unit_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('technical_kpi_payroll_items')) {
            return;
        }

        Schema::table('technical_kpi_payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('technical_kpi_payroll_items', 'kpi_definition_id')) {
                $table->dropIndex(['kpi_definition_id']);
                $table->dropColumn('kpi_definition_id');
            }
            if (Schema::hasColumn('technical_kpi_payroll_items', 'calc_type')) {
                $table->dropColumn('calc_type');
            }
        });
    }
};
