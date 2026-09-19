<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_compensation_months')) {
            return;
        }

        if (! Schema::hasColumn('crm_compensation_months', 'kpi_calculate_on')) {
            Schema::table('crm_compensation_months', function (Blueprint $table): void {
                $table->string('kpi_calculate_on', 30)
                    ->default('commission_base')
                    ->after('calculate_on');
            });
        }

        DB::table('crm_compensation_months')
            ->where(function ($query): void {
                $query->whereNull('kpi_calculate_on')
                    ->orWhere('kpi_calculate_on', '');
            })
            ->update([
                'kpi_calculate_on' => 'commission_base',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (
            Schema::hasTable('crm_compensation_months')
            && Schema::hasColumn('crm_compensation_months', 'kpi_calculate_on')
        ) {
            Schema::table('crm_compensation_months', function (Blueprint $table): void {
                $table->dropColumn('kpi_calculate_on');
            });
        }
    }
};
