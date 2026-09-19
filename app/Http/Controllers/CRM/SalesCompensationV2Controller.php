<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Services\CRM\Commission\SalesCompensationV2Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesCompensationV2Controller extends Controller
{
    public function __construct(private readonly SalesCompensationV2Service $service) {}

    public function index(Request $request)
    {
        $month = $this->service->normalizeMonth($request->get('month'));
        $tab = in_array($request->get('tab'), ['overview', 'policy', 'adjustments'], true)
            ? $request->get('tab') : 'overview';

        $data = $this->service->dashboard($month, [
            'sales_id' => $request->get('sales_id'),
            'q' => $request->get('q'),
            'order_type' => $request->get('order_type'),
            'customer_group' => $request->get('customer_group'),
        ], auth()->user());

        return view('sales.compensation-v2.index', $data + [
            'tab' => $tab,
            'filters' => $request->only(['sales_id', 'q', 'order_type', 'customer_group']),
            'statusLabels' => [
                'draft' => 'Bản nháp',
                'pending' => 'Chờ duyệt',
                'approved' => 'Đã duyệt',
                'locked' => 'Đã khóa',
            ],
        ]);
    }

    public function settings(Request $request)
    {
        return redirect()->route('sales.commissions.index', [
            'month' => $this->service->normalizeMonth($request->get('month')),
            'tab' => 'policy',
        ]);
    }

    public function savePolicy(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'sales_base_salary' => ['required'],
            'sales_responsibility_allowance' => ['required'],
            'sales_travel_allowance' => ['required'],
            'sales_target_revenue' => ['required'],
            'sales_threshold_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'lead_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'member_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'retail_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'other_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'project_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'over_target_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'new_dealer_bonus' => ['required'],
            'new_dealer_min_order' => ['required'],
            'new_dealer_min_products' => ['required', 'integer', 'min:0'],
            'leader_base_salary' => ['required'],
            'leader_management_allowance' => ['required'],
            'leader_travel_mode' => ['required', 'in:fixed,percent'],
            'leader_travel_value' => ['required'],
            'leader_target_revenue' => ['required'],
            'leader_threshold_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'leader_team_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'leader_over_target_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'calculate_on' => ['required', 'in:paid_before_vat,paid_after_vat,order_before_vat'],
            'kpi_calculate_on' => ['required', 'in:order_revenue,paid_in_period,commission_base'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated += [
            'include_partial_payment' => $request->boolean('include_partial_payment'),
            'hold_if_debt' => $request->boolean('hold_if_debt'),
            'require_approved_policy' => $request->boolean('require_approved_policy'),
            'leader_personal_commission' => $request->boolean('leader_personal_commission'),
        ];

        return $this->runAction(function () use ($month, $validated): void {
            $this->service->savePolicy($month, $validated, auth()->user());
        }, $month, 'policy', 'Đã lưu chính sách thu nhập tháng '.$month.'.');
    }

    public function saveStaff(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $rows = $request->input('staff', []);

        if (! is_array($rows)) {
            $rows = [];
        }

        return $this->runAction(function () use ($month, $rows): void {
            $this->service->saveStaff($month, $rows, auth()->user());
        }, $month, 'policy', 'Đã lưu cấu hình nhân sự và KPI tháng.');
    }

    public function saveOverride(Request $request, int $orderId)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $validated = $request->validate([
            'sales_id' => ['nullable', 'integer'],
            'order_type' => ['required', 'in:distribution,project'],
            'customer_group' => ['required', 'in:lead,member,retail,other'],
            'rate_override_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_override_amount' => ['nullable'],
            'new_dealer_bonus_override' => ['nullable'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $validated += [
            'is_new_dealer' => $request->boolean('is_new_dealer'),
            'is_excluded' => $request->boolean('is_excluded'),
        ];

        return $this->runAction(function () use ($month, $orderId, $validated): void {
            $this->service->saveOverride($month, $orderId, $validated, auth()->user());
        }, $month, 'overview', 'Đã áp dụng điều chỉnh riêng cho đơn hàng.');
    }

    public function deleteOverride(Request $request, int $orderId)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(function () use ($month, $orderId): void {
            $this->service->deleteOverride($month, $orderId, auth()->user());
        }, $month, 'overview', 'Đã xóa điều chỉnh riêng của đơn hàng.');
    }

    public function addAdjustment(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'adjustment_type' => ['required', 'in:bonus,deduction,allowance,correction'],
            'amount' => ['required'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->runAction(function () use ($month, $validated): void {
            $this->service->addAdjustment($month, $validated, auth()->user());
        }, $month, 'adjustments', 'Đã thêm khoản điều chỉnh thu nhập.');
    }

    public function deleteAdjustment(Request $request, int $adjustmentId)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(function () use ($month, $adjustmentId): void {
            $this->service->deleteAdjustment($month, $adjustmentId, auth()->user());
        }, $month, 'adjustments', 'Đã xóa khoản điều chỉnh.');
    }

    public function copyPrevious(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(function () use ($month): void {
            $this->service->copyPrevious($month, auth()->user());
        }, $month, 'policy', 'Đã sao chép chính sách và cấu hình nhân sự từ tháng trước.');
    }

    public function submit(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(fn () => $this->service->submit($month, auth()->user()), $month, 'policy', 'Đã gửi chính sách cho Ban Giám đốc duyệt.');
    }

    public function approve(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(fn () => $this->service->approve($month, auth()->user()), $month, 'policy', 'Đã phê duyệt chính sách tháng.');
    }

    public function reject(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        return $this->runAction(fn () => $this->service->reject($month, $reason, auth()->user()), $month, 'policy', 'Đã trả chính sách về bản nháp.');
    }

    public function lock(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));

        return $this->runAction(fn () => $this->service->lock($month, auth()->user()), $month, 'overview', 'Đã khóa bảng thu nhập tháng và lưu snapshot.');
    }

    public function unlock(Request $request)
    {
        $month = $this->service->normalizeMonth($request->input('period_month'));
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        return $this->runAction(fn () => $this->service->unlock($month, $reason, auth()->user()), $month, 'policy', 'Đã mở khóa tháng để điều chỉnh lại.');
    }

    public function exportExcel(Request $request)
    {
        $month = $this->service->normalizeMonth($request->get('month'));
        $data = $this->service->dashboard($month, [], auth()->user());
        $spreadsheet = new Spreadsheet();

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Tổng thu nhập');
        $summaryHeaders = [
            'STT', 'Nhân sự', 'Vai trò', 'Tổng doanh thu', 'Thu trong tháng', 'Công nợ',
            'Cơ sở hoa hồng', 'Cơ sở KPI', 'Target', '% đạt', 'Lương cứng', 'Phụ cấp KPI',
            'Phụ cấp thị trường', 'Hoa hồng cá nhân', 'Hoa hồng nhóm', 'Thưởng đại lý',
            'Điều chỉnh', 'Tổng thu nhập',
        ];
        $summary->fromArray($summaryHeaders, null, 'A1');
        $rowNo = 2;
        foreach ($data['staff_rows'] as $index => $row) {
            $summary->fromArray([
                $index + 1,
                $row['name'],
                $row['role_type'] === 'leader' ? 'Team Leader' : 'Sale',
                $row['total_order_revenue'],
                $row['paid_in_period'],
                $row['debt'],
                $row['commission_base'],
                $row['kpi_revenue'],
                $row['target_revenue'],
                $row['achievement_percent'] / 100,
                $row['base_salary'],
                $row['responsibility_allowance'],
                $row['travel_allowance'],
                $row['personal_commission'],
                $row['team_commission'],
                $row['dealer_bonus'],
                $row['adjustment_total'],
                $row['total_income'],
            ], null, 'A'.$rowNo++);
        }

        $orders = $spreadsheet->createSheet();
        $orders->setTitle('Chi tiết đơn hàng');
        $orderHeaders = [
            'STT', 'Mã đơn', 'Ngày đơn', 'Ngày tính', 'Khách hàng', 'Nhóm khách', 'Sale', 'Loại đơn',
            'Giá trị đơn', 'Trước VAT', 'Đã thu lũy kế', 'Thu trong tháng', 'Công nợ',
            'Cơ sở tính', 'Cơ sở KPI', 'Tỷ lệ', 'Hoa hồng', 'Giữ lại', 'Thưởng đại lý', 'Diễn giải',
        ];
        $orders->fromArray($orderHeaders, null, 'A1');
        $rowNo = 2;
        foreach ($data['orders'] as $index => $row) {
            $orders->fromArray([
                $index + 1,
                $row['order_code'],
                $row['order_date'],
                $row['activity_date'],
                $row['customer_name'],
                $this->service->customerGroupLabel($row['customer_group']),
                $row['sales_name'],
                $row['order_type'] === 'project' ? 'Công trình' : 'Phân phối',
                $row['order_total'],
                $row['order_before_vat'],
                $row['paid_lifetime'],
                $row['paid_in_period'],
                $row['debt'],
                $row['commission_base'],
                $row['kpi_base'],
                $row['rate_percent'] / 100,
                $row['commission'],
                $row['held_commission'],
                $row['dealer_bonus'],
                $row['commission_note'],
            ], null, 'A'.$rowNo++);
        }

        foreach ([$summary, $orders] as $sheet) {
            $lastColumn = $sheet->getHighestColumn();
            $lastRow = $sheet->getHighestRow();
            $sheet->freezePane('A2');
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            for ($col = 1; $col <= Coordinate::columnIndexFromString($lastColumn); $col++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            }
        }

        if ($summary->getHighestRow() >= 2) {
            $summary->getStyle('D2:I'.$summary->getHighestRow())->getNumberFormat()->setFormatCode('#,##0 "đ"');
            $summary->getStyle('K2:R'.$summary->getHighestRow())->getNumberFormat()->setFormatCode('#,##0 "đ"');
            $summary->getStyle('J2:J'.$summary->getHighestRow())->getNumberFormat()->setFormatCode('0.00%');
        }
        if ($orders->getHighestRow() >= 2) {
            $orders->getStyle('I2:O'.$orders->getHighestRow())->getNumberFormat()->setFormatCode('#,##0 "đ"');
            $orders->getStyle('P2:P'.$orders->getHighestRow())->getNumberFormat()->setFormatCode('0.0000%');
            $orders->getStyle('Q2:S'.$orders->getHighestRow())->getNumberFormat()->setFormatCode('#,##0 "đ"');
        }

        $filename = 'thu_nhap_sales_'.$month.'_'.now()->format('Ymd_His').'.xlsx';
        $temp = storage_path('app/'.$filename);
        (new Xlsx($spreadsheet))->save($temp);

        return response()->download($temp, $filename)->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $month = $this->service->normalizeMonth($request->get('month'));
        $data = $this->service->dashboard($month, [], auth()->user());

        return Pdf::loadView('sales.compensation-v2.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('thu_nhap_sales_'.$month.'_'.now()->format('Ymd_His').'.pdf');
    }

    private function runAction(callable $callback, string $month, string $tab, string $success)
    {
        try {
            $callback();

            return redirect()->route('sales.commissions.index', ['month' => $month, 'tab' => $tab])
                ->with('success', $success);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('sales.commissions.index', ['month' => $month, 'tab' => $tab])
                ->with('error', $e->getMessage());
        }
    }
}
