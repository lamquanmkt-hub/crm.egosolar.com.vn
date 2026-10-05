<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Payroll\PayrollCalculator;
use App\Services\Hr\Payroll\PayrollSettings;
use Tests\TestCase;

/**
 * Bộ tính lương phải ra đúng số của file Excel lương EGO tháng 8/2026 (sheet "2. CHI TIỀN LƯƠNG"),
 * và bậc quy đổi KPI phải đúng tài liệu HCNS / Kế toán kho đã chốt với Ban Giám đốc.
 */
final class PayrollCalculatorTest extends TestCase
{
    private function input(array $override = []): array
    {
        return $override + [
            'standard_days' => 23, 'paid_days' => 23,
            'base_salary' => 0, 'kpi_salary' => 0, 'decision_bonus' => 0, 'travel_allowance' => 0,
            'phone_allowance' => 0, 'meal_allowance' => 0, 'insurance_salary' => 0, 'insurance_enabled' => true,
            'dependents' => 0, 'pit_mode' => 'progressive', 'kpi_rate' => 1.0,
            'other_income' => 0, 'trip_allowance' => 0, 'ot_amount' => 0,
            'advance' => 0, 'late_penalty' => 0, 'other_deduction' => 0,
        ];
    }

    public function test_matches_excel_row_full_month_with_dependents(): void
    {
        // Dòng 17 file Excel: Giám đốc, 23/23 công, 2 người phụ thuộc.
        $r = (new PayrollCalculator)->calculate($this->input([
            'base_salary' => 10000000, 'decision_bonus' => 3300000, 'travel_allowance' => 3000000,
            'phone_allowance' => 2500000, 'meal_allowance' => 1200000, 'insurance_salary' => 10000000, 'dependents' => 2,
        ]), PayrollSettings::DEFAULTS);

        $this->assertSame(16300000.0, $r['taxable_income']);
        $this->assertSame(3700000.0, $r['non_taxable_income']);
        $this->assertSame(20000000.0, $r['gross_income']);
        $this->assertSame(27900000.0, $r['family_deduction']);
        $this->assertSame(1050000.0, $r['insurance_employee']);
        $this->assertSame(2150000.0, $r['insurance_employer']);
        $this->assertSame(0.0, $r['pit']);
        $this->assertSame(18950000.0, $r['net_salary']);
    }

    public function test_matches_excel_row_prorated_with_kpi_salary_and_late_penalty(): void
    {
        // Dòng 18 file Excel: kỹ thuật, 22/23 công, hiệu suất 6.292.000, khác 500.000, công tác 100.000, trừ chấm công 400.000.
        $r = (new PayrollCalculator)->calculate($this->input([
            'paid_days' => 22, 'base_salary' => 5310000, 'kpi_salary' => 6292000, 'travel_allowance' => 1428000,
            'phone_allowance' => 1500000, 'meal_allowance' => 1200000, 'insurance_salary' => 5310000,
            'other_income' => 500000, 'trip_allowance' => 100000, 'late_penalty' => 400000,
        ]), PayrollSettings::DEFAULTS);

        $this->assertEqualsWithDelta(5079130.43, $r['base_pay'], 0.01);
        $this->assertEqualsWithDelta(6018434.78, $r['kpi_pay'], 0.01);
        $this->assertSame(12963478.0, $r['taxable_income']);
        $this->assertSame(2682609.0, $r['non_taxable_income']);
        $this->assertSame(15646087.0, $r['gross_income']);
        $this->assertSame(557550.0, $r['insurance_employee']);
        $this->assertSame(14688537.0, $r['net_salary']);
    }

    public function test_kpi_rate_scales_only_the_kpi_salary(): void
    {
        $r = (new PayrollCalculator)->calculate($this->input([
            'base_salary' => 7000000, 'kpi_salary' => 3000000, 'kpi_rate' => 0.93, 'insurance_enabled' => false,
        ]), PayrollSettings::DEFAULTS);

        // Ví dụ trong tài liệu KPI Kế toán kho: 7.000.000 + 3.000.000 × 93% = 9.790.000.
        $this->assertSame(2790000.0, $r['kpi_pay']);
        $this->assertSame(9790000.0, $r['net_salary']);
    }

