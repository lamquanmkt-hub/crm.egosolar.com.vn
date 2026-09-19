<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_payroll_kpi_items') && ! Schema::hasColumn('technical_payroll_kpi_items', 'source_code')) {
            Schema::table('technical_payroll_kpi_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('technical_payroll_kpi_items', 'source_code')) {
                    $table->string('source_code', 60)->default('manual')->after('calc_type')->index();
                }
            });

            // Đồng bộ 5 tiêu chí Solar hiện có. Các dòng thêm mới vẫn mặc định nhập tay.
            $map = [
                1 => 'project_timeline',
                2 => 'project_quality',
                3 => 'project_material_waste',
                4 => 'project_hse',
                5 => 'project_evn_app',
            ];
            foreach ($map as $sort => $source) {
                DB::table('technical_payroll_kpi_items')
                    ->where('sort_order', $sort)
                    ->where('is_enabled', 1)
                    ->update(['source_code' => $source]);
            }
        }

        if (Schema::hasTable('technical_kpi_payroll_items') && ! Schema::hasColumn('technical_kpi_payroll_items', 'source_code')) {
            Schema::table('technical_kpi_payroll_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('technical_kpi_payroll_items', 'source_code')) {
                    $table->string('source_code', 60)->default('manual')->after('calc_type')->index();
                }
            });
        }

        if (! Schema::hasTable('technical_kpi_project_evidence')) {
            Schema::create('technical_kpi_project_evidence', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('payroll_month', 7)->index();
                $table->boolean('timeline_excluded')->default(false);
                $table->text('timeline_exclusion_reason')->nullable();
                $table->boolean('quality_first_pass')->nullable();
                $table->decimal('material_waste_percent', 8, 2)->nullable();
                $table->boolean('hse_pass')->nullable();
                $table->boolean('evn_app_required')->nullable();
                $table->boolean('evn_app_completed')->nullable();
                $table->decimal('penalty_points', 8, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->dateTime('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['site_id', 'user_id', 'payroll_month'], 'tech_kpi_project_user_month_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_kpi_project_evidence');
        if (Schema::hasTable('technical_kpi_payroll_items') && Schema::hasColumn('technical_kpi_payroll_items', 'source_code')) {
            Schema::table('technical_kpi_payroll_items', function (Blueprint $table): void {
                $table->dropColumn('source_code');
            });
        }
        if (Schema::hasTable('technical_payroll_kpi_items') && Schema::hasColumn('technical_payroll_kpi_items', 'source_code')) {
            Schema::table('technical_payroll_kpi_items', function (Blueprint $table): void {
                $table->dropColumn('source_code');
            });
        }
    }
};
