<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('technical_payroll_kpi_items')) {
            Schema::create('technical_payroll_kpi_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('unit')->nullable();
                $table->decimal('plan_value', 15, 2)->default(0);
                $table->decimal('actual_value', 15, 2)->default(0);
                $table->decimal('weight', 8, 4)->default(0.1);
                $table->string('calc_type')->default('actual_div_plan');
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('technical_payroll_kpi_items') && DB::table('technical_payroll_kpi_items')->count() === 0) {
            $rows = [
                ['name' => 'Tiến độ thi công tổng thể', 'unit' => 'Ngày', 'plan_value' => 4, 'actual_value' => 4, 'weight' => 0.20, 'calc_type' => 'plan_div_actual', 'note' => 'KH / TH. Ít ngày hơn là tốt.', 'sort_order' => 1],
                ['name' => 'Chất lượng công trình', 'unit' => 'Lỗi', 'plan_value' => 0, 'actual_value' => 0, 'weight' => 0.10, 'calc_type' => 'minus_quality', 'note' => 'Mỗi lỗi bị trừ theo setting.', 'sort_order' => 2],
                ['name' => 'An toàn lao động', 'unit' => 'Sự cố', 'plan_value' => 0, 'actual_value' => 0, 'weight' => 0.10, 'calc_type' => 'minus_safety', 'note' => 'Mỗi sự cố bị trừ theo setting.', 'sort_order' => 3],
                ['name' => 'Mức độ hài lòng khách hàng', 'unit' => 'Feedback', 'plan_value' => 0, 'actual_value' => 0, 'weight' => 0.10, 'calc_type' => 'customer_feedback', 'note' => 'Tự tính từ feedback xấu / trung lập / tốt.', 'sort_order' => 4],
                ['name' => 'Kiểm tra bảo hành định kỳ', 'unit' => 'Lần', 'plan_value' => 7, 'actual_value' => 7, 'weight' => 0.10, 'calc_type' => 'actual_div_plan', 'note' => 'TH / KH.', 'sort_order' => 5],
                ['name' => 'Số giờ làm thêm', 'unit' => 'Giờ', 'plan_value' => 0, 'actual_value' => 0, 'weight' => 0.10, 'calc_type' => 'ot_rule', 'note' => 'OT càng ít càng tốt.', 'sort_order' => 6],
                ['name' => 'Công trình hỗ trợ chốt thành công', 'unit' => 'Công trình', 'plan_value' => 0, 'actual_value' => 10, 'weight' => 0.10, 'calc_type' => 'success_project', 'note' => 'Mỗi công trình cộng theo setting, có trần.', 'sort_order' => 7],
                ['name' => 'Bảo quản máy móc, thiết bị', 'unit' => 'Hư hỏng', 'plan_value' => 0, 'actual_value' => 0, 'weight' => 0.10, 'calc_type' => 'minus_equipment', 'note' => 'Mỗi hư hỏng/mất mát bị trừ theo setting.', 'sort_order' => 8],
                ['name' => 'Tuân thủ quy định chấm công', 'unit' => 'Ngày công', 'plan_value' => 26, 'actual_value' => 26, 'weight' => 0.10, 'calc_type' => 'actual_div_plan', 'note' => 'Ngày công thực tế / ngày công chuẩn.', 'sort_order' => 9],
            ];

            $now = now();

            foreach ($rows as $row) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                DB::table('technical_payroll_kpi_items')->insert($row);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_payroll_kpi_items');
    }
};
