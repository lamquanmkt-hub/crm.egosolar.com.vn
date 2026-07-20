<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MaterialRequestStatus;
use App\Models\Inventory\Catalog\Product;
use App\Models\Projects\MaterialRequest;
use App\Services\Inventory\Stock\StockLotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service quản lý đơn vật tư công trình: tạo nháp, duyệt, xuất kho, tính giá vốn.
 */
class MaterialRequestService
{
    /**
     * Tạo đơn vật tư nháp kèm các dòng vật tư trong kho và ngoài kho.
     */
    public function createDraft(array $data): MaterialRequest
    {
        return DB::transaction(function () use ($data) {
            $items = (array) ($data['items'] ?? []);
            $extraItems = (array) ($data['extra_items'] ?? []);

            $warehouseId = $this->pickWarehouseId($items, $extraItems);

            $materialRequestId = DB::table('material_requests')->insertGetId($this->filterColumns('material_requests', [
                'site_id' => (int) ($data['site_id'] ?? 0),
                'warehouse_id' => $warehouseId,
                'created_by' => Auth::id() ?: 1,
                'status' => MaterialRequestStatus::DRAFT->value,
                'note' => $data['note'] ?? null,
                'total_cost' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $this->insertRequestItems($materialRequestId, $warehouseId, $items, $extraItems);

            $materialRequest = MaterialRequest::query()
                ->with(['items.product', 'site'])
                ->findOrFail($materialRequestId);

            $this->refreshCosts($materialRequest);

            return $materialRequest->fresh(['items.product', 'site']);
        });
    }

    /**
     * Cập nhật đơn vật tư còn ở trạng thái được phép sửa, tạo lại các dòng item.
     */
    public function updateDraft(MaterialRequest $materialRequest, array $data): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest, $data) {
            if (! in_array((string) $materialRequest->status, [
                MaterialRequestStatus::DRAFT->value,
                MaterialRequestStatus::SUBMITTED->value,
                MaterialRequestStatus::ADMIN_APPROVED->value,
            ], true)) {
                abort(403, 'Chỉ được sửa đơn vật tư khi còn ở trạng thái nháp.');
            }

            $items = (array) ($data['items'] ?? []);
            $extraItems = (array) ($data['extra_items'] ?? []);

            $warehouseId = $this->pickWarehouseId($items, $extraItems, (int) ($materialRequest->warehouse_id ?? 0));

            DB::table('material_requests')
                ->where('id', $materialRequest->id)
                ->update($this->filterColumns('material_requests', [
                    'site_id' => (int) ($data['site_id'] ?? $materialRequest->site_id),
                    'warehouse_id' => $warehouseId,
                    'note' => $data['note'] ?? null,
                    'updated_at' => now(),
                ]));

            DB::table('material_request_items')
                ->where('material_request_id', $materialRequest->id)
                ->delete();

            $this->insertRequestItems((int) $materialRequest->id, $warehouseId, $items, $extraItems);

            $materialRequest = MaterialRequest::query()
                ->with(['items.product', 'site'])
                ->findOrFail($materialRequest->id);

            $this->refreshCosts($materialRequest);

            return $materialRequest->fresh(['items.product', 'site']);
        });
    }

