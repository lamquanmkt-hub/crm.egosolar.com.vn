<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Tham số tính lương. Mặc định khớp file Excel lương EGO tháng 8/2026; HR/Kế toán sửa ở trang Cài đặt lương.
 * Mỗi kỳ lương chụp lại tham số lúc tính (settings_snapshot) để kỳ đã duyệt không đổi khi đổi cài đặt.
 */
final class PayrollSettings
{
    public const DEFAULTS = [
        'personal_deduction' => 15500000,      // Giảm trừ bản thân / tháng
        'dependent_deduction' => 6200000,      // Giảm trừ mỗi người phụ thuộc / tháng
        'bhxh_employee_rate' => 8,             // %
        'bhyt_employee_rate' => 1.5,
        'bhtn_employee_rate' => 1,
        'bhxh_employer_rate' => 17.5,
        'bhyt_employer_rate' => 3,
        'bhtn_employer_rate' => 1,
        'insurance_salary_cap' => 46800000,    // Trần lương đóng BH = 20 × lương cơ sở 2.340.000
        'insurance_unpaid_days_limit' => 14,   // Luật BHXH: nghỉ không lương từ số ngày này trở lên => không đóng BH tháng đó
        'insurance_min_days' => 0,             // Thêm điều kiện riêng: ngày tính lương dưới mức này => không đóng (file Excel cũ dùng 15; 0 = bỏ)
        'flat_pit_threshold' => 5000000,       // Khấu trừ 10% khi thu nhập tính thuế vượt mức này (thử việc / thời vụ), giống file Excel
        'ot_auto' => 0,                        // 1 = tự tính tiền tăng ca từ đơn đã duyệt; 0 = HR nhập tay (như file Excel)
        'ot_rate_weekday' => 150,              // % lương giờ — ngày làm việc (Điều 98 BLLĐ tối thiểu 150%)
        'ot_rate_restday' => 200,              // % lương giờ — ngày nghỉ hằng tuần (tối thiểu 200%)
        'ot_rate_holiday' => 300,              // % lương giờ — ngày lễ, Tết (tối thiểu 300%)
        'paid_leave_types' => 'annual',        // Loại nghỉ phép được hưởng lương (phân tách bằng dấu phẩy)
    ];

    public const LABELS = [
        'personal_deduction' => 'Giảm trừ bản thân (VNĐ/tháng)',
        'dependent_deduction' => 'Giảm trừ mỗi người phụ thuộc (VNĐ/tháng)',
        'bhxh_employee_rate' => 'BHXH người lao động (%)',
        'bhyt_employee_rate' => 'BHYT người lao động (%)',
        'bhtn_employee_rate' => 'BHTN người lao động (%)',
        'bhxh_employer_rate' => 'BHXH công ty đóng (%)',
        'bhyt_employer_rate' => 'BHYT công ty đóng (%)',
        'bhtn_employer_rate' => 'BHTN công ty đóng (%)',
        'insurance_salary_cap' => 'Trần lương đóng bảo hiểm (VNĐ)',
        'insurance_unpaid_days_limit' => 'Không đóng BH khi nghỉ không lương từ (ngày) — luật: 14',
        'insurance_min_days' => 'Không đóng BH khi ngày tính lương dưới (ngày) — 0 = không áp dụng',
        'flat_pit_threshold' => 'Ngưỡng khấu trừ thuế 10% (lao động thời vụ / thử việc)',
        'ot_auto' => 'Tự tính tiền tăng ca (1 = có, 0 = HR nhập tay)',
        'ot_rate_weekday' => 'Tăng ca ngày làm việc (% lương giờ)',
        'ot_rate_restday' => 'Tăng ca ngày nghỉ hằng tuần (% lương giờ)',
        'ot_rate_holiday' => 'Tăng ca ngày lễ, Tết (% lương giờ)',
        'paid_leave_types' => 'Loại nghỉ phép hưởng lương',
    ];

    public const LEAVE_TYPES = [
        'annual' => 'Nghỉ phép năm',
        'personal' => 'Nghỉ việc riêng',
        'sick' => 'Nghỉ ốm (BHXH chi trả)',
        'unpaid' => 'Nghỉ không lương',
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $values = self::DEFAULTS;

        if (SchemaCache::hasTable('hr_payroll_settings')) {
            $stored = DB::table('hr_payroll_settings')->pluck('setting_value', 'setting_key');
            foreach ($values as $key => $default) {
                if ($stored->has($key) && $stored->get($key) !== null && $stored->get($key) !== '') {
                    $values[$key] = is_numeric($default) ? (float) $stored->get($key) : (string) $stored->get($key);
                }
            }
        }

        return $values;
    }

    /** @return array<int, string> */
    public static function paidLeaveTypes(array $settings): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($settings['paid_leave_types'] ?? '')))));
    }

    public static function save(array $input, ?int $userId): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = is_array($input[$key]) ? implode(',', $input[$key]) : (string) $input[$key];
            DB::table('hr_payroll_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
