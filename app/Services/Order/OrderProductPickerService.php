<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Services\PricingServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Truy vấn dữ liệu cho form đơn hàng (product picker): catalog sản phẩm,
 * kho theo sản phẩm/company, sản phẩm theo kho (kể cả tồn 0) và giá bán.
 *
 * Mọi method trả dữ liệu thuần (array/Collection) — controller chịu trách
 * nhiệm authorize, đọc request và bọc JSON response.
 *
 * Tách nguyên trạng từ OrderController (P1a refactor) — hành vi chốt bằng
 * OrderProductPickerCharacterizationTest.
 */
class OrderProductPickerService
{
    public function __construct(
        private readonly OrderStockGuard $stockGuard,
        private readonly PricingServiceInterface $pricingService,
    ) {}

    /**
     * Danh sách kho thuộc company (join pivot company_warehouse), sắp theo tên.
     *
     * @return Collection<int, object{id: int, name: string}>
     */
    public function warehousesByCompany(int $companyId): Collection
    {

        if (! $companyId) {
            return collect();
        }

        $warehouses = DB::table('crm_warehouses as w')
            ->join('company_warehouse as cw', 'cw.warehouse_id', '=', 'w.id')
            ->where('cw.company_id', $companyId)
            ->orderBy('w.name')
            ->select('w.id', 'w.name')
            ->get();

        return $warehouses;
    }

