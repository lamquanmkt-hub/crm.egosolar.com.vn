<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Truy vấn tồn kho theo lô FIFO (crm_product_stock_lots) cho màn sản phẩm:
 * phân trang danh sách gộp theo lô, tính tổng toàn bộ kết quả (mọi trang),
 * và gắn giá vốn trung bình theo lô vào từng sản phẩm.
 *
 * Tách nguyên trạng từ ProductController (P1b refactor) — hành vi chốt bằng
 * ProductPagesCharacterizationTest.
 */
class ProductStockLotQueryService
{
    /**
     * ✅ TÍNH TỔNG THEO TOÀN BỘ KẾT QUẢ SAU FILTER (ALL PAGES)
     * - total_qty: SUM(qty) theo điều kiện kho/công ty
     * - total_amount: SUM( (giá vốn sau VAT) * qty )
     */
    public function calcTotalsAllPages(
        string $keyword,
        ?int $categoryId,
        ?int $brandId,
        ?int $companyId,
        ?int $warehouseId
    ): object {
        $product = (new Product);
        $productTable = $product->getTable();

        /*
        |--------------------------------------------------------------------------
        | Ưu tiên tính theo lô FIFO còn lại
        |--------------------------------------------------------------------------
        | total_qty    = tổng qty_remaining của lô
        | total_amount = tổng qty_remaining * giá vốn sau VAT của từng lô
        | Như vậy cùng SKU nhiều lô giá khác nhau vẫn ra đúng giá trị tồn.
        */
        if (Schema::hasTable('crm_product_stock_lots')) {
            $q = DB::table('crm_product_stock_lots as l')
                ->join($productTable.' as p', 'p.id', '=', 'l.product_id')
                ->where('l.qty_remaining', '>', 0);

            if (Schema::hasColumn($productTable, 'is_active')) {
                $q->where(function ($activeQuery) {
                    $activeQuery->where('p.is_active', 1)->orWhereNull('p.is_active');
                });
            }

            if ($keyword !== '') {
                $q->where(function ($w) use ($keyword) {
                    $w->where('p.name', 'like', "%{$keyword}%")
                        ->orWhere('p.sku', 'like', "%{$keyword}%");
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

            $costAfterExpr = '
                COALESCE(
                    NULLIF(l.actual_cost_after_vat, 0),
                    NULLIF(l.cost_after_vat, 0),
                    COALESCE(l.cost_before_vat, 0) * (1 + (COALESCE(l.cost_vat_percent, 0) / 100)),
                    0
                )
            ';

            $row = $q->selectRaw("
                    COALESCE(SUM(COALESCE(l.qty_remaining, 0)), 0) as total_qty,
                    COALESCE(SUM(COALESCE(l.qty_remaining, 0) * {$costAfterExpr}), 0) as total_amount
                ")
                ->first();

            return (object) [
                'total_qty' => (int) ($row->total_qty ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback cũ nếu chưa có bảng lô
        |--------------------------------------------------------------------------
        */
        $costAfterExpr = "
            (CASE
                WHEN {$productTable}.price_agent_vat IS NOT NULL AND {$productTable}.price_agent_vat > 0
                    THEN {$productTable}.price_agent_vat
                ELSE
                    COALESCE({$productTable}.price_agent,0) * (1 + (COALESCE({$productTable}.vat_percent,0) / 100))
            END)
        ";

        $q = DB::table($productTable)
            ->leftJoin('crm_product_stock as s', 's.product_id', '=', "{$productTable}.id");

        if (Schema::hasColumn($productTable, 'is_active')) {
            $q->where(function ($activeQuery) use ($productTable) {
                $activeQuery->where("{$productTable}.is_active", 1)->orWhereNull("{$productTable}.is_active");
            });
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword, $productTable) {
                $w->where("{$productTable}.name", 'like', "%{$keyword}%")
                    ->orWhere("{$productTable}.sku", 'like', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $q->where("{$productTable}.category_id", $categoryId);
        }
        if ($brandId) {
            $q->where("{$productTable}.brand_id", $brandId);
        }

        if ($warehouseId) {
            $q->where('s.warehouse_id', $warehouseId);
        } elseif ($companyId) {
            $q->whereIn('s.warehouse_id', function ($qq) use ($companyId) {
                $qq->from('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', $companyId);
            });
        }

        $row = $q->selectRaw("
                COALESCE(SUM(COALESCE(s.qty,0)),0) as total_qty,
                COALESCE(SUM(($costAfterExpr) * COALESCE(s.qty,0)),0) as total_amount
            ")
            ->first();

        return (object) [
            'total_qty' => (int) ($row->total_qty ?? 0),
            'total_amount' => (float) ($row->total_amount ?? 0),
        ];
    }

    /**
     * Tách danh sách tồn kho theo từng dòng nhập/lô FIFO.
     * Một dòng = Product + SKU + kho + giá vốn + số lượng còn + chi phí riêng.
     */
    public function paginateStockLotIndexRows(
        string $keyword,
        ?int $categoryId,
        ?int $brandId,
        ?int $companyId,
        ?int $warehouseId,
        int $perPage = 20
    ): LengthAwarePaginator {
        $productTable = (new Product)->getTable();
        $costAfterExpr = $this->stockLotActualCostExpr('l', 'p');

        /*
        |--------------------------------------------------------------------------
        | EGO V10: catalog la bang goc, loc kho/cong ty ngay trong LEFT JOIN
        |--------------------------------------------------------------------------
        | Neu loc l.warehouse_id/l.company_id bang WHERE o ben duoi, cac san pham
        | khong co lo con ton se co l.* = NULL va bi loai khoi ket qua. Do do san
        | pham ton = 0 / chua tung co lo trong kho se "bien mat".
        |
        | Dua dieu kien vao ON cua LEFT JOIN giu lai moi san pham active trong
        | catalog; san pham khong co lo phu hop se nhan stocks_sum_qty = 0.
        */
        $q = Product::query()
            ->from($productTable.' as p')
            ->leftJoin('crm_product_stock_lots as l', function ($join) use ($warehouseId, $companyId) {
                $join->on('l.product_id', '=', 'p.id')
                    ->where('l.qty_remaining', '>', 0);

                if ($warehouseId) {
                    $join->where('l.warehouse_id', '=', $warehouseId);
                } elseif ($companyId) {
                    $join->where('l.company_id', '=', $companyId);
                }
            })
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('companies as c', 'c.id', '=', 'l.company_id');

        try {
            $q->with(['mainImage']);
        } catch (\Throwable $e) {
        }
        try {
            $q->with(['brand', 'category']);
        } catch (\Throwable $e) {
        }

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

        $q->select([
            'p.*',
            DB::raw('l.id as stock_lot_id'),
            DB::raw('l.lot_code as stock_lot_code'),
            DB::raw('l.lot_name as stock_lot_name'),
            DB::raw('l.company_id as stock_lot_company_id'),
            DB::raw('l.warehouse_id as stock_lot_warehouse_id'),
            DB::raw('c.name as stock_lot_company_name'),
            DB::raw('w.name as warehouse_name'),
            DB::raw('COALESCE(l.qty_in, 0) as lot_qty_in'),
            DB::raw('COALESCE(l.qty_remaining, 0) as stocks_sum_qty'),
            DB::raw('COALESCE(l.qty_remaining, 0) as warehouse_qty'),
            DB::raw('l.received_at as stock_lot_received_at'),
            DB::raw('COALESCE(l.cost_before_vat, p.price_agent, 0) as lot_cost_before_vat'),
            DB::raw('COALESCE(l.cost_vat_percent, p.cost_vat_percent, p.vat_percent, 0) as lot_cost_vat_percent'),
            DB::raw('COALESCE(l.cost_after_vat, p.price_agent_vat, 0) as lot_cost_after_vat'),
            DB::raw('COALESCE(l.extra_cost, 0) as lot_extra_cost'),
            DB::raw("{$costAfterExpr} as lot_actual_cost_after_vat"),
            DB::raw('COALESCE(l.cost_before_vat, p.price_agent, 0) as price_agent'),
            DB::raw("{$costAfterExpr} as price_agent_vat"),
            DB::raw('(COALESCE(l.qty_remaining, 0) * '.$costAfterExpr.') as lot_total_amount'),
        ]);

        return $q->orderByRaw('p.name ASC, p.sku ASC, COALESCE(l.received_at, l.created_at) ASC, l.id ASC')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Sinh biểu thức SQL tính giá vốn sau VAT của lô (ưu tiên actual_cost_after_vat, rồi cost_after_vat, cuối cùng tính từ giá trước VAT).
     */
    public function stockLotActualCostExpr(string $lotAlias = 'l', string $productAlias = 'p'): string
    {
        return "
            COALESCE(
                NULLIF({$lotAlias}.actual_cost_after_vat, 0),
                NULLIF({$lotAlias}.cost_after_vat, 0),
                COALESCE({$lotAlias}.cost_before_vat, {$productAlias}.price_agent, 0)
                    * (1 + (COALESCE({$lotAlias}.cost_vat_percent, {$productAlias}.cost_vat_percent, {$productAlias}.vat_percent, 0) / 100)),
                0
            )
        ";
    }

    /**
     * Gắn giá vốn bình quân (trước/sau VAT) tính từ các lô còn tồn vào từng sản phẩm trong danh sách.
     */
    public function attachLotAverageCostsToProducts($products, ?int $companyId = null, ?int $warehouseId = null): void
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        $items = method_exists($products, 'items') ? $products->items() : (is_iterable($products) ? $products : []);
        $ids = collect($items)->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $q = DB::table('crm_product_stock_lots as l')
            ->whereIn('l.product_id', $ids->all())
            ->where('l.qty_remaining', '>', 0);

        if ($warehouseId) {
            $q->where('l.warehouse_id', $warehouseId);
        } elseif ($companyId) {
            $q->where('l.company_id', $companyId);
        }

        $costBeforeExpr = 'COALESCE(l.cost_before_vat, 0)';
        $vatExpr = 'COALESCE(l.cost_vat_percent, 0)';
        $costAfterExpr = '
            COALESCE(
                NULLIF(l.actual_cost_after_vat, 0),
                NULLIF(l.cost_after_vat, 0),
                COALESCE(l.cost_before_vat, 0) * (1 + (COALESCE(l.cost_vat_percent, 0) / 100)),
                0
            )
        ';

        $rows = $q->selectRaw("
                l.product_id,
                COALESCE(SUM(l.qty_remaining), 0) as lot_qty,
                COALESCE(SUM(l.qty_remaining * {$costBeforeExpr}) / NULLIF(SUM(l.qty_remaining), 0), 0) as avg_cost_before_vat,
                COALESCE(SUM(l.qty_remaining * {$vatExpr}) / NULLIF(SUM(l.qty_remaining), 0), 0) as avg_cost_vat_percent,
                COALESCE(SUM(l.qty_remaining * {$costAfterExpr}) / NULLIF(SUM(l.qty_remaining), 0), 0) as avg_cost_after_vat,
                COALESCE(SUM(l.qty_remaining * {$costAfterExpr}), 0) as lot_total_amount
            ")
            ->groupBy('l.product_id')
            ->get()
            ->keyBy('product_id');

        foreach ($items as $product) {
            $row = $rows->get($product->id);

            if (! $row) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Ghi đè thuộc tính hiển thị
            |--------------------------------------------------------------------------
            | Để view cũ đang dùng price_agent / price_agent_vat tự hiển thị đúng
            | giá vốn trung bình theo lô mà không cần sửa nhiều blade.
            */
            $product->price_agent = (float) $row->avg_cost_before_vat;
            $product->price_agent_vat = (float) $row->avg_cost_after_vat;
            $product->cost_vat_percent = (float) $row->avg_cost_vat_percent;

            // Một số view cũ đọc vat_percent cho cột VAT giá vốn.
            $product->vat_percent = (float) $row->avg_cost_vat_percent;

            $product->lot_avg_cost_before_vat = (float) $row->avg_cost_before_vat;
            $product->lot_avg_cost_after_vat = (float) $row->avg_cost_after_vat;
            $product->lot_avg_cost_vat_percent = (float) $row->avg_cost_vat_percent;
            $product->lot_total_amount = (float) $row->lot_total_amount;
            $product->lot_qty = (int) $row->lot_qty;
        }
    }
}
