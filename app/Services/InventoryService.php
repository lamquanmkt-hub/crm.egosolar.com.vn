<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service truy vấn tồn kho sản phẩm theo từng kho (có cache).
 */
final class InventoryService
{
    private const STOCK_TABLE = 'crm_product_stock';

    private const PRODUCT_TABLE = 'crm_product_catalog';

    /**
     * Trả về danh sách sản phẩm có tồn kho theo từng kho:
     * - warehouse_id
     * - product_id
     * - quantity (SUM)
     * - product_name
     */
    public function getProductsWithStock(): Collection
    {
        // đổi key để né cache cũ (nếu trước đó cache [] )
        return Cache::remember('inventory.products_with_stock:v5', 300, function (): Collection {
            return DB::table(self::STOCK_TABLE.' as s')
                ->leftJoin(self::PRODUCT_TABLE.' as p', 'p.id', '=', 's.product_id')
                ->select([
                    's.warehouse_id',
                    's.product_id',
                ])
                ->selectRaw('SUM(s.qty) as quantity')
                ->selectRaw('p.name as product_name')
                ->groupBy('s.warehouse_id', 's.product_id', 'p.name')
                ->havingRaw('SUM(s.qty) > 0')
                ->orderBy('s.warehouse_id')
                ->orderBy('p.name')
                ->get();
        });
    }
}