    public function test_insurance_follows_unpaid_day_rule_and_cap_applies(): void
    {
        $calc = new PayrollCalculator;
        $salary = ['base_salary' => 6000000, 'insurance_salary' => 6000000];

        // Luật BHXH: nghỉ không lương từ 14 ngày => không đóng; 9 ngày vẫn đóng dù chỉ còn 14 ngày tính lương.
        $off = $calc->calculate($this->input(['paid_days' => 9, 'unpaid_days' => 14] + $salary), PayrollSettings::DEFAULTS);
        $this->assertSame(0.0, $off['insurance_employee']);
        $on = $calc->calculate($this->input(['paid_days' => 14, 'unpaid_days' => 9] + $salary), PayrollSettings::DEFAULTS);
        $this->assertSame(630000.0, $on['insurance_employee']);

        // Cách file Excel cũ (dưới 15 ngày tính lương thì không đóng) vẫn bật được trong cài đặt.
        $excel = $calc->calculate($this->input(['paid_days' => 14, 'unpaid_days' => 9] + $salary), ['insurance_min_days' => 15] + PayrollSettings::DEFAULTS);
        $this->assertSame(0.0, $excel['insurance_employee']);

        $high = $calc->calculate($this->input(['base_salary' => 90000000, 'insurance_salary' => 90000000]), PayrollSettings::DEFAULTS);
        $this->assertSame(46800000.0, $high['insurance_base']);
    }

    public function test_progressive_pit_brackets(): void
    {
        $this->assertSame(0.0, PayrollCalculator::progressivePit(0));
        $this->assertSame(500000.0, PayrollCalculator::progressivePit(10000000));
        $this->assertSame(1500000.0, PayrollCalculator::progressivePit(20000000));
        $this->assertSame(6500000.0, PayrollCalculator::progressivePit(50000000));
        $this->assertSame(20500000.0, PayrollCalculator::progressivePit(100000000));
        $this->assertSame(27500000.0, PayrollCalculator::progressivePit(120000000));

        // Thử việc / thời vụ: 10% khi vượt 5.000.000 (file Excel dùng "> 5.000.000"), không giảm trừ gia cảnh.
        $flat = (new PayrollCalculator)->calculate($this->input(['base_salary' => 6000000, 'pit_mode' => 'flat_10', 'insurance_enabled' => false, 'dependents' => 2]), PayrollSettings::DEFAULTS);
        $this->assertSame(600000.0, $flat['pit']);
        $this->assertSame(0.0, $flat['family_deduction']);
        $atThreshold = (new PayrollCalculator)->calculate($this->input(['base_salary' => 5000000, 'pit_mode' => 'flat_10', 'insurance_enabled' => false]), PayrollSettings::DEFAULTS);
        $this->assertSame(0.0, $atThreshold['pit']);
    }

    public function test_ke_toan_kho_payout_follows_tier_table(): void
    {
        // Bảng bậc trong tài liệu KPI Kế toán kho (bản nạp sẵn trong migration).
        $template = (object) [
            'max_payout_rate' => 1.20,
            'payout_tiers' => [
                ['min' => 100.01, 'mode' => 'proportional', 'rate' => 1.10],
                ['min' => 90, 'mode' => 'fixed', 'rate' => 1.00],
                ['min' => 70, 'mode' => 'fixed', 'rate' => 0.90],
                ['min' => 0, 'mode' => 'fixed', 'rate' => 0],
            ],
        ];
        $kpi = new HrKpiEvaluator;

        $this->assertSame(0.0, $kpi->payout($template, 69.99)['rate']);
        $this->assertSame(0.9, $kpi->payout($template, 70)['rate']);
        $this->assertSame(0.9, $kpi->payout($template, 89.99)['rate']);
        $this->assertSame(1.0, $kpi->payout($template, 93)['rate']);
        $this->assertSame(1.0, $kpi->payout($template, 100)['rate']);
        $this->assertSame(1.111, $kpi->payout($template, 101)['rate']);
        $this->assertSame(1.2, $kpi->payout($template, 110)['rate']);
        $this->assertSame(1.2, $kpi->payout($template, 130)['rate']);
    }

