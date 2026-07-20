<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ProductStockServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service xử lý tồn kho sản phẩm (ProductStock): kiểm tra, điều chỉnh, báo cáo.
 */
class ProductStockService implements ProductStockServiceInterface
{
    /**
     * Tự resolve company_id theo kho/sp nếu caller không truyền
     */
    private function resolveCompanyId(int $warehouseId, int $productId): ?int
    {
        $cid = (int) (DB::table('company_warehouse')->where('warehouse_id', $warehouseId)->value('company_id') ?? 0);
        if ($cid > 0) {
            return $cid;
        }

        $cid = (int) (DB::table('crm_product_stock')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('company_id') ?? 0);

        return $cid > 0 ? $cid : null;
    }

    /**
     * Kiểm tra tồn kho
     */
    public function checkStock($productId, $warehouseId, ?int $companyId = null): int
    {
        $productId = (int) $productId;
        $warehouseId = (int) $warehouseId;

        if ($companyId === null) {
            $companyId = $this->resolveCompanyId($warehouseId, $productId);
        }

        $q = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($companyId !== null) {
            $q->where('company_id', $companyId);
        }

        return (int) ($q->value('qty') ?? 0);
    }

    /**
     * Điều chỉnh tồn kho
     *
     * ✅ FIX: dùng Query Builder + lockForUpdate => không cần PK vẫn update được
     */
    public function adjustStock(
        $productId,
        $warehouseId,
        int $changeQty,
        ?string $reason = null,
        $referenceId = null,
        ?int $companyId = null
    ): void {
        $productId = (int) $productId;
        $warehouseId = (int) $warehouseId;

        DB::transaction(function () use ($productId, $warehouseId, $changeQty, $reason, $referenceId, $companyId) {

            if ($companyId === null) {
                $companyId = $this->resolveCompanyId($warehouseId, $productId);
            }
            if (! $companyId) {
                throw new \Exception("Thiếu company_id để cập nhật tồn kho (warehouse_id={$warehouseId}, product_id={$productId}).");
            }

            $baseWhere = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'company_id' => $companyId,
            ];

            // lock row để tránh trừ tồn race-condition
            $row = DB::table('crm_product_stock')
                ->where($baseWhere)
                ->lockForUpdate()
                ->first();

            $currentQty = (int) ($row->qty ?? 0);
            $newQty = $currentQty + (int) $changeQty;

            if ($newQty < 0) {
                throw new \Exception("Không đủ tồn kho. Hiện tại: {$currentQty}, Yêu cầu: ".abs($changeQty));
            }

            if (! $row) {
                DB::table('crm_product_stock')->insert([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'company_id' => $companyId,
                    'qty' => 0,
                    'serials_json' => null,
                    'last_updated' => now(),
                ]);
            }

            DB::table('crm_product_stock')
                ->where($baseWhere)
                ->update([
                    'qty' => $newQty,
                    'last_updated' => now(),
                ]);

            if (Schema::hasTable('crm_stock_movements')) {
                $columns = Schema::getColumnListing('crm_stock_movements');
                $has = fn (string $column): bool => in_array($column, $columns, true);
                $movement = [];

                if ($has('product_id')) {
                    $movement['product_id'] = $productId;
                }
                if ($has('warehouse_id')) {
                    $movement['warehouse_id'] = $warehouseId;
                }
                if ($has('company_id')) {
                    $movement['company_id'] = $companyId;
                }
                if ($has('change_qty')) {
                    $movement['change_qty'] = (int) $changeQty;
                }
                if ($has('qty_before')) {
                    $movement['qty_before'] = $currentQty;
                }
                if ($has('qty_after')) {
                    $movement['qty_after'] = $newQty;
                }
                if ($has('reason')) {
                    $movement['reason'] = $reason;
                }
                if ($has('reference_id')) {
                    $movement['reference_id'] = $referenceId;
                }
                if ($has('created_by')) {
                    $movement['created_by'] = Auth::id();
                }
                if ($has('created_at')) {
                    $movement['created_at'] = now();
                }
                if ($has('updated_at')) {
                    $movement['updated_at'] = now();
                }

                if (! empty($movement)) {
                    DB::table('crm_stock_movements')->insert($movement);
                }
            }
        });
    }

    /**
     * Nhập kho: tăng tồn kho sản phẩm tại kho chỉ định.
     */
    public function stockIn($productId, $warehouseId, int $quantity, string $reason = 'Nhập kho', ?int $companyId = null): void
    {
        $this->adjustStock($productId, $warehouseId, $quantity, $reason, null, $companyId);
    }

    /**
     * Xuất kho: giảm tồn kho sản phẩm tại kho chỉ định.
     */
    public function stockOut($productId, $warehouseId, int $quantity, string $reason = 'Xuất kho', $referenceId = null, ?int $companyId = null): void
    {
        $this->adjustStock($productId, $warehouseId, -$quantity, $reason, $referenceId, $companyId);
    }

    /**
     * Tính tổng tồn kho của một sản phẩm trên mọi kho.
     */
    public function getTotalStockByProduct($productId): int
    {
        return (int) DB::table('crm_product_stock')->where('product_id', (int) $productId)->sum('qty');
    }

    /**
     * Lấy danh sách sản phẩm sắp hết hàng (tồn <= ngưỡng, > 0).
     */
    public function getLowStockProducts($threshold = 10): Collection|array
    {
        // giữ nguyên nếu bạn đang dùng Eloquent ở nơi khác
        return \App\Models\Inventory\Stock\ProductStock::with(['product', 'warehouse'])
            ->where('qty', '<=', $threshold)
            ->where('qty', '>', 0)
            ->get();
    }

    /**
     * Lấy danh sách sản phẩm đã hết hàng (tồn = 0).
     */
    public function getOutOfStockProducts(): Collection|array
    {
        return \App\Models\Inventory\Stock\ProductStock::with(['product', 'warehouse'])
            ->where('qty', '=', 0)
            ->get();
    }

    /**
     * Báo cáo tồn kho theo kho, sắp xếp tồn tăng dần.
     */
    public function getStockReportByWarehouse($warehouseId): Collection|array
    {
        return \App\Models\Inventory\Stock\ProductStock::where('warehouse_id', (int) $warehouseId)
            ->with('product')
            ->orderBy('qty', 'asc')
            ->get();
    }

    /**
     * Tổng hợp tồn kho theo sản phẩm (tổng số lượng, số kho có hàng).
     */
    public function getStockSummary(): Collection|array
    {
        return \App\Models\Inventory\Stock\ProductStock::select(
            'product_id',
            DB::raw('SUM(qty) as total_qty'),
            DB::raw('COUNT(warehouse_id) as warehouse_count')
        )
            ->with('product')
            ->groupBy('product_id')
            ->having('total_qty', '>', 0)
            ->get();
    }

    /**
     * Lấy lịch sử biến động tồn kho của sản phẩm (có phân trang, lọc theo kho).
     */
    public function getStockHistory($productId, $warehouseId = null, $limit = 50): array|LengthAwarePaginator
    {
        $query = \App\Models\Inventory\Stock\StockMovement::where('product_id', (int) $productId)
            ->with(['product', 'warehouse', 'creator']);

        if ($warehouseId) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        return $query->latest()->paginate($limit);
    }
}
