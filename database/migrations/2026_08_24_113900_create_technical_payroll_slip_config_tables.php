<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_payroll_slip_fields')) {
            Schema::create('technical_payroll_slip_fields', function (Blueprint $table) {
                $table->id();
                $table->string('field_key', 120)->unique();
                $table->string('label', 190);
                $table->string('group_key', 40)->default('income'); // info|income|deduction|summary
                $table->string('field_type', 30)->default('money'); // money|number|percent|text
                $table->string('source_column', 120)->nullable();
                $table->decimal('default_value', 18, 2)->nullable();
                $table->boolean('is_in_total')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->integer('sort_order')->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('technical_payroll_slip_values')) {
            Schema::create('technical_payroll_slip_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_id');
                $table->unsignedBigInteger('field_id');
                $table->decimal('numeric_value', 18, 2)->nullable();
                $table->text('text_value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['payroll_id', 'field_id'], 'tech_payroll_slip_values_unique');
                $table->index('payroll_id');
                $table->index('field_id');
            });
        }

        if (Schema::hasTable('technical_payroll_slip_fields') && DB::table('technical_payroll_slip_fields')->count() === 0) {
            $now = now();
            DB::table('technical_payroll_slip_fields')->insert([
                ['field_key' => 'employee_name', 'label' => 'Nhân viên', 'group_key' => 'info', 'field_type' => 'text', 'source_column' => 'employee_name', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 10, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'position_name', 'label' => 'Chức vụ', 'group_key' => 'info', 'field_type' => 'text', 'source_column' => 'position_name', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 20, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'payroll_month', 'label' => 'Kỳ lương', 'group_key' => 'info', 'field_type' => 'text', 'source_column' => 'payroll_month', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 30, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'gross_salary', 'label' => 'Lương thỏa thuận', 'group_key' => 'info', 'field_type' => 'money', 'source_column' => 'gross_salary', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 40, 'note' => 'Thông tin tham chiếu, không cộng trực tiếp vào thực nhận.', 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'base_salary', 'label' => 'Lương cố định', 'group_key' => 'income', 'field_type' => 'money', 'source_column' => 'base_salary', 'default_value' => null, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 100, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'real_kpi_salary', 'label' => 'Lương KPI thực nhận', 'group_key' => 'income', 'field_type' => 'money', 'source_column' => 'real_kpi_salary', 'default_value' => null, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 110, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'allowance', 'label' => 'Phụ cấp', 'group_key' => 'income', 'field_type' => 'money', 'source_column' => null, 'default_value' => 0, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 120, 'note' => 'Khoản nhập tay theo từng phiếu lương.', 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'bonus', 'label' => 'Thưởng khác', 'group_key' => 'income', 'field_type' => 'money', 'source_column' => null, 'default_value' => 0, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 130, 'note' => 'Khoản nhập tay theo từng phiếu lương.', 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'advance', 'label' => 'Tạm ứng', 'group_key' => 'deduction', 'field_type' => 'money', 'source_column' => null, 'default_value' => 0, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 200, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'other_deduction', 'label' => 'Khấu trừ khác', 'group_key' => 'deduction', 'field_type' => 'money', 'source_column' => null, 'default_value' => 0, 'is_in_total' => 1, 'is_enabled' => 1, 'sort_order' => 210, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'total_kpi_percent', 'label' => 'KPI tổng', 'group_key' => 'summary', 'field_type' => 'percent', 'source_column' => 'total_kpi_percent', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 300, 'note' => 'Giá trị lưu trong hệ thống ở dạng hệ số.', 'created_at' => $now, 'updated_at' => $now],
                ['field_key' => 'note', 'label' => 'Ghi chú', 'group_key' => 'summary', 'field_type' => 'text', 'source_column' => 'note', 'default_value' => null, 'is_in_total' => 0, 'is_enabled' => 1, 'sort_order' => 310, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_payroll_slip_values');
        Schema::dropIfExists('technical_payroll_slip_fields');
    }
};
