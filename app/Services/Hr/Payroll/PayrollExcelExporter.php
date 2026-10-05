<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Xuất bảng lương tháng ra Excel, thứ tự cột theo sheet "2. CHI TIỀN LƯƠNG" của file lương cũ. */
final class PayrollExcelExporter
{
    /** [tiêu đề, cột dòng lương hoặc closure, là tiền?] */
    private function columns(): array
    {
        return [
            ['STT', null, false],
            ['Họ và tên', 'employee_name', false],
            ['Mã NV', 'employee_code', false],
            ['Phòng ban', 'department_name', false],
            ['Chức vụ', 'position_name', false],
            ['Ngày công chuẩn', 'standard_days', false],
            ['Ngày tính lương', 'paid_days', false],
            ['Số người phụ thuộc', 'dependents', false],
            ['Lương cơ bản theo ngày công', 'base_pay', true],
            ['Thưởng theo QĐ', 'decision_bonus_pay', true],
            ['Hỗ trợ đi lại', 'travel_pay', true],
            ['% KPI', fn ($l) => $l->kpi_percent !== null ? (float) $l->kpi_percent / 100 : null, false],
            ['Hệ số lương KPI', fn ($l) => $l->kpi_rate !== null ? (float) $l->kpi_rate : null, false],
            ['Hiệu suất công việc (lương KPI)', 'kpi_pay', true],
            ['Khác (chuyên cần, sinh nhật)', 'other_income', true],
            ['Tổng thu nhập chịu thuế', 'taxable_income', true],
            ['Tiền ăn', 'meal_pay', true],
            ['Hỗ trợ điện thoại', 'phone_pay', true],
            ['Tăng ca, công tác tỉnh', fn ($l) => (float) $l->ot_amount + (float) $l->trip_allowance, true],
            ['Tổng thu nhập không chịu thuế', 'non_taxable_income', true],
            ['TỔNG THU NHẬP', 'gross_income', true],
            ['Giảm trừ cá nhân & người phụ thuộc', 'family_deduction', true],
            ['Lương tham gia bảo hiểm', 'insurance_base', true],
            ['BHXH NLĐ', 'bhxh_employee', true],
            ['BHYT NLĐ', 'bhyt_employee', true],
            ['BHTN NLĐ', 'bhtn_employee', true],
            ['Cộng BH NLĐ', 'insurance_employee', true],
            ['BHXH Cty', 'bhxh_employer', true],
            ['BHYT Cty', 'bhyt_employer', true],
            ['BHTN Cty', 'bhtn_employer', true],
            ['Cộng BH Cty', 'insurance_employer', true],
            ['Thu nhập tính thuế TNCN', 'taxable_after_deduction', true],
            ['Thuế TNCN', 'pit', true],
            ['Tạm ứng', 'advance', true],
            ['Phạt đi trễ (chấm công)', 'late_penalty', true],
            ['Trừ khác', 'other_deduction', true],
            ['THỰC LĨNH', 'net_salary', true],
            ['Ghi chú', 'note', false],
        ];
    }

    public function download(object $period): StreamedResponse
    {
        $lines = DB::table('hr_payroll_lines')->where('period_id', $period->id)->orderBy('employee_name')->get();
        [$year, $month] = explode('-', (string) $period->payroll_month);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Bang luong');
        $columns = $this->columns();
        $last = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setCellValue('A1', 'BẢNG TÍNH - THANH TOÁN TIỀN LƯƠNG THÁNG '.(int) $month.'/'.$year);
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $period->status === 'approved'
            ? 'Đã duyệt lúc '.date('d/m/Y H:i', strtotime((string) $period->approved_at))
            : 'BẢN NHÁP — chưa duyệt');
        $sheet->mergeCells("A2:{$last}2");

        $headerRow = 4;
        foreach ($columns as $i => [$title]) {
            $sheet->setCellValue([$i + 1, $headerRow], $title);
        }
        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF6FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(45);

        $row = $headerRow + 1;
        foreach ($lines as $n => $line) {
            foreach ($columns as $i => [, $source]) {
                $value = match (true) {
                    $source === null => $n + 1,
                    $source instanceof \Closure => $source($line),
                    default => $line->{$source},
                };
                $sheet->setCellValue([$i + 1, $row], is_numeric($value) ? (float) $value : $value);
            }
            $row++;
        }

        $first = $headerRow + 1;
        $end = max($row - 1, $first);
        $sheet->setCellValue([2, $row], 'TỔNG CỘNG');
        foreach ($columns as $i => [, , $isMoney]) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            if ($isMoney) {
                $sheet->setCellValue("{$col}{$row}", "=SUM({$col}{$first}:{$col}{$end})");
                $sheet->getStyle("{$col}{$first}:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }
        $sheet->getStyle("L{$first}:L{$end}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("M{$first}:M{$end}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$last}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getColumnDimension('B')->setWidth(28);
        foreach (range(3, count($columns)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(15);
        }
        $sheet->freezePane('C'.($headerRow + 1));

        $days = $spreadsheet->createSheet()->setTitle('Ngay cong');
        $days->fromArray(['Họ và tên', 'Mã NV', 'Công chuẩn', 'Đi làm / công tác', 'Nghỉ phép hưởng lương', 'Nghỉ lễ', 'Không lương / vắng', 'Ngày tính lương', 'Số lần đi trễ', 'Giờ tăng ca đã duyệt'], null, 'A1');
        $days->getStyle('A1:J1')->getFont()->setBold(true);
        $r = 2;
        foreach ($lines as $line) {
            $days->fromArray([$line->employee_name, $line->employee_code, (float) $line->standard_days, (float) $line->worked_days, (float) $line->paid_leave_days, (float) $line->holiday_days, (float) $line->unpaid_days, (float) $line->paid_days, (int) $line->late_count, (float) $line->ot_hours], null, 'A'.$r++);
        }
        $days->getColumnDimension('A')->setWidth(28);

        $filename = 'bang-luong-'.$period->payroll_month.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
