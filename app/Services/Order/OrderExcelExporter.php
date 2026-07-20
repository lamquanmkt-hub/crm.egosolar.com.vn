<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\CRM\Orders\Order;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất Excel danh sách đơn hàng (PhpSpreadsheet).
 *
 * Sheet đầu là tổng quan mọi đơn theo bộ lọc; mỗi đơn có thêm 1 sheet
 * chi tiết (dòng hàng, thuế, thanh toán). Sales chỉ export đơn mình tạo.
 *
 * Tách nguyên trạng từ OrderController::exportExcel (P1a refactor) —
 * hành vi chốt bằng OrderExportExcelCharacterizationTest.
 */
class OrderExcelExporter
{
    /**
     * Dựng workbook theo bộ lọc và trả về response tải file .xlsx.
     *
     * @param  array<string, mixed>  $filters  Bộ lọc từ query string trang danh sách đơn
     * @param  User|null  $user  User hiện tại — dùng giới hạn dữ liệu theo role sales
     */
    public function download(array $filters, ?User $user): StreamedResponse
    {

        $query = Order::query()
            ->with([
                'lead.customer',
                'company',
                'warehouse',
                'items.product',
                'items.warehouse',
                'payments.method',
                'payments.recordedBy',
                'creator',
                'currentStatusType',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('sales')) {
            $query->where('created_by', $user->id);
        }

        if (! empty($filters['search'])) {
            $keyword = trim((string) $filters['search']);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%'.$keyword.'%')
                    ->orWhere('receiver_name', 'like', '%'.$keyword.'%')
                    ->orWhere('receiver_phone', 'like', '%'.$keyword.'%')
                    ->orWhereHas('lead.customer', function ($customerQuery) use ($keyword) {
                        $customerQuery->where('name', 'like', '%'.$keyword.'%')
                            ->orWhere('phone', 'like', '%'.$keyword.'%');
                    });
            });
        }

