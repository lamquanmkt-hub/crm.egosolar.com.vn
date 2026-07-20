<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiểm tra tồn kho trước khi xuất hàng cho đơn.
 *
 * Chịu trách nhiệm:
 * - Chặn xuất kho khi tồn hiện tại không đủ so với số lượng đơn cần
 * - Tính tồn hiện tại của 1 sản phẩm tại 1 kho (ưu tiên crm_product_stock,
 *   fallback crm_product_stock_lots rồi crm_product_catalog.quantity)
 * - Gom tồn theo sản phẩm của cả kho phục vụ form chọn sản phẩm
 *
 * Tách từ OrderController (P1a refactor) — hành vi giữ nguyên,
 * được chốt bằng WarehouseIssueCharacterizationTest.
 */
class OrderStockGuard
{
    /**
     * Chặn xuất kho nếu tồn hiện tại không đủ. Tạo đơn vẫn cho phép tồn 0.
     *
     * @param  int  $orderId  Id đơn hàng cần kiểm tra
     * @param  bool  $lockRows  Khóa dòng tồn kho (lockForUpdate) khi đang trong transaction xuất kho
     *
     * @throws \RuntimeException Khi có ít nhất 1 dòng hàng thiếu tồn hoặc chưa chọn kho
     */
    public function assertOrderStockAvailable(int $orderId, bool $lockRows = false): void
    {
        if (! Schema::hasTable('crm_orders') || ! Schema::hasTable('crm_order_items')) {
            return;
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        if (! $order) {
            return;
        }

        $itemColumns = Schema::getColumnListing('crm_order_items');
        $hasItemWarehouse = in_array('warehouse_id', $itemColumns, true);

        $itemsQuery = DB::table('crm_order_items')
            ->where('order_id', $orderId)
            ->select(['id', 'product_id', 'quantity']);

        if ($hasItemWarehouse) {
            $itemsQuery->addSelect('warehouse_id');
        }

        $requiredRows = [];
        foreach ($itemsQuery->get() as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $warehouseId = $hasItemWarehouse
                ? (int) ($item->warehouse_id ?? 0)
                : (int) ($order->warehouse_id ?? 0);
            $quantity = (int) ($item->quantity ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $productId.':'.$warehouseId;
            if (! isset($requiredRows[$key])) {
                $requiredRows[$key] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                ];
            }

            $requiredRows[$key]['quantity'] += $quantity;
        }

        $errors = [];
        foreach ($requiredRows as $row) {
            $productId = (int) $row['product_id'];
            $warehouseId = (int) $row['warehouse_id'];
            $requiredQty = (int) $row['quantity'];

            if ($warehouseId <= 0) {
                $errors[] = $this->productLabel($productId).' chưa chọn kho xuất.';

                continue;
            }

            $availableQty = $this->currentWarehouseStock($productId, $warehouseId, $lockRows);

            if ($availableQty < $requiredQty) {
                if ($availableQty <= 0) {
                    $errors[] = sprintf(
                        '%s tại %s đã hết hàng. Vui lòng điều hàng/nhập kho trước khi xuất kho.',
                        $this->productLabel($productId),
                        $this->warehouseLabel($warehouseId)
                    );
                } else {
                    $errors[] = sprintf(
                        '%s tại %s không đủ tồn: chỉ còn %s, cần xuất %s. Vui lòng điều hàng/nhập kho trước khi xuất kho.',
                        $this->productLabel($productId),
                        $this->warehouseLabel($warehouseId),
                        number_format($availableQty, 0, ',', '.'),
                        number_format($requiredQty, 0, ',', '.')
                    );
                }
            }
        }

        if (! empty($errors)) {
            throw new \RuntimeException("Không đủ tồn kho để xuất kho:\n- ".implode("\n- ", $errors));
        }
    }

    /**
     * Lấy tồn hiện tại của sản phẩm tại một kho; có thể khóa dòng (lockForUpdate) khi xuất kho.
     *
     * Thứ tự nguồn tồn: crm_product_stock → crm_product_stock_lots → crm_product_catalog.quantity.
     */
    public function currentWarehouseStock(int $productId, int $warehouseId, bool $lockRows = false): int
    {
        if (Schema::hasTable('crm_product_stock')) {
            $query = DB::table('crm_product_stock')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId);

            if ($lockRows) {
                $query->lockForUpdate();
            }

            return max(0, (int) $query->sum('qty'));
        }

        if (Schema::hasTable('crm_product_stock_lots')) {
            $query = DB::table('crm_product_stock_lots')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId);

            if ($lockRows) {
                $query->lockForUpdate();
            }

            return max(0, (int) $query->sum('qty_remaining'));
        }

        if (Schema::hasTable('crm_product_catalog') && Schema::hasColumn('crm_product_catalog', 'quantity')) {
            return max(0, (int) DB::table('crm_product_catalog')->where('id', $productId)->value('quantity'));
        }

        return 0;
    }

    /**
     * Gom tồn khả dụng theo sản phẩm của 1 kho: map product_id => tổng tồn.
     *
     * @return array<int, int>
     */
    public function stockByProductForWarehouse(int $warehouseId): array
    {
        if (Schema::hasTable('crm_product_stock')) {
            return DB::table('crm_product_stock')
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) as stock_qty')
                ->groupBy('product_id')
                ->pluck('stock_qty', 'product_id')
                ->map(fn ($qty) => (int) $qty)
                ->all();
        }

        if (Schema::hasTable('crm_product_stock_lots')) {
            return DB::table('crm_product_stock_lots')
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('product_id, COALESCE(SUM(qty_remaining), 0) as stock_qty')
                ->groupBy('product_id')
                ->pluck('stock_qty', 'product_id')
                ->map(fn ($qty) => (int) $qty)
                ->all();
        }

        return [];
    }

    /**
     * Nhãn hiển thị sản phẩm dạng "Tên (SKU)" dùng trong thông báo lỗi tồn kho.
     */
    private function productLabel(int $productId): string
    {
        if (! Schema::hasTable('crm_product_catalog')) {
            return 'Sản phẩm #'.$productId;
        }

        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->select(['id', 'name', 'sku'])
            ->first();

        if (! $product) {
            return 'Sản phẩm #'.$productId;
        }

        $name = trim((string) ($product->name ?? 'Sản phẩm #'.$productId));
        $sku = trim((string) ($product->sku ?? ''));

        return $sku !== '' ? ($name.' ('.$sku.')') : $name;
    }

    /**
     * Nhãn hiển thị tên kho dùng trong thông báo lỗi tồn kho.
     */
    private function warehouseLabel(int $warehouseId): string
    {
        if (! Schema::hasTable('crm_warehouses')) {
            return 'Kho #'.$warehouseId;
        }

        $name = DB::table('crm_warehouses')->where('id', $warehouseId)->value('name');

        return $name ? (string) $name : ('Kho #'.$warehouseId);
    }
}
