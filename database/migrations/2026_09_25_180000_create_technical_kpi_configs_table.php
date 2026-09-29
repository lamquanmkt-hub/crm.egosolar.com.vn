<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('technical_kpi_configs')) {
            Schema::create('technical_kpi_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('version')->default(1);
                $table->string('version_name', 191);
                $table->string('status', 32)->default('draft'); // draft, applied, archived
                $table->boolean('is_current')->default(false);
                $table->date('effective_date')->nullable();
                $table->json('salary_structure');
                $table->json('criteria_config');
                $table->json('payout_tiers');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'is_current']);
                $table->index('effective_date');
            });

            // Seed initial approved version from standard
            $initialSalary = [
                'base_salary_rate' => 0.70,
                'kpi_salary_rate' => 0.30,
                'allow_exceed_100' => false,
                'max_kpi_rate' => 1.00,
                'round_rule' => '1000',
                'effective_date' => '2026-09-01',
            ];

            $initialCriteria = [
                [
                    'no' => 1,
                    'code' => 'timeline',
                    'name' => 'Tiến độ hoàn thành lắp đặt hệ thống',
                    'weight' => 0.30,
                    'formula' => '(Số hệ thống hoàn thành đúng tiến độ / Tổng số hệ thống bàn giao) x 100%',
                    'source' => 'Báo cáo nghiệm thu & tiến độ thi công CRM',
                    'threshold' => '100% đúng hạn theo hợp đồng (2 - 5 ngày/hệ thống)',
                    'is_calculated' => true,
                    'status' => 'applied',
                    'effective_date' => '2026-09-01',
                ],
                [
                    'no' => 2,
                    'code' => 'quality',
                    'name' => 'Chất lượng thi công & Thẩm mỹ',
                    'weight' => 0.25,
                    'formula' => 'Nghiệm thu đạt ngay lần đầu, không bị khách hàng phàn nàn',
                    'source' => 'Biên bản nghiệm thu kỹ thuật & đánh giá hiện trường CRM',
                    'threshold' => 'Khung giàn phẳng, dây ống gọn, keo chống dột 100%',
                    'is_calculated' => true,
                    'status' => 'applied',
                    'effective_date' => '2026-09-01',
                ],
                [
                    'no' => 3,
                    'code' => 'survey',
                    'name' => 'Khảo sát kỹ thuật & Khối lượng',
                    'weight' => 0.15,
                    'formula' => 'Sai số vật tư thực tế so với khảo sát bóc tách < 3%',
                    'source' => 'Khảo sát kỹ thuật & bóc tách dự toán CRM',
                    'threshold' => 'Đo đạc chính xác hướng mái, góc nghiêng, sai số < 3%',
                    'is_calculated' => true,
                    'status' => 'applied',
                    'effective_date' => '2026-09-01',
                ],
                [
                    'no' => 4,
                    'code' => 'hse',
                    'name' => 'An toàn lao động (HSE) & Vệ sinh',
                    'weight' => 0.15,
                    'formula' => '0 tai nạn; 100% dọn dẹp sạch sẽ mái và công trình sau thi công',
                    'source' => 'Nhật ký thi công, báo cáo an toàn hiện trường CRM',
                    'threshold' => '0 vi phạm an toàn; thu dọn toàn bộ rác cáp và bao bì',
                    'is_calculated' => true,
                    'status' => 'applied',
                    'effective_date' => '2026-09-01',
                ],
                [
                    'no' => 5,
                    'code' => 'evn_app',
                    'name' => 'Hỗ trợ thủ tục EVN & Cài đặt App',
                    'weight' => 0.15,
                    'formula' => '100% hệ thống được đấu nối thử và cài App giám sát cho chủ nhà',
                    'source' => 'Biên bản bàn giao cài app & đấu nối CRM',
                    'threshold' => 'Đấu nối chuẩn EVN; hướng dẫn khách dùng App thành thạo',
                    'is_calculated' => true,
                    'status' => 'applied',
                    'effective_date' => '2026-09-01',
                ],
                [
                    'no' => 6,
                    'code' => 'warranty',
                    'name' => 'Thời gian phản hồi và xử lý bảo hành SP',
                    'weight' => 0.00,
                    'formula' => 'Tỷ lệ giải quyết sự cố bảo hành trong thời hạn quy định',
                    'source' => 'Phân hệ Bảo hành & Sự cố kỹ thuật CRM',
                    'threshold' => 'Tiếp nhận và xử lý sự cố trong vòng 24h - 48h',
                    'is_calculated' => false,
                    'status' => 'draft',
                    'pending_reason' => 'Nháp — Chờ lãnh đạo xác nhận',
                    'effective_date' => '2026-09-01',
                ],
            ];

            $initialPayoutTiers = [
                'under_75' => [
                    'min' => 0.00,
                    'max' => 0.75,
                    'rate' => 0.00,
                    'label' => 'Không đạt (< 75%)',
                    'note' => 'Không nhận tiền lương KPI tháng đó',
                ],
                'from_75_to_90' => [
                    'min' => 0.75,
                    'max' => 0.90,
                    'rate' => 0.80,
                    'label' => 'Cần cải thiện (75% đến dưới 90%)',
                    'note' => 'Hưởng 80% tiền lương KPI',
                ],
                'from_90_to_100' => [
                    'min' => 0.90,
                    'max' => 1.00,
                    'rate' => 1.00,
                    'label' => 'Đạt yêu cầu (90% đến dưới 100%)',
                    'note' => 'Hưởng 100% tiền lương KPI',
                ],
                'above_100' => [
                    'min' => 1.00,
                    'max' => 1.30,
                    'rate' => 1.00,
                    'label' => 'Vượt chỉ tiêu (Từ 100% trở lên)',
                    'note' => 'Hưởng 100% tiền lương KPI (chờ phê duyệt nếu thưởng 110%-120%)',
                ],
            ];

            DB::table('technical_kpi_configs')->insert([
                'version' => 1,
                'version_name' => 'Phiên bản chuẩn Ban lãnh đạo (Tháng 09/2026)',
                'status' => 'applied',
                'is_current' => true,
                'effective_date' => '2026-09-01',
                'salary_structure' => json_encode($initialSalary, JSON_UNESCAPED_UNICODE),
                'criteria_config' => json_encode($initialCriteria, JSON_UNESCAPED_UNICODE),
                'payout_tiers' => json_encode($initialPayoutTiers, JSON_UNESCAPED_UNICODE),
                'notes' => 'Phiên bản cấu hình chuẩn theo văn bản và tài liệu Ban lãnh đạo ban hành.',
                'created_by' => 1,
                'updated_by' => 1,
                'approved_by' => 1,
                'approved_at' => '2026-09-01 08:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_kpi_configs');
    }
};
