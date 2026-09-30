<?php

namespace App\Http\Controllers\Inventory;

use App\Contracts\Services\StockLotServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Brand;
use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Catalog\ProductCategory;
use App\Models\Inventory\Stock\ProductStock;
use App\Services\Inventory\ProductCatalogOptionsService;
use App\Services\Inventory\ProductStockLotExcelExporter;
use App\Services\Inventory\ProductStockLotQueryService;
use App\Services\Inventory\Stock\StockLotService;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Controller quản lý sản phẩm và tồn kho: danh mục, nhập/xuất kho, lô hàng FIFO, serial, bảng giá theo tier và lịch sử kho.
 */
class ProductController extends Controller
{
    /**
     * Khởi tạo controller, inject service truy vấn lô/tồn, nạp options catalog và exporter.
     */
    public function __construct(
        private readonly ProductStockLotQueryService $lotQuery,
        private readonly ProductCatalogOptionsService $catalogOptions,
        private readonly ProductStockLotExcelExporter $lotExporter,
    ) {}

    /**
     * EGO: Lay SKU goc tu SKU bi sinh loi dang ABC-LOT20260508193129-1
     */
    private function egoBaseSkuFromLotSku(string $sku): string
    {
        return preg_replace('/-LOT20[0-9]{12,14}(?:-[0-9]+)?$/', '', trim($sku));
    }

    /**
     * LIST
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->get('search', ''));
        // Hệ thống hiện chỉ vận hành kho của Công ty Quốc Tế EGO.
        // Không nhận company_id từ URL để tránh truy cập chéo dữ liệu EGO VN.
        $companyId = EgoCompanyLock::id();
        $companyMode = (string) $companyId;
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $categoryId = $request->filled('category_id') ? (int) $request->get('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->get('brand_id') : null;
        $priceTierId = $request->filled('price_tier_id') ? (int) $request->get('price_tier_id') : null;

        if ($warehouseId && ! Warehouse::withoutGlobalScopes()
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->exists()) {
            $warehouseId = null;
        }

        if (Schema::hasTable('crm_product_stock_lots')) {
            $products = $this->lotQuery->paginateStockLotIndexRows($keyword, $categoryId, $brandId, $companyId, $warehouseId);

            $companies = DB::table('companies')->select('id', 'name')->where('id', EgoCompanyLock::id())->get();
            $categories = $this->catalogOptions->buildCategoryOptions();

            $warehousesQ = Warehouse::query()->orderBy('name');
            if ($companyId) {
                $warehousesQ->whereIn('id', function ($qq) use ($companyId) {
                    $qq->from('company_warehouse')
                        ->select('warehouse_id')
                        ->where('company_id', $companyId);
                });
            }
            $warehouses = $warehousesQ->get();

            $brands = Brand::query()->orderBy('name')->get();
            $priceTiers = $this->catalogOptions->loadPriceTiers();
            $this->catalogOptions->attachTierPricesToProducts($products);

            $totalsAll = $this->lotQuery->calcTotalsAllPages($keyword, $categoryId, $brandId, $companyId, $warehouseId);
            $totalQtyAll = $totalsAll->total_qty;
            $totalAmountAll = $totalsAll->total_amount;

            return view('products.index', compact(
                'products',
                'companies',
                'categories',
                'warehouses',
                'brands',
                'priceTiers',
                'companyMode',
                'companyId',
                'keyword',
                'warehouseId',
                'categoryId',
                'brandId',
                'priceTierId',
                'totalQtyAll',
                'totalAmountAll'
            ));
        }

        $q = Product::query();

        if (Schema::hasColumn((new Product)->getTable(), 'is_active')) {
            $q->where(function ($activeQuery) {
                $activeQuery->where('is_active', 1)->orWhereNull('is_active');
            });
        }

        // load relations an toàn
        try {
            $q->with(['mainImage']);
        } catch (\Throwable $e) {
        }
        try {
            $q->with(['brand', 'category', 'stocks.warehouse']);
        } catch (\Throwable $e) {
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }

        $productTable = $q->getModel()->getTable();

        // ✅ SUM tồn kho: chọn kho -> chỉ where warehouse_id
        // ✅ Tất cả kho -> nếu có company thì lọc theo pivot company_warehouse
        $q->select("{$productTable}.*")->selectSub(function ($sub) use ($companyId, $warehouseId, $productTable) {
            $sub->from('crm_product_stock as s')
                ->selectRaw('COALESCE(SUM(s.qty),0)')
                ->whereColumn('s.product_id', "{$productTable}.id");

            if ($warehouseId) {
                $sub->where('s.warehouse_id', $warehouseId);
            } else {
                if ($companyId) {
                    $sub->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                        $qq->from('company_warehouse')
                            ->select('warehouse_id')
                            ->where('company_id', $companyId);
                    });
                }
            }
        }, 'stocks_sum_qty');

        $products = $q->orderByDesc('id')->paginate(20)->withQueryString();

        // dropdown data
        $companies = DB::table('companies')->select('id', 'name')->where('id', EgoCompanyLock::id())->get();
        $categories = $this->catalogOptions->buildCategoryOptions();

        // ✅ Warehouses theo công ty (pivot company_warehouse)
        $warehousesQ = Warehouse::query()->orderBy('name');
        if ($companyId) {
            $warehousesQ->whereIn('id', function ($qq) use ($companyId) {
                $qq->from('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', $companyId);
            });
        }
        $warehouses = $warehousesQ->get();

        $brands = Brand::query()->orderBy('name')->get();
        $priceTiers = $this->catalogOptions->loadPriceTiers();

        $this->catalogOptions->attachTierPricesToProducts($products);
        $this->lotQuery->attachLotAverageCostsToProducts($products, $companyId, $warehouseId);

        // ✅ TOTALS ALL PAGES
        $totalsAll = $this->lotQuery->calcTotalsAllPages($keyword, $categoryId, $brandId, $companyId, $warehouseId);
        $totalQtyAll = $totalsAll->total_qty;
        $totalAmountAll = $totalsAll->total_amount;

        return view('products.index', compact(
            'products',
            'companies',
            'categories',
            'warehouses',
            'brands',
            'priceTiers',
            'companyMode',
            'companyId',
            'keyword',
            'warehouseId',
            'categoryId',
            'brandId',
            'priceTierId',
            'totalQtyAll',
            'totalAmountAll'
        ));
    }

    /**
     * Trang sản phẩm đầu vào (nhập kho): danh sách sản phẩm từ catalog (kể cả tồn 0) kèm giá theo tier.
     */
    public function input(Request $request)
    {
        // Dùng đúng logic giống index() để tránh 500
        $keyword = trim((string) $request->get('search', ''));
        $companyId = EgoCompanyLock::id();
        $companyMode = (string) $companyId;
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $categoryId = $request->filled('category_id') ? (int) $request->get('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->get('brand_id') : null;
        $priceTierId = $request->filled('price_tier_id') ? (int) $request->get('price_tier_id') : null;

        if ($warehouseId && ! Warehouse::withoutGlobalScopes()
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->exists()) {
            $warehouseId = null;
        }

        /*
        |--------------------------------------------------------------------------
        | EGO FIX: Products input must use product catalog as base
        |--------------------------------------------------------------------------
        | Old logic used stock lots as the base when crm_product_stock_lots exists.
        | That can hide products when qty_remaining is 0.
        |
        | Correct behavior: catalog products must still be shown with quantity = 0,
        | so users can import stock again later.
        */

        $q = Product::query();

        if (Schema::hasColumn((new Product)->getTable(), 'is_active')) {
            $q->where(function ($activeQuery) {
                $activeQuery->where('is_active', 1)->orWhereNull('is_active');
            });
        }

        // ✅ giữ y như index (an toàn)
        try {
            $q->with(['mainImage']);
        } catch (\Throwable $e) {
        }
        try {
            $q->with(['brand', 'category', 'stocks.warehouse']);
        } catch (\Throwable $e) {
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }

        $productTable = $q->getModel()->getTable();

        $q->select("{$productTable}.*")->selectSub(function ($sub) use ($companyId, $warehouseId, $productTable) {
            $sub->from('crm_product_stock as s')
                ->selectRaw('COALESCE(SUM(s.qty),0)')
                ->whereColumn('s.product_id', "{$productTable}.id");

            if ($warehouseId) {
                $sub->where('s.warehouse_id', $warehouseId);
            } else {
                if ($companyId) {
                    $sub->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                        $qq->from('company_warehouse')
                            ->select('warehouse_id')
                            ->where('company_id', $companyId);
                    });
                }
            }
        }, 'stocks_sum_qty');

        $products = $q->orderByDesc('id')->paginate(20)->withQueryString();

        // dropdown data giống index()
        $categories = $this->catalogOptions->buildCategoryOptions();

        /* EGO_FIX_PRODUCTS_INPUT_ALL_WAREHOUSES_START
           Màn Sản phẩm đầu vào chỉ hiển thị kho thuộc Công ty Quốc Tế EGO.
           Một số model Warehouse đang bị scope theo công ty đang chọn trong session,
           nên dùng withoutGlobalScopes() và chỉ lọc theo company_id khi người dùng
           chọn rõ bộ lọc công ty trên màn hình. */
        $warehousesQ = method_exists(Warehouse::class, 'withoutGlobalScopes')
            ? Warehouse::withoutGlobalScopes()
            : Warehouse::query();
        $warehousesQ->orderBy('name');
        if ($companyId) {
            $warehousesQ->whereIn('id', function ($qq) use ($companyId) {
                $qq->from('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', $companyId);
            });
        }
        $warehouses = $warehousesQ->get();
        /* EGO_FIX_PRODUCTS_INPUT_ALL_WAREHOUSES_END */

        $brands = Brand::query()->orderBy('name')->get();
        $priceTiers = $this->catalogOptions->loadPriceTiers();

        // input view KHÔNG cần tier prices nhưng không sao nếu có
        $this->catalogOptions->attachTierPricesToProducts($products);
        $this->lotQuery->attachLotAverageCostsToProducts($products, $companyId, $warehouseId);

        // ✅ TOTALS ALL PAGES
        $totalsAll = $this->lotQuery->calcTotalsAllPages($keyword, $categoryId, $brandId, $companyId, $warehouseId);
        $totalQtyAll = $totalsAll->total_qty;
        $totalAmountAll = $totalsAll->total_amount;

