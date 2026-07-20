<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xuất Excel danh sách nhập kho theo lô (trang /products/input).
 *
 * Tách nguyên trạng từ ProductController::exportStockLotInputExcel
 * (P1b refactor) — hành vi chốt bằng ProductPagesCharacterizationTest.
 */
class ProductStockLotExcelExporter
{
    public function __construct(
        private readonly ProductStockLotQueryService $lotQuery,
    ) {}

    /**
     * Xuất Excel danh sách lô hàng còn tồn (trang nhập kho) theo bộ lọc từ khóa/danh mục/thương hiệu/công ty/kho.
     */
    public function exportStockLotInputExcel(
        string $keyword,
        ?int $categoryId,
        ?int $brandId,
        ?int $companyId,
        ?int $warehouseId
    ) {
        $productTable = (new Product)->getTable();
        $costAfterExpr = $this->lotQuery->stockLotActualCostExpr('l', 'p');

        $q = DB::table('crm_product_stock_lots as l')
            ->join($productTable.' as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
            ->leftJoin('crm_product_categories as cat', 'cat.id', '=', 'p.category_id')
            ->leftJoin('crm_brands as b', 'b.id', '=', 'p.brand_id')
            ->where('l.qty_remaining', '>', 0);

        if (Schema::hasColumn($productTable, 'is_active')) {
            $q->where(function ($activeQuery) {
                $activeQuery->where('p.is_active', 1)->orWhereNull('p.is_active');
            });
        }

        if ($keyword !== '') {
            $q->where(function ($wq) use ($keyword) {
                $wq->where('p.name', 'like', "%{$keyword}%")
                    ->orWhere('p.sku', 'like', "%{$keyword}%")
                    ->orWhere('l.lot_code', 'like', "%{$keyword}%")
                    ->orWhere('l.lot_name', 'like', "%{$keyword}%")
                    ->orWhere('w.name', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where('p.category_id', $categoryId);
        }
        if ($brandId) {
            $q->where('p.brand_id', $brandId);
        }
        if ($warehouseId) {
            $q->where('l.warehouse_id', $warehouseId);
        } elseif ($companyId) {
            $q->where('l.company_id', $companyId);
        }

        $rows = $q->select([
            'p.name',
            'p.sku',
            'p.note',
            'cat.name as category_name',
            'b.name as brand_name',
            'c.name as company_name',
            'w.name as warehouse_name',
            'l.lot_code',
            'l.lot_name',
            'l.received_at',
            'l.qty_in',
            'l.qty_remaining',
            'l.cost_before_vat',
            'l.cost_vat_percent',
            'l.cost_after_vat',
            'l.extra_cost',
            DB::raw("{$costAfterExpr} as actual_cost_after_vat"),
            DB::raw('(l.qty_remaining * '.$costAfterExpr.') as total_amount'),
        ])
            ->orderBy('p.name')
            ->orderBy('p.sku')
            ->orderByRaw('COALESCE(l.received_at, l.created_at) ASC')
            ->orderBy('l.id')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Tồn kho theo dòng nhập');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ton kho theo dong');

        $sheet->mergeCells('A1:R1');
        $sheet->setCellValue('A1', 'DANH SÁCH TỒN KHO THEO TỪNG DÒNG NHẬP / SKU / GIÁ VỐN');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:R2');
        $sheet->setCellValue('A2', 'Xuất lúc: '.now()->format('d/m/Y H:i').' | Tổng dòng tồn: '.$rows->count());
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $headers = [
            'STT', 'Tên sản phẩm', 'SKU', 'Tên lô', 'Mã lô', 'Công ty', 'Kho', 'Ngày nhập',
            'SL nhập', 'SL còn', 'Giá vốn trước VAT', 'VAT %', 'Giá vốn sau VAT',
            'Chi phí riêng', 'Giá vốn thực tế / cái', 'Tổng vốn thực tế', 'Danh mục', 'Thương hiệu',
        ];

        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:R4')->getFont()->setBold(true);
        $sheet->getStyle('A4:R4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EAF6FF');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $sheet->fromArray([
                $index + 1,
                $row->name,
                $row->sku,
                $row->lot_name ?: '-',
                $row->lot_code ?: '-',
                $row->company_name ?: '-',
                $row->warehouse_name ?: '-',
                $row->received_at ? \Carbon\Carbon::parse($row->received_at)->format('d/m/Y') : '-',
                (int) $row->qty_in,
                (int) $row->qty_remaining,
                round((float) $row->cost_before_vat),
                (float) $row->cost_vat_percent,
                round((float) $row->cost_after_vat),
                round((float) $row->extra_cost),
                round((float) $row->actual_cost_after_vat),
                round((float) $row->total_amount),
                $row->category_name ?: '-',
                $row->brand_name ?: '-',
            ], null, 'A'.$rowNumber);

            $rowNumber++;
        }

        $lastRow = max($rowNumber - 1, 4);
        $sheet->getStyle('A4:R'.$lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        foreach (['K', 'M', 'N', 'O', 'P'] as $column) {
            $sheet->getStyle($column.'5:'.$column.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'R') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        $fileName = 'ton-kho-theo-dong-nhap-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
