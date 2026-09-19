<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_slip_settings')) {
            Schema::create('payroll_slip_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_slip_components')) {
            Schema::create('payroll_slip_components', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('label', 190);
                $table->string('section', 30)->default('income'); // info|income|deduction
                $table->string('source', 80)->default('manual');
                $table->string('display_format', 20)->default('money'); // money|number|percent
                $table->decimal('default_amount', 15, 2)->default(0);
                $table->boolean('affects_total')->default(true);
                $table->boolean('editable_amount')->default(true);
                $table->boolean('show_on_payslip')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('payroll_slip_component_values')) {
            Schema::create('payroll_slip_component_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->foreignId('component_id')->constrained('payroll_slip_components');
                $table->decimal('amount', 15, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['payroll_id', 'component_id'], 'payroll_slip_value_unique');
            });
        }

        $now = now();
        $settings = [
            'title' => 'PHIẾU LƯƠNG NHÂN VIÊN',
            'subtitle' => 'Chi tiết thu nhập, khấu trừ và thực nhận theo kỳ lương',
            'footer_note' => 'Phiếu lương được tổng hợp từ dữ liệu chấm công, KPI và các khoản điều chỉnh đã được xác nhận.',
            'show_attendance' => '1',
            'show_kpi_summary' => '1',
            'show_note' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('payroll_slip_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        if (DB::table('payroll_slip_components')->whereNull('deleted_at')->count() === 0) {
            $components = [
                ['code' => 'basic_salary', 'label' => 'Lương tháng', 'section' => 'info', 'source' => 'basic_salary', 'display_format' => 'money', 'affects_total' => false, 'editable_amount' => true, 'sort_order' => 10],
                ['code' => 'standard_days', 'label' => 'Ngày công chuẩn', 'section' => 'info', 'source' => 'standard_days', 'display_format' => 'number', 'affects_total' => false, 'editable_amount' => true, 'sort_order' => 20],
                ['code' => 'working_days', 'label' => 'Ngày công thực tế', 'section' => 'info', 'source' => 'working_days', 'display_format' => 'number', 'affects_total' => false, 'editable_amount' => true, 'sort_order' => 30],
                ['code' => 'salary_by_days', 'label' => 'Lương theo ngày công', 'section' => 'income', 'source' => 'salary_by_days', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => false, 'sort_order' => 100],
                ['code' => 'business_trip', 'label' => 'Công tác phí', 'section' => 'income', 'source' => 'income_business_trip', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 110],
                ['code' => 'meal', 'label' => 'Phụ cấp cơm', 'section' => 'income', 'source' => 'income_meal', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 120],
                ['code' => 'phone', 'label' => 'Phụ cấp điện thoại', 'section' => 'income', 'source' => 'income_phone', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 130],
                ['code' => 'housing', 'label' => 'Phụ cấp nhà ở', 'section' => 'income', 'source' => 'income_housing', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 140],
                ['code' => 'fuel', 'label' => 'Phụ cấp xăng xe', 'section' => 'income', 'source' => 'income_fuel', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 150],
                ['code' => 'child', 'label' => 'Phụ cấp con nhỏ', 'section' => 'income', 'source' => 'income_child', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 160],
                ['code' => 'province', 'label' => 'Phụ cấp tỉnh', 'section' => 'income', 'source' => 'income_province', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 170],
                ['code' => 'commission', 'label' => 'Hoa hồng / OT', 'section' => 'income', 'source' => 'commission', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 180],
                ['code' => 'bonus', 'label' => 'Thưởng', 'section' => 'income', 'source' => 'bonus', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 190],
                ['code' => 'technical_kpi', 'label' => 'KPI kỹ thuật đã duyệt', 'section' => 'info', 'source' => 'technical_kpi', 'display_format' => 'money', 'affects_total' => false, 'editable_amount' => false, 'sort_order' => 210],
                ['code' => 'bhxh', 'label' => 'BHXH', 'section' => 'deduction', 'source' => 'deduction_bhxh', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 300],
                ['code' => 'bhyt', 'label' => 'BHYT', 'section' => 'deduction', 'source' => 'deduction_bhyt', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 310],
                ['code' => 'bhtn', 'label' => 'BHTN', 'section' => 'deduction', 'source' => 'deduction_bhtn', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 320],
                ['code' => 'pit', 'label' => 'Thuế TNCN', 'section' => 'deduction', 'source' => 'deduction_pit', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 330],
                ['code' => 'advance', 'label' => 'Tạm ứng', 'section' => 'deduction', 'source' => 'advance', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 340],
                ['code' => 'late_penalty', 'label' => 'Phạt đi trễ', 'section' => 'deduction', 'source' => 'late_penalty', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => false, 'sort_order' => 350],
                ['code' => 'other_deduction', 'label' => 'Khấu trừ khác', 'section' => 'deduction', 'source' => 'deduction_other', 'display_format' => 'money', 'affects_total' => true, 'editable_amount' => true, 'sort_order' => 360],
            ];

            foreach ($components as $component) {
                DB::table('payroll_slip_components')->insert(array_merge([
                    'default_amount' => 0,
                    'show_on_payslip' => true,
                    'is_active' => true,
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $component));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slip_component_values');
        Schema::dropIfExists('payroll_slip_components');
        Schema::dropIfExists('payroll_slip_settings');
    }
};
