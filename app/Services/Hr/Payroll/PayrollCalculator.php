<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

/**
 * Tính một dòng lương — hàm thuần, không truy vấn DB. Công thức bám đúng file Excel lương EGO
 * (sheet "3. THÔNG TIN VỀ LƯƠNG" và "2. CHI TIỀN LƯƠNG"):
 *
 *   Tỷ lệ công        = Ngày tính lương / Ngày công chuẩn
 *   Các khoản lương   = Mức hồ sơ × Tỷ lệ công (lương chính, thưởng QĐ, đi lại, điện thoại, tiền ăn, hiệu suất)
 *   Lương KPI         = Hiệu suất × Tỷ lệ công × Hệ số KPI
 *   Chịu thuế (L)     = Lương chính + Thưởng QĐ + Đi lại + Lương KPI + Khác
 *   Không chịu thuế   = Tiền ăn + Điện thoại + Công tác tỉnh + Tăng ca
 *   BH người LĐ (W)   = Lương đóng BH (có trần) × 10,5%; không đóng khi nghỉ không lương ≥ 14 ngày (luật)
 *   TN tính thuế (AC) = max(L − Giảm trừ gia cảnh − W, 0) → thuế lũy tiến 5 bậc
 *                       (thử việc / thời vụ: không giảm trừ, 10% khi L − W > ngưỡng)
 *   Thực lĩnh         = Tổng thu nhập − W − Thuế − Tạm ứng − Phạt đi trễ − Trừ khác
 */
final class PayrollCalculator
{
    /** Biểu thuế lũy tiến (thu nhập tính thuế tháng ≤ ngưỡng => thuế suất, số trừ nhanh). */
    public const PIT_BRACKETS = [
        [10000000, 0.05, 0],
        [30000000, 0.10, 500000],
        [60000000, 0.20, 3500000],
        [100000000, 0.30, 9500000],
        [PHP_INT_MAX, 0.35, 14500000],
    ];