        return view('products.index_input', compact(
            'products',
            'categories',
            'warehouses',
            'brands',
            'priceTiers',
            'companyMode',
            'companyId',
            'keyword',
            'warehouseId',
            'categoryId',
            'brandId',
            'priceTierId',
            'totalQtyAll',
            'totalAmountAll'
        ));
    }

    /**
     * Xuất Excel sản phẩm đầu vào - 1 sheet tổng hợp.
     */
    public function exportInputExcel(Request $request)
    {
        $keyword = trim((string) $request->get('search', ''));
        $companyId = EgoCompanyLock::id();
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $categoryId = $request->filled('category_id') ? (int) $request->get('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->get('brand_id') : null;

        if ($warehouseId && ! Warehouse::withoutGlobalScopes()
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->exists()) {
            $warehouseId = null;
        }

        if (Schema::hasTable('crm_product_stock_lots')) {
            return $this->lotExporter->exportStockLotInputExcel($keyword, $categoryId, $brandId, $companyId, $warehouseId);
        }

        $q = Product::query();

        if (Schema::hasColumn((new Product)->getTable(), 'is_active')) {
            $q->where(function ($activeQuery) {
                $activeQuery->where('is_active', 1)->orWhereNull('is_active');
            });
        }

        try {
            $q->with(['brand', 'category', 'stocks.warehouse']);
        } catch (\Throwable $e) {
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }

        if ($brandId) {
            $q->where('brand_id', $brandId);
        }

        $productTable = $q->getModel()->getTable();

        $q->select("{$productTable}.*")->selectSub(function ($sub) use ($companyId, $warehouseId, $productTable) {
            $sub->from('crm_product_stock as s')
                ->selectRaw('COALESCE(SUM(s.qty),0)')
                ->whereColumn('s.product_id', "{$productTable}.id");

            if ($warehouseId) {
                $sub->where('s.warehouse_id', $warehouseId);
            } elseif ($companyId) {
                $sub->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                    $qq->from('company_warehouse')
                        ->select('warehouse_id')
                        ->where('company_id', $companyId);
                });
            }
        }, 'stocks_sum_qty');

        $products = $q->orderByDesc('id')->get();
        $this->lotQuery->attachLotAverageCostsToProducts($products, $companyId, $warehouseId);
        $productIds = $products->pluck('id')->filter()->values();

        $stockRows = collect();

        if ($productIds->isNotEmpty() && Schema::hasTable('crm_product_stock')) {
            $stockQuery = DB::table('crm_product_stock as s')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 's.warehouse_id')
                ->whereIn('s.product_id', $productIds->all());

            if ($warehouseId) {
                $stockQuery->where('s.warehouse_id', $warehouseId);
            } elseif ($companyId) {
                $stockQuery->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                    $qq->from('company_warehouse')
                        ->select('warehouse_id')
                        ->where('company_id', $companyId);
                });
            }

            $stockRows = $stockQuery
                ->select([
                    's.product_id',
                    's.warehouse_id',
                    's.qty',
                    DB::raw('COALESCE(w.name, CONCAT("Kho #", s.warehouse_id)) as warehouse_name'),
                ])
                ->orderBy('w.name')
                ->get()
                ->groupBy('product_id');
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Sản phẩm đầu vào');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('San pham dau vao');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'DANH SÁCH SẢN PHẨM ĐẦU VÀO');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'Xuất lúc: '.now()->format('d/m/Y H:i').' | Tổng sản phẩm: '.$products->count());
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'STT',
            'Tên sản phẩm',
            'SKU',
            'Ghi chú',
            'Kho / Tồn kho',
            'Danh mục',
            'Thương hiệu',
            'Giá vốn trước VAT',
            'VAT %',
            'Giá vốn sau VAT',
            'Số lượng',
            'Tổng tiền',
        ];

        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:L4')->applyFromArray($headerStyle);

        $rowNumber = 5;

        foreach ($products as $index => $product) {
            $qty = (int) ($product->stocks_sum_qty ?? 0);

            $priceBeforeVat = (float) ($product->price_agent ?? 0);

            if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                $vatPercent = (float) ($product->cost_vat_percent ?? 0);
            } else {
                $vatPercent = (float) ($product->vat_percent ?? 0);
            }

            $priceAfterVat = (float) ($product->price_agent_vat ?? 0);

            if ($priceAfterVat <= 0) {
                $priceAfterVat = $priceBeforeVat * (1 + ($vatPercent / 100));
            }

            $stockText = collect($stockRows->get($product->id, collect()))
                ->map(function ($row) {
                    return ($row->warehouse_name ?? '-').': '.(int) ($row->qty ?? 0);
                })
                ->filter()
                ->implode("\n");

            if ($stockText === '') {
                $stockText = 'Chưa có tồn kho';
            }

            $sheet->fromArray([
                $index + 1,
                $product->name,
                $product->sku,
                $product->note ?: '',
                $stockText,
                optional($product->category)->name ?? '-',
                optional($product->brand)->name ?? '-',
                round($priceBeforeVat),
                $vatPercent,
                round($priceAfterVat),
                $qty,
                round($priceAfterVat * $qty),
            ], null, 'A'.$rowNumber);

            $rowNumber++;
        }

        $lastRow = max($rowNumber - 1, 4);

        $sheet->getStyle('A4:L'.$lastRow)->applyFromArray($cellStyle);
        $sheet->getStyle('E5:E'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('D5:D'.$lastRow)->getAlignment()->setWrapText(true);

        foreach (['H', 'J', 'L'] as $column) {
            $sheet->getStyle($column.'5:'.$column.$lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($lastRow >= 5) {
            $totalRow = $lastRow + 2;
            $sheet->setCellValue('A'.$totalRow, 'TỔNG');
            $sheet->mergeCells('A'.$totalRow.':J'.$totalRow);
            $sheet->setCellValue('K'.$totalRow, '=SUM(K5:K'.$lastRow.')');
            $sheet->setCellValue('L'.$totalRow, '=SUM(L5:L'.$lastRow.')');
            $sheet->getStyle('A'.$totalRow.':L'.$totalRow)->applyFromArray($cellStyle);
            $sheet->getStyle('A'.$totalRow.':L'.$totalRow)->getFont()->setBold(true);
            $sheet->getStyle('L'.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }

        $sheet->freezePane('A5');

        $fileName = 'san-pham-dau-vao-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Trang sản phẩm đầu ra (xuất kho): danh sách theo lô tồn kho kèm giá tier, bộ lọc giống trang input.
     */
    public function output(Request $request)
    {
        // ✅ output giống input 100% (vì view output cần prices tier)
        $keyword = trim((string) $request->get('search', ''));
        $companyRaw = $request->get('company_id', 'all');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $categoryId = $request->filled('category_id') ? (int) $request->get('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->get('brand_id') : null;
        $priceTierId = $request->filled('price_tier_id') ? (int) $request->get('price_tier_id') : null;

        $companyId = null;
        $companyMode = 'all';
        if ($companyRaw === '1' || $companyRaw === 1 || $companyRaw === '2' || $companyRaw === 2) {
            $companyId = (int) $companyRaw;
            $companyMode = (string) $companyId;
        }

        if (Schema::hasTable('crm_product_stock_lots')) {
            $products = $this->lotQuery->paginateStockLotIndexRows($keyword, $categoryId, $brandId, $companyId, $warehouseId);

            $categories = $this->catalogOptions->buildCategoryOptions();
            $warehousesQ = Warehouse::query()->orderBy('name');
            if ($companyId) {
                $warehousesQ->whereIn('id', function ($qq) use ($companyId) {
                    $qq->from('company_warehouse')
                        ->select('warehouse_id')
                        ->where('company_id', $companyId);
                });
            }
            $warehouses = $warehousesQ->get();
            $brands = Brand::query()->orderBy('name')->get();
            $priceTiers = $this->catalogOptions->loadPriceTiers();
            $this->catalogOptions->attachTierPricesToProducts($products);

            $totalsAll = $this->lotQuery->calcTotalsAllPages($keyword, $categoryId, $brandId, $companyId, $warehouseId);
            $totalQtyAll = $totalsAll->total_qty;
            $totalAmountAll = $totalsAll->total_amount;

            return view('products.index_output', compact(
                'products',
                'categories',
                'warehouses',
                'brands',
                'priceTiers',
                'companyMode',
                'companyId',
                'keyword',
                'warehouseId',
                'categoryId',
                'brandId',
                'priceTierId',
                'totalQtyAll',
                'totalAmountAll'
            ));
        }

        $q = Product::query();

        if (Schema::hasColumn((new Product)->getTable(), 'is_active')) {
            $q->where(function ($activeQuery) {
                $activeQuery->where('is_active', 1)->orWhereNull('is_active');
            });
        }

        try {
            $q->with(['mainImage']);
        } catch (\Throwable $e) {
        }
        try {
            $q->with(['brand', 'category', 'stocks.warehouse']);
        } catch (\Throwable $e) {
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }
        if ($brandId) {
            $q->where('brand_id', $brandId);
        }

        $productTable = $q->getModel()->getTable();

        $q->select("{$productTable}.*")->selectSub(function ($sub) use ($companyId, $warehouseId, $productTable) {
            $sub->from('crm_product_stock as s')
                ->selectRaw('COALESCE(SUM(s.qty),0)')
                ->whereColumn('s.product_id', "{$productTable}.id");

            if ($warehouseId) {
                $sub->where('s.warehouse_id', $warehouseId);
            } else {
                if ($companyId) {
                    $sub->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                        $qq->from('company_warehouse')
                            ->select('warehouse_id')
                            ->where('company_id', $companyId);
                    });
                }
            }
        }, 'stocks_sum_qty');

        $products = $q->orderByDesc('id')->paginate(20)->withQueryString();

        $categories = $this->catalogOptions->buildCategoryOptions();

        $warehousesQ = Warehouse::query()->orderBy('name');
        if ($companyId) {
            $warehousesQ->whereIn('id', function ($qq) use ($companyId) {
                $qq->from('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', $companyId);
            });
        }
        $warehouses = $warehousesQ->get();

        $brands = Brand::query()->orderBy('name')->get();
        $priceTiers = $this->catalogOptions->loadPriceTiers();

        // ✅ bắt buộc cho view output (dropdown tier dùng $product->prices)
        $this->catalogOptions->attachTierPricesToProducts($products);
        $this->lotQuery->attachLotAverageCostsToProducts($products, $companyId, $warehouseId);

        // ✅ TOTALS ALL PAGES
        $totalsAll = $this->lotQuery->calcTotalsAllPages($keyword, $categoryId, $brandId, $companyId, $warehouseId);
        $totalQtyAll = $totalsAll->total_qty;
        $totalAmountAll = $totalsAll->total_amount;

        return view('products.index_output', compact(
            'products',
            'categories',
            'warehouses',
            'brands',
            'priceTiers',
            'companyMode',
            'companyId',
            'keyword',
            'warehouseId',
            'categoryId',
            'brandId',
            'priceTierId',
            'totalQtyAll',
            'totalAmountAll'
        ));
    }

    /**
     * Trang lịch sử xuất nhập kho: đọc crm_stock_movements kèm thông tin đơn hàng, phiếu vật tư và người thao tác.
     */
    public function history(Request $request)
    {
        $hasNote = Schema::hasColumn('crm_stock_movements', 'note');
        $hasReferenceType = Schema::hasColumn('crm_stock_movements', 'reference_type');
        $hasQtyBefore = Schema::hasColumn('crm_stock_movements', 'qty_before');
        $hasQtyAfter = Schema::hasColumn('crm_stock_movements', 'qty_after');

        $orderTable = Schema::hasTable('crm_orders') ? 'crm_orders' : (Schema::hasTable('orders') ? 'orders' : null);
        $orderCodeColumn = null;

        if ($orderTable) {
            foreach (['order_code', 'code', 'order_no', 'order_number'] as $col) {
                if (Schema::hasColumn($orderTable, $col)) {
                    $orderCodeColumn = $col;
                    break;
                }
            }
        }

        $q = DB::table('crm_stock_movements as m')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
            ->leftJoin('material_requests as mr', function ($join) use ($hasReferenceType) {
                $join->on('mr.id', '=', 'm.reference_id');

                if ($hasReferenceType) {
                    $join->where(function ($j) {
                        $j->where('m.reference_type', '=', 'material_request')
                            ->orWhere('m.reason', 'like', '%material%')
                            ->orWhere('m.reason', 'like', '%vật tư%')
                            ->orWhere('m.reason', 'like', '%công trình%');
                    });
                } else {
                    $join->where(function ($j) {
                        $j->where('m.reason', 'like', '%material%')
                            ->orWhere('m.reason', 'like', '%vật tư%')
                            ->orWhere('m.reason', 'like', '%công trình%');
                    });
                }
            })
            ->leftJoin('sites as s', 's.id', '=', 'mr.site_id')
            ->leftJoin('crm_product_stock as ps', function ($join) {
                $join->on('ps.product_id', '=', 'm.product_id')
                    ->on('ps.warehouse_id', '=', 'm.warehouse_id');
            });

        if ($orderTable) {
            $q->leftJoin($orderTable.' as o', function ($join) use ($hasReferenceType) {
                $join->on('o.id', '=', 'm.reference_id');

                if ($hasReferenceType) {
                    $join->where(function ($j) {
                        $j->where('m.reference_type', '=', 'order')
                            ->orWhere('m.reason', 'like', '%đơn hàng%')
                            ->orWhere('m.reason', 'like', '%order%');
                    });
                } else {
                    $join->where(function ($j) {
                        $j->where('m.reason', 'like', '%đơn hàng%')
                            ->orWhere('m.reason', 'like', '%order%');
                    });
                }
            });
        }

        if ($request->filled('warehouse_id')) {
            $q->where('m.warehouse_id', (int) $request->warehouse_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'in') {
                $q->where('m.change_qty', '>', 0);
            } elseif ($request->type === 'out') {
                $q->where('m.change_qty', '<', 0);
            }
        }

        if ($request->filled('q')) {
            $kw = trim($request->q);

            $q->where(function ($sub) use ($kw, $orderTable, $orderCodeColumn, $hasNote) {
                $sub->where('p.name', 'like', "%{$kw}%")
                    ->orWhere('p.sku', 'like', "%{$kw}%")
                    ->orWhere('w.name', 'like', "%{$kw}%")
                    ->orWhere('s.name', 'like', "%{$kw}%")
                    ->orWhere('m.reason', 'like', "%{$kw}%");

                if ($hasNote) {
                    $sub->orWhere('m.note', 'like', "%{$kw}%");
                }

                if ($orderTable && $orderCodeColumn) {
                    $sub->orWhere('o.'.$orderCodeColumn, 'like', "%{$kw}%");
                }
            });
        }

        $select = [
            'm.*',
            'p.name as product_name',
            'p.sku as product_sku',
            'w.name as warehouse_name',
            'u.name as user_name',
            's.name as site_name',
            $hasQtyBefore ? DB::raw('m.qty_before as qty_before_safe') : DB::raw('NULL as qty_before_safe'),
            $hasQtyAfter ? DB::raw('m.qty_after as qty_after_safe') : DB::raw('NULL as qty_after_safe'),
            DB::raw('COALESCE(ps.qty, 0) as current_qty'),
        ];

        if ($orderTable && $orderCodeColumn) {
            $select[] = DB::raw('o.'.$orderCodeColumn.' as order_code');
        } else {
            $select[] = DB::raw('NULL as order_code');
        }

        $logs = $q->select($select)
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->paginate(20)
            ->withQueryString();

        $warehouses = DB::table('crm_warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('products.history', compact('logs', 'warehouses'));
    }

    /**
     * CREATE
     */
    public function create(Request $request)
    {
        $companies = DB::table('companies')->select('id', 'name')->where('id', EgoCompanyLock::id())->get();
        $categories = ProductCategory::query()->orderBy('name')->get();
        $brands = Brand::query()->orderBy('name')->get();

        $companyWarehouses = $this->catalogOptions->loadCompanyWarehouses([EgoCompanyLock::id()]);
        $warehouses = Warehouse::withoutGlobalScopes()
            ->where('company_id', EgoCompanyLock::id())
            ->orderBy('name')
            ->get();
        $priceTiers = $this->catalogOptions->loadPriceTiers();

        // formData đúng format blade bạn dùng
        $formData = [
            'warehouseQty' => [],
            'serialsByWarehouse' => [],
            'totalQty' => 0,
            'tierPrices' => [],
        ];

        $productStockLogs = collect();

        return view('products.create', [
            'product' => null,
            'companies' => $companies,
            'categories' => $categories,
            'brands' => $brands,
            'companyWarehouses' => $companyWarehouses,
            'warehouses' => $warehouses,
            'priceTiers' => $priceTiers,
            'formData' => $formData,
            'productStockLogs' => $productStockLogs,
        ]);
    }

    /**
     * STORE
     */
    public function store(Request $request)
    {
        $lockedCompanyId = EgoCompanyLock::id();
        $lockedLines = array_map(function ($line) use ($lockedCompanyId) {
            if (is_array($line)) {
                $line['company_id'] = $lockedCompanyId;
            }

            return $line;
        }, (array) $request->input('v2_lines', []));

        $request->merge([
            'company_id' => $lockedCompanyId,
            'v2_lines' => $lockedLines,
        ]);

        DB::beginTransaction();

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:255',
                'cost_vat_percent' => 'nullable|numeric|min:0|max:100',
            ]);

            $data = $request->all();

            /*
             * EGO INVENTORY V2:
             * Mỗi dòng SKU trên giao diện sẽ tạo 1 sản phẩm/dòng tồn riêng.
             * Không gộp theo tên sản phẩm.
             */
            if ($request->has('v2_lines') && ! $request->boolean('group_edit_mode')) {
                $v2Lines = array_values(array_filter((array) $request->input('v2_lines', []), function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }

                    return trim((string) ($row['sku'] ?? '')) !== ''
                        || (int) ($row['qty_in'] ?? 0) > 0
                        || (int) ($row['warehouse_id'] ?? 0) > 0;
                }));

                if (! empty($v2Lines)) {
                    foreach ($v2Lines as $lineIndex => $line) {
                        $lineSku = trim((string) ($line['sku'] ?? ''));
                        $lineLotName = trim((string) ($line['lot_name'] ?? ''));
                        $companyId = EgoCompanyLock::id();
                        $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                        $qtyIn = max(0, (int) ($line['qty_in'] ?? 0));
                        $serialCodes = $this->egoParseSerialCodes($line['serials'] ?? '');

                        if (! empty($serialCodes)) {
                            $qtyIn = count($serialCodes);
                        }

                        if ($lineSku === '') {
                            throw new \Exception('Vui lòng nhập SKU cho dòng số '.($lineIndex + 1));
                        }

                        if ($warehouseId <= 0) {
                            throw new \Exception('Vui lòng chọn kho cho SKU '.$lineSku);
                        }

                        $warehouseIsValid = Warehouse::withoutGlobalScopes()
                            ->where('id', $warehouseId)
                            ->where('company_id', $companyId)
                            ->exists();

                        if (! $warehouseIsValid) {
                            throw new \Exception('Kho của SKU '.$lineSku.' không thuộc Công ty Quốc Tế EGO.');
                        }

                        $finalSku = $this->egoBaseSkuFromLotSku($lineSku);
                        $existingLineProduct = Product::where('sku', $finalSku)->first();

                        $costBeforeVat = max(0, (float) ($line['cost_before_vat'] ?? 0));
                        $costVatPercent = max(0, (float) ($line['cost_vat_percent'] ?? 0));
                        $costAfterVat = round($costBeforeVat * (1 + $costVatPercent / 100), 2);

                        $product = $existingLineProduct ?: new Product;
                        $product->name = $data['name'] ?? '';
                        $product->sku = $finalSku;
                        $product->category_id = $data['category_id'] ?? null;
                        $product->brand_id = $data['brand_id'] ?? null;
                        $product->note = $data['note'] ?? null;

                        if (Schema::hasColumn($product->getTable(), 'warehouse_note')) {
                            $product->warehouse_note = $data['warehouse_note'] ?? null;
                        }

                        $product->price_agent = $costBeforeVat;

                        if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                            $product->cost_vat_percent = $costVatPercent;
                        }

                        if (Schema::hasColumn($product->getTable(), 'price_agent_vat')) {
                            $product->price_agent_vat = $costAfterVat;
                        } else {
                            $product->price_agent_vat = $costAfterVat;
                        }

                        $retailBeforeVat = isset($data['price_retail']) ? (float) $data['price_retail'] : 0;
                        $retailVat = isset($data['vat_percent']) ? (float) $data['vat_percent'] : 0;

                        if (Schema::hasColumn($product->getTable(), 'price_retail')) {
                            $product->price_retail = $retailBeforeVat;
                        }

                        if (Schema::hasColumn($product->getTable(), 'price_retail_vat')) {
                            $product->price_retail_vat = $retailBeforeVat * (1 + $retailVat / 100);
                        }

                        if (Schema::hasColumn($product->getTable(), 'vat_percent')) {
                            $product->vat_percent = $retailVat;
                        }

                        $product->is_serialized = (! empty($data['is_serialized']) || ! empty($serialCodes)) ? 1 : 0;
                        $product->is_active = 1;
                        $product->save();

                        $lotRequest = new Request($request->all());
                        $lotRequest->merge([
                            'initial_lots' => [
                                [
                                    'company_id' => $companyId,
                                    'warehouse_id' => $warehouseId,
                                    'qty_in' => $qtyIn,
                                    'cost_before_vat' => $costBeforeVat,
                                    'cost_vat_percent' => $costVatPercent,
                                    'extra_cost' => (float) ($line['extra_cost'] ?? 0),
                                    'received_at' => $line['received_at'] ?? now()->toDateString(),
                                    'lot_name' => 'Dòng tồn V2 - '.$finalSku,
                                    'lot_code' => '',
                                    'note' => $line['note'] ?? null,
                                ],
                            ],
                        ]);

                        $this->saveTierPricesFromRequest((int) $product->id, $request);
                        $this->saveInitialLotsFromCreateRequest($product, $lotRequest);

                        if (! empty($serialCodes)) {
                            $this->egoCreateSerialsForProduct(
                                (int) $product->id,
                                $warehouseId,
                                $companyId,
                                $serialCodes,
                                $line['note'] ?? null
                            );
                        }
                    }

                    DB::commit();

                    return redirect()
                        ->route('products.input')
                        ->with('success', 'Đã tạo '.count($v2Lines).' dòng tồn kho V2.');
                }
            }

            $sku = trim((string) ($data['sku'] ?? ''));

            /*
            |--------------------------------------------------------------------------
            | Quy tắc V2
            |--------------------------------------------------------------------------
            | - Tên sản phẩm có thể trùng.
            | - SKU là khóa phân biệt sản phẩm.
            | - Nếu nhập lại cùng SKU: không tạo product trùng, không ghi đè giá vốn cũ,
            |   chỉ tạo dòng tồn/lô mới với giá vốn + chi phí riêng.
            */
            $product = Product::where('sku', $sku)->first();
            $isExistingSku = (bool) $product;

            if (! $product) {
                $product = new Product;
                $product->name = $data['name'] ?? '';
                $product->sku = $sku;
                $product->category_id = $data['category_id'] ?? null;
                $product->brand_id = $data['brand_id'] ?? null;
                $product->note = $data['note'] ?? null;

                if (Schema::hasColumn($product->getTable(), 'warehouse_note')) {
                    $product->warehouse_note = $data['warehouse_note'] ?? null;
                }

                $costBeforeVat = isset($data['price_agent']) ? (float) $data['price_agent'] : 0;
                $costVat = isset($data['cost_vat_percent']) ? (float) $data['cost_vat_percent'] : 0;

                $product->price_agent = $costBeforeVat;

                if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                    $product->cost_vat_percent = $costVat;
                }

                $product->price_agent_vat = $costBeforeVat * (1 + $costVat / 100);

                $retailBeforeVat = isset($data['price_retail']) ? (float) $data['price_retail'] : 0;
                $retailVat = isset($data['vat_percent']) ? (float) $data['vat_percent'] : 0;

                if (Schema::hasColumn($product->getTable(), 'price_retail')) {
                    $product->price_retail = $retailBeforeVat;
                }

                if (Schema::hasColumn($product->getTable(), 'price_retail_vat')) {
                    $product->price_retail_vat = $retailBeforeVat * (1 + $retailVat / 100);
                }

                if (Schema::hasColumn($product->getTable(), 'vat_percent')) {
                    $product->vat_percent = $retailVat;
                }

                $product->is_serialized = ! empty($data['is_serialized']) ? 1 : 0;
                $product->is_active = 1;
                $product->save();

                $this->saveTierPricesFromRequest((int) $product->id, $request);
            } else {
                if (Schema::hasColumn($product->getTable(), 'is_active') && (int) ($product->is_active ?? 1) !== 1) {
                    $product->is_active = 1;
                    $product->save();
                }
            }

            $serialCodes = $this->egoParseSerialCodes($request->input('serials', ''));

            if (! empty($serialCodes)) {
                $product->is_serialized = 1;
                $product->save();
            }

            $this->saveStocksFromRequest((int) $product->id, $request);
            $this->saveInitialLotsFromCreateRequest($product, $request);

            if (! empty($serialCodes)) {
                $warehouseId = (int) $request->input('warehouse_id', 0);
                $companyId = (int) $request->input('company_id', 0);

                if ($warehouseId <= 0) {
                    $firstLot = (array) $request->input('initial_lots.0', []);
                    $warehouseId = (int) ($firstLot['warehouse_id'] ?? 0);
                    $companyId = (int) ($firstLot['company_id'] ?? $companyId);
                }

                if ($warehouseId <= 0) {
                    throw new \Exception('Vui lòng chọn kho trước khi nhập serial.');
                }

                $this->egoCreateSerialsForProduct(
                    (int) $product->id,
                    $warehouseId,
                    $companyId > 0 ? $companyId : null,
                    $serialCodes,
                    $request->input('note')
                );
            }

            DB::commit();

            $message = $isExistingSku
                ? 'SKU đã tồn tại nên hệ thống chỉ tạo dòng tồn/lô mới, không ghi đè giá vốn hoặc giá bán cũ.'
                : 'Đã tạo sản phẩm và dòng tồn theo SKU.';

            return redirect()->route('products.index')->with('success', $message);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Lỗi tạo sản phẩm: '.$e->getMessage());
        }
    }

    /**
     * Tách chuỗi serial nhập tay (phân cách bởi xuống dòng, phẩy, chấm phẩy) thành mảng mã duy nhất.
     */
    private function egoParseSerialCodes($text): array
    {
        $parts = preg_split('/[\r\n,;]+/', (string) $text);
        $codes = [];

        foreach ($parts as $part) {
            $code = trim((string) $part);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Tạo serial mới cho sản phẩm tại kho: sinh unit, identifier, trạng thái in_stock và sự kiện bảo hành "receive".
     * Ném exception nếu thiếu bảng serial hoặc mã serial đã tồn tại.
     */
    private function egoCreateSerialsForProduct(int $productId, int $warehouseId, ?int $companyId, array $codes, ?string $note = null): void
    {
        if (empty($codes)) {
            return;
        }

        foreach ([
            'crm_serial_units',
            'crm_serial_identifiers',
            'crm_serial_unit_identifiers',
            'crm_serial_unit_states',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \Exception('Thiếu bảng serial: '.$table);
            }
        }

        $existing = DB::table('crm_serial_identifiers')
            ->whereIn('code', $codes)
            ->pluck('code')
            ->all();

        if (! empty($existing)) {
            throw new \Exception('Serial đã tồn tại: '.implode(', ', $existing));
        }

        foreach ($codes as $code) {
            $unitData = [
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                $unitData['warehouse_id'] = $warehouseId;
            }

            $unitId = DB::table('crm_serial_units')->insertGetId($unitData);

            $identifierId = DB::table('crm_serial_identifiers')->insertGetId([
                'type' => 'serial',
                'code' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('crm_serial_unit_identifiers')->insert([
                'serial_unit_id' => $unitId,
                'serial_identifier_id' => $identifierId,
                'is_primary' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stateData = [
                'serial_unit_id' => $unitId,
                'warehouse_id' => $warehouseId,
                'state' => 'in_stock',
                'synced_at' => now(),
            ];

            if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                $stateData['company_id'] = $companyId;
            }

            DB::table('crm_serial_unit_states')->insert($stateData);

            if (Schema::hasTable('crm_serial_warranty_events')) {
                DB::table('crm_serial_warranty_events')->insert([
                    'serial_unit_id' => $unitId,
                    'serial_code' => $code,
                    'event_type' => 'receive',
                    'from_state' => null,
                    'to_state' => 'in_stock',
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => $warehouseId,
                    'customer_id' => null,
                    'order_id' => null,
                    'created_by' => auth()->id(),
                    'note' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * UPDATE
     */
    public function update(Request $request, $id)
    {

        /* EGO_FIX_UPDATE_GROUP_QTY_NO_DUPLICATE */
        if ($request->boolean('group_edit_mode')) {
            $lines = array_values(array_filter((array) $request->input('v2_lines', []), function ($row) {
                return is_array($row) && (
                    trim((string) ($row['sku'] ?? '')) !== ''
                    || (int) ($row['qty_in'] ?? 0) > 0
                    || (int) ($row['stock_lot_id'] ?? 0) > 0
                );
            }));

            if (empty($lines)) {
                return back()->withInput()->with('error', 'Vui lòng nhập ít nhất 1 dòng tồn.');
            }

            DB::beginTransaction();

            try {
                $rootProduct = Product::lockForUpdate()->findOrFail($id);
                $productTable = $rootProduct->getTable();
                $affectedProductIds = [];
                /* EGO_MANUAL_GROUP_INPUT_HISTORY_CACHE */
                $egoManualStockRunning = [];

                foreach ($lines as $lineIndex => $line) {
                    $lineProductId = (int) ($line['product_id'] ?? 0);
                    $lineLotId = (int) ($line['stock_lot_id'] ?? 0);

                    $rawSku = trim((string) ($line['sku'] ?? ''));
                    $baseSku = preg_replace('/-(?:LOT|NEW)20[0-9]{12,14}(?:-[0-9]+)?$/', '', $rawSku);

                    if ($baseSku === '') {
                        throw new \Exception('Vui lòng nhập SKU cho dòng số '.($lineIndex + 1));
                    }

                    $companyId = (int) ($line['company_id'] ?? 0);
                    $warehouseId = (int) ($line['warehouse_id'] ?? 0);

                    if ($companyId <= 0 || $warehouseId <= 0) {
                        throw new \Exception('Vui lòng chọn công ty và kho cho SKU '.$baseSku);
                    }

                    $qtyIn = max(0, (int) ($line['qty_in'] ?? 0));
                    $costBeforeVat = max(0, (float) ($line['cost_before_vat'] ?? 0));
                    $costVatPercent = max(0, (float) ($line['cost_vat_percent'] ?? 0));
                    $extraCost = (float) ($line['extra_cost'] ?? 0);
                    $costAfterVat = round($costBeforeVat * (1 + $costVatPercent / 100), 2);
                    $actualCostAfterVat = round($costAfterVat + ($qtyIn > 0 ? $extraCost / $qtyIn : 0), 2);
                    $receivedAt = $line['received_at'] ?? now()->toDateString();
                    $lineNote = trim((string) ($line['note'] ?? ''));
                    $lineLotName = trim((string) ($line['lot_name'] ?? ''));

                    $lineProduct = null;

                    if ($lineProductId > 0) {
                        $lineProduct = Product::where('id', $lineProductId)->lockForUpdate()->first();
                    }

                    if (! $lineProduct && $lineLotId > 0 && Schema::hasTable('crm_product_stock_lots')) {
                        $lotProductId = DB::table('crm_product_stock_lots')->where('id', $lineLotId)->value('product_id');

                        if ($lotProductId) {
                            $lineProduct = Product::where('id', (int) $lotProductId)->lockForUpdate()->first();
                        }
                    }

                    if (! $lineProduct) {
                        $lineProduct = Product::where('sku', $baseSku)->lockForUpdate()->first();
                    }

                    $isNewProduct = false;

                    if (! $lineProduct) {
                        $isNewProduct = true;
                        $lineProduct = new Product;
                    }

                    $finalSku = $baseSku;

                    if ($isNewProduct) {
                        $suffix = 1;
                        while (Product::where('sku', $finalSku)->exists()) {
                            $finalSku = $baseSku.'-NEW'.now()->format('YmdHis').'-'.$suffix;
                            $suffix++;
                        }
                    }

                    $lineProduct->name = $request->input('name', $rootProduct->name);
                    $lineProduct->sku = $finalSku;

                    if (Schema::hasColumn($productTable, 'category_id')) {
                        $lineProduct->category_id = $request->input('category_id') ?: null;
                    }

                    if (Schema::hasColumn($productTable, 'brand_id')) {
                        $lineProduct->brand_id = $request->input('brand_id') ?: null;
                    }

                    if (Schema::hasColumn($productTable, 'note')) {
                        $lineProduct->note = $request->input('note');
                    }

                    if (Schema::hasColumn($productTable, 'price_agent')) {
                        $lineProduct->price_agent = $costBeforeVat;
                    }

                    if (Schema::hasColumn($productTable, 'cost_vat_percent')) {
                        $lineProduct->cost_vat_percent = $costVatPercent;
                    }

                    if (Schema::hasColumn($productTable, 'price_agent_vat')) {
                        $lineProduct->price_agent_vat = $costAfterVat;
                    }

                    $retailBeforeVat = (float) $request->input('price_retail', 0);
                    $retailVat = (float) $request->input('vat_percent', 0);

                    if (Schema::hasColumn($productTable, 'price_retail')) {
                        $lineProduct->price_retail = $retailBeforeVat;
                    }

                    if (Schema::hasColumn($productTable, 'vat_percent')) {
                        $lineProduct->vat_percent = $retailVat;
                    }

                    if (Schema::hasColumn($productTable, 'price_retail_vat')) {
                        $lineProduct->price_retail_vat = round($retailBeforeVat * (1 + $retailVat / 100), 2);
                    }

                    if (Schema::hasColumn($productTable, 'is_active')) {
                        $lineProduct->is_active = 1;
                    }

                    $lineProduct->save();
                    $affectedProductIds[] = (int) $lineProduct->id;

                    if (method_exists($this, 'saveTierPricesFromRequest')) {
                        $this->saveTierPricesFromRequest((int) $lineProduct->id, $request);
                    }

                    if (Schema::hasTable('crm_product_stock_lots')) {
                        $lot = null;

                        if ($lineLotId > 0) {
                            $lot = DB::table('crm_product_stock_lots')
                                ->where('id', $lineLotId)
                                ->lockForUpdate()
                                ->first();
                        }

                        $lotData = [
                            'product_id' => (int) $lineProduct->id,
                            'company_id' => $companyId,
                            'warehouse_id' => $warehouseId,
                            'qty_in' => $qtyIn,
                            'qty_remaining' => $qtyIn,
                            'cost_before_vat' => $costBeforeVat,
                            'cost_vat_percent' => $costVatPercent,
                            'cost_after_vat' => $costAfterVat,
                            'extra_cost' => $extraCost,
                            'actual_cost_after_vat' => $actualCostAfterVat,
                            'received_at' => $receivedAt,
                            'updated_at' => now(),
                        ];

                        if (Schema::hasColumn('crm_product_stock_lots', 'lot_name')) {
                            $lotData['lot_name'] = $lineLotName !== '' ? $lineLotName : ('Dòng tồn / SKU '.($lineIndex + 1));
                        }

                        if (Schema::hasColumn('crm_product_stock_lots', 'note')) {
                            $lotData['note'] = $lineNote;
                        }

                        $oldLotQty = $lot ? (int) ($lot->qty_remaining ?? 0) : 0;

                        if ($lot) {
                            DB::table('crm_product_stock_lots')->where('id', (int) $lot->id)->update($lotData);
                            $savedLotId = (int) $lot->id;
                        } else {
                            $lotData['created_at'] = now();

                            if (Schema::hasColumn('crm_product_stock_lots', 'lot_code')) {
                                $lotData['lot_code'] = '';
                            }

                            $savedLotId = (int) DB::table('crm_product_stock_lots')->insertGetId($lotData);
                        }

                        $manualChangeQty = $qtyIn - $oldLotQty;

                        if ($manualChangeQty !== 0) {
                            $stockCacheKey = (int) $lineProduct->id.':'.$companyId.':'.$warehouseId;

                            if (! array_key_exists($stockCacheKey, $egoManualStockRunning)) {
                                $egoManualStockRunning[$stockCacheKey] = $this->egoCurrentStockQtyForHistory((int) $lineProduct->id, $companyId, $warehouseId);
                            }

                            $manualQtyBefore = (int) $egoManualStockRunning[$stockCacheKey];
                            $manualQtyAfter = max(0, $manualQtyBefore + $manualChangeQty);
                            $egoManualStockRunning[$stockCacheKey] = $manualQtyAfter;

                            $this->egoInsertManualStockHistory(
                                (int) $lineProduct->id,
                                $companyId,
                                $warehouseId,
                                $manualChangeQty,
                                $manualQtyBefore,
                                $manualQtyAfter,
                                $manualChangeQty > 0 ? 'Nhập tay từ trang sản phẩm đầu vào' : 'Điều chỉnh giảm tay từ trang sản phẩm đầu vào',
                                $savedLotId,
                                $lineNote ?: ($manualChangeQty > 0 ? 'Nhập tay từ trang sản phẩm đầu vào' : 'Điều chỉnh giảm tay từ trang sản phẩm đầu vào'),
                                'manual_input'
                            );
                        }
                    }
                }

                $affectedProductIds = array_values(array_unique($affectedProductIds));

                if (Schema::hasTable('crm_product_stock') && Schema::hasTable('crm_product_stock_lots')) {
                    $stockColumns = Schema::getColumnListing('crm_product_stock');
                    $qtyColumn = null;

                    foreach (['qty', 'quantity', 'stock_qty', 'stock_quantity'] as $candidate) {
                        if (in_array($candidate, $stockColumns, true)) {
                            $qtyColumn = $candidate;
                            break;
                        }
                    }

                    if ($qtyColumn) {
                        foreach ($affectedProductIds as $pid) {
                            DB::table('crm_product_stock')->where('product_id', $pid)->delete();

                            $stockRows = DB::table('crm_product_stock_lots')
                                ->where('product_id', $pid)
                                ->where('qty_remaining', '>', 0)
                                ->selectRaw('product_id, company_id, warehouse_id, SUM(qty_remaining) as total_qty')
                                ->groupBy('product_id', 'company_id', 'warehouse_id')
                                ->get();

                            foreach ($stockRows as $stockRow) {
                                $insert = [
                                    'product_id' => (int) $stockRow->product_id,
                                    'company_id' => (int) $stockRow->company_id,
                                    'warehouse_id' => (int) $stockRow->warehouse_id,
                                    $qtyColumn => (float) $stockRow->total_qty,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];

                                $insert = array_intersect_key($insert, array_flip($stockColumns));
                                DB::table('crm_product_stock')->insert($insert);
                            }
                        }
                    }
                }

                DB::commit();

                return redirect()
                    ->route('products.input')
                    ->with('success', 'Đã cập nhật số lượng / giá vốn cho '.count($lines).' dòng tồn.');
            } catch (\Throwable $e) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', 'Lỗi lưu dòng tồn: '.$e->getMessage());
            }
        }

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($id);

            if ($request->filled('fifo_action')) {
                $message = $this->handleFifoLotAction($product, $request);

                DB::commit();

                return back()->with('success', $message);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:255',
                'cost_vat_percent' => 'nullable|numeric|min:0|max:100',
            ]);

            $data = $request->all();

            /*
             * EGO INVENTORY V2:
             * Mỗi dòng SKU trên giao diện sẽ tạo 1 sản phẩm/dòng tồn riêng.
             * Không gộp theo tên sản phẩm.
             */
            if ($request->has('v2_lines') && ! $request->boolean('group_edit_mode')) {
                $v2Lines = array_values(array_filter((array) $request->input('v2_lines', []), function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }

                    return trim((string) ($row['sku'] ?? '')) !== ''
                        || (int) ($row['qty_in'] ?? 0) > 0
                        || (int) ($row['warehouse_id'] ?? 0) > 0;
                }));

                if (! empty($v2Lines)) {
                    foreach ($v2Lines as $lineIndex => $line) {
                        $lineSku = trim((string) ($line['sku'] ?? ''));
                        $companyId = (int) ($line['company_id'] ?? 0);
                        $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                        $qtyIn = max(0, (int) ($line['qty_in'] ?? 0));

                        if ($lineSku === '') {
                            throw new \Exception('Vui lòng nhập SKU cho dòng số '.($lineIndex + 1));
                        }

                        if ($companyId <= 0 || $warehouseId <= 0) {
                            throw new \Exception('Vui lòng chọn công ty và kho cho SKU '.$lineSku);
                        }

                        $finalSku = $this->egoBaseSkuFromLotSku($lineSku);
                        $existingLineProduct = Product::where('sku', $finalSku)->first();

                        $costBeforeVat = max(0, (float) ($line['cost_before_vat'] ?? 0));
                        $costVatPercent = max(0, (float) ($line['cost_vat_percent'] ?? 0));
                        $costAfterVat = round($costBeforeVat * (1 + $costVatPercent / 100), 2);

                        $product = $existingLineProduct ?: new Product;
                        $product->name = $data['name'] ?? '';
                        $product->sku = $finalSku;
                        $product->category_id = $data['category_id'] ?? null;
                        $product->brand_id = $data['brand_id'] ?? null;
                        $product->note = $data['note'] ?? null;

                        if (Schema::hasColumn($product->getTable(), 'warehouse_note')) {
                            $product->warehouse_note = $data['warehouse_note'] ?? null;
                        }

                        $product->price_agent = $costBeforeVat;

                        if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                            $product->cost_vat_percent = $costVatPercent;
                        }

                        if (Schema::hasColumn($product->getTable(), 'price_agent_vat')) {
                            $product->price_agent_vat = $costAfterVat;
                        } else {
                            $product->price_agent_vat = $costAfterVat;
                        }

                        $retailBeforeVat = isset($data['price_retail']) ? (float) $data['price_retail'] : 0;
                        $retailVat = isset($data['vat_percent']) ? (float) $data['vat_percent'] : 0;

                        if (Schema::hasColumn($product->getTable(), 'price_retail')) {
                            $product->price_retail = $retailBeforeVat;
                        }

                        if (Schema::hasColumn($product->getTable(), 'price_retail_vat')) {
                            $product->price_retail_vat = $retailBeforeVat * (1 + $retailVat / 100);
                        }

                        if (Schema::hasColumn($product->getTable(), 'vat_percent')) {
                            $product->vat_percent = $retailVat;
                        }

                        $product->is_serialized = ! empty($data['is_serialized']) ? 1 : 0;
                        $product->is_active = 1;
                        $product->save();

                        $lotRequest = new Request($request->all());
                        $lotRequest->merge([
                            'initial_lots' => [
                                [
                                    'company_id' => $companyId,
                                    'warehouse_id' => $warehouseId,
                                    'qty_in' => $qtyIn,
                                    'cost_before_vat' => $costBeforeVat,
                                    'cost_vat_percent' => $costVatPercent,
                                    'extra_cost' => (float) ($line['extra_cost'] ?? 0),
                                    'received_at' => $line['received_at'] ?? now()->toDateString(),
                                    'lot_name' => 'Dòng tồn V2 - '.$finalSku,
                                    'lot_code' => '',
                                    'note' => $line['note'] ?? null,
                                ],
                            ],
                        ]);

                        $this->saveTierPricesFromRequest((int) $product->id, $request);
                        $this->saveInitialLotsFromCreateRequest($product, $lotRequest);
                    }

                    DB::commit();

                    return redirect()
                        ->route('products.input')
                        ->with('success', 'Đã tạo '.count($v2Lines).' dòng tồn kho V2.');
                }
            }

            $product->name = $data['name'] ?? $product->name;
            $product->sku = $data['sku'] ?? $product->sku;
            $product->category_id = $data['category_id'] ?? $product->category_id;
            $product->brand_id = $data['brand_id'] ?? $product->brand_id;
            $product->note = $data['note'] ?? $product->note;

            if (Schema::hasColumn($product->getTable(), 'warehouse_note')) {
                $product->warehouse_note = $data['warehouse_note'] ?? $product->warehouse_note;
            }

            // Giá vốn trước VAT
            if (isset($data['price_agent'])) {
                $product->price_agent = (float) $data['price_agent'];
            }

            // VAT của giá vốn
            $costVat = 0;
            if (isset($data['cost_vat_percent'])) {
                $costVat = (float) $data['cost_vat_percent'];

                if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                    $product->cost_vat_percent = $costVat;
                }
            } else {
                if (Schema::hasColumn($product->getTable(), 'cost_vat_percent')) {
                    $costVat = (float) ($product->cost_vat_percent ?? 0);
                }
            }

            // Tính lại giá vốn sau VAT
            $product->price_agent_vat = (float) ($product->price_agent ?? 0) * (1 + $costVat / 100);

            // Giá bán mặc định
            $retailBeforeVat = isset($data['price_retail'])
                ? (float) $data['price_retail']
                : (float) ($product->price_retail ?? 0);

            $retailVat = isset($data['vat_percent'])
                ? (float) $data['vat_percent']
                : (float) ($product->vat_percent ?? 0);

            if (Schema::hasColumn($product->getTable(), 'price_retail')) {
                $product->price_retail = $retailBeforeVat;
            }

            if (Schema::hasColumn($product->getTable(), 'price_retail_vat')) {
                $product->price_retail_vat = $retailBeforeVat * (1 + $retailVat / 100);
            }

            if (Schema::hasColumn($product->getTable(), 'vat_percent')) {
                $product->vat_percent = $retailVat;
            }
            $product->is_serialized = ! empty($data['is_serialized']) ? 1 : 0;

            $product->save();

            $this->saveStocksFromRequest((int) $product->id, $request);
            $this->saveTierPricesFromRequest((int) $product->id, $request);

            DB::commit();

            return back()->with('success', 'Đã cập nhật sản phẩm');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Lỗi cập nhật sản phẩm: '.$e->getMessage());
        }
    }

    /**
     * Tính giá vốn sau VAT của lô từ giá trước VAT và % VAT.
     */
    private function lotCostAfterVat(float $beforeVat, float $vatPercent): float
    {
        return round($beforeVat * (1 + max(0, $vatPercent) / 100), 2);
    }

    /**
     * Cộng/trừ tồn kho tổng (crm_product_stock) theo thay đổi từ lô và ghi stock movement; chặn tồn âm.
     */
    private function changeProductStockFromLot(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $changeQty,
        string $reason,
        int $referenceId
    ): void {
        if ($changeQty === 0) {
            return;
        }

        $stock = ProductStock::where([
            'product_id' => $productId,
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
        ])->lockForUpdate()->first();

        $oldQty = (int) ($stock->qty ?? 0);
        $newQty = $oldQty + $changeQty;

        if ($newQty < 0) {
            throw new \Exception("Không đủ tồn kho tổng để giảm. Tồn hiện tại {$oldQty}, cần giảm ".abs($changeQty));
        }

        ProductStock::updateOrCreate(
            [
                'product_id' => $productId,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'qty' => $newQty,
                'last_updated' => now(),
            ]
        );

        if (Schema::hasTable('crm_stock_movements')) {
            $movement = [];

            $addColumn = function (string $column, mixed $value) use (&$movement) {
                if (Schema::hasColumn('crm_stock_movements', $column)) {
                    $movement[$column] = $value;
                }
            };

            $addColumn('product_id', $productId);
            $addColumn('company_id', $companyId);
            $addColumn('warehouse_id', $warehouseId);
            $addColumn('change_qty', $changeQty);
            $addColumn('qty_before', $oldQty);
            $addColumn('qty_after', $newQty);

            $movementType = $changeQty > 0 ? 'in' : 'out';
            $addColumn('type', $movementType);
            $addColumn('movement_type', $movementType);

            $addColumn('reason', $reason);
            $addColumn('note', $reason);
            $addColumn('reference_type', 'stock_lot');
            $addColumn('reference_id', $referenceId);

            $userId = auth()->id();
            $addColumn('created_by', $userId);
            $addColumn('user_id', $userId);
            $addColumn('created_at', now());
            $addColumn('updated_at', now());

            if (! empty($movement)) {
                DB::table('crm_stock_movements')->insert($movement);
            }
        }
    }

    /**
     * Ghi log audit thay đổi số lượng lô vào crm_stock_movements (chỉ ghi các cột đang tồn tại trong bảng).
     */
    private function logStockLotAudit(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $lotId,
        int $qtyBefore,
        int $qtyAfter,
        string $reason
    ): void {
        if (! Schema::hasTable('crm_stock_movements')) {
            return;
        }

        $columns = Schema::getColumnListing('crm_stock_movements');
        $has = fn (string $column): bool => in_array($column, $columns, true);
        $payload = [];

        if ($has('product_id')) {
            $payload['product_id'] = $productId;
        }
        if ($has('company_id')) {
            $payload['company_id'] = $companyId;
        }
        if ($has('warehouse_id')) {
            $payload['warehouse_id'] = $warehouseId;
        }
        if ($has('change_qty')) {
            $payload['change_qty'] = $qtyAfter - $qtyBefore;
        }
        if ($has('qty_before')) {
            $payload['qty_before'] = $qtyBefore;
        }
        if ($has('qty_after')) {
            $payload['qty_after'] = $qtyAfter;
        }
        if ($has('reason')) {
            $payload['reason'] = $reason;
        }
        if ($has('reference_id')) {
            $payload['reference_id'] = $lotId;
        }
        if ($has('created_by')) {
            $payload['created_by'] = auth()->id();
        }
        if ($has('created_at')) {
            $payload['created_at'] = now();
        }
        if ($has('updated_at')) {
            $payload['updated_at'] = now();
        }

        if (! empty($payload)) {
            DB::table('crm_stock_movements')->insert($payload);
        }
    }

    /**
     * Xử lý các hành động lô FIFO từ form sản phẩm (add_lot, adjust_lot_qty, update_lot_cost, delete_lot).
     *
     * @return string Thông báo kết quả hiển thị cho người dùng
     */
    private function handleFifoLotAction(Product $product, Request $request): string
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
            throw new \Exception('Chưa có bảng crm_product_stock_lots.');
        }

        $action = (string) $request->input('fifo_action');

        if ($action === 'add_lot') {
            $companyId = (int) $request->input('company_id');
            $warehouseId = (int) $request->input('warehouse_id');
            $qtyIn = max(0, (int) $request->input('qty_in'));
            $costBeforeVat = max(0, (float) $request->input('cost_before_vat'));
            $vatPercent = max(0, (float) $request->input('cost_vat_percent'));
            $extraCost = max(0, (float) $request->input('extra_cost'));
            $receivedAt = $request->input('received_at') ?: now();
            $lotCode = trim((string) $request->input('lot_code'));
            $lotName = trim((string) $request->input('lot_name'));
            $note = trim((string) $request->input('note'));

            if ($companyId <= 0 || $warehouseId <= 0) {
                throw new \Exception('Vui lòng chọn công ty và kho cho lô nhập.');
            }

            if ($qtyIn <= 0) {
                throw new \Exception('Số lượng nhập lô phải lớn hơn 0.');
            }

            if ($lotCode === '') {
                $lotCode = 'LOT-P'.$product->id.'-W'.$warehouseId.'-'.now()->format('YmdHis');
            }

            if ($lotName === '') {
                $lotName = $lotCode;
            }

            $costAfterVat = $this->lotCostAfterVat($costBeforeVat, $vatPercent);
            $extraCostPerUnit = $qtyIn > 0 ? round($extraCost / $qtyIn, 2) : 0;
            $actualCostAfterVat = $costAfterVat + $extraCostPerUnit;

            $insert = [
                'product_id' => (int) $product->id,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'lot_code' => $lotCode,
                'received_at' => $receivedAt,
                'qty_in' => $qtyIn,
                'qty_remaining' => $qtyIn,
                'cost_before_vat' => $costBeforeVat,
                'cost_vat_percent' => $vatPercent,
                'cost_after_vat' => $costAfterVat,
                'source_type' => 'manual_lot',
                'source_id' => (int) $product->id,
                'note' => $note,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('crm_product_stock_lots', 'lot_name')) {
                $insert['lot_name'] = $lotName;
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'extra_cost')) {
                $insert['extra_cost'] = $extraCost;
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'actual_cost_after_vat')) {
                $insert['actual_cost_after_vat'] = $actualCostAfterVat;
            }

            $lotId = (int) DB::table('crm_product_stock_lots')->insertGetId($insert);

            $this->changeProductStockFromLot(
                (int) $product->id,
                $companyId,
                $warehouseId,
                $qtyIn,
                'Nhập thêm lô FIFO từ trang sửa sản phẩm',
                $lotId
            );

            return 'Đã thêm lô FIFO mới và cộng tồn kho.';
        }

        if ($action === 'adjust_lot_qty') {
            $lotId = (int) $request->input('lot_id');
            $changeQty = (int) $request->input('change_qty');

            if ($lotId <= 0) {
                throw new \Exception('Thiếu ID lô.');
            }

            if ($changeQty === 0) {
                throw new \Exception('Số lượng điều chỉnh phải khác 0.');
            }

            $lot = DB::table('crm_product_stock_lots')
                ->where('id', $lotId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                throw new \Exception('Không tìm thấy lô cần điều chỉnh.');
            }

            $oldRemain = (int) ($lot->qty_remaining ?? 0);
            $oldIn = (int) ($lot->qty_in ?? 0);
            $newRemain = $oldRemain + $changeQty;

            if ($newRemain < 0) {
                throw new \Exception("Lô không đủ số lượng để giảm. Còn {$oldRemain}, cần giảm ".abs($changeQty));
            }

            $newIn = $oldIn + $changeQty;
            if ($newIn < $newRemain) {
                $newIn = $newRemain;
            }

            if ($newIn < 0) {
                $newIn = 0;
            }

            DB::table('crm_product_stock_lots')
                ->where('id', $lotId)
                ->update([
                    'qty_in' => $newIn,
                    'qty_remaining' => $newRemain,
                    'updated_at' => now(),
                ]);

            $this->changeProductStockFromLot(
                (int) $product->id,
                (int) $lot->company_id,
                (int) $lot->warehouse_id,
                $changeQty,
                'Điều chỉnh số lượng trong lô FIFO',
                $lotId
            );

            return 'Đã điều chỉnh số lượng trong lô FIFO.';
        }

        if ($action === 'update_lot_cost') {
            $lotId = (int) $request->input('lot_id');
            $newCompanyId = (int) $request->input('company_id');
            $newWarehouseId = (int) $request->input('warehouse_id');

            $costBeforeVat = max(0, (float) $request->input('cost_before_vat'));
            $vatPercent = max(0, (float) $request->input('cost_vat_percent'));
            $extraCost = max(0, (float) $request->input('extra_cost'));
            $receivedAt = $request->input('received_at') ?: null;
            $lotCode = trim((string) $request->input('lot_code'));
            $lotName = trim((string) $request->input('lot_name'));
            $note = trim((string) $request->input('note'));

            if ($lotId <= 0) {
                throw new \Exception('Thiếu ID lô.');
            }

            if ($newCompanyId <= 0 || $newWarehouseId <= 0) {
                throw new \Exception('Vui lòng chọn công ty và kho cho lô.');
            }

            $lot = DB::table('crm_product_stock_lots')
                ->where('id', $lotId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                throw new \Exception('Không tìm thấy lô cần sửa.');
            }

            $oldCompanyId = (int) $lot->company_id;
            $oldWarehouseId = (int) $lot->warehouse_id;
            $qtyRemain = (int) ($lot->qty_remaining ?? 0);

            $costAfterVat = $this->lotCostAfterVat($costBeforeVat, $vatPercent);
            $lotQtyIn = max(1, (int) ($lot->qty_in ?? 0));
            $extraCostPerUnit = round($extraCost / $lotQtyIn, 2);
            $actualCostAfterVat = $costAfterVat + $extraCostPerUnit;

            $update = [
                'company_id' => $newCompanyId,
                'warehouse_id' => $newWarehouseId,
                'cost_before_vat' => $costBeforeVat,
                'cost_vat_percent' => $vatPercent,
                'cost_after_vat' => $costAfterVat,
                'updated_at' => now(),
            ];

            if ($lotCode !== '') {
                $update['lot_code'] = $lotCode;
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'lot_name') && $lotName !== '') {
                $update['lot_name'] = $lotName;
            }

            if ($receivedAt) {
                $update['received_at'] = $receivedAt;
            }

            if ($note !== '') {
                $update['note'] = $note;
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'extra_cost')) {
                $update['extra_cost'] = $extraCost;
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'actual_cost_after_vat')) {
                $update['actual_cost_after_vat'] = $actualCostAfterVat;
            }

            $movedWarehouse = $oldCompanyId !== $newCompanyId || $oldWarehouseId !== $newWarehouseId;

            if ($movedWarehouse && $qtyRemain > 0) {
                $this->changeProductStockFromLot(
                    (int) $product->id,
                    $oldCompanyId,
                    $oldWarehouseId,
                    -$qtyRemain,
                    'Chuyển lô FIFO sang công ty/kho khác',
                    $lotId
                );

                $this->changeProductStockFromLot(
                    (int) $product->id,
                    $newCompanyId,
                    $newWarehouseId,
                    $qtyRemain,
                    'Nhận lô FIFO chuyển từ công ty/kho khác',
                    $lotId
                );
            }

            DB::table('crm_product_stock_lots')
                ->where('id', $lotId)
                ->update($update);

            $this->logStockLotAudit(
                (int) $product->id,
                $newCompanyId,
                $newWarehouseId,
                $lotId,
                $qtyRemain,
                $qtyRemain,
                'Sửa thông tin lô FIFO: giá vốn/chi phí/kho/ngày nhập'
            );

            return $movedWarehouse
                ? 'Đã cập nhật lô và chuyển tồn sang công ty/kho mới.'
                : 'Đã cập nhật thông tin lô FIFO.';
        }

        if ($action === 'delete_lot') {
            $lotId = (int) $request->input('lot_id');

            if ($lotId <= 0) {
                throw new \Exception('Thiếu ID lô cần xóa.');
            }

            $lot = DB::table('crm_product_stock_lots')
                ->where('id', $lotId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                throw new \Exception('Không tìm thấy lô cần xóa.');
            }

            if (Schema::hasTable('crm_order_item_stock_allocations')
                && DB::table('crm_order_item_stock_allocations')->where('stock_lot_id', $lotId)->exists()) {
                throw new \Exception('Lô này đã phát sinh xuất kho nên không được xóa.');
            }

            if ((int) ($lot->qty_remaining ?? 0) !== (int) ($lot->qty_in ?? 0)) {
                throw new \Exception('Lô này đã bị trừ/xuất kho nên không được xóa.');
            }

            $qtyRemain = (int) ($lot->qty_remaining ?? 0);

            if ($qtyRemain > 0) {
                $this->changeProductStockFromLot(
                    (int) $product->id,
                    (int) $lot->company_id,
                    (int) $lot->warehouse_id,
                    -$qtyRemain,
                    'Xóa lô FIFO chưa phát sinh xuất kho',
                    $lotId
                );
            }

            DB::table('crm_product_stock_lots')->where('id', $lotId)->delete();

            return 'Đã xóa lô FIFO chưa phát sinh xuất kho.';
        }

        throw new \Exception('Hành động lô FIFO không hợp lệ.');
    }

    /**
     * ✅ LƯU TỒN KHO + SERIALS_JSON
     * stocks[cid][wid][qty]
     * stocks[cid][wid][serials] = JSON string
     *
     * Rule:
     * - Nếu bật serial:
     *   + Kho nào có serial => qty = count(serial) của kho đó
     *   + Kho nào không có serial => giữ qty input (KHÔNG lock)
     */
    private function saveStocksFromRequest(int $productId, Request $request): void
    {
        // Form tạo mới V2 dùng initial_lots. Không đồng bộ lại stocks để tránh tạo/cộng tồn trùng lô.
        if (count((array) $request->input('initial_lots', [])) > 0) {
            return;
        }

        $isSerialized = (bool) $request->input('is_serialized', false);
        $stocks = (array) $request->input('stocks', []);

        $hasSerialCol = Schema::hasColumn('crm_product_stock', 'serials_json');
        $canUseLots = class_exists(StockLotService::class)
            && Schema::hasTable('crm_product_stock_lots');

        $product = Product::findOrFail($productId);

        $costBeforeVat = (float) $request->input('price_agent', $product->price_agent ?? 0);

        $costVatPercent = (float) $request->input(
            'cost_vat_percent',
            Schema::hasColumn($product->getTable(), 'cost_vat_percent')
                ? ($product->cost_vat_percent ?? 0)
                : ($product->vat_percent ?? 0)
        );

        foreach ($stocks as $companyId => $warehouses) {
            foreach ((array) $warehouses as $warehouseId => $payload) {

                $companyId = (int) $companyId;
                $warehouseId = (int) $warehouseId;

                $qty = (int) ($payload['qty'] ?? 0);
                if ($qty < 0) {
                    $qty = 0;
                }

                $serialJson = $payload['serials'] ?? '[]';
                $serialArr = [];
                $tmp = json_decode((string) $serialJson, true);

                if (is_array($tmp)) {
                    $serialArr = array_values(array_filter(array_map(function ($x) {
                        return strtoupper(trim((string) $x));
                    }, $tmp)));
                }

                if ($isSerialized && count($serialArr) > 0) {
                    $qty = count($serialArr);
                }

                /*
                |--------------------------------------------------------------------------
                | FIFO LOT MODE
                |--------------------------------------------------------------------------
                | Không update thẳng crm_product_stock nữa.
                | Gửi qua StockLotService:
                | - Nếu tăng tồn: tạo lô mới theo giá vốn hiện tại.
                | - Nếu giảm tồn: trừ lô cũ nhất trước.
                | - Vẫn cập nhật crm_product_stock.qty để dropdown và báo tồn nhanh.
                */
                if ($canUseLots) {
                    app(StockLotServiceInterface::class)->syncManualStock(
                        $product,
                        $companyId,
                        $warehouseId,
                        $qty,
                        $costBeforeVat,
                        $costVatPercent
                    );

                    if ($hasSerialCol) {
                        ProductStock::where([
                            'product_id' => $productId,
                            'company_id' => $companyId,
                            'warehouse_id' => $warehouseId,
                        ])->update([
                            'serials_json' => json_encode($serialArr, JSON_UNESCAPED_UNICODE),
                            'last_updated' => now(),
                        ]);
                    }

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | FALLBACK CŨ
                |--------------------------------------------------------------------------
                | Chỉ chạy nếu chưa có bảng lô FIFO.
                */
                $existingStock = ProductStock::where([
                    'product_id' => $productId,
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                ])->first();

                $oldQty = (int) ($existingStock->qty ?? 0);
                $changeQty = $qty - $oldQty;

                $update = [
                    'qty' => $qty,
                    'last_updated' => now(),
                ];

                if ($hasSerialCol) {
                    $update['serials_json'] = json_encode($serialArr, JSON_UNESCAPED_UNICODE);
                }

                ProductStock::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouseId,
                    ],
                    $update
                );

                if (Schema::hasTable('crm_stock_movements') && $changeQty !== 0) {
                    $movement = [];

                    $addColumn = function (string $column, mixed $value) use (&$movement) {
                        if (Schema::hasColumn('crm_stock_movements', $column)) {
                            $movement[$column] = $value;
                        }
                    };

                    $addColumn('product_id', $productId);
                    $addColumn('company_id', $companyId);
                    $addColumn('warehouse_id', $warehouseId);
                    $addColumn('change_qty', $changeQty);
                    $addColumn('qty_before', $oldQty);
                    $addColumn('qty_after', $qty);

                    $movementType = $changeQty > 0 ? 'in' : 'out';
                    $addColumn('type', $movementType);
                    $addColumn('movement_type', $movementType);

                    $reason = 'Điều chỉnh tồn kho từ trang sửa sản phẩm';
                    $addColumn('reason', $reason);
                    $addColumn('note', $reason);

                    $addColumn('reference_type', 'product_edit');
                    $addColumn('reference_id', $productId);

                    $userId = auth()->id();
                    $addColumn('created_by', $userId);
                    $addColumn('user_id', $userId);

                    $addColumn('created_at', now());
                    $addColumn('updated_at', now());

                    if (! empty($movement)) {
                        DB::table('crm_stock_movements')->insert($movement);
                    }
                }
            }
        }
    }

    /**
     * ✅ LƯU GIÁ THEO LOẠI GIÁ (prices[tier_id])
     */
    private function egoMoneyToFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $value = str_replace([' ', 'đ', 'Đ'], '', $value);

        if ($value === '') {
            return null;
        }

        $hasComma = strpos($value, ',') !== false;
        $hasDot = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Lưu bảng giá theo tier của sản phẩm từ request: xóa giá cũ rồi ghi lại giá trước/sau VAT theo từng tier.
     */
    private function saveTierPricesFromRequest(int $productId, Request $request): void
    {
        $prices = (array) $request->input('prices', []);

        if (empty($prices)) {
            return;
        }

        $candidateTables = [
            'crm_product_prices',
            'product_prices',
            'crm_prices',
        ];

        $table = null;
        foreach ($candidateTables as $t) {
            if (
                $this->catalogOptions->tableExists($t)
                && Schema::hasColumn($t, 'product_id')
                && (Schema::hasColumn($t, 'price_tier_id') || Schema::hasColumn($t, 'tier_id'))
                && (Schema::hasColumn($t, 'price') || Schema::hasColumn($t, 'value'))
            ) {
                $table = $t;
                break;
            }
        }

        if (! $table) {
            return;
        }

        $tierCol = Schema::hasColumn($table, 'price_tier_id') ? 'price_tier_id' : 'tier_id';
        $priceCol = Schema::hasColumn($table, 'price') ? 'price' : 'value';

        $columns = Schema::getColumnListing($table);
        $hasVatCol = in_array('vat_percent', $columns, true);
        $hasAfterVatCol = in_array('price_after_vat', $columns, true);
        $hasEffectiveFrom = in_array('effective_from', $columns, true);
        $hasEffectiveTo = in_array('effective_to', $columns, true);
        $hasCreatedAt = in_array('created_at', $columns, true);
        $hasUpdatedAt = in_array('updated_at', $columns, true);

        DB::table($table)->where('product_id', $productId)->delete();

        foreach ($prices as $tierId => $row) {
            if (! is_array($row)) {
                continue;
            }

            $beforeVat = $this->egoMoneyToFloat($row['before_vat'] ?? null);
            $vatPercent = $this->egoMoneyToFloat($row['vat_percent'] ?? 0);

            if ($beforeVat === null || $beforeVat <= 0) {
                continue;
            }

            $insertData = [
                'product_id' => $productId,
                $tierCol => (int) $tierId,
                $priceCol => round($beforeVat, 2),
            ];

            if ($hasVatCol) {
                $insertData['vat_percent'] = round((float) ($vatPercent ?? 0), 2);
            }

            if ($hasAfterVatCol) {
                $insertData['price_after_vat'] = round($beforeVat * (1 + (float) ($vatPercent ?? 0) / 100), 2);
            }

            if ($hasEffectiveFrom) {
                $insertData['effective_from'] = null;
            }

            if ($hasEffectiveTo) {
                $insertData['effective_to'] = null;
            }

            if ($hasCreatedAt) {
                $insertData['created_at'] = now();
            }

            if ($hasUpdatedAt) {
                $insertData['updated_at'] = now();
            }

            DB::table($table)->insert($insertData);
        }
    }

    /**
     * DELETE
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);
            $productId = (int) $product->id;

            /*
            |--------------------------------------------------------------------------
            | Nếu sản phẩm đã nằm trong đơn hàng thì không xóa cứng
            |--------------------------------------------------------------------------
            | Xóa cứng sẽ làm hỏng lịch sử đơn hàng/PDF/công nợ.
            | Khi đã dùng trong đơn, ta ẩn khỏi danh sách bằng is_active = 0.
            */
            $usedInOrders = false;

            foreach (['crm_order_items', 'order_items'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) {
                    if (DB::table($table)->where('product_id', $productId)->exists()) {
                        $usedInOrders = true;
                        break;
                    }
                }
            }

            if ($usedInOrders) {
                if (Schema::hasColumn($product->getTable(), 'is_active')) {
                    $product->is_active = 0;
                    $product->save();

                    DB::commit();

                    return back()->with('success', 'Sản phẩm đã có trong đơn hàng nên hệ thống đã ẩn sản phẩm khỏi danh sách thay vì xóa cứng.');
                }

                DB::rollBack();

                return back()->with('error', 'Sản phẩm đã có trong đơn hàng nên không thể xóa cứng.');
            }

            /*
            |--------------------------------------------------------------------------
            | Sản phẩm chưa dùng trong đơn: xóa dữ liệu phụ thuộc trước
            |--------------------------------------------------------------------------
            */
            $tablesByProductId = [
                'crm_product_stock_lots',
                'crm_product_stock',
                'crm_stock_movements',
                'crm_product_prices',
                'product_prices',
                'crm_prices',
                'crm_serial_units',
            ];

            foreach ($tablesByProductId as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) {
                    DB::table($table)->where('product_id', $productId)->delete();
                }
            }

            $product->delete();

            DB::commit();

            return back()->with('success', 'Đã xóa sản phẩm và dữ liệu tồn/lô liên quan.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Không thể xóa sản phẩm: '.$e->getMessage());
        }
    }

    /* EGO_MANUAL_STOCK_HISTORY_HELPERS_START */
    /**
     * Lấy tồn hiện tại (có khóa dòng) của sản phẩm theo công ty/kho để ghi lịch sử nhập tay.
     */
    private function egoCurrentStockQtyForHistory(int $productId, int $companyId, int $warehouseId): int
    {
        if (! Schema::hasTable('crm_product_stock')) {
            return 0;
        }

        $query = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if (Schema::hasColumn('crm_product_stock', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return (int) ($query->lockForUpdate()->value('qty') ?? 0);
    }

    /**
     * Ghi một dòng lịch sử xuất nhập kho thủ công vào crm_stock_movements (chỉ ghi các cột đang tồn tại trong bảng).
     */
    private function egoInsertManualStockHistory(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $changeQty,
        int $qtyBefore,
        int $qtyAfter,
        string $reason,
        ?int $referenceId = null,
        ?string $note = null,
        string $referenceType = 'manual_input'
    ): void {
        if ($changeQty === 0 || ! Schema::hasTable('crm_stock_movements')) {
            return;
        }

        $columns = Schema::getColumnListing('crm_stock_movements');
        $has = fn (string $column): bool => in_array($column, $columns, true);
        $payload = [];

        if ($has('product_id')) {
            $payload['product_id'] = $productId;
        }
        if ($has('company_id')) {
            $payload['company_id'] = $companyId;
        }
        if ($has('warehouse_id')) {
            $payload['warehouse_id'] = $warehouseId;
        }
        if ($has('change_qty')) {
            $payload['change_qty'] = $changeQty;
        }
        if ($has('qty_before')) {
            $payload['qty_before'] = $qtyBefore;
        }
        if ($has('qty_after')) {
            $payload['qty_after'] = $qtyAfter;
        }

        $movementType = $changeQty > 0 ? 'in' : 'out';
        if ($has('type')) {
            $payload['type'] = $movementType;
        }
        if ($has('movement_type')) {
            $payload['movement_type'] = $movementType;
        }

        if ($has('reason')) {
            $payload['reason'] = $reason;
        }
        if ($has('note')) {
            $payload['note'] = $note ?: $reason;
        }
        if ($has('reference_type')) {
            $payload['reference_type'] = $referenceType;
        }
        if ($has('reference_id') && $referenceId !== null) {
            $payload['reference_id'] = $referenceId;
        }

        $userId = auth()->id();
        if ($has('created_by')) {
            $payload['created_by'] = $userId;
        }
        if ($has('user_id')) {
            $payload['user_id'] = $userId;
        }
        if ($has('created_at')) {
            $payload['created_at'] = now();
        }
        if ($has('updated_at')) {
            $payload['updated_at'] = now();
        }

        if (! empty($payload)) {
            DB::table('crm_stock_movements')->insert($payload);
        }
    }
    /* EGO_MANUAL_STOCK_HISTORY_HELPERS_END */

    /**
     * Lưu các lô tồn đầu kỳ (initial_lots) vào crm_product_stock_lots khi tạo sản phẩm mới.
     */
    private function saveInitialLotsFromCreateRequest($product, $request): void
    {
        $schema = \Illuminate\Support\Facades\Schema::class;
        $db = DB::class;

        if (! $schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        $initialLots = (array) $request->input('initial_lots', []);

        if (empty($initialLots)) {
            return;
        }

        $lotColumns = $schema::getColumnListing('crm_product_stock_lots');
        $hasLotCol = fn (string $column): bool => in_array($column, $lotColumns, true);

        foreach ($initialLots as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $companyId = (int) ($row['company_id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            $qtyIn = max(0, (int) ($row['qty_in'] ?? 0));

            if ($companyId <= 0 && $warehouseId <= 0 && $qtyIn <= 0) {
                continue;
            }

            if ($companyId <= 0 || $warehouseId <= 0) {
                throw new \Exception('Vui lòng chọn công ty và kho cho lô nhập ban đầu.');
            }

            if ($qtyIn <= 0) {
                throw new \Exception('Số lượng lô nhập ban đầu phải lớn hơn 0.');
            }

            $costBeforeVat = max(0, (float) ($row['cost_before_vat'] ?? 0));
            $vatPercent = max(0, (float) ($row['cost_vat_percent'] ?? 0));
            $costAfterVat = round($costBeforeVat * (1 + $vatPercent / 100), 2);
            $extraCost = (float) ($row['extra_cost'] ?? 0);
            $extraCostPerUnit = $qtyIn > 0 ? round($extraCost / $qtyIn, 2) : 0;
            $actualCostAfterVat = round($costAfterVat + $extraCostPerUnit, 2);
            $receivedAt = $row['received_at'] ?? null;
            $lotName = trim((string) ($row['lot_name'] ?? ''));
            $lotCode = trim((string) ($row['lot_code'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));

            if ($lotCode === '') {
                $lotCode = 'LOT-P'.$product->id.'-W'.$warehouseId.'-'.now()->format('YmdHis').'-'.($index + 1);
            }

            if ($lotName === '') {
                $lotName = $lotCode;
            }

            $lotInsert = [];

            $putLot = function (string $column, $value) use (&$lotInsert, $hasLotCol): void {
                if ($hasLotCol($column)) {
                    $lotInsert[$column] = $value;
                }
            };

            $putLot('product_id', (int) $product->id);
            $putLot('company_id', $companyId);
            $putLot('warehouse_id', $warehouseId);
            $putLot('lot_name', $lotName);
            $putLot('lot_code', $lotCode);
            $putLot('received_at', $receivedAt ?: now());
            $putLot('qty_in', $qtyIn);
            $putLot('qty_remaining', $qtyIn);
            $putLot('cost_before_vat', $costBeforeVat);
            $putLot('cost_vat_percent', $vatPercent);
            $putLot('cost_after_vat', $costAfterVat);
            $putLot('extra_cost', $extraCost);
            $putLot('actual_cost_after_vat', $actualCostAfterVat);
            $putLot('source_type', 'manual_input');
            $putLot('source_id', (int) $product->id);
            $putLot('note', $note);
            $putLot('created_by', auth()->id());
            $putLot('created_at', now());
            $putLot('updated_at', now());

            $qtyBefore = $this->egoCurrentStockQtyForHistory((int) $product->id, $companyId, $warehouseId);
            $qtyAfter = $qtyBefore + $qtyIn;

            $lotId = (int) $db::table('crm_product_stock_lots')->insertGetId($lotInsert);

            if ($schema::hasTable('crm_product_stock')) {
                $stockColumns = $schema::getColumnListing('crm_product_stock');
                $hasStockCol = fn (string $column): bool => in_array($column, $stockColumns, true);

                $query = $db::table('crm_product_stock')
                    ->where('product_id', (int) $product->id)
                    ->where('warehouse_id', $warehouseId);

                if ($hasStockCol('company_id')) {
                    $query->where('company_id', $companyId);
                }

                $stock = $query->first();

                if ($stock) {
                    $update = [];
                    if ($hasStockCol('qty')) {
                        $update['qty'] = $qtyAfter;
                    }
                    if ($hasStockCol('last_updated')) {
                        $update['last_updated'] = now();
                    }
                    if ($hasStockCol('updated_at')) {
                        $update['updated_at'] = now();
                    }

                    if (! empty($update)) {
                        $db::table('crm_product_stock')->where('id', $stock->id)->update($update);
                    }
                } else {
                    $stockInsert = [];
                    if ($hasStockCol('product_id')) {
                        $stockInsert['product_id'] = (int) $product->id;
                    }
                    if ($hasStockCol('company_id')) {
                        $stockInsert['company_id'] = $companyId;
                    }
                    if ($hasStockCol('warehouse_id')) {
                        $stockInsert['warehouse_id'] = $warehouseId;
                    }
                    if ($hasStockCol('qty')) {
                        $stockInsert['qty'] = $qtyAfter;
                    }
                    if ($hasStockCol('serials_json')) {
                        $stockInsert['serials_json'] = json_encode([]);
                    }
                    if ($hasStockCol('last_updated')) {
                        $stockInsert['last_updated'] = now();
                    }
                    if ($hasStockCol('created_at')) {
                        $stockInsert['created_at'] = now();
                    }
                    if ($hasStockCol('updated_at')) {
                        $stockInsert['updated_at'] = now();
                    }

                    if (! empty($stockInsert)) {
                        $db::table('crm_product_stock')->insert($stockInsert);
                    }
                }
            }

            $this->egoInsertManualStockHistory(
                (int) $product->id,
                $companyId,
                $warehouseId,
                $qtyIn,
                $qtyBefore,
                $qtyAfter,
                'Nhập tay từ trang sản phẩm đầu vào',
                $lotId,
                $note ?: 'Nhập tay từ trang sản phẩm đầu vào',
                'manual_input'
            );
        }
    }

    /* EGO_HOTFIX_RESTORE_EDIT_START */
    /**
     * Hồ sơ chi tiết sản phẩm: giá, tồn theo kho, lô nhập và biến động gần nhất.
     *
     * Đọc dữ liệu theo hướng an toàn để trang chi tiết không lỗi 500 khi một
     * relation hoặc bảng phụ chưa có dữ liệu tương ứng.
     */
    public function show($id)
    {
        $product = Product::query()->findOrFail((int) $id);
        $companyId = EgoCompanyLock::id();

        // Từng relation được nạp riêng để một relation phụ lỗi không làm sập cả trang.
        foreach ([
            'category',
            'brand',
            'prices.priceTier',
            'mainImage.media.metadata',
        ] as $relation) {
            try {
                $product->loadMissing($relation);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $stocks = collect();
        try {
            if (Schema::hasTable('crm_product_stock')) {
                $stocks = ProductStock::withoutGlobalScopes()
                    ->with(['warehouse' => function ($query) use ($companyId) {
                        $query->withoutGlobalScopes()
                            ->where('company_id', $companyId);
                    }])
                    ->where('product_id', $product->id)
                    ->where('company_id', $companyId)
                    ->orderBy('warehouse_id')
                    ->get();
            }
        } catch (\Throwable $e) {
            report($e);
            $stocks = collect();
        }

        $lots = collect();
        try {
            if (Schema::hasTable('crm_product_stock_lots')) {
                $lotsQuery = DB::table('crm_product_stock_lots as l')
                    ->leftJoin('crm_warehouses as w', function ($join) use ($companyId) {
                        $join->on('w.id', '=', 'l.warehouse_id')
                            ->where('w.company_id', '=', $companyId);
                    })
                    ->where('l.product_id', $product->id);

                if (Schema::hasColumn('crm_product_stock_lots', 'company_id')) {
                    $lotsQuery->where('l.company_id', $companyId);
                }

                $lots = $lotsQuery
                    ->select('l.*', 'w.name as warehouse_name')
                    ->orderByDesc(
                        Schema::hasColumn('crm_product_stock_lots', 'received_at')
                            ? 'l.received_at'
                            : 'l.id'
                    )
                    ->orderByDesc('l.id')
                    ->limit(30)
                    ->get();
            }
        } catch (\Throwable $e) {
            report($e);
            $lots = collect();
        }

        $movements = collect();
        try {
            if (Schema::hasTable('crm_stock_movements')) {
                $movements = DB::table('crm_stock_movements as m')
                    ->leftJoin('crm_warehouses as w', function ($join) use ($companyId) {
                        $join->on('w.id', '=', 'm.warehouse_id')
                            ->where('w.company_id', '=', $companyId);
                    })
                    ->where('m.product_id', $product->id)
                    ->whereNotNull('w.id')
                    ->select('m.*', 'w.name as warehouse_name')
                    ->orderByDesc('m.created_at')
                    ->orderByDesc('m.id')
                    ->limit(40)
                    ->get();
            }
        } catch (\Throwable $e) {
            report($e);
            $movements = collect();
        }

        return view('products.show', compact('product', 'stocks', 'lots', 'movements'));
    }

    /**
     * Form sửa sản phẩm: nạp tồn theo công ty/kho, serial, giá tier và lịch sử xuất nhập kho của sản phẩm.
     */
    public function edit($id)
    {
        $product = Product::findOrFail($id);

        $companies = DB::table('companies')->select('id', 'name')->where('id', EgoCompanyLock::id())->get();
        $categories = ProductCategory::query()->orderBy('name')->get();
        $brands = Brand::query()->orderBy('name')->get();

        $companyWarehouses = $this->catalogOptions->loadCompanyWarehouses([EgoCompanyLock::id()]);
        $priceTiers = $this->catalogOptions->loadPriceTiers();

        $rows = DB::table('crm_product_stock')
            ->where('product_id', $product->id)
            ->get();

        $warehouseQty = [];
        $serialsByWarehouse = []; // ✅ load từ serials_json
        $totalQty = 0;

        $hasSerialCol = Schema::hasColumn('crm_product_stock', 'serials_json');

        foreach ($rows as $r) {
            $cid = (int) $r->company_id;
            $wid = (int) $r->warehouse_id;
            $qty = (int) $r->qty;

            $warehouseQty[$cid] = $warehouseQty[$cid] ?? [];
            $warehouseQty[$cid][$wid] = $qty;
            $totalQty += $qty;

            $serialsByWarehouse[$cid] = $serialsByWarehouse[$cid] ?? [];
            $serialsByWarehouse[$cid][$wid] = [];

            if ($hasSerialCol) {
                $json = (string) ($r->serials_json ?? '');
                $arr = [];
                if ($json !== '') {
                    $tmp = json_decode($json, true);
                    if (is_array($tmp)) {
                        $arr = $tmp;
                    }
                }
                $serialsByWarehouse[$cid][$wid] = array_values(array_filter(array_map('strval', $arr)));
            }
        }

        $tierPrices = $this->catalogOptions->loadTierPricesForProduct((int) $product->id);

        $productStockLogs = collect();

        if (Schema::hasTable('crm_stock_movements')) {
            $hasReferenceType = Schema::hasColumn('crm_stock_movements', 'reference_type');
            $hasNote = Schema::hasColumn('crm_stock_movements', 'note');
            $hasQtyBefore = Schema::hasColumn('crm_stock_movements', 'qty_before');
            $hasQtyAfter = Schema::hasColumn('crm_stock_movements', 'qty_after');

            $orderTable = Schema::hasTable('crm_orders')
                ? 'crm_orders'
                : (Schema::hasTable('orders') ? 'orders' : null);

            $orderCodeColumn = null;

            if ($orderTable) {
                foreach (['order_code', 'code', 'order_no', 'order_number'] as $col) {
                    if (Schema::hasColumn($orderTable, $col)) {
                        $orderCodeColumn = $col;
                        break;
                    }
                }
            }

            $productStockLogsQuery = DB::table('crm_stock_movements as m')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'm.warehouse_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
                ->leftJoin('material_requests as mr', function ($join) use ($hasReferenceType) {
                    $join->on('mr.id', '=', 'm.reference_id');

                    if ($hasReferenceType) {
                        $join->where(function ($j) {
                            $j->where('m.reference_type', '=', 'material_request')
                                ->orWhere('m.reason', 'like', '%material%')
                                ->orWhere('m.reason', 'like', '%vật tư%')
                                ->orWhere('m.reason', 'like', '%công trình%');
                        });
                    } else {
                        $join->where(function ($j) {
                            $j->where('m.reason', 'like', '%material%')
                                ->orWhere('m.reason', 'like', '%vật tư%')
                                ->orWhere('m.reason', 'like', '%công trình%');
                        });
                    }
                })
                ->leftJoin('sites as st', 'st.id', '=', 'mr.site_id')
                ->where('m.product_id', $product->id);

            if ($orderTable) {
                $productStockLogsQuery->leftJoin($orderTable.' as o', function ($join) use ($hasReferenceType) {
                    $join->on('o.id', '=', 'm.reference_id');

                    if ($hasReferenceType) {
                        $join->where(function ($j) {
                            $j->where('m.reference_type', '=', 'order')
                                ->orWhere('m.reason', 'like', '%đơn hàng%')
                                ->orWhere('m.reason', 'like', '%order%');
                        });
                    } else {
                        $join->where(function ($j) {
                            $j->where('m.reason', 'like', '%đơn hàng%')
                                ->orWhere('m.reason', 'like', '%order%');
                        });
                    }
                });
            }

            $stockLogSelect = [
                'm.*',
                'w.name as warehouse_name',
                'u.name as user_name',
                'st.name as site_name',
                $hasQtyBefore ? DB::raw('m.qty_before as qty_before_safe') : DB::raw('NULL as qty_before_safe'),
                $hasQtyAfter ? DB::raw('m.qty_after as qty_after_safe') : DB::raw('NULL as qty_after_safe'),
                $hasNote ? DB::raw('m.note as note_safe') : DB::raw('NULL as note_safe'),
                $hasReferenceType ? DB::raw('m.reference_type as reference_type_safe') : DB::raw('NULL as reference_type_safe'),
            ];

            if ($orderTable && $orderCodeColumn) {
                $stockLogSelect[] = DB::raw('o.'.$orderCodeColumn.' as order_code');
            } else {
                $stockLogSelect[] = DB::raw('NULL as order_code');
            }

            $productStockLogs = $productStockLogsQuery
                ->select($stockLogSelect)
                ->orderByDesc('m.created_at')
                ->orderByDesc('m.id')
                ->limit(80)
                ->get();
        }

        $productStockLots = collect();

        if (Schema::hasTable('crm_product_stock_lots')) {
            $productStockLots = DB::table('crm_product_stock_lots as l')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->where('l.product_id', $product->id)
                ->select([
                    'l.*',
                    DB::raw('COALESCE(w.name, CONCAT("Kho #", l.warehouse_id)) as warehouse_name'),
                ])
                ->orderByRaw('COALESCE(l.received_at, l.created_at) ASC')
                ->orderBy('l.id')
                ->get();
        }

        $formData = [
            'warehouseQty' => $warehouseQty,
            'serialsByWarehouse' => $serialsByWarehouse,
            'totalQty' => $totalQty,
            'tierPrices' => $tierPrices,
        ];

        return view('products.edit', [
            'product' => $product,
            'companies' => $companies,
            'categories' => $categories,
            'brands' => $brands,
            'companyWarehouses' => $companyWarehouses,
            'priceTiers' => $priceTiers,
            'formData' => $formData,
            'productStockLogs' => $productStockLogs,
            'productStockLots' => $productStockLots,
        ]);
    }
    /* EGO_HOTFIX_RESTORE_EDIT_END */

}
