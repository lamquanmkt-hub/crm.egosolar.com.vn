<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KPI văn phòng (HCNS, Kế toán kho) + Bảng lương tự động thay file Excel (chỉ THÊM bảng mới).
 *
 * - hr_salary_profiles:   hồ sơ lương từng nhân viên theo tháng hiệu lực (lương chính, hiệu suất/KPI, phụ cấp, BH, NPT).
 * - hr_kpi_templates:     bộ KPI theo vị trí (HCNS, Kế toán kho) + bậc quy đổi lương KPI.
 * - hr_kpi_criteria:      tiêu chí, trọng số, chỉ tiêu, cách tính % đạt.
 * - hr_kpi_scores:        kết quả từng tiêu chí theo nhân viên / tháng (người đánh giá nhập).
 * - hr_payroll_settings:  tham số tính lương (giảm trừ gia cảnh, tỷ lệ BH, trần BH...).
 * - hr_payroll_periods:   kỳ lương tháng (nháp → đã duyệt/khoá).
 * - hr_payroll_lines:     dòng lương từng nhân viên (ảnh chụp số liệu tại thời điểm tính).
 *
 * KPI Kỹ sư dân dụng dùng module có sẵn (technical_kpi_*), không tạo lại ở đây.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_salary_profiles')) {
            Schema::create('hr_salary_profiles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->char('effective_month', 7)->comment('YYYY-MM: áp dụng từ tháng này');
                $table->decimal('base_salary', 15, 2)->default(0)->comment('Lương chính');
                $table->decimal('kpi_salary', 15, 2)->default(0)->comment('Hiệu suất công việc = lương KPI 100%');
                $table->decimal('decision_bonus', 15, 2)->default(0)->comment('Thưởng theo QĐ');
                $table->decimal('travel_allowance', 15, 2)->default(0)->comment('Hỗ trợ đi lại');
                $table->decimal('phone_allowance', 15, 2)->default(0)->comment('Hỗ trợ điện thoại');
                $table->decimal('meal_allowance', 15, 2)->default(0)->comment('Tiền ăn');
                $table->decimal('insurance_salary', 15, 2)->nullable()->comment('Lương đóng BH; trống = lương chính');
                $table->boolean('insurance_enabled')->default(true);
                $table->unsignedTinyInteger('dependents')->default(0)->comment('Số người phụ thuộc');
                $table->string('pit_mode', 20)->default('progressive')->comment('progressive|flat_10|none');
                $table->string('kpi_template', 40)->nullable()->comment('technical|hcns|ke_toan_kho|null = hưởng đủ hiệu suất');
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'effective_month'], 'hr_salary_profile_user_month_uq');
            });
        }

        if (! Schema::hasTable('hr_kpi_templates')) {
            Schema::create('hr_kpi_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 190);
                $table->string('position_label', 190)->nullable();
                $table->text('description')->nullable();
                $table->json('payout_tiers')->comment('[{min, mode: fixed|proportional, rate, label}] xét từ min cao xuống');
                $table->decimal('max_payout_rate', 6, 4)->nullable()->comment('Trần hệ số lương KPI; trống = không giới hạn');
                $table->decimal('max_achievement', 6, 2)->default(120)->comment('Trần % đạt của một tiêu chí');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_kpi_criteria')) {
            Schema::create('hr_kpi_criteria', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('template_id')->index();
                $table->string('code', 40);
                $table->string('group_name', 190)->nullable();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('target_text', 255)->nullable();
                $table->decimal('weight', 6, 2)->nullable()->comment('Trọng số (điểm). Trống = chưa có, chưa tính được KPI');
                $table->string('calc_type', 20)->default('manual')->comment('manual|higher_better|lower_better');
                $table->decimal('target_value', 15, 2)->nullable();
                $table->string('unit', 40)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['template_id', 'code'], 'hr_kpi_criteria_template_code_uq');
            });
        }

        if (! Schema::hasTable('hr_kpi_scores')) {
            Schema::create('hr_kpi_scores', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->char('payroll_month', 7);
                $table->unsignedBigInteger('criterion_id');
                $table->decimal('actual_value', 15, 2)->nullable();
                $table->decimal('achievement', 6, 2)->nullable()->comment('% đạt nhập tay; trống = tự tính từ Thực tế');
                $table->boolean('not_applicable')->default(false)->comment('Tháng này không phát sinh => bỏ khỏi trọng số');
                $table->string('note', 1000)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'payroll_month', 'criterion_id'], 'hr_kpi_scores_user_month_criterion_uq');
                $table->index('payroll_month', 'hr_kpi_scores_month_idx');
            });
        }

        if (! Schema::hasTable('hr_payroll_settings')) {
            Schema::create('hr_payroll_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_payroll_periods')) {
            Schema::create('hr_payroll_periods', function (Blueprint $table): void {
                $table->id();
                $table->char('payroll_month', 7)->unique();
                $table->string('status', 20)->default('draft')->comment('draft|approved');
                $table->decimal('standard_days', 5, 2)->default(0);
                $table->json('settings_snapshot')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_payroll_lines')) {
            Schema::create('hr_payroll_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('period_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->string('employee_name', 190);
                $table->string('employee_code', 60)->nullable();
                $table->string('department_name', 190)->nullable();
                $table->string('position_name', 190)->nullable();

                // Ngày công
                $table->decimal('standard_days', 5, 2)->default(0);
                $table->decimal('worked_days', 5, 2)->default(0);
                $table->decimal('paid_leave_days', 5, 2)->default(0);
                $table->decimal('holiday_days', 5, 2)->default(0);
                $table->decimal('unpaid_days', 5, 2)->default(0);
                $table->decimal('paid_days', 5, 2)->default(0)->comment('Ngày tính lương = làm + phép + lễ');
                $table->unsignedInteger('late_count')->default(0);
                $table->decimal('ot_hours', 7, 2)->default(0);

                // Hồ sơ lương tại thời điểm tính
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('kpi_salary', 15, 2)->default(0);
                $table->decimal('decision_bonus', 15, 2)->default(0);
                $table->decimal('travel_allowance', 15, 2)->default(0);
                $table->decimal('phone_allowance', 15, 2)->default(0);
                $table->decimal('meal_allowance', 15, 2)->default(0);
                $table->decimal('insurance_salary', 15, 2)->default(0);
                $table->unsignedTinyInteger('dependents')->default(0);
                $table->string('pit_mode', 20)->default('progressive');

                // KPI
                $table->string('kpi_template', 40)->nullable();
                $table->decimal('kpi_percent', 8, 2)->nullable()->comment('% KPI đạt (vd 93.50)');
                $table->decimal('kpi_rate', 6, 4)->nullable()->comment('Hệ số lương KPI (vd 0.9350)');
                $table->string('kpi_label', 190)->nullable();

                // Thu nhập
                $table->decimal('base_pay', 15, 2)->default(0);
                $table->decimal('decision_bonus_pay', 15, 2)->default(0);
                $table->decimal('travel_pay', 15, 2)->default(0);
                $table->decimal('kpi_pay', 15, 2)->default(0);
                $table->decimal('other_income', 15, 2)->default(0);
                $table->decimal('taxable_income', 15, 2)->default(0);
                $table->decimal('meal_pay', 15, 2)->default(0);
                $table->decimal('phone_pay', 15, 2)->default(0);
                $table->decimal('trip_allowance', 15, 2)->default(0);
                $table->decimal('ot_amount', 15, 2)->default(0);
                $table->decimal('non_taxable_income', 15, 2)->default(0);
                $table->decimal('gross_income', 15, 2)->default(0);

                // Bảo hiểm & thuế
                $table->decimal('family_deduction', 15, 2)->default(0);
                $table->decimal('insurance_base', 15, 2)->default(0);
                $table->decimal('bhxh_employee', 15, 2)->default(0);
                $table->decimal('bhyt_employee', 15, 2)->default(0);
                $table->decimal('bhtn_employee', 15, 2)->default(0);
                $table->decimal('insurance_employee', 15, 2)->default(0);
                $table->decimal('bhxh_employer', 15, 2)->default(0);
                $table->decimal('bhyt_employer', 15, 2)->default(0);
                $table->decimal('bhtn_employer', 15, 2)->default(0);
                $table->decimal('insurance_employer', 15, 2)->default(0);
                $table->decimal('taxable_after_deduction', 15, 2)->default(0);
                $table->decimal('pit', 15, 2)->default(0);

                // Khấu trừ & thực lĩnh
                $table->decimal('advance', 15, 2)->default(0);
                $table->decimal('late_penalty', 15, 2)->default(0);
                $table->decimal('other_deduction', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);

                $table->json('manual')->nullable()->comment('Giá trị HR sửa tay, giữ lại khi Tính lại');
                $table->json('warnings')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['period_id', 'user_id'], 'hr_payroll_lines_period_user_uq');
            });
        }

        $this->seedKpiTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_lines');
        Schema::dropIfExists('hr_payroll_periods');
        Schema::dropIfExists('hr_payroll_settings');
        Schema::dropIfExists('hr_kpi_scores');
        Schema::dropIfExists('hr_kpi_criteria');
        Schema::dropIfExists('hr_kpi_templates');
        Schema::dropIfExists('hr_salary_profiles');
    }

    /** Nạp 2 bộ KPI theo tài liệu Ban lãnh đạo (chỉ khi chưa có — chạy lại không ghi đè chỉnh sửa). */
    private function seedKpiTemplates(): void
    {
        $now = now();

        $templates = [
            [
                'code' => 'hcns',
                'name' => 'KPI Hành chính Nhân sự',
                'position_label' => 'Chuyên viên Hành chính Nhân sự tổng hợp',
                'description' => 'Tài liệu KPI HCNS chưa có trọng số: tạm chia đều 9 tiêu chí, Ban Giám đốc sửa trọng số trong Cài đặt KPI.',
                'payout_tiers' => [
                    ['min' => 95, 'mode' => 'fixed', 'rate' => 1.10, 'label' => 'Xuất sắc (≥ 95 điểm)'],
                    ['min' => 80, 'mode' => 'fixed', 'rate' => 1.00, 'label' => 'Đạt yêu cầu (80 – 94 điểm)'],
                    ['min' => 65, 'mode' => 'fixed', 'rate' => 0.85, 'label' => 'Cần cải thiện (65 – 79 điểm)'],
                    ['min' => 0, 'mode' => 'fixed', 'rate' => 0.00, 'label' => 'Không đạt (< 65 điểm)'],
                ],
                'max_payout_rate' => null,
                'criteria' => [
                    ['recruit_time', null, 'Thời gian tuyển dụng', 'Số ngày trung bình từ khi tiếp nhận yêu cầu đến khi ứng viên nhận việc', '23 – 25 ngày', 'lower_better', 25, 'ngày'],
                    ['recruit_cost', null, 'Chi phí tuyển dụng', 'Tổng chi phí chia cho số vị trí tuyển thành công', '500.000 – 1.000.000 VNĐ', 'lower_better', 1000000, 'VNĐ/vị trí'],
                    ['probation_quit', null, 'Tỷ lệ nghỉ việc trong thử việc', 'Tỷ lệ nhân sự mới nghỉ việc ngay trong giai đoạn thử việc', '≤ 10%', 'lower_better', 10, '%'],
                    ['payroll_accuracy', null, 'Độ chính xác bảng lương', 'Tỷ lệ tính lương, thưởng chính xác không sai sót', '≥ 99%', 'higher_better', 99, '%'],
                    ['insurance', null, 'Xử lý chế độ BHXH', 'Tỷ lệ nộp hồ sơ, chốt sổ và giải quyết chế độ bảo hiểm đúng hạn cho người lao động', '100%', 'higher_better', 100, '%'],
                    ['assets', null, 'Quản lý tài sản & văn phòng phẩm', 'Tỷ lệ đáp ứng yêu cầu cấp phát và kiểm kê tài sản đúng định kỳ (mỗi tháng)', '≥ 95% đúng SLA', 'higher_better', 95, '%'],
                    ['documents', null, 'Xử lý công văn, giấy tờ', 'Tốc độ tiếp nhận, phân loại và luân chuyển văn bản nội bộ/bên ngoài chính xác', '100%', 'higher_better', 100, '%'],
                    ['satisfaction', null, 'Mức độ hài lòng nội bộ', 'Mức độ hài lòng của nhân viên với môi trường làm việc qua khảo sát định kỳ (mỗi tháng)', '≥ 80%', 'higher_better', 80, '%'],
                    ['admin_deadline', null, 'Hoàn thành đúng hạn thủ tục hành chính', 'Xử lý thủ tục hành chính liên quan tới hồ sơ pháp lý, giấy phép… theo deadline được giao', '≥ 80%', 'higher_better', 80, '%'],
                ],
                // Tạm chia đều (tổng 100) để bảng lương không bị chặn; % KPI chia theo tổng trọng số nên lệch 0,01 không ảnh hưởng.
                'weights' => ['recruit_time' => 11.12, 'recruit_cost' => 11.11, 'probation_quit' => 11.11, 'payroll_accuracy' => 11.11,
                    'insurance' => 11.11, 'assets' => 11.11, 'documents' => 11.11, 'satisfaction' => 11.11, 'admin_deadline' => 11.11],
            ],
            [
                'code' => 'ke_toan_kho',
                'name' => 'KPI Kế toán kho',
                'position_label' => 'Kế toán kho',
                'description' => 'Cơ cấu lương: 70% lương cơ bản – 30% lương KPI. Quy đổi theo bảng bậc trong tài liệu (ví dụ 93% → 2.790.000đ trong tài liệu tính theo % thực tế, khác bảng bậc — đổi ở Cài đặt KPI nếu Ban Giám đốc chọn cách đó).',
                'payout_tiers' => [
                    // "101% – 110%: hưởng 110% – 120%" => % KPI × 1,1, trần 120%.
                    ['min' => 100.01, 'mode' => 'proportional', 'rate' => 1.10, 'label' => 'Vượt chỉ tiêu (> 100%)'],
                    ['min' => 90, 'mode' => 'fixed', 'rate' => 1.00, 'label' => 'Đạt (90% – 100%)'],
                    ['min' => 70, 'mode' => 'fixed', 'rate' => 0.90, 'label' => 'Cần cải thiện (70% – 89%)'],
                    ['min' => 0, 'mode' => 'fixed', 'rate' => 0.00, 'label' => 'Không đạt (< 70%) — chỉ nhận lương cơ bản'],
                ],
                'max_payout_rate' => 1.20,
                'criteria' => [
                    ['stock_variance', 'I. Độ chính xác số liệu', 'Tỷ lệ chênh lệch số liệu giữa sổ sách và thực tế kiểm kê (kiểm tra mỗi tháng)', '(Số mã hàng lệch / Tổng số mã hàng kiểm kê) × 100%', '0% (Khớp 100%)', 'lower_better', 0, '%'],
                    ['voucher_errors', 'I. Độ chính xác số liệu', 'Tỷ lệ sai sót khi nhập liệu hóa đơn, phiếu xuất nhập', '(Số chứng từ nhập lỗi / Tổng số chứng từ) × 100%', '≤ 1% tổng chứng từ', 'lower_better', 1, '%'],
                    ['entry_time', 'II. Tính kịp thời (tốc độ xử lý)', 'Thời gian nhập liệu phiếu xuất/nhập vào phần mềm', 'Tính từ lúc nhận chứng từ từ thủ kho/giao nhận', 'Trong vòng 8 giờ làm việc', 'lower_better', 8, 'giờ'],
                    ['stocktake', 'II. Tính kịp thời (tốc độ xử lý)', 'Tiến độ thực hiện và đối chiếu biên bản kiểm kê định kỳ', 'Hoàn thành đối chiếu và ký biên bản với thủ kho', 'Trước ngày 02 hàng tháng', 'manual', null, null],
                    ['nxt_report', 'III. Kiểm soát tồn kho & báo cáo', 'Thời gian lập báo cáo nhập – xuất – tồn định kỳ', 'Nộp báo cáo tuần (Thứ 2) và báo cáo tháng', 'Đúng hạn 100%', 'higher_better', 100, '%'],
                    ['aging_alert', 'III. Kiểm soát tồn kho & báo cáo', 'Tỷ lệ phát hiện và cảnh báo hàng tồn lâu, hàng lỗi', 'Gửi email cảnh báo hàng tồn > 30 ngày cho Giám đốc/Kinh doanh', 'Cảnh báo trước ít nhất 15 ngày', 'manual', null, null],
                    ['archive', 'IV. Kỷ luật & lưu trữ', 'Tính ngăn nắp, đầy đủ của hồ sơ chứng từ gốc', 'Kiểm tra đột xuất không bị thất lạc, rách nát', '100% chứng từ đóng tập, dễ tìm', 'manual', null, null],
                ],
                'weights' => ['stock_variance' => 25, 'voucher_errors' => 15, 'entry_time' => 15, 'stocktake' => 15, 'nxt_report' => 10, 'aging_alert' => 10, 'archive' => 10],
            ],
        ];

        foreach ($templates as $t) {
            if (DB::table('hr_kpi_templates')->where('code', $t['code'])->exists()) {
                continue;
            }

            $templateId = DB::table('hr_kpi_templates')->insertGetId([
                'code' => $t['code'],
                'name' => $t['name'],
                'position_label' => $t['position_label'],
                'description' => $t['description'],
                'payout_tiers' => json_encode($t['payout_tiers'], JSON_UNESCAPED_UNICODE),
                'max_payout_rate' => $t['max_payout_rate'],
                'max_achievement' => 120,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($t['criteria'] as $i => [$code, $group, $name, $description, $target, $calcType, $targetValue, $unit]) {
                DB::table('hr_kpi_criteria')->insert([
                    'template_id' => $templateId,
                    'code' => $code,
                    'group_name' => $group,
                    'name' => $name,
                    'description' => $description,
                    'target_text' => $target,
                    'weight' => $t['weights'][$code] ?? null,
                    'calc_type' => $calcType,
                    'target_value' => $targetValue,
                    'unit' => $unit,
                    'sort_order' => ($i + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