        if (! empty($filters['company_id']) && \Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'company_id')) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];

            if ($status === 'sales') {
                $query->where('current_department', 'sales');
            } elseif ($status === 'ketoan') {
                $query->whereIn('current_department', ['accounting', 'ketoan']);
            } elseif ($status === 'duyet1') {
                $query->whereIn('current_department', ['sales_manager', 'duyet1']);
            } elseif ($status === 'duyet2') {
                $query->whereIn('current_department', ['management', 'director', 'duyet2']);
            } elseif ($status === 'kho') {
                $query->whereIn('current_department', ['warehouse', 'kho']);
            } elseif ($status === 'completed') {
                $query->where('current_department', 'completed');
            } elseif ($status === 'cancelled') {
                $query->where(function ($q) {
                    $q->whereIn('current_department', ['cancelled', 'canceled', 'da_huy', 'huy'])
                        ->orWhereIn('status', ['cancelled', 'canceled', 'da_huy', 'huy']);
                });
            }
        }

        $orders = $query->get();

        if (! empty($filters['payment_filter'])) {
            $paymentFilter = (string) $filters['payment_filter'];

            $orders = $orders->filter(function ($order) use ($paymentFilter) {
                $total = (float) ($order->total_amount ?? 0);
                $paid = (float) collect($order->payments ?? [])->sum('amount');
                $debt = max($total - $paid, 0);

                if ($paymentFilter === 'paid') {
                    return $total > 0 && $debt <= 0;
                }

                if ($paymentFilter === 'unpaid') {
                    return $paid <= 0 && $total > 0;
                }

                if ($paymentFilter === 'debt') {
                    return $debt > 0;
                }

                if ($paymentFilter === 'debt_30') {
                    if ($debt <= 0 || empty($order->order_date)) {
                        return false;
                    }

                    try {
                        return \Carbon\Carbon::parse($order->order_date)->lte(now()->subDays(30));
                    } catch (\Throwable $e) {
                        return false;
                    }
                }

                return true;
            })->values();
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Danh sách đơn hàng');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $sectionStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0891B2'],
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $autoSize = function ($sheet, string $lastColumn) {
            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        };

        $moneyColumns = function ($sheet, array $columns, int $startRow, int $endRow) {
            if ($endRow < $startRow) {
                return;
            }

            foreach ($columns as $column) {
                $sheet->getStyle($column.$startRow.':'.$column.$endRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        };

        $departmentLabel = function ($department) {
            return match ((string) $department) {
                'sales' => 'Sales',
                'sales_manager' => 'Sales Manager',
                'accounting', 'ketoan' => 'Kế toán',
                'management', 'director' => 'Giám đốc',
                'warehouse', 'kho' => 'Kho',
                'shipping' => 'Vận chuyển',
                'completed' => 'Hoàn tất',
                'cancelled', 'canceled' => 'Đã hủy',
                default => $department ?: '-',
            };
        };

        $statusLabel = function ($order, float $paid, float $remain) {
            $rawStatus = strtolower(trim((string) ($order->status ?? '')));
            $rawDept = strtolower(trim((string) ($order->current_department ?? '')));
            $displayStatusRaw = strtolower(trim((string) optional($order->currentStatusType)->name));

            $cancelValues = ['cancelled', 'canceled', 'da_huy', 'huy'];

            $isCancelled =
                in_array($rawStatus, $cancelValues, true) ||
                in_array($rawDept, $cancelValues, true) ||
                str_contains($displayStatusRaw, 'hủy') ||
                str_contains($displayStatusRaw, 'cancel');

            if ($isCancelled) {
                return 'Đã hủy';
            }

            if ((int) ($order->inventory_issued ?? 0) === 1) {
                return $remain > 0 ? 'Công nợ' : 'Hoàn tất';
            }

            if (in_array($rawDept, ['warehouse', 'kho'], true)) {
                return 'Sẵn sàng xuất kho';
            }

            return optional($order->currentStatusType)->name ?? '-';
        };

        $cleanSheetTitle = function ($name, $usedTitles) {
            $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', (string) $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            if ($name === '') {
                $name = 'Don hang';
            }

            $base = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
            $title = $base;
            $i = 2;

            while (in_array($title, $usedTitles, true)) {
                $suffix = ' '.$i;
                $limit = 31 - strlen($suffix);
                $shortBase = function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit);
                $title = $shortBase.$suffix;
                $i++;
            }

            return $title;
        };

        $calculateTaxTotals = function ($order) {
            $beforeTax = 0;
            $taxAmount = 0;
            $afterTax = 0;

            foreach ($order->items ?? [] as $item) {
                $qty = (int) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discountPercent = (float) ($item->discount_percent ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $vatPercent = (float) (optional($item->product)->vat_percent ?? 0);

                $subtotal = $qty * $unitPrice;
                $discount = $discountAmount > 0
                    ? ($discountAmount * $qty)
                    : ($subtotal * $discountPercent / 100);

                $lineTotal = max($subtotal - $discount, 0);

                if ($vatPercent > 0) {
                    $lineBeforeTax = $lineTotal / (1 + ($vatPercent / 100));
                    $lineTax = $lineTotal - $lineBeforeTax;
                } else {
                    $lineBeforeTax = $lineTotal;
                    $lineTax = 0;
                }

                $beforeTax += $lineBeforeTax;
                $taxAmount += $lineTax;
                $afterTax += $lineTotal;
            }

            if ($afterTax <= 0 && (float) ($order->total_amount ?? 0) > 0) {
                $afterTax = (float) $order->total_amount;
                $beforeTax = $afterTax;
                $taxAmount = 0;
            }

            return (object) [
                'before_tax' => round($beforeTax),
                'tax_amount' => round($taxAmount),
                'after_tax' => round($afterTax),
            ];
        };

        $usedSheetTitles = ['Tong hop don hang'];
        $detailSheetTitles = [];

        foreach ($orders as $order) {
            $sheetTitle = $cleanSheetTitle($order->order_code ?: ('Don '.$order->id), $usedSheetTitles);
            $usedSheetTitles[] = $sheetTitle;
            $detailSheetTitles[(int) $order->id] = $sheetTitle;
        }

        /*
        |--------------------------------------------------------------------------
        | Sheet 1: Tổng hợp đơn hàng
        |--------------------------------------------------------------------------
        */
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Tong hop don hang');

        $summarySheet->mergeCells('A1:S1');
        $summarySheet->setCellValue('A1', 'DANH SÁCH ĐƠN HÀNG');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $summarySheet->mergeCells('A2:S2');
        $summarySheet->setCellValue('A2', 'Xuất lúc: '.now()->format('d/m/Y H:i').' | Số đơn: '.$orders->count());
        $summarySheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày đặt',
            'Khách hàng',
            'SĐT',
            'Công ty',
            'Người tạo',
            'Tổng trước thuế',
            'Thuế VAT',
            'Tổng sau thuế',
            'Đã thu',
            'Còn nợ',
            'Trạng thái',
            'Bộ phận hiện tại',
            'Hóa đơn',
            'Mã số thuế',
            'Xuất kho',
            'Vận chuyển',
            'Ghi chú',
        ];

        $summarySheet->fromArray($headers, null, 'A4');
        $summarySheet->getStyle('A4:S4')->applyFromArray($headerStyle);

        $rowNumber = 5;

        foreach ($orders as $index => $order) {
            $customer = optional(optional($order->lead)->customer);
            $paid = (float) collect($order->payments ?? [])->sum('amount');
            $total = (float) ($order->total_amount ?? 0);
            $remain = max($total - $paid, 0);
            $taxTotals = $calculateTaxTotals($order);
            $sheetTitle = $detailSheetTitles[(int) $order->id] ?? null;

            $summarySheet->fromArray([
                $index + 1,
                $order->order_code,
                $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : '',
                $customer->name ?? '-',
                $customer->phone ?? '-',
                optional($order->company)->name ?? '-',
                optional($order->creator)->name ?? '-',
                $taxTotals->before_tax,
                $taxTotals->tax_amount,
                round($total),
                round($paid),
                round($remain),
                $statusLabel($order, $paid, $remain),
                $departmentLabel($order->current_department),
                match ($order->invoice_status ?? 'none') {
                    'issued' => 'Đã xuất',
                    'pending' => 'Chờ xuất',
                    default => 'Không yêu cầu',
                },
                $order->invoice_tax_code ?: '',
                (int) ($order->inventory_issued ?? 0) === 1 ? 'Đã xuất kho' : 'Chưa xuất kho',
                $order->shipping_status ?: '',
                $order->note ?: '',
            ], null, 'A'.$rowNumber);

            if ($sheetTitle) {
                $escapedSheetTitle = str_replace("'", "''", $sheetTitle);
                $cell = 'B'.$rowNumber;

                $summarySheet->getCell($cell)
                    ->getHyperlink()
                    ->setUrl("sheet://'{$escapedSheetTitle}'!A1");

                $summarySheet->getStyle($cell)->getFont()
                    ->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE)
                    ->getColor()
                    ->setRGB('0563C1');
            }

            $rowNumber++;
        }

        $lastDataRow = $rowNumber - 1;

        if ($lastDataRow >= 5) {
            $totalRow = $rowNumber + 1;
            $summarySheet->setCellValue('A'.$totalRow, 'TỔNG');
            $summarySheet->mergeCells('A'.$totalRow.':G'.$totalRow);
            $summarySheet->setCellValue('H'.$totalRow, '=SUM(H5:H'.$lastDataRow.')');
            $summarySheet->setCellValue('I'.$totalRow, '=SUM(I5:I'.$lastDataRow.')');
            $summarySheet->setCellValue('J'.$totalRow, '=SUM(J5:J'.$lastDataRow.')');
            $summarySheet->setCellValue('K'.$totalRow, '=SUM(K5:K'.$lastDataRow.')');
            $summarySheet->setCellValue('L'.$totalRow, '=SUM(L5:L'.$lastDataRow.')');

            $summarySheet->getStyle('A4:S'.$totalRow)->applyFromArray($cellStyle);
            $summarySheet->getStyle('A'.$totalRow.':S'.$totalRow)->getFont()->setBold(true);
            $moneyColumns($summarySheet, ['H', 'I', 'J', 'K', 'L'], 5, $totalRow);
            $summarySheet->getStyle('S5:S'.$lastDataRow)->getAlignment()->setWrapText(true);
        }

        $summarySheet->freezePane('A5');
        $autoSize($summarySheet, 'S');

        /*
        |--------------------------------------------------------------------------
        | Sheet từng đơn hàng
        |--------------------------------------------------------------------------
        */
        foreach ($orders as $order) {
            $customer = optional(optional($order->lead)->customer);
            $paid = (float) collect($order->payments ?? [])->sum('amount');
            $total = (float) ($order->total_amount ?? 0);
            $remain = max($total - $paid, 0);
            $taxTotals = $calculateTaxTotals($order);

            $sheetTitle = $detailSheetTitles[(int) $order->id] ?? $cleanSheetTitle($order->order_code ?: ('Don '.$order->id), []);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            $sheet->mergeCells('A1:I1');
            $sheet->setCellValue('A1', 'CHI TIẾT ĐƠN HÀNG - '.($order->order_code ?? $order->id));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('A2', '← Về tổng hợp');
            $sheet->getCell('A2')->getHyperlink()->setUrl("sheet://'Tong hop don hang'!A1");
            $sheet->getStyle('A2')->getFont()
                ->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE)
                ->getColor()
                ->setRGB('0563C1');

            $currentRow = 4;

            $writeSection = function ($title, array $items) use ($sheet, &$currentRow, $headerStyle, $sectionStyle, $cellStyle) {
                $sheet->mergeCells('A'.$currentRow.':I'.$currentRow);
                $sheet->setCellValue('A'.$currentRow, $title);
                $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($sectionStyle);
                $currentRow++;

                $sheet->fromArray(['Hạng mục', 'Giá trị', 'Ghi chú', '', '', '', '', '', ''], null, 'A'.$currentRow);
                $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($headerStyle);
                $currentRow++;

                $startItemRow = $currentRow;

                foreach ($items as $item) {
                    $sheet->fromArray([
                        $item[0] ?? '',
                        $item[1] ?? '',
                        $item[2] ?? '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    ], null, 'A'.$currentRow);

                    $currentRow++;
                }

                $sheet->getStyle('A'.($startItemRow - 2).':I'.($currentRow - 1))->applyFromArray($cellStyle);
                $currentRow++;
            };

            $writeSection('1. Thông tin đơn hàng', [
                ['Mã đơn', $order->order_code, ''],
                ['Ngày đặt', $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : '', ''],
                ['Công ty', optional($order->company)->name ?? '-', ''],
                ['Người tạo', optional($order->creator)->name ?? '-', ''],
                ['Trạng thái', $statusLabel($order, $paid, $remain), ''],
                ['Bộ phận hiện tại', $departmentLabel($order->current_department), ''],
                ['Xuất kho', (int) ($order->inventory_issued ?? 0) === 1 ? 'Đã xuất kho' : 'Chưa xuất kho', ''],
                ['Vận chuyển', $order->shipping_status ?: '', ''],
            ]);

            $writeSection('2. Khách hàng & giao hàng', [
                ['Khách hàng', $customer->name ?? '-', ''],
                ['Số điện thoại', $customer->phone ?? '-', ''],
                ['Người nhận', $order->receiver_name ?: '', ''],
                ['SĐT người nhận', $order->receiver_phone ?: '', ''],
                ['Địa chỉ giao hàng', $order->shipping_address ?: '', ''],
                ['Đơn vị vận chuyển', $order->shipping_carrier ?: '', ''],
                ['Mã vận đơn', $order->tracking_number ?: '', ''],
                ['Ghi chú giao hàng', $order->shipping_note ?: '', ''],
            ]);

            $writeSection('3. Thanh toán, thuế & hóa đơn', [
                ['Tổng trước thuế', $taxTotals->before_tax, 'Tính theo VAT sản phẩm nếu có'],
                ['Thuế VAT', $taxTotals->tax_amount, 'Tổng tiền thuế VAT'],
                ['Tổng sau thuế', round($total), 'Tổng tiền đơn hàng'],
                ['Đã thu', round($paid), ''],
                ['Còn nợ', round($remain), ''],
                ['Trạng thái hóa đơn', match ($order->invoice_status ?? 'none') {
                    'issued' => 'Đã xuất',
                    'pending' => 'Chờ xuất',
                    default => 'Không yêu cầu',
                }, ''],
                ['Tên công ty xuất HĐ', $order->invoice_company_name ?: '', ''],
                ['Mã số thuế', $order->invoice_tax_code ?: '', ''],
                ['Địa chỉ hóa đơn', $order->invoice_address ?: '', ''],
                ['Email nhận hóa đơn', $order->invoice_email ?: '', ''],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Bảng sản phẩm
            |--------------------------------------------------------------------------
            */
            $sheet->mergeCells('A'.$currentRow.':I'.$currentRow);
            $sheet->setCellValue('A'.$currentRow, '4. Sản phẩm trong đơn');
            $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($sectionStyle);
            $currentRow++;

            $sheet->fromArray([
                'STT',
                'Sản phẩm',
                'Kho',
                'Số lượng',
                'Đơn giá',
                'VAT %',
                'Trước thuế',
                'Thuế VAT',
                'Thành tiền',
            ], null, 'A'.$currentRow);

            $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($headerStyle);
            $currentRow++;

            $itemStartRow = $currentRow;

            foreach ($order->items ?? [] as $itemIndex => $item) {
                $qty = (int) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discountPercent = (float) ($item->discount_percent ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $vatPercent = (float) (optional($item->product)->vat_percent ?? 0);

                $subtotal = $qty * $unitPrice;
                $discount = $discountAmount > 0
                    ? ($discountAmount * $qty)
                    : ($subtotal * $discountPercent / 100);

                $lineTotal = max($subtotal - $discount, 0);

                if ($vatPercent > 0) {
                    $lineBeforeTax = $lineTotal / (1 + ($vatPercent / 100));
                    $lineTax = $lineTotal - $lineBeforeTax;
                } else {
                    $lineBeforeTax = $lineTotal;
                    $lineTax = 0;
                }

                $sheet->fromArray([
                    $itemIndex + 1,
                    optional($item->product)->name ?? ('SP #'.($item->product_id ?? '')),
                    optional($item->warehouse)->name ?? '-',
                    $qty,
                    round($unitPrice),
                    $vatPercent,
                    round($lineBeforeTax),
                    round($lineTax),
                    round($lineTotal),
                ], null, 'A'.$currentRow);

                $currentRow++;
            }

            if ($currentRow === $itemStartRow) {
                $sheet->mergeCells('A'.$currentRow.':I'.$currentRow);
                $sheet->setCellValue('A'.$currentRow, 'Không có sản phẩm.');
                $currentRow++;
            }

            $sheet->getStyle('A'.($itemStartRow - 1).':I'.($currentRow - 1))->applyFromArray($cellStyle);
            $moneyColumns($sheet, ['E', 'G', 'H', 'I'], $itemStartRow, $currentRow - 1);
            $currentRow++;

            /*
            |--------------------------------------------------------------------------
            | Bảng thanh toán
            |--------------------------------------------------------------------------
            */
            $sheet->mergeCells('A'.$currentRow.':I'.$currentRow);
            $sheet->setCellValue('A'.$currentRow, '5. Lịch sử thanh toán');
            $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($sectionStyle);
            $currentRow++;

            $sheet->fromArray([
                'STT',
                'Ngày thanh toán',
                'Phương thức',
                'Số tiền',
                'Người ghi nhận',
                'Ghi chú',
                '',
                '',
                '',
            ], null, 'A'.$currentRow);

            $sheet->getStyle('A'.$currentRow.':I'.$currentRow)->applyFromArray($headerStyle);
            $currentRow++;

            $paymentStartRow = $currentRow;

            foreach ($order->payments ?? [] as $paymentIndex => $payment) {
                $sheet->fromArray([
                    $paymentIndex + 1,
                    $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '',
                    optional($payment->method)->name ?? '-',
                    round((float) ($payment->amount ?? 0)),
                    optional($payment->recordedBy)->name ?? '-',
                    $payment->note ?: '',
                    '',
                    '',
                    '',
                ], null, 'A'.$currentRow);

                $currentRow++;
            }

            if ($currentRow === $paymentStartRow) {
                $sheet->mergeCells('A'.$currentRow.':I'.$currentRow);
                $sheet->setCellValue('A'.$currentRow, 'Chưa có thanh toán.');
                $currentRow++;
            }

            $sheet->getStyle('A'.($paymentStartRow - 1).':I'.($currentRow - 1))->applyFromArray($cellStyle);
            $moneyColumns($sheet, ['D'], $paymentStartRow, $currentRow - 1);

            $sheet->getStyle('B1:I'.$currentRow)
                ->getAlignment()
                ->setWrapText(true);

            $sheet->freezePane('A4');
            $autoSize($sheet, 'I');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'danh-sach-don-hang-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
