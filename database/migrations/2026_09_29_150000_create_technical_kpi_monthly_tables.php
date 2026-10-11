<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI kỹ thuật — dữ liệu nhập theo tháng (chỉ THÊM bảng mới, không sửa bảng cũ).
 *
 * - technical_kpi_salaries:        lương thoả thuận theo kỹ sư, có tháng hiệu lực (nhập ở Cài đặt KPI).
 * - technical_kpi_monthly_scores:  Kế hoạch / Thực tế của từng tiêu chí, từng kỹ sư, từng tháng (Trưởng phòng nhập).
 * - technical_kpi_adjustments:     cộng điểm thưởng / trừ điểm vi phạm theo tháng, bắt buộc lý do.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_kpi_salaries')) {
            Schema::create('technical_kpi_salaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('agreed_salary', 15, 2);
                $table->char('effective_month', 7)->comment('YYYY-MM: áp dụng từ tháng này');
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'effective_month'], 'tech_kpi_salary_user_month_uq');
            });
        }

        if (! Schema::hasTable('technical_kpi_monthly_scores')) {
            Schema::create('technical_kpi_monthly_scores', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->char('payroll_month', 7);
                $table->string('criterion_code', 40);
                $table->decimal('plan_value', 10, 2)->nullable();
                $table->decimal('actual_value', 10, 2)->nullable();
                $table->string('note', 1000)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'payroll_month', 'criterion_code'], 'tech_kpi_monthly_user_month_code_uq');
                $table->index('payroll_month', 'tech_kpi_monthly_month_idx');
            });
        }

        if (! Schema::hasTable('technical_kpi_adjustments')) {
            Schema::create('technical_kpi_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->char('payroll_month', 7);
                $table->decimal('points', 6, 2)->comment('Dương = cộng điểm thưởng, âm = trừ điểm vi phạm');
                $table->text('reason');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'payroll_month'], 'tech_kpi_adjust_user_month_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_kpi_adjustments');
        Schema::dropIfExists('technical_kpi_monthly_scores');
        Schema::dropIfExists('technical_kpi_salaries');
    }
};