    /**
     * Catalog sản phẩm cho ô chọn sản phẩm: kèm giá bán lẻ/đại lý sau VAT,
     * giá theo từng bảng giá (tier), tồn kho (sản phẩm serial hoá đếm theo
     * serial in_stock, còn lại cộng từ crm_product_stock).
     *
     * @param  string  $term  Từ khóa tìm theo tên/SKU ('' = tất cả)
     * @param  int  $productId  Lọc đúng 1 sản phẩm (>0 thì bỏ qua $term)
     * @param  int  $limit  Số dòng tối đa
     * @return Collection<int, array<string, mixed>>
     */
    public function productCatalog(string $term, int $productId, int $limit): Collection
    {

        if (! Schema::hasTable('crm_product_catalog')) {
            return collect();
        }

        $columns = Schema::getColumnListing('crm_product_catalog');
        $has = static fn (string $column): bool => in_array($column, $columns, true);

        $selects = ['p.id'];
        foreach ([
            'name',
            'sku',
            'brand_id',
            'is_serialized',
            'is_active',
            'deleted_at',
            'price',
            'price_agent',
            'price_agent_vat',
            'price_retail',
            'price_retail_vat',
            'vat_percent',
        ] as $column) {
            $selects[] = $has($column)
                ? 'p.'.$column
                : DB::raw('NULL as '.$column);
        }

        $brandJoin = Schema::hasTable('crm_brands')
            && $has('brand_id')
            && Schema::hasColumn('crm_brands', 'id')
            && Schema::hasColumn('crm_brands', 'name');

        $selects[] = $brandJoin
            ? 'b.name as brand_name'
            : DB::raw('NULL as brand_name');

        $query = DB::table('crm_product_catalog as p')->select($selects);

        if ($brandJoin) {
            $query->leftJoin('crm_brands as b', 'b.id', '=', 'p.brand_id');
        }

        if ($has('deleted_at')) {
            $query->whereNull('p.deleted_at');
        }

        if ($has('is_active')) {
            $query->where(function ($builder) {
                $builder->where('p.is_active', 1)->orWhereNull('p.is_active');
            });
        }

        if ($productId > 0) {
            $query->where('p.id', $productId);
        } elseif ($term !== '') {
            $query->where(function ($builder) use ($term, $has) {
                $first = true;

                foreach (['name', 'sku'] as $column) {
                    if (! $has($column)) {
                        continue;
                    }

                    if ($first) {
                        $builder->where('p.'.$column, 'like', '%'.$term.'%');
                        $first = false;
                    } else {
                        $builder->orWhere('p.'.$column, 'like', '%'.$term.'%');
                    }
                }
            });
        }

        $products = $query
            ->orderBy($has('name') ? 'p.name' : 'p.id')
            ->limit($limit)
            ->get();

        $productIds = $products->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $tierPrices = [];
        $tierVats = [];

        if ($productIds !== [] && Schema::hasTable('crm_product_prices')) {
            $priceColumns = Schema::getColumnListing('crm_product_prices');

            if (
                in_array('product_id', $priceColumns, true)
                && in_array('price_tier_id', $priceColumns, true)
                && in_array('price', $priceColumns, true)
            ) {
                $tierRows = DB::table('crm_product_prices')
                    ->whereIn('product_id', $productIds)
                    ->select([
                        'product_id',
                        'price_tier_id',
                        'price',
                        in_array('vat_percent', $priceColumns, true)
                            ? 'vat_percent'
                            : DB::raw('NULL as vat_percent'),
                    ])
                    ->get();

                foreach ($tierRows as $tierRow) {
                    $pid = (int) ($tierRow->product_id ?? 0);
                    $tid = (int) ($tierRow->price_tier_id ?? 0);
                    $beforeVat = (float) ($tierRow->price ?? 0);

                    if ($pid <= 0 || $tid <= 0 || $beforeVat <= 0) {
                        continue;
                    }

                    $vat = max(0, (float) ($tierRow->vat_percent ?? 0));
                    $tierPrices[$pid][$tid] = (int) round($beforeVat * (1 + $vat / 100));
                    $tierVats[$pid][$tid] = $vat;
                }
            }
        }

        $stockTotals = [];
        if ($productIds !== [] && Schema::hasTable('crm_product_stock')) {
            $stockTotals = DB::table('crm_product_stock')
                ->whereIn('product_id', $productIds)
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) as available_qty')
                ->groupBy('product_id')
                ->pluck('available_qty', 'product_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->all();
        }

        $serialAvailable = [];
        $serialReserved = [];

        if (
            $productIds !== []
            && Schema::hasTable('crm_serial_unit_states')
            && Schema::hasTable('crm_serial_units')
            && Schema::hasColumn('crm_serial_unit_states', 'state')
        ) {
            $serialRows = DB::table('crm_serial_unit_states as state')
                ->join('crm_serial_units as unit', 'unit.id', '=', 'state.serial_unit_id')
                ->whereIn('unit.product_id', $productIds)
                ->whereIn('state.state', ['in_stock', 'reserved'])
                ->selectRaw('unit.product_id, state.state, COUNT(*) as total')
                ->groupBy('unit.product_id', 'state.state')
                ->get();

            foreach ($serialRows as $serialRow) {
                $pid = (int) $serialRow->product_id;
                $count = (int) $serialRow->total;

                if ((string) $serialRow->state === 'in_stock') {
                    $serialAvailable[$pid] = $count;
                } elseif ((string) $serialRow->state === 'reserved') {
                    $serialReserved[$pid] = $count;
                }
            }
        }

        $payload = $products->map(function ($product) use (
            $tierPrices,
            $tierVats,
            $stockTotals,
            $serialAvailable,
            $serialReserved
        ) {
            $id = (int) $product->id;
            $vat = max(0, (float) ($product->vat_percent ?? 0));
            $isSerialized = (bool) ($product->is_serialized ?? false);

            $retailBefore = (float) ($product->price_retail ?? $product->price ?? 0);
            $agentBefore = (float) ($product->price_agent ?? 0);

            $retailAfter = (float) ($product->price_retail_vat ?? 0);
            if ($retailAfter <= 0 && $retailBefore > 0) {
                $retailAfter = round($retailBefore * (1 + $vat / 100));
            }

            $agentAfter = (float) ($product->price_agent_vat ?? 0);
            if ($agentAfter <= 0 && $agentBefore > 0) {
                $agentAfter = round($agentBefore * (1 + $vat / 100));
            }

            $stockTotal = $isSerialized
                ? (int) ($serialAvailable[$id] ?? 0)
                : (int) ($stockTotals[$id] ?? 0);

            $name = trim((string) ($product->name ?? ('Sản phẩm #'.$id)));
            $sku = trim((string) ($product->sku ?? ''));
            $brand = trim((string) ($product->brand_name ?? ''));

            return [
                'id' => $id,
                'product_id' => $id,
                'name' => $name,
                'sku' => $sku,
                'brand' => $brand,
                'brand_name' => $brand,
                'text' => $sku !== '' ? ($name.' · '.$sku) : $name,
                'is_serialized' => $isSerialized,
                'stock_total' => $stockTotal,
                'reserved_total' => $isSerialized ? (int) ($serialReserved[$id] ?? 0) : 0,
                'price' => (int) round($retailAfter > 0 ? $retailAfter : $retailBefore),
                'price_retail' => (int) round($retailAfter > 0 ? $retailAfter : $retailBefore),
                'price_agent' => (int) round($agentAfter > 0 ? $agentAfter : ($retailAfter > 0 ? $retailAfter : $retailBefore)),
                'vat_percent' => $vat,
                'tier_prices' => (object) ($tierPrices[$id] ?? []),
                'tier_vats' => (object) ($tierVats[$id] ?? []),
            ];
        })->values();

        return $payload;
    }