    public function test_hcns_payout_tiers(): void
    {
        $template = (object) [
            'max_payout_rate' => null,
            'payout_tiers' => [
                ['min' => 95, 'mode' => 'fixed', 'rate' => 1.10],
                ['min' => 80, 'mode' => 'fixed', 'rate' => 1.00],
                ['min' => 65, 'mode' => 'fixed', 'rate' => 0.85],
                ['min' => 0, 'mode' => 'fixed', 'rate' => 0],
            ],
        ];
        $kpi = new HrKpiEvaluator;

        $this->assertSame(1.1, $kpi->payout($template, 96)['rate']);
        $this->assertSame(1.0, $kpi->payout($template, 94.9)['rate']);
        $this->assertSame(0.85, $kpi->payout($template, 65)['rate']);
        $this->assertSame(0.0, $kpi->payout($template, 64.9)['rate']);
    }

    public function test_criterion_achievement_by_calc_type(): void
    {
        $kpi = new HrKpiEvaluator;
        $score = fn ($actual, $achievement = null) => (object) ['actual_value' => $actual, 'achievement' => $achievement, 'not_applicable' => false];
        $higher = (object) ['calc_type' => 'higher_better', 'target_value' => 80];
        $lower = (object) ['calc_type' => 'lower_better', 'target_value' => 25];
        $zero = (object) ['calc_type' => 'lower_better', 'target_value' => 0];

        $this->assertSame(112.5, $kpi->achievement($higher, $score(90), 120));
        $this->assertSame(120.0, $kpi->achievement($higher, $score(200), 120));
        $this->assertSame(100.0, $kpi->achievement($lower, $score(20), 120));
        $this->assertEqualsWithDelta(83.33, $kpi->achievement($lower, $score(30), 120), 0.01);
        $this->assertSame(100.0, $kpi->achievement($zero, $score(0), 120));
        $this->assertSame(0.0, $kpi->achievement($zero, $score(2), 120));
        $this->assertSame(90.0, $kpi->achievement($zero, $score(2, 90), 120), 'Nhập tay % đạt luôn được ưu tiên');
    }

    public function test_score_requires_weights_and_skips_not_applicable_criteria(): void
    {
        $kpi = new HrKpiEvaluator;
        $template = (object) ['max_achievement' => 120];
        $criteria = collect([
            (object) ['id' => 1, 'name' => 'A', 'weight' => 60, 'calc_type' => 'manual', 'target_value' => null],
            (object) ['id' => 2, 'name' => 'B', 'weight' => 40, 'calc_type' => 'manual', 'target_value' => null],
        ]);
        $row = fn ($ach, $na = false) => (object) ['actual_value' => null, 'achievement' => $ach, 'not_applicable' => $na];

        $this->assertSame(92.0, $kpi->score($template, $criteria, collect([1 => $row(100), 2 => $row(80)]))['total']);
        $this->assertSame(100.0, $kpi->score($template, $criteria, collect([1 => $row(100), 2 => $row(null, true)]))['total']);
        $this->assertSame(HrKpiEvaluator::STATUS_INCOMPLETE, $kpi->score($template, $criteria, collect([1 => $row(100)]))['status']);

        $unweighted = collect([(object) ['id' => 1, 'name' => 'A', 'weight' => null, 'calc_type' => 'manual', 'target_value' => null]]);
        $this->assertSame(HrKpiEvaluator::STATUS_NO_WEIGHT, $kpi->score($template, $unweighted, collect([1 => $row(100)]))['status']);
    }
}
