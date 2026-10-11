<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung cho bảng lương / KPI văn phòng (chỉ THÊM, không đụng dữ liệu người dùng đã nhập):
 * - hr_payroll_lines: ghi lại ai sửa tay dòng lương, lúc nào.
 * - Bộ KPI nạp sẵn: nếu vẫn đúng giá trị bản nháp cũ (chưa ai sửa ở Cài đặt KPI) thì đổi sang
 *   bảng bậc Kế toán kho theo tài liệu và trọng số HCNS tạm chia đều. Đã sửa tay thì giữ nguyên.
 */
return new class extends Migration
{
    private const OLD_KTK_TIERS = [
        ['min' => 70, 'mode' => 'proportional', 'rate' => 1.00],
        ['min' => 0, 'mode' => 'fixed', 'rate' => 0.00],
    ];

    public function up(): void
    {
        if (Schema::hasTable('hr_payroll_lines')) {
            Schema::table('hr_payroll_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('hr_payroll_lines', 'manual_updated_by')) {
                    $table->unsignedBigInteger('manual_updated_by')->nullable()->after('manual');
                }
                if (! Schema::hasColumn('hr_payroll_lines', 'manual_updated_at')) {
                    $table->timestamp('manual_updated_at')->nullable()->after('manual_updated_by');
                }
            });
        }

        if (! Schema::hasTable('hr_kpi_templates') || ! Schema::hasTable('hr_kpi_criteria')) {
            return;
        }

        $ktk = DB::table('hr_kpi_templates')->where('code', 'ke_toan_kho')->first();
        if ($ktk && $this->sameTiers(json_decode((string) $ktk->payout_tiers, true) ?: [], self::OLD_KTK_TIERS)) {
            DB::table('hr_kpi_templates')->where('id', $ktk->id)->update([
                'payout_tiers' => json_encode([
                    ['min' => 100.01, 'mode' => 'proportional', 'rate' => 1.10, 'label' => 'Vượt chỉ tiêu (> 100%)'],
                    ['min' => 90, 'mode' => 'fixed', 'rate' => 1.00, 'label' => 'Đạt (90% – 100%)'],
                    ['min' => 70, 'mode' => 'fixed', 'rate' => 0.90, 'label' => 'Cần cải thiện (70% – 89%)'],
                    ['min' => 0, 'mode' => 'fixed', 'rate' => 0.00, 'label' => 'Không đạt (< 70%) — chỉ nhận lương cơ bản'],
                ], JSON_UNESCAPED_UNICODE),
                'max_payout_rate' => 1.20,
                'updated_at' => now(),
            ]);
        }

        $hcns = DB::table('hr_kpi_templates')->where('code', 'hcns')->first();
        if ($hcns && ! DB::table('hr_kpi_criteria')->where('template_id', $hcns->id)->whereNotNull('weight')->exists()) {
            $ids = DB::table('hr_kpi_criteria')->where('template_id', $hcns->id)->orderBy('sort_order')->orderBy('id')->pluck('id');
            foreach ($ids as $i => $id) {
                DB::table('hr_kpi_criteria')->where('id', $id)->update(['weight' => $i === 0 ? 11.12 : 11.11, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_payroll_lines')) {
            Schema::table('hr_payroll_lines', function (Blueprint $table): void {
                foreach (['manual_updated_at', 'manual_updated_by'] as $column) {
                    if (Schema::hasColumn('hr_payroll_lines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function sameTiers(array $stored, array $expected): bool
    {
        $norm = fn (array $tiers) => array_map(fn ($t) => [(float) ($t['min'] ?? 0), (string) ($t['mode'] ?? 'fixed'), (float) ($t['rate'] ?? 0)], $tiers);

        return $norm($stored) === $norm($expected);
    }
};