    /**
     * Danh sách kho khả dụng cho 1 sản phẩm kèm tồn/serial từng kho,
     * sắp kho còn hàng lên trước.
     *
     * @return array<string, mixed>|null null nếu sản phẩm không tồn tại (controller trả 404)
     */
    public function warehousesForProduct(int $productId, int $companyId): ?array
    {

        if (
            ! Schema::hasTable('crm_product_catalog')
            || ! Schema::hasTable('crm_warehouses')
        ) {
            return ['warehouses' => []];
        }

        $productColumns = Schema::getColumnListing('crm_product_catalog');
        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->select([
                'id',
                in_array('is_serialized', $productColumns, true)
                    ? 'is_serialized'
                    : DB::raw('0 as is_serialized'),
            ])
            ->first();

        if (! $product) {
            return null;
        }
        $warehouseColumns = Schema::getColumnListing('crm_warehouses');
        $hasWarehouseColumn = static fn (string $column): bool => in_array($column, $warehouseColumns, true);

        $warehouseQuery = DB::table('crm_warehouses as warehouse')
            ->select([
                'warehouse.id',
                'warehouse.name',
                $hasWarehouseColumn('location')
                    ? 'warehouse.location'
                    : DB::raw('NULL as location'),
                $hasWarehouseColumn('company_id')
                    ? 'warehouse.company_id'
                    : DB::raw('NULL as company_id'),
            ]);

        if ($hasWarehouseColumn('is_active')) {
            $warehouseQuery->where(function ($builder) {
                $builder->where('warehouse.is_active', 1)->orWhereNull('warehouse.is_active');
            });
        }

        if ($companyId > 0) {
            $warehouseQuery->where(function ($builder) use ($companyId, $hasWarehouseColumn) {
                $hasDirectCompany = $hasWarehouseColumn('company_id');

                if ($hasDirectCompany) {
                    $builder->where('warehouse.company_id', $companyId);
                }

                if (Schema::hasTable('company_warehouse')) {
                    $method = $hasDirectCompany ? 'orWhereExists' : 'whereExists';
                    $builder->{$method}(function ($subQuery) use ($companyId) {
                        $subQuery->selectRaw('1')
                            ->from('company_warehouse as pivot')
                            ->whereColumn('pivot.warehouse_id', 'warehouse.id')
                            ->where('pivot.company_id', $companyId);
                    });
                }
            });
        }

        $warehouses = $warehouseQuery->orderBy('warehouse.name')->get();
        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();

        $pivotCompanies = [];
        if ($warehouseIds !== [] && Schema::hasTable('company_warehouse')) {
            $pivotCompanies = DB::table('company_warehouse')
                ->whereIn('warehouse_id', $warehouseIds)
                ->orderBy('company_id')
                ->pluck('company_id', 'warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $resolvedCompanyIds = $warehouses->map(function ($warehouse) use ($pivotCompanies) {
            return (int) ($warehouse->company_id ?? $pivotCompanies[(int) $warehouse->id] ?? 0);
        })->filter()->unique()->values()->all();

        $companyNames = Schema::hasTable('companies') && $resolvedCompanyIds !== []
            ? DB::table('companies')->whereIn('id', $resolvedCompanyIds)->pluck('name', 'id')->all()
            : [];

        $stockByWarehouse = [];
        if ($warehouseIds !== [] && Schema::hasTable('crm_product_stock')) {
            $stockByWarehouse = DB::table('crm_product_stock')
                ->where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->selectRaw('warehouse_id, COALESCE(SUM(qty), 0) as total')
                ->groupBy('warehouse_id')
                ->pluck('total', 'warehouse_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->all();
        } elseif ($warehouseIds !== [] && Schema::hasTable('crm_product_stock_lots')) {
            $stockByWarehouse = DB::table('crm_product_stock_lots')
                ->where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->selectRaw('warehouse_id, COALESCE(SUM(qty_remaining), 0) as total')
                ->groupBy('warehouse_id')
                ->pluck('total', 'warehouse_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->all();
        }

        $serialAvailableByWarehouse = [];
        $serialReservedByWarehouse = [];

        if (
            $warehouseIds !== []
            && Schema::hasTable('crm_serial_unit_states')
            && Schema::hasTable('crm_serial_units')
            && Schema::hasColumn('crm_serial_unit_states', 'state')
        ) {
            $serialRows = DB::table('crm_serial_unit_states as state')
                ->join('crm_serial_units as unit', 'unit.id', '=', 'state.serial_unit_id')
                ->where('unit.product_id', $productId)
                ->whereIn('state.warehouse_id', $warehouseIds)
                ->whereIn('state.state', ['in_stock', 'reserved'])
                ->selectRaw('state.warehouse_id, state.state, COUNT(*) as total')
                ->groupBy('state.warehouse_id', 'state.state')
                ->get();

            foreach ($serialRows as $serialRow) {
                $warehouseId = (int) $serialRow->warehouse_id;
                $count = (int) $serialRow->total;

                if ((string) $serialRow->state === 'in_stock') {
                    $serialAvailableByWarehouse[$warehouseId] = $count;
                } elseif ((string) $serialRow->state === 'reserved') {
                    $serialReservedByWarehouse[$warehouseId] = $count;
                }
            }
        }

        $isSerialized = (bool) ($product->is_serialized ?? false);

        $payload = $warehouses->map(function ($warehouse) use (
            $pivotCompanies,
            $companyNames,
            $stockByWarehouse,
            $serialAvailableByWarehouse,
            $serialReservedByWarehouse,
            $isSerialized
        ) {
            $warehouseId = (int) $warehouse->id;
            $resolvedCompanyId = (int) ($warehouse->company_id ?? $pivotCompanies[$warehouseId] ?? 0);
            $stockQty = (int) ($stockByWarehouse[$warehouseId] ?? 0);
            $serialAvailable = (int) ($serialAvailableByWarehouse[$warehouseId] ?? 0);
            $serialReserved = (int) ($serialReservedByWarehouse[$warehouseId] ?? 0);
            $available = $isSerialized ? $serialAvailable : $stockQty;

            return [
                'id' => $warehouseId,
                'name' => (string) ($warehouse->name ?? ('Kho #'.$warehouseId)),
                'location' => (string) ($warehouse->location ?? ''),
                'company_id' => $resolvedCompanyId,
                'company_name' => (string) ($companyNames[$resolvedCompanyId] ?? ''),
                'is_serialized' => $isSerialized,
                'stock_qty' => $isSerialized ? $serialAvailable + $serialReserved : $stockQty,
                'reserved_qty' => $isSerialized ? $serialReserved : 0,
                'available_qty' => max(0, $available),
                'has_stock' => $available > 0,
            ];
        })->sortBy([
            ['has_stock', 'desc'],
            ['available_qty', 'desc'],
            ['name', 'asc'],
        ])->values();

        return [
            'product_id' => $productId,
            'is_serialized' => $isSerialized,
            'warehouses' => $payload,
        ];
    }

    /**
     * Giá bán của sản phẩm: ưu tiên bảng giá tier, fallback giá đại lý
     * (customer_type_id = 1) hoặc bán lẻ trên catalog.
     *
     * @return array{price: int, vat_percent: float|int, price_before_vat: int}
     */
    public function productPrice(int $productId, ?int $priceTierId, ?int $customerTypeId): array
    {

        if (! Schema::hasTable('crm_product_catalog')) {
            return [
                'price' => 0,
                'vat_percent' => 0,
                'price_before_vat' => 0,
            ];
        }

        $columns = Schema::getColumnListing('crm_product_catalog');
        $has = fn (string $col): bool => in_array($col, $columns, true);

        $selects = ['id'];

        foreach ([
            'price',
            'price_agent',
            'price_agent_vat',
            'price_retail',
            'price_retail_vat',
            'vat_percent',
        ] as $col) {
            $selects[] = $has($col) ? $col : DB::raw('NULL as '.$col);
        }

        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->select($selects)
            ->first();

        if (! $product) {
            return [
                'price' => 0,
                'vat_percent' => 0,
                'price_before_vat' => 0,
            ];
        }

        $vat = $this->pricingService->resolveVatPercentForOrderItem((int) $product->id, $priceTierId);

        $beforeVat = 0.0;
        $afterVat = 0.0;

        if ($priceTierId && Schema::hasTable('crm_product_prices')) {
            $priceCols = Schema::getColumnListing('crm_product_prices');

            if (
                in_array('product_id', $priceCols, true)
                && in_array('price_tier_id', $priceCols, true)
                && in_array('price', $priceCols, true)
            ) {
                $tierRow = DB::table('crm_product_prices')
                    ->where('product_id', (int) $product->id)
                    ->where('price_tier_id', $priceTierId)
                    ->first();

                if ($tierRow) {
                    $beforeVat = (float) ($tierRow->price ?? 0);

                    foreach (['vat_percent', 'vat', 'tax_percent'] as $vatCol) {
                        if (in_array($vatCol, $priceCols, true) && (float) ($tierRow->{$vatCol} ?? 0) > 0) {
                            $vat = (float) $tierRow->{$vatCol};
                            break;
                        }
                    }

                    if ($beforeVat > 0) {
                        $afterVat = round($beforeVat * (1 + max(0, $vat) / 100), 0);
                    }
                }
            }
        }

        if ($afterVat <= 0) {
            if ((int) $customerTypeId === 1) {
                $beforeVat = (float) ($product->price_agent ?? 0);
                $afterVat = (float) ($product->price_agent_vat ?? 0);
            } else {
                $beforeVat = (float) ($product->price_retail ?? ($product->price ?? 0));
                $afterVat = (float) ($product->price_retail_vat ?? 0);
            }

            if ($afterVat <= 0 && $beforeVat > 0) {
                $afterVat = round($beforeVat * (1 + max(0, $vat) / 100), 0);
            }
        }

        return [
            'price' => (int) round($afterVat),
            'vat_percent' => (float) $vat,
            'price_before_vat' => (int) round($beforeVat),
        ];
    }

    /**
     * Sản phẩm theo kho cho dropdown tạo đơn — hiển thị cả sản phẩm tồn 0
     * (sales vẫn tạo đơn được, bước kho mới chặn thiếu tồn).
     *
     * @return list<array<string, mixed>>
     */
    public function productsForWarehouseSelect(int $warehouseId, string $term, int $limit): array
    {

        if (! Schema::hasTable('crm_product_catalog')) {
            return [];
        }

        $productColumns = Schema::getColumnListing('crm_product_catalog');
        $hasProductColumn = function (string $column) use ($productColumns): bool {
            return in_array($column, $productColumns, true);
        };

        $selects = ['p.id'];
        foreach (['name', 'sku', 'price', 'price_agent', 'price_agent_vat', 'price_retail', 'price_retail_vat', 'vat_percent', 'quantity'] as $column) {
            $selects[] = $hasProductColumn($column)
                ? "p.{$column}"
                : DB::raw('NULL as '.$column);
        }

        $hasBrandJoin = Schema::hasTable('crm_brands')
            && $hasProductColumn('brand_id')
            && Schema::hasColumn('crm_brands', 'id')
            && Schema::hasColumn('crm_brands', 'name');

        if ($hasBrandJoin) {
            $selects[] = 'b.name as brand_name';
        } else {
            $selects[] = DB::raw('NULL as brand_name');
        }

        $productsQuery = DB::table('crm_product_catalog as p')
            ->select($selects);

        if ($hasBrandJoin) {
            $productsQuery->leftJoin('crm_brands as b', 'b.id', '=', 'p.brand_id');
        }

        if ($hasProductColumn('deleted_at')) {
            $productsQuery->whereNull('p.deleted_at');
        }

        if ($hasProductColumn('is_active')) {
            $productsQuery->where(function ($q) {
                $q->where('p.is_active', 1)->orWhereNull('p.is_active');
            });
        }

        if ($term !== '') {
            $productsQuery->where(function ($q) use ($term, $hasProductColumn) {
                if ($hasProductColumn('name')) {
                    $q->where('p.name', 'like', '%'.$term.'%');
                }

                if ($hasProductColumn('sku')) {
                    $q->orWhere('p.sku', 'like', '%'.$term.'%');
                }
            });
        }

        $products = $productsQuery
            ->orderBy($hasProductColumn('sku') ? 'p.sku' : 'p.id')
            ->orderBy($hasProductColumn('name') ? 'p.name' : 'p.id')
            ->limit($limit)
            ->get();

        // Load đủ giá theo từng loại giá để màn tạo đơn hiển thị giống màn sửa sản phẩm.
        // Giá trong crm_product_prices đang lưu trước VAT, nên trả về JS giá sau VAT.
        $productIds = $products->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $tierPricesMap = [];
        $tierVatMap = [];
        if (! empty($productIds) && Schema::hasTable('crm_product_prices')) {
            $priceTableColumns = Schema::getColumnListing('crm_product_prices');

            if (in_array('product_id', $priceTableColumns, true)
                && in_array('price_tier_id', $priceTableColumns, true)
                && in_array('price', $priceTableColumns, true)) {
                $tierRows = DB::table('crm_product_prices')
                    ->whereIn('product_id', $productIds)
                    ->select('product_id', 'price_tier_id', 'price', 'vat_percent')
                    ->get();

                foreach ($tierRows as $tierRow) {
                    $productId = (int) ($tierRow->product_id ?? 0);
                    $tierId = (int) ($tierRow->price_tier_id ?? 0);
                    $priceBeforeVat = (float) ($tierRow->price ?? 0);

                    if ($productId > 0 && $tierId > 0 && $priceBeforeVat > 0) {
                        $tierPricesMap[$productId][$tierId] = $priceBeforeVat;
                        $tierVatMap[$productId][$tierId] = (float) ($tierRow->vat_percent ?? 0);
                    }
                }
            }
        }

        $stockByProduct = $this->stockGuard->stockByProductForWarehouse($warehouseId);

        return $products->map(function ($product) use ($stockByProduct, $tierPricesMap, $tierVatMap) {
            $productId = (int) ($product->id ?? 0);
            $stock = max(0, (int) ($stockByProduct[$productId] ?? 0));
            $vatPercent = max(0, (float) ($product->vat_percent ?? 0));

            $agentBase = (float) ($product->price_agent ?? 0);
            $retailBase = (float) ($product->price_retail ?? ($product->price ?? 0));

            $agentAfterVat = (float) ($product->price_agent_vat ?? 0);
            if ($agentAfterVat <= 0 && $agentBase > 0) {
                $agentAfterVat = round($agentBase * (1 + $vatPercent / 100), 0);
            }

            $retailAfterVat = (float) ($product->price_retail_vat ?? 0);
            if ($retailAfterVat <= 0 && $retailBase > 0) {
                $retailAfterVat = round($retailBase * (1 + $vatPercent / 100), 0);
            }

            $defaultPrice = $retailAfterVat > 0 ? $retailAfterVat : (float) ($product->price ?? 0);

            $tierPrices = new \stdClass;
            $tierVats = new \stdClass;

            foreach (($tierPricesMap[$productId] ?? []) as $tierId => $priceBeforeVat) {
                if ((float) $priceBeforeVat > 0) {
                    $tierVat = (float) ($tierVatMap[$productId][$tierId] ?? $vatPercent);
                    $tierPriceAfterVat = round((float) $priceBeforeVat * (1 + max(0, $tierVat) / 100), 0);
                    $tierPrices->{$tierId} = (int) round($tierPriceAfterVat, 0);
                    $tierVats->{$tierId} = $tierVat;
                }
            }

            $name = trim((string) ($product->name ?? ('Sản phẩm #'.$productId)));
            $sku = trim((string) ($product->sku ?? ''));
            $brand = trim((string) ($product->brand_name ?? ''));

            $parts = [$name];
            if ($sku !== '') {
                $parts[] = 'SKU: '.$sku;
            }
            if ($brand !== '') {
                $parts[] = 'Thương hiệu: '.$brand;
            }
            $parts[] = 'Tồn: '.$stock;

            $text = implode(' - ', $parts);

            return [
                'id' => $productId,
                'value' => $productId,
                'product_id' => $productId,
                'name' => $name,
                'sku' => $sku,
                'brand' => $brand,
                'brand_name' => $brand,
                'text' => $text,
                'label' => $text,
                'stock' => $stock,
                'qty' => $stock,
                'quantity' => $stock,
                'inventory' => $stock,
                'available_stock' => $stock,
                'is_out_of_stock' => $stock <= 0,
                'disabled' => false,
                'price' => (int) round($defaultPrice),
                'price_agent' => (int) round($agentAfterVat > 0 ? $agentAfterVat : $defaultPrice),
                'price_retail' => (int) round($retailAfterVat > 0 ? $retailAfterVat : $defaultPrice),
                'price_before_vat' => (int) round($retailBase > 0 ? $retailBase : (float) ($product->price ?? 0)),
                'vat_percent' => $vatPercent,
                'tier_prices' => $tierPrices,
                'tier_vats' => $tierVats,
            ];
        })->values()->all();
    }
}