    /**
     * Gửi đơn vật tư nháp cho admin duyệt.
     */
    public function submit(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest) {
            if ((string) $materialRequest->status !== MaterialRequestStatus::DRAFT->value) {
                abort(403, 'Chỉ đơn nháp mới được gửi admin duyệt.');
            }

            DB::table('material_requests')
                ->where('id', $materialRequest->id)
                ->update($this->filterColumns('material_requests', [
                    'status' => MaterialRequestStatus::SUBMITTED->value,
                    'updated_at' => now(),
                ]));

            return $materialRequest->fresh(['items.product', 'site']);
        });
    }

    /**
     * Admin duyệt đơn vật tư đang ở trạng thái chờ duyệt.
     */
    public function adminApprove(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest) {
            if (! in_array((string) $materialRequest->status, [
                MaterialRequestStatus::SUBMITTED->value,
                MaterialRequestStatus::DRAFT->value,
            ], true)) {
                abort(403, 'Đơn này không ở trạng thái chờ admin duyệt.');
            }

            DB::table('material_requests')
                ->where('id', $materialRequest->id)
                ->update($this->filterColumns('material_requests', [
                    'status' => MaterialRequestStatus::ADMIN_APPROVED->value,
                    'accounting_approved_at' => now(),
                    'accounting_approved_by' => Auth::id(),
                    'updated_at' => now(),
                ]));

            return $materialRequest->fresh(['items.product', 'site']);
        });
    }

    /**
     * Kho duyệt và xuất kho đơn vật tư: cập nhật giá vốn, trừ tồn, xử lý vật tư ngoài kho.
     */
    public function warehouseApprove(MaterialRequest $materialRequest, array $costs = []): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest, $costs) {
            $materialRequest = MaterialRequest::query()
                ->with(['items.product', 'site'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if ((string) $materialRequest->status === MaterialRequestStatus::EXPORTED->value) {
                $this->refreshCosts($materialRequest);

                return $materialRequest->fresh(['items.product', 'site']);
            }

            if ((string) $materialRequest->status !== MaterialRequestStatus::ADMIN_APPROVED->value) {
                abort(403, 'Chỉ đơn đã admin duyệt mới được kho duyệt / xuất kho.');
            }

            foreach ($materialRequest->items as $item) {
                $itemId = (int) $item->id;
                $qty = $this->toFloat($item->qty ?? 0);
                $qty = $qty > 0 ? $qty : 1;

                if (! empty($item->product_id)) {
                    $this->refreshStockItemCost($itemId, (int) $item->product_id, $qty);
                    $this->exportStockProduct($materialRequest, (int) $item->product_id, $qty);

                    continue;
                }

                $costRow = (array) ($costs[$itemId] ?? []);

                $unitCostBeforeVat = $this->toFloat($costRow['unit_cost'] ?? $item->unit_cost ?? 0);
                $vatPercent = $this->toFloat($costRow['vat_percent'] ?? $item->vat_percent ?? 0);
                $unit = trim((string) ($costRow['unit'] ?? $item->unit ?? $this->externalUnitFromNote((string) $item->note)));
                $warehouseId = (int) ($costRow['warehouse_id'] ?? $materialRequest->warehouse_id ?? 0);
                $addToCatalog = (string) ($costRow['add_to_catalog'] ?? '0') === '1';

                if ($unitCostBeforeVat <= 0) {
                    throw new RuntimeException('Vui lòng nhập giá vốn cho vật tư ngoài kho: '.$this->externalName((string) $item->note));
                }

                $lineTotal = $this->lineTotalWithVat($qty, $unitCostBeforeVat, $vatPercent);

                DB::table('material_request_items')
                    ->where('id', $itemId)
                    ->update($this->filterColumns('material_request_items', [
                        'unit' => $unit !== '' ? $unit : null,
                        'unit_cost' => $unitCostBeforeVat,
                        'vat_percent' => $vatPercent,
                        'line_total' => $lineTotal,
                        'updated_at' => now(),
                    ]));

                if ($addToCatalog) {
                    if ($warehouseId <= 0) {
                        throw new RuntimeException('Vui lòng chọn kho cho vật tư ngoài kho: '.$this->externalName((string) $item->note));
                    }

                    $productId = $this->createOrUpdateExternalProduct(
                        materialRequestId: (int) $materialRequest->id,
                        itemId: $itemId,
                        name: $this->externalName((string) $item->note),
                        unit: $unit,
                        unitCostBeforeVat: $unitCostBeforeVat,
                        vatPercent: $vatPercent
                    );

                    $this->importThenExportExternalProduct(
                        materialRequest: $materialRequest,
                        productId: $productId,
                        warehouseId: $warehouseId,
                        qty: $qty,
                        itemId: $itemId
                    );
                }
            }

            $this->refreshCosts($materialRequest);

            DB::table('material_requests')
                ->where('id', $materialRequest->id)
                ->update($this->filterColumns('material_requests', [
                    'status' => MaterialRequestStatus::EXPORTED->value,
                    'updated_at' => now(),
                ]));

            Cache::forget('products.dropdown');
            Cache::forget('warehouses.dropdown');

            return $materialRequest->fresh(['items.product', 'site']);
        });
    }

    /**
     * Tính lại giá vốn từng dòng và tổng chi phí đơn, đồng bộ chi phí sang công trình.
     *
     * @return float Tổng chi phí của đơn vật tư.
     */
    public function refreshCosts(MaterialRequest $materialRequest): float
    {
        $items = DB::table('material_request_items')
            ->where('material_request_id', $materialRequest->id)
            ->get();

        $total = 0.0;

        foreach ($items as $item) {
            $qty = $this->toFloat($item->qty ?? 0);
            $qty = $qty > 0 ? $qty : 1;

            if (! empty($item->product_id)) {
                $product = DB::table('crm_product_catalog')
                    ->where('id', (int) $item->product_id)
                    ->first();

                $unitCostAfterVat = $this->productUnitCostAfterVat($product);
                $unit = $product->unit ?? $item->unit ?? null;
                $vatPercent = $this->toFloat($product->cost_vat_percent ?? $product->vat_percent ?? $item->vat_percent ?? 0);

                if ($this->toFloat($item->unit_cost ?? 0) > 0) {
                    $unitCostAfterVat = $this->toFloat($item->unit_cost);
                }

                $lineTotal = round($qty * $unitCostAfterVat, 2);

                DB::table('material_request_items')
                    ->where('id', $item->id)
                    ->update($this->filterColumns('material_request_items', [
                        'unit' => $unit,
                        'unit_cost' => $unitCostAfterVat,
                        'vat_percent' => $vatPercent,
                        'line_total' => $lineTotal,
                        'updated_at' => now(),
                    ]));

                $total += $lineTotal;

                continue;
            }

            $unitCostBeforeVat = $this->toFloat($item->unit_cost ?? 0);
            $vatPercent = $this->toFloat($item->vat_percent ?? 0);
            $lineTotal = $this->lineTotalWithVat($qty, $unitCostBeforeVat, $vatPercent);

            DB::table('material_request_items')
                ->where('id', $item->id)
                ->update($this->filterColumns('material_request_items', [
                    'line_total' => $lineTotal,
                    'updated_at' => now(),
                ]));

            $total += $lineTotal;
        }

        DB::table('material_requests')
            ->where('id', $materialRequest->id)
            ->update($this->filterColumns('material_requests', [
                'total_cost' => $total,
                'updated_at' => now(),
            ]));

        $this->syncSiteMaterialCost((int) $materialRequest->site_id);

        return $total;
    }

    /**
     * Thêm các dòng vật tư trong kho và ngoài kho vào đơn.
     */
    private function insertRequestItems(int $materialRequestId, int $defaultWarehouseId, array $items, array $extraItems): void
    {
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $qty = $this->toFloat($row['qty'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;

            $product = DB::table('crm_product_catalog')
                ->where('id', $productId)
                ->first();

            $unitCostAfterVat = $this->productUnitCostAfterVat($product);
            $vatPercent = $this->toFloat($product->cost_vat_percent ?? $product->vat_percent ?? 0);
            $unit = $product->unit ?? null;
            $note = trim((string) ($row['note'] ?? ''));

            $lineTotal = round($qty * $unitCostAfterVat, 2);

            DB::table('material_request_items')->insert($this->filterColumns('material_request_items', [
                'material_request_id' => $materialRequestId,
                'product_id' => $productId,
                'qty' => $qty,
                'note' => $note !== '' ? $note : null,
                'unit' => $unit,
                'unit_cost' => $unitCostAfterVat,
                'vat_percent' => $vatPercent,
                'line_total' => $lineTotal,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        foreach ($extraItems as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $qty = $this->toFloat($row['qty'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;

            $unit = trim((string) ($row['unit'] ?? ''));
            $noteRaw = trim((string) ($row['note'] ?? ''));

            $noteParts = [$name];

            if ($unit !== '') {
                $noteParts[] = 'ĐVT: '.$unit;
            }

            if ($noteRaw !== '') {
                $noteParts[] = $noteRaw;
            }

            DB::table('material_request_items')->insert($this->filterColumns('material_request_items', [
                'material_request_id' => $materialRequestId,
                'product_id' => null,
                'qty' => $qty,
                'note' => implode(' | ', $noteParts),
                'unit' => $unit !== '' ? $unit : null,
                'unit_cost' => 0,
                'vat_percent' => 0,
                'line_total' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Cập nhật lại giá vốn dòng vật tư trong kho theo giá sản phẩm hiện tại.
     */
    private function refreshStockItemCost(int $itemId, int $productId, float $qty): void
    {
        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->first();

        $unitCostAfterVat = $this->productUnitCostAfterVat($product);
        $vatPercent = $this->toFloat($product->cost_vat_percent ?? $product->vat_percent ?? 0);
        $unit = $product->unit ?? null;
        $lineTotal = round($qty * $unitCostAfterVat, 2);

        DB::table('material_request_items')
            ->where('id', $itemId)
            ->update($this->filterColumns('material_request_items', [
                'unit' => $unit,
                'unit_cost' => $unitCostAfterVat,
                'vat_percent' => $vatPercent,
                'line_total' => $lineTotal,
                'updated_at' => now(),
            ]));
    }

    /**
     * Tạo hoặc cập nhật sản phẩm catalog cho vật tư ngoài kho.
     */
    private function createOrUpdateExternalProduct(
        int $materialRequestId,
        int $itemId,
        string $name,
        ?string $unit,
        float $unitCostBeforeVat,
        float $vatPercent
    ): int {
        $name = trim($name) !== '' ? trim($name) : ('Vật tư ngoài kho #'.$itemId);
        $unit = trim((string) $unit);
        $unitCostAfterVat = $this->unitCostAfterVat($unitCostBeforeVat, $vatPercent);

        $existing = DB::table('crm_product_catalog')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            DB::table('crm_product_catalog')
                ->where('id', $existing->id)
                ->update($this->filterColumns('crm_product_catalog', [
                    'unit' => $unit !== '' ? $unit : ($existing->unit ?? null),
                    'price' => $unitCostBeforeVat,
                    'price_agent' => $unitCostBeforeVat,
                    'cost_vat_percent' => $vatPercent,
                    'price_agent_vat' => $unitCostAfterVat,
                    'vat_percent' => $vatPercent,
                    'is_active' => 1,
                    'updated_at' => now(),
                ]));

            return (int) $existing->id;
        }

        $sku = $this->makeUniqueSku($name, $materialRequestId, $itemId);

        return (int) DB::table('crm_product_catalog')->insertGetId($this->filterColumns('crm_product_catalog', [
            'name' => $name,
            'description' => 'Tạo tự động từ đơn vật tư #'.$materialRequestId.', dòng #'.$itemId,
            'sku' => $sku,
            'is_serialized' => 0,
            'unit' => $unit !== '' ? $unit : null,
            'category_id' => $this->defaultProductCategoryId(),
            'brand_id' => null,
            'price' => $unitCostBeforeVat,
            'price_agent' => $unitCostBeforeVat,
            'cost_vat_percent' => $vatPercent,
            'price_agent_vat' => $unitCostAfterVat,
            'price_retail' => 0,
            'price_retail_vat' => null,
            'vat_percent' => $vatPercent,
            'note' => 'Tạo tự động khi kho duyệt đơn vật tư #'.$materialRequestId,
            'quantity' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Nhập vật tư ngoài kho vào lô rồi xuất ra ngay để lưu đủ lịch sử nhập/xuất.
     */
    private function importThenExportExternalProduct(
        MaterialRequest $materialRequest,
        int $productId,
        int $warehouseId,
        float $qty,
        int $itemId
    ): void {
        if ($productId <= 0 || $warehouseId <= 0) {
            return;
        }

        $stockQty = (int) round($qty);
        if ($stockQty <= 0) {
            $stockQty = 1;
        }

        /*
         * Vật tư ngoài kho: nhập vào lô rồi xuất ra ngay.
         * Như vậy lịch sử có đủ 2 dòng nhập/xuất, còn tồn cuối không bị ảo.
         */
        if (Schema::hasTable('crm_product_stock_lots')) {
            $product = Product::find($productId);
            if (! $product) {
                return;
            }

            $companyId = $this->warehouseCompanyId($warehouseId);
            $costBeforeVat = (float) ($product->price_agent ?? $product->price ?? 0);
            $vatPercent = (float) ($product->cost_vat_percent ?? $product->vat_percent ?? 0);

            $lotId = app(StockLotService::class)->receiveLot(
                $product,
                $companyId,
                $warehouseId,
                $stockQty,
                $costBeforeVat,
                $vatPercent,
                0,
                [
                    'source_type' => 'material_external_import',
                    'reference_type' => 'material_request',
                    'source_id' => (int) $materialRequest->id,
                    'reason' => 'Nhập vật tư ngoài kho từ đơn vật tư #'.(int) $materialRequest->id,
                    'note' => 'Nhập vật tư ngoài kho trước khi xuất cho công trình',
                ]
            );

            app(StockLotService::class)->issueLots(
                $productId,
                $companyId > 0 ? $companyId : null,
                $warehouseId,
                $stockQty,
                [
                    'reason' => 'Xuất kho cho công trình / đơn vật tư #'.(int) $materialRequest->id,
                    'reference_type' => 'material_request',
                    'reference_id' => (int) $materialRequest->id,
                    'note' => 'Xuất vật tư ngoài kho sau khi nhập tự động. Lot #'.$lotId,
                ]
            );

            $this->refreshProductTotalQty($productId);

            return;
        }

        if (! Schema::hasTable('crm_product_stock')) {
            return;
        }

        $companyId = $this->warehouseCompanyId($warehouseId);

        $stock = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            DB::table('crm_product_stock')->insert($this->filterColumns('crm_product_stock', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'company_id' => $companyId,
                'qty' => 0,
                'serials_json' => null,
                'last_updated' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $stock = DB::table('crm_product_stock')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();
        }

        $currentQty = (int) ($stock->qty ?? 0);

        DB::table('crm_product_stock')
            ->where('id', $stock->id)
            ->update($this->filterColumns('crm_product_stock', [
                'qty' => $currentQty + $stockQty,
                'last_updated' => now(),
                'updated_at' => now(),
            ]));

        $this->insertStockMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            changeQty: $stockQty,
            reason: 'material_external_import',
            referenceId: (int) $materialRequest->id,
            qtyBefore: $currentQty,
            qtyAfter: $currentQty + $stockQty,
            referenceType: 'material_request',
            note: 'Nhập vật tư ngoài kho trước khi xuất cho công trình'
        );

        $afterImport = $currentQty + $stockQty;
        $afterExport = $afterImport - $stockQty;

        DB::table('crm_product_stock')
            ->where('id', $stock->id)
            ->update($this->filterColumns('crm_product_stock', [
                'qty' => $afterExport,
                'last_updated' => now(),
                'updated_at' => now(),
            ]));

        $this->insertStockMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            changeQty: -$stockQty,
            reason: 'material_request_export',
            referenceId: (int) $materialRequest->id,
            qtyBefore: $afterImport,
            qtyAfter: $afterExport,
            referenceType: 'material_request',
            note: 'Xuất vật tư ngoài kho sau khi nhập tự động'
        );

        $this->refreshProductTotalQty($productId);
    }

    /**
     * Xuất kho vật tư theo FIFO lô, fallback trừ thẳng tồn nếu chưa có bảng lô.
     */
    private function exportStockProduct(MaterialRequest $materialRequest, int $productId, float $qty): void
    {
        $warehouseId = (int) ($materialRequest->warehouse_id ?? 0);

        if ($warehouseId <= 0 || $productId <= 0) {
            return;
        }

        $stockQty = (int) round($qty);
        if ($stockQty <= 0) {
            $stockQty = 1;
        }

        /*
         * QUAN TRỌNG:
         * Trước đây đơn vật tư trừ thẳng crm_product_stock nhưng không trừ crm_product_stock_lots.
         * Sửa lại dùng FIFO giống đơn hàng để tồn tổng và lô luôn khớp nhau.
         */
        if (Schema::hasTable('crm_product_stock_lots')) {
            $companyId = $this->warehouseCompanyId($warehouseId);

            app(StockLotService::class)->issueLots(
                $productId,
                $companyId > 0 ? $companyId : null,
                $warehouseId,
                $stockQty,
                [
                    'reason' => 'Xuất kho cho công trình / đơn vật tư #'.(int) $materialRequest->id,
                    'reference_type' => 'material_request',
                    'reference_id' => (int) $materialRequest->id,
                    'note' => 'Xuất kho cho công trình / đơn vật tư',
                ]
            );

            $this->refreshProductTotalQty($productId);

            return;
        }

        if (! Schema::hasTable('crm_product_stock')) {
            return;
        }

        $stock = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $qtyBefore = (int) ($stock->qty ?? 0);

        if ($qtyBefore < $stockQty) {
            throw new RuntimeException('Không đủ tồn kho để xuất vật tư. Tồn hiện tại '.$qtyBefore.', cần '.$stockQty.'.');
        }

        $qtyAfter = $qtyBefore - $stockQty;

        DB::table('crm_product_stock')
            ->where('id', $stock->id)
            ->update($this->filterColumns('crm_product_stock', [
                'qty' => $qtyAfter,
                'last_updated' => now(),
                'updated_at' => now(),
            ]));

        $this->insertStockMovement(
            productId: $productId,
            warehouseId: $warehouseId,
            changeQty: -$stockQty,
            reason: 'material_request_export',
            referenceId: (int) $materialRequest->id,
            qtyBefore: $qtyBefore,
            qtyAfter: $qtyAfter,
            referenceType: 'material_request',
            note: 'Xuất kho cho công trình / đơn vật tư'
        );

        $this->refreshProductTotalQty($productId);
    }

    /**
     * Ghi bản ghi biến động tồn kho nếu bảng movements tồn tại.
     */
    private function insertStockMovement(
        int $productId,
        int $warehouseId,
        int $changeQty,
        string $reason,
        int $referenceId,
        ?int $qtyBefore = null,
        ?int $qtyAfter = null,
        ?string $referenceType = null,
        ?string $note = null
    ): void {
        if (! Schema::hasTable('crm_stock_movements')) {
            return;
        }

        $payload = $this->filterColumns('crm_stock_movements', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'change_qty' => $changeQty,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'type' => $changeQty >= 0 ? 'in' : 'out',
            'movement_type' => $changeQty >= 0 ? 'in' : 'out',
            'reason' => $reason,
            'note' => $note ?: $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => Auth::id(),
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_stock_movements')->insert($payload);
    }

    /**
     * Đồng bộ lại tổng tồn của sản phẩm vào bảng catalog.
     */
    private function refreshProductTotalQty(int $productId): void
    {
        if (! Schema::hasTable('crm_product_stock') || ! Schema::hasTable('crm_product_catalog')) {
            return;
        }

        if (! Schema::hasColumn('crm_product_catalog', 'quantity')) {
            return;
        }

        $totalQty = (int) DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->sum('qty');

        DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->update($this->filterColumns('crm_product_catalog', [
                'quantity' => $totalQty,
                'updated_at' => now(),
            ]));
    }

    /**
     * Đồng bộ tổng chi phí vật tư đã xuất kho sang các cột chi phí của công trình.
     */
    private function syncSiteMaterialCost(int $siteId): void
    {
        if ($siteId <= 0 || ! Schema::hasTable('sites')) {
            return;
        }

        $totalCost = (float) DB::table('material_requests')
            ->where('site_id', $siteId)
            ->where('status', MaterialRequestStatus::EXPORTED->value)
            ->sum('total_cost');

        $updates = [
            'updated_at' => now(),
        ];

        foreach (['material_cost', 'actual_material_cost', 'project_cost', 'total_cost'] as $column) {
            if (Schema::hasColumn('sites', $column)) {
                $updates[$column] = $totalCost;
            }
        }

        if (count($updates) > 1) {
            DB::table('sites')
                ->where('id', $siteId)
                ->update($this->filterColumns('sites', $updates));
        }
    }

    /**
     * Chọn kho cho đơn: ưu tiên kho trong các dòng, fallback kho mặc định.
     */
    private function pickWarehouseId(array $items, array $extraItems, int $fallback = 0): int
    {
        foreach ($items as $row) {
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);

            if ($warehouseId > 0) {
                return $warehouseId;
            }
        }

        foreach ($extraItems as $row) {
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);

            if ($warehouseId > 0) {
                return $warehouseId;
            }
        }

        if ($fallback > 0) {
            return $fallback;
        }

        if (Schema::hasTable('crm_warehouses')) {
            $firstWarehouse = DB::table('crm_warehouses')
                ->orderBy('id')
                ->value('id');

            if ($firstWarehouse) {
                return (int) $firstWarehouse;
            }
        }

        return 1;
    }

    /**
     * Lấy company_id của kho nếu có.
     */
    private function warehouseCompanyId(int $warehouseId): ?int
    {
        if (! Schema::hasTable('crm_warehouses')) {
            return null;
        }

        if (! Schema::hasColumn('crm_warehouses', 'company_id')) {
            return null;
        }

        $companyId = DB::table('crm_warehouses')
            ->where('id', $warehouseId)
            ->value('company_id');

        return $companyId ? (int) $companyId : null;
    }

    /**
     * Tính đơn giá vốn sau VAT của sản phẩm theo thứ tự ưu tiên cột giá.
     */
    private function productUnitCostAfterVat(?object $product): float
    {
        if (! $product) {
            return 0.0;
        }

        $priceAgentVat = $this->toFloat($product->price_agent_vat ?? 0);
        $priceAgent = $this->toFloat($product->price_agent ?? 0);
        $price = $this->toFloat($product->price ?? 0);
        $costVatPercent = $this->toFloat($product->cost_vat_percent ?? $product->vat_percent ?? 0);

        if ($priceAgentVat > 0) {
            return $priceAgentVat;
        }

        if ($priceAgent > 0) {
            return $this->unitCostAfterVat($priceAgent, $costVatPercent);
        }

        if ($price > 0) {
            return $this->unitCostAfterVat($price, $costVatPercent);
        }

        return 0.0;
    }

    /**
     * Quy đổi đơn giá trước VAT sang sau VAT.
     */
    private function unitCostAfterVat(float $unitCostBeforeVat, float $vatPercent): float
    {
        return round($unitCostBeforeVat * (1 + ($vatPercent / 100)), 2);
    }

    /**
     * Tính thành tiền dòng gồm VAT theo số lượng và đơn giá trước VAT.
     */
    private function lineTotalWithVat(float $qty, float $unitCostBeforeVat, float $vatPercent): float
    {
        return round($qty * $this->unitCostAfterVat($unitCostBeforeVat, $vatPercent), 2);
    }

    /**
     * Tách tên vật tư ngoài kho từ chuỗi ghi chú.
     */
    private function externalName(string $note): string
    {
        $clean = $this->stripKnownPrefix($note);

        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), function ($value) {
            return $value !== '';
        }));

        return $parts[0] ?? 'Vật tư ngoài kho';
    }

    /**
     * Tách đơn vị tính (ĐVT) của vật tư ngoài kho từ ghi chú.
     */
    private function externalUnitFromNote(string $note): string
    {
        $clean = $this->stripKnownPrefix($note);

        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), function ($value) {
            return $value !== '';
        }));

        foreach ($parts as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    /**
     * Bỏ tiền tố phân loại đã biết ở đầu ghi chú.
     */
    private function stripKnownPrefix(string $note): string
    {
        $note = preg_replace('/^\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u', '', $note);

        return trim((string) $note);
    }

    /**
     * Sinh SKU duy nhất cho sản phẩm vật tư ngoài kho.
     */
    private function makeUniqueSku(string $name, int $materialRequestId, int $itemId): string
    {
        $base = strtoupper(Str::slug(Str::ascii($name), '-'));

        if ($base === '') {
            $base = 'VT-NGOAI-KHO';
        }

        $base = substr($base, 0, 55);
        $sku = $base.'-MR'.$materialRequestId.'-'.$itemId;

        $original = $sku;
        $i = 1;

        while (DB::table('crm_product_catalog')->where('sku', $sku)->exists()) {
            $sku = $original.'-'.$i;
            $i++;
        }

        return $sku;
    }

    /**
     * Chọn danh mục sản phẩm mặc định cho vật tư ngoài kho.
     */
    private function defaultProductCategoryId(): ?int
    {
        if (! Schema::hasTable('crm_product_categories')) {
            return null;
        }

        $categoryId = DB::table('crm_product_categories')
            ->where('name', 'like', '%Vật tư%')
            ->orWhere('name', 'like', '%Phụ kiện%')
            ->orWhere('name', 'like', '%Inverter%')
            ->orderBy('id')
            ->value('id');

        if ($categoryId) {
            return (int) $categoryId;
        }

        $firstCategoryId = DB::table('crm_product_categories')
            ->orderBy('id')
            ->value('id');

        return $firstCategoryId ? (int) $firstCategoryId : null;
    }

    /**
     * Lọc mảng dữ liệu, chỉ giữ các key trùng với cột thực tế của bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(function ($value, string $key) use ($columns) {
                return in_array($key, $columns, true);
            })
            ->all();
    }

    /**
     * Chuyển giá trị bất kỳ (kể cả chuỗi có định dạng) về float an toàn.
     */
    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = preg_replace('/[^\d.\-]/', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
