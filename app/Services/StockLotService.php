<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Stock\ProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockLotService
{
    public function syncManualStock(
        Product $product,
        int $companyId,
        int $warehouseId,
        int $targetQty,
        float $costBeforeVat = 0,
        float $costVatPercent = 0
    ): void {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            $this->syncLegacyStock((int) $product->id, $companyId, $warehouseId, $targetQty);
            return;
        }

        $stock = ProductStock::where([
            'product_id'   => (int) $product->id,
            'company_id'   => $companyId,
            'warehouse_id' => $warehouseId,
        ])->lockForUpdate()->first();

        $currentQty = (int) ($stock->qty ?? 0);
        $diff = $targetQty - $currentQty;

        if ($diff === 0) {
            ProductStock::updateOrCreate(
                [
                    'product_id'   => (int) $product->id,
                    'company_id'   => $companyId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'qty'          => $targetQty,
                    'last_updated' => now(),
                ]
            );
            return;
        }

        if ($diff > 0) {
            $this->receiveLot($product, $companyId, $warehouseId, $diff, $costBeforeVat, $costVatPercent, 0, [
                'source_type' => 'manual_stock_sync',
                'note' => 'Tăng tồn từ form sản phẩm',
            ]);
            return;
        }

        $this->issueLots((int) $product->id, $companyId, $warehouseId, abs($diff), [
            'reason' => 'Giảm tồn từ form sản phẩm',
            'reference_type' => 'manual_stock_sync',
            'reference_id' => (int) $product->id,
        ]);
    }

    public function receiveLot(
        Product $product,
        int $companyId,
        int $warehouseId,
        int $qtyIn,
        float $costBeforeVat,
        float $costVatPercent,
        float $extraCost = 0,
        array $meta = []
    ): int {
        if ($qtyIn <= 0) {
            throw new \InvalidArgumentException('Số lượng nhập lô phải lớn hơn 0.');
        }

        if (!Schema::hasTable('crm_product_stock_lots')) {
            $this->changeProductStock((int) $product->id, $companyId, $warehouseId, $qtyIn, 'Nhập kho', (int) $product->id);
            return 0;
        }

        $costAfterVat = round($costBeforeVat * (1 + max(0, $costVatPercent) / 100), 2);
        $actualCostAfterVat = round($costAfterVat + (($qtyIn > 0) ? ($extraCost / $qtyIn) : 0), 2);

        $columns = Schema::getColumnListing('crm_product_stock_lots');
        $has = fn (string $column): bool => in_array($column, $columns, true);
        $put = function (array &$payload, string $column, $value) use ($has): void {
            if ($has($column)) {
                $payload[$column] = $value;
            }
        };

        $lotCode = trim((string) ($meta['lot_code'] ?? ''));
        if ($lotCode === '') {
            $lotCode = 'LOT-P' . $product->id . '-W' . $warehouseId . '-' . now()->format('YmdHis');
        }

        $payload = [];
        $put($payload, 'product_id', (int) $product->id);
        $put($payload, 'company_id', $companyId);
        $put($payload, 'warehouse_id', $warehouseId);
        $put($payload, 'lot_code', $lotCode);
        $put($payload, 'lot_name', $meta['lot_name'] ?? $lotCode);
        $put($payload, 'received_at', $meta['received_at'] ?? now());
        $put($payload, 'qty_in', $qtyIn);
        $put($payload, 'qty_remaining', $qtyIn);
        $put($payload, 'cost_before_vat', $costBeforeVat);
        $put($payload, 'cost_vat_percent', $costVatPercent);
        $put($payload, 'cost_after_vat', $costAfterVat);
        $put($payload, 'extra_cost', $extraCost);
        $put($payload, 'actual_cost_after_vat', $actualCostAfterVat);
        $put($payload, 'source_type', $meta['source_type'] ?? 'manual_lot');
        $put($payload, 'source_id', $meta['source_id'] ?? (int) $product->id);
        $put($payload, 'note', $meta['note'] ?? null);
        $put($payload, 'created_by', auth()->id());
        $put($payload, 'created_at', now());
        $put($payload, 'updated_at', now());

        $lotId = (int) DB::table('crm_product_stock_lots')->insertGetId($payload);

        $this->changeProductStock(
            (int) $product->id,
            $companyId,
            $warehouseId,
            $qtyIn,
            (string) ($meta['reason'] ?? 'Nhập lô tồn kho'),
            $lotId,
            (string) ($meta['reference_type'] ?? $meta['source_type'] ?? 'manual_lot'),
            (string) ($meta['note'] ?? ($meta['reason'] ?? 'Nhập lô tồn kho'))
        );

        return $lotId;
    }

    public function issueLots(
        int $productId,
        ?int $companyId,
        int $warehouseId,
        int $qty,
        array $meta = []
    ): array {
        if ($qty <= 0) {
            return [];
        }

        if (!Schema::hasTable('crm_product_stock_lots')) {
            $this->changeProductStock($productId, (int) ($companyId ?? 0), $warehouseId, -$qty, $meta['reason'] ?? 'Xuất kho', (int) ($meta['reference_id'] ?? 0));
            return [];
        }

        $remaining = $qty;
        $allocations = [];

        $lotsQuery = DB::table('crm_product_stock_lots')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('COALESCE(received_at, created_at) ASC')
            ->orderBy('id')
            ->lockForUpdate();

        if ($companyId) {
            $lotsQuery->where('company_id', $companyId);
        }

        $lots = $lotsQuery->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $lot->qty_remaining);
            if ($take <= 0) {
                continue;
            }

            DB::table('crm_product_stock_lots')
                ->where('id', $lot->id)
                ->update([
                    'qty_remaining' => (int) $lot->qty_remaining - $take,
                    'updated_at' => now(),
                ]);

            $unitCostBeforeVat = (float) ($lot->cost_before_vat ?? 0);
            $unitCostAfterVat = (float) ($lot->actual_cost_after_vat ?: $lot->cost_after_vat ?: 0);

            $allocations[] = [
                'stock_lot_id' => (int) $lot->id,
                'product_id' => $productId,
                'company_id' => $lot->company_id ? (int) $lot->company_id : null,
                'warehouse_id' => (int) $lot->warehouse_id,
                'qty' => $take,
                'unit_cost_before_vat' => $unitCostBeforeVat,
                'unit_cost_after_vat' => $unitCostAfterVat,
                'total_cost_after_vat' => round($unitCostAfterVat * $take, 2),
            ];

            $this->insertOrderAllocationIfPossible($allocations[array_key_last($allocations)], $meta);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \Exception('Không đủ tồn theo SKU/lô để xuất. Cần ' . $qty . ', còn thiếu ' . $remaining . '.');
        }

        $this->changeProductStock(
            $productId,
            (int) ($companyId ?? ($allocations[0]['company_id'] ?? 0)),
            $warehouseId,
            -$qty,
            (string) ($meta['reason'] ?? 'Xuất kho theo FIFO'),
            (int) ($meta['reference_id'] ?? 0),
            (string) ($meta['reference_type'] ?? 'stock_issue'),
            (string) ($meta['note'] ?? ($meta['reason'] ?? 'Xuất kho theo FIFO'))
        );

        return $allocations;
    }

    public function changeProductStock(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $changeQty,
        string $reason,
        int $referenceId = 0,
        ?string $referenceType = null,
        ?string $note = null
    ): void {
        if ($changeQty === 0 && $reason === '') {
            return;
        }

        $stock = ProductStock::where([
            'product_id'   => $productId,
            'company_id'   => $companyId,
            'warehouse_id' => $warehouseId,
        ])->lockForUpdate()->first();

        $oldQty = (int) ($stock->qty ?? 0);
        $newQty = $oldQty + $changeQty;

        if ($newQty < 0) {
            throw new \Exception('Không đủ tồn kho tổng để trừ. Tồn hiện tại ' . $oldQty . ', cần ' . abs($changeQty) . '.');
        }

        ProductStock::updateOrCreate(
            [
                'product_id'   => $productId,
                'company_id'   => $companyId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'qty'          => $newQty,
                'last_updated' => now(),
            ]
        );

        $this->logMovement($productId, $companyId, $warehouseId, $changeQty, $oldQty, $newQty, $reason, $referenceId, $referenceType, $note);
    }


    /**
     * Xuất kho cho 1 dòng đơn hàng theo FIFO.
     *
     * OrderController đang gọi method này khi duyệt bước Kho.
     * Method này lấy đúng product, kho, số lượng từ order item,
     * sau đó trừ từng lô trong crm_product_stock_lots và ghi allocation
     * vào crm_order_item_stock_allocations nếu bảng tồn tại.
     */
    public function issueOrderItemFifo($order, $item): array
    {
        $qty = (int) ($item->quantity ?? $item->qty ?? 0);

        if ($qty <= 0) {
            return [];
        }

        $productId = (int) ($item->product_id ?? optional($item->product)->id ?? 0);
        $warehouseId = (int) ($item->warehouse_id ?? $order->warehouse_id ?? 0);
        $companyIdRaw = $item->company_id ?? $order->company_id ?? null;
        $companyId = $companyIdRaw ? (int) $companyIdRaw : null;

        if ($productId <= 0) {
            throw new \Exception('Không xác định được sản phẩm để xuất kho.');
        }

        if ($warehouseId <= 0) {
            throw new \Exception('Không xác định được kho xuất cho sản phẩm #' . $productId . '.');
        }

        $orderId = (int) ($order->id ?? 0);
        $orderItemId = (int) ($item->id ?? 0);

        /*
         * Chống trừ tồn 2 lần nếu trước đó đã ghi allocation nhưng quy trình bị lỗi giữa chừng.
         */
        if (Schema::hasTable('crm_order_item_stock_allocations') && $orderId > 0 && $orderItemId > 0) {
            $allocatedQty = (int) DB::table('crm_order_item_stock_allocations')
                ->where('order_id', $orderId)
                ->where('order_item_id', $orderItemId)
                ->sum('qty');

            if ($allocatedQty >= $qty) {
                return DB::table('crm_order_item_stock_allocations')
                    ->where('order_id', $orderId)
                    ->where('order_item_id', $orderItemId)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
            }

            $qty -= $allocatedQty;
        }

        $orderCode = (string) ($order->order_code ?? ('#' . $orderId));

        return $this->issueLots($productId, $companyId, $warehouseId, $qty, [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'reason' => 'Xuất kho đơn hàng ' . $orderCode,
        ]);
    }

    private function syncLegacyStock(int $productId, int $companyId, int $warehouseId, int $targetQty): void
    {
        ProductStock::updateOrCreate(
            [
                'product_id'   => $productId,
                'company_id'   => $companyId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'qty'          => max(0, $targetQty),
                'last_updated' => now(),
            ]
        );
    }

    private function insertOrderAllocationIfPossible(array $allocation, array $meta): void
    {
        if (!Schema::hasTable('crm_order_item_stock_allocations')) {
            return;
        }

        $orderId = (int) ($meta['order_id'] ?? 0);
        $orderItemId = (int) ($meta['order_item_id'] ?? 0);

        if ($orderId <= 0 || $orderItemId <= 0) {
            return;
        }

        DB::table('crm_order_item_stock_allocations')->insert([
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $allocation['product_id'],
            'stock_lot_id' => $allocation['stock_lot_id'],
            'company_id' => $allocation['company_id'],
            'warehouse_id' => $allocation['warehouse_id'],
            'qty' => $allocation['qty'],
            'unit_cost_before_vat' => $allocation['unit_cost_before_vat'],
            'unit_cost_after_vat' => $allocation['unit_cost_after_vat'],
            'total_cost_after_vat' => $allocation['total_cost_after_vat'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function logMovement(
        int $productId,
        int $companyId,
        int $warehouseId,
        int $changeQty,
        int $qtyBefore,
        int $qtyAfter,
        string $reason,
        int $referenceId = 0,
        ?string $referenceType = null,
        ?string $note = null
    ): void {
        if (!Schema::hasTable('crm_stock_movements')) {
            return;
        }

        $columns = Schema::getColumnListing('crm_stock_movements');
        $has = fn (string $column): bool => in_array($column, $columns, true);
        $payload = [];

        if ($has('product_id')) $payload['product_id'] = $productId;
        if ($has('company_id')) $payload['company_id'] = $companyId;
        if ($has('warehouse_id')) $payload['warehouse_id'] = $warehouseId;
        if ($has('change_qty')) $payload['change_qty'] = $changeQty;
        if ($has('qty_before')) $payload['qty_before'] = $qtyBefore;
        if ($has('qty_after')) $payload['qty_after'] = $qtyAfter;
        if ($has('type')) $payload['type'] = $changeQty >= 0 ? 'in' : 'out';
        if ($has('movement_type')) $payload['movement_type'] = $changeQty >= 0 ? 'in' : 'out';
        if ($has('reason')) $payload['reason'] = $reason;
        if ($has('note')) $payload['note'] = $note ?: $reason;
        if ($has('reference_type')) $payload['reference_type'] = $referenceType;
        if ($has('reference_id')) $payload['reference_id'] = $referenceId ?: null;
        if ($has('created_by')) $payload['created_by'] = auth()->id();
        if ($has('user_id')) $payload['user_id'] = auth()->id();
        if ($has('created_at')) $payload['created_at'] = now();
        if ($has('updated_at')) $payload['updated_at'] = now();

        if (!empty($payload)) {
            DB::table('crm_stock_movements')->insert($payload);
        }
    }
}