    /**
     * @param  array{
     *     standard_days: float, paid_days: float, unpaid_days?: float,
     *     base_salary: float, kpi_salary: float, decision_bonus: float, travel_allowance: float,
     *     phone_allowance: float, meal_allowance: float, insurance_salary: float, insurance_enabled: bool,
     *     dependents: int, pit_mode: string, kpi_rate: ?float,
     *     other_income: float, trip_allowance: float, ot_amount: float,
     *     advance: float, late_penalty: float, other_deduction: float
     * }  $in
     * @param  array<string, mixed>  $settings  PayrollSettings::all()
     * @return array<string, float>
     */
    public function calculate(array $in, array $settings): array
    {
        $standard = (float) $in['standard_days'];
        $ratio = $standard > 0 ? max((float) $in['paid_days'], 0) / $standard : 0.0;

        $basePay = $in['base_salary'] * $ratio;
        $decisionBonusPay = $in['decision_bonus'] * $ratio;
        $travelPay = $in['travel_allowance'] * $ratio;
        $kpiPay = $in['kpi_salary'] * $ratio * (float) ($in['kpi_rate'] ?? 0);
        $mealPay = $in['meal_allowance'] * $ratio;
        $phonePay = $in['phone_allowance'] * $ratio;

        $taxable = round($basePay + $decisionBonusPay + $travelPay + $kpiPay + $in['other_income']);
        $nonTaxable = round($mealPay + $phonePay + $in['trip_allowance'] + $in['ot_amount']);
        $gross = $taxable + $nonTaxable;

        $insuranceBase = 0.0;
        if ($in['insurance_enabled'] && self::insuredThisMonth((float) $in['paid_days'], (float) ($in['unpaid_days'] ?? 0), $settings)) {
            $insuranceBase = min((float) $in['insurance_salary'], (float) $settings['insurance_salary_cap']);
        }
        $bhxh = round($insuranceBase * $settings['bhxh_employee_rate'] / 100);
        $bhyt = round($insuranceBase * $settings['bhyt_employee_rate'] / 100);
        $bhtn = round($insuranceBase * $settings['bhtn_employee_rate'] / 100);
        $insuranceEmployee = $bhxh + $bhyt + $bhtn;
        $bhxhEr = round($insuranceBase * $settings['bhxh_employer_rate'] / 100);
        $bhytEr = round($insuranceBase * $settings['bhyt_employer_rate'] / 100);
        $bhtnEr = round($insuranceBase * $settings['bhtn_employer_rate'] / 100);

        $familyDeduction = (float) $settings['personal_deduction'] + $in['dependents'] * (float) $settings['dependent_deduction'];
        $taxableAfter = max($taxable - $familyDeduction - $insuranceEmployee, 0);

        // Thử việc / thời vụ: không giảm trừ gia cảnh, khấu trừ 10% khi phần vượt BH lớn hơn ngưỡng (giống file Excel).
        $flatBase = max($taxable - $insuranceEmployee, 0);
        $pit = match ($in['pit_mode']) {
            'none' => 0.0,
            'flat_10' => $flatBase > (float) $settings['flat_pit_threshold'] ? round($flatBase * 0.10) : 0.0,
            default => self::progressivePit($taxableAfter),
        };
        if ($in['pit_mode'] === 'flat_10') {
            $familyDeduction = 0.0;
            $taxableAfter = $flatBase;
        }

        $net = round($gross - $insuranceEmployee - $pit - $in['advance'] - $in['late_penalty'] - $in['other_deduction']);

        return [
            'base_pay' => round($basePay, 2),
            'decision_bonus_pay' => round($decisionBonusPay, 2),
            'travel_pay' => round($travelPay, 2),
            'kpi_pay' => round($kpiPay, 2),
            'other_income' => (float) $in['other_income'],
            'taxable_income' => $taxable,
            'meal_pay' => round($mealPay, 2),
            'phone_pay' => round($phonePay, 2),
            'trip_allowance' => (float) $in['trip_allowance'],
            'ot_amount' => (float) $in['ot_amount'],
            'non_taxable_income' => $nonTaxable,
            'gross_income' => $gross,
            'family_deduction' => $familyDeduction,
            'insurance_base' => $insuranceBase,
            'bhxh_employee' => $bhxh,
            'bhyt_employee' => $bhyt,
            'bhtn_employee' => $bhtn,
            'insurance_employee' => $insuranceEmployee,
            'bhxh_employer' => $bhxhEr,
            'bhyt_employer' => $bhytEr,
            'bhtn_employer' => $bhtnEr,
            'insurance_employer' => $bhxhEr + $bhytEr + $bhtnEr,
            'taxable_after_deduction' => $taxableAfter,
            'pit' => $pit,
            'advance' => (float) $in['advance'],
            'late_penalty' => (float) $in['late_penalty'],
            'other_deduction' => (float) $in['other_deduction'],
            'net_salary' => $net,
        ];
    }

    /** Tháng này có đóng bảo hiểm không: theo mốc nghỉ không lương (luật) và mốc ngày tính lương (tuỳ chọn). */
    public static function insuredThisMonth(float $paidDays, float $unpaidDays, array $settings): bool
    {
        $unpaidLimit = (float) ($settings['insurance_unpaid_days_limit'] ?? 0);
        if ($unpaidLimit > 0 && $unpaidDays >= $unpaidLimit) {
            return false;
        }

        return $paidDays >= (float) ($settings['insurance_min_days'] ?? 0);
    }

    public static function progressivePit(float $taxableAfter): float
    {
        if ($taxableAfter <= 0) {
            return 0.0;
        }

        foreach (self::PIT_BRACKETS as [$limit, $rate, $quickDeduction]) {
            if ($taxableAfter <= $limit) {
                return round($taxableAfter * $rate - $quickDeduction);
            }
        }

        return 0.0;
    }
}
