<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Import verified Excel values without recalculating them or creating login accounts. */
final class PayrollSnapshotImporter
{
    public function import(array $source, int $actorId): object
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $source['month'] ?? '')
            || ! preg_match('/^[a-f0-9]{64}$/', $source['source_sha256'] ?? '')
            || empty($source['records']) || ! empty($source['source_errors'])
            || ! is_numeric($source['standard_days'] ?? null) || $source['standard_days'] <= 0) {
            throw new RuntimeException('Nguồn nhập bảng lương không hợp lệ.');
        }

        $lines = [];
        $codes = [];
        $userIds = [];
        foreach ($source['records'] as $record) {
            if (! empty($record['errors']) || empty($record['profile']) || ! empty($record['profile']['errors'])
                || empty($record['attendance']) || ! empty($record['attendance']['errors'])) {
                throw new RuntimeException('Thiếu hoặc lỗi dữ liệu Excel ở dòng '.($record['payroll_row'] ?? '?').'.');
            }
            $code = preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $record['code'])));
            if ($code === '' || isset($codes[$code])) {
                throw new RuntimeException('Mã nhân viên trống hoặc bị trùng.');
            }
            $codes[$code] = true;
            $uid = $record['user']['id'] ?? null;
            if ($uid !== null) {
                $user = DB::table('users')->where('id', $uid)->first(['id', 'name']);
                if (! $user || $this->nameKey($user->name) !== $this->nameKey($record['name']) || isset($userIds[$uid])) {
                    throw new RuntimeException('Liên kết nhân viên không khớp họ tên đầy đủ.');
                }
                $userIds[$uid] = true;
            }
            $values = $record['values'];
            foreach ([$values, $record['profile']['values'], $record['attendance']['values']] as $numbers) {
                foreach ($numbers as $number) {
                    if (! is_numeric($number) || ! is_finite((float) $number)) {
                        throw new RuntimeException('Có số tiền hoặc ngày công chưa xác định.');
                    }
                }
            }
            $this->equal($values['taxable_income'], $values['base_pay'] + $values['decision_bonus_pay'] + $values['travel_pay'] + $values['kpi_pay'] + $values['other_income']);
            $this->equal($values['non_taxable_income'], $values['meal_pay'] + $values['phone_pay'] + $values['extra_pay']);
            $this->equal($values['extra_pay'], $values['trip_allowance'] + $values['ot_amount']);
            $this->equal($values['gross_income'], $values['taxable_income'] + $values['non_taxable_income']);
            $this->equal($values['insurance_employee'], $values['bhxh_employee'] + $values['bhyt_employee'] + $values['bhtn_employee']);
            $this->equal($values['insurance_employer'], $values['bhxh_employer'] + $values['bhyt_employer'] + $values['bhtn_employer']);
            $this->equal($values['net_salary'], $values['gross_income'] - $values['insurance_employee'] - $values['pit'] - $values['advance'] - $values['late_penalty'] - $values['other_deduction']);
            $this->equal($values['paid_days'], $record['attendance']['values']['paid_days']);
            $profile = $record['profile']['values'];
            $this->equal($profile['standard_days'], $source['standard_days']);
            $this->equal($profile['paid_days'], $values['paid_days']);
            unset($values['extra_pay']);
            $position = trim((string) $record['profile']['position']);
            $department = $record['user']['department'] ?? $this->departmentFor($position);
            unset($profile['standard_days'], $profile['paid_days'], $profile['dependents']);
            $lines[] = $values + $profile + $record['attendance']['values'] + [
                'user_id' => $uid,
                'employee_name' => trim($record['name']),
                'employee_code' => $code,
                'position_name' => $position,
                'department_name' => $department,
                'standard_days' => $source['standard_days'],
                'pit_mode' => $values['family_deduction'] > 0 ? 'progressive' : 'flat_10',
                'kpi_percent' => null,
                'kpi_rate' => null,
                'kpi_label' => 'Tiền KPI theo Excel; chưa có điểm chấm',
                'warnings' => $uid === null ? json_encode(['Chờ liên kết nhân viên CRM'], JSON_UNESCAPED_UNICODE) : null,
                'note' => 'Nhập từ '.$source['source_name'].' · '.$source['payroll_sheet'].' · dòng '.$record['payroll_row'].'. Phòng ban theo chức vụ hoặc hồ sơ CRM; chưa có điểm KPI, số lần trễ và giờ tăng ca chi tiết.',
            ];
        }

        return DB::transaction(function () use ($source, $lines, $actorId) {
            $existing = DB::table('hr_payroll_periods')->where('payroll_month', $source['month'])->lockForUpdate()->first();
            if ($existing) {
                $meta = json_decode((string) $existing->settings_snapshot, true);
                if (($meta['import']['source_sha256'] ?? null) !== $source['source_sha256']) {
                    throw new RuntimeException('Đã có bảng lương tháng này; không ghi đè.');
                }
                $this->verifyLines((int) $existing->id, $lines);

                return $existing;
            }
            $id = DB::table('hr_payroll_periods')->insertGetId([
                'payroll_month' => $source['month'], 'status' => 'draft',
                'standard_days' => $source['standard_days'],
                'settings_snapshot' => json_encode(['import' => [
                    'mode' => 'excel_snapshot', 'source_name' => $source['source_name'],
                    'source_sha256' => $source['source_sha256'], 'source_sheet' => $source['payroll_sheet'],
                    'source_rows' => array_column($source['records'], 'payroll_row'),
                    'imported_at' => now()->toIso8601String(), 'linked' => count(array_filter($lines, fn ($line) => $line['user_id'] !== null)),
                    'missing_details' => ['kpi_percent', 'kpi_rate', 'late_count', 'ot_hours'],
                ]], JSON_UNESCAPED_UNICODE),
                'created_by' => $actorId,
                'note' => 'Bảng lương lịch sử nhập từ Excel để đối chiếu. Chưa chuyển sang tính lương tự động từ dữ liệu ERP.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::table('hr_payroll_lines')->insert($line + ['period_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->verifyLines($id, $lines);

            return DB::table('hr_payroll_periods')->where('id', $id)->first();
        });
    }

    private function verifyLines(int $periodId, array $expected): void
    {
        $stored = DB::table('hr_payroll_lines')->where('period_id', $periodId)->get()->keyBy('employee_code');
        if ($stored->count() !== count($expected)) {
            throw new RuntimeException('Số dòng sau nhập không khớp nguồn.');
        }
        foreach ($expected as $line) {
            $actual = $stored->get($line['employee_code']);
            if (! $actual) {
                throw new RuntimeException('Thiếu nhân viên sau nhập.');
            }
            foreach ($line as $key => $value) {
                $equal = $key === 'warnings'
                    ? json_decode((string) $actual->{$key}, true) === json_decode((string) $value, true)
                    : (is_numeric($value) ? abs((float) $actual->{$key} - (float) $value) <= 0.011 : $actual->{$key} === $value);
                if (! $equal) {
                    throw new RuntimeException('Số liệu sau nhập không khớp: '.$line['employee_code'].' / '.$key.'.');
                }
            }
        }
    }

    private function nameKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function equal(float $actual, float $expected): void
    {
        if (abs($actual - $expected) > 1.01) {
            throw new RuntimeException('Số liệu Excel không cân: '.$actual.' / '.$expected.'.');
        }
    }

    private function departmentFor(string $position): ?string
    {
        $key = mb_strtolower($position);

        return match (true) {
            str_contains($key, 'giám đốc') => 'Ban giám đốc',
            str_contains($key, 'kỹ') => 'Kỹ Thuật',
            str_contains($key, 'kinh doanh'), str_contains($key, 'kd') => 'Kinh doanh',
            default => null,
        };
    }
}
