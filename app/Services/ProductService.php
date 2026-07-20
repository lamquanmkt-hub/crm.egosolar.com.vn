<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ProductServiceInterface;
use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Pricing\PriceTier;
use App\Models\Inventory\Pricing\ProductPrice;
use App\Models\Inventory\Serial\SerialEventLine;
use App\Models\Inventory\Serial\SerialIdentifier;
use App\Models\Inventory\Serial\SerialUnit;
use App\Models\Inventory\Serial\SerialUnitState;
use App\Models\Inventory\Stock\InventoryEvent;
use App\Models\Inventory\Stock\ProductStock;
use App\Models\Media\MediaFile;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service xử lý nghiệp vụ sản phẩm (Product): danh mục, giá, media, tồn kho, serial.
 */
class ProductService implements ProductServiceInterface
{
    /**
     * Lấy danh sách sản phẩm còn tồn tại kho cho dropdown (kèm giá sau VAT và giá theo tier).
     */
    public function getProductsForSelect(int $warehouseId): array
    {
        $brandTable = Schema::hasTable('crm_brands') ? 'crm_brands' : null;

        $query = DB::table('crm_product_catalog as p')
            ->join('crm_product_stock as s', 's.product_id', '=', 'p.id');

        if ($brandTable && Schema::hasColumn('crm_product_catalog', 'brand_id')) {
            $query->leftJoin($brandTable.' as b', 'b.id', '=', 'p.brand_id');
        }

        $select = [
            'p.id',
            'p.name',
            'p.sku',
            'p.price_agent',
            'p.price_retail',
            'p.vat_percent',
            DB::raw('SUM(s.qty) as stock_qty'),
        ];

        if ($brandTable && Schema::hasColumn('crm_product_catalog', 'brand_id')) {
            $select[] = DB::raw('COALESCE(b.name, "") as brand_name');
        } else {
            $select[] = DB::raw('"" as brand_name');
        }

        $rows = $query
            ->where('s.warehouse_id', $warehouseId)
            ->where('s.qty', '>', 0)
            ->select($select)
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.price_agent', 'p.price_retail', 'p.vat_percent');

        if ($brandTable && Schema::hasColumn('crm_product_catalog', 'brand_id')) {
            $rows->groupBy('b.name');
        }

        $rows = $rows
            ->orderBy('p.name')
            ->get();

        // Load tier prices in batch for all products
        $productIds = $rows->pluck('id')->toArray();
        $tierPricesMap = [];
        if (! empty($productIds) && Schema::hasTable('crm_product_prices')) {
            $tierRows = DB::table('crm_product_prices')
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'price_tier_id', 'price')
                ->get();

            foreach ($tierRows as $tp) {
                // If multiple rows per product+tier, last one wins (delete+insert pattern)
                $tierPricesMap[(int) $tp->product_id][(int) $tp->price_tier_id] = (float) $tp->price;
            }
        }

        return $rows->map(function ($r) use ($tierPricesMap) {
            $name = trim((string) ($r->name ?? ''));
            $sku = trim((string) ($r->sku ?? ''));
            $brand = trim((string) ($r->brand_name ?? ''));
            $qty = (int) ($r->stock_qty ?? 0);
            $vat = max(0, (float) ($r->vat_percent ?? 0));

            $parts = [];

            if ($name !== '') {
                $parts[] = $name;
            }

            if ($sku !== '') {
                $parts[] = 'SKU: '.$sku;
            }

            if ($brand !== '') {
                $parts[] = 'Thương hiệu: '.$brand;
            }

            $parts[] = 'Tồn: '.$qty;

            $label = implode(' - ', $parts);

            $priceAgent = (float) ($r->price_agent ?? 0);
            $priceRetail = (float) ($r->price_retail ?? 0);

            // Build tier_prices: {tier_id: price_after_vat}
            $tierPrices = new \stdClass;
            foreach ($tierPricesMap[(int) $r->id] ?? [] as $tierId => $beforeVat) {
                if ($beforeVat > 0) {
                    $tierPrices->{$tierId} = (int) round($beforeVat * (1 + $vat / 100), 0);
                }
            }

            return [
                'id' => (int) $r->id,
                'display' => $label,
                'name' => $name,
                'sku' => $sku,
                'brand_name' => $brand,
                'stock_qty' => $qty,
                'price_agent' => (int) round($priceAgent * (1 + $vat / 100), 0),
                'price_retail' => (int) round($priceRetail * (1 + $vat / 100), 0),
                'vat_percent' => $vat,
                'tier_prices' => $tierPrices,
            ];
        })->values()->toArray();
    }

    /**
     * Khởi tạo service với các repository/service phụ thuộc.
     */
    public function __construct(
        protected ProductRepositoryInterface $repo,
        protected ProductCategoryRepositoryInterface $categories,
        protected StockService $stockService,
        protected \App\Contracts\Services\WarehouseServiceInterface $warehouseService,
        protected \App\Contracts\Services\BrandServiceInterface $brandService,
    ) {}

    /**
     * ✅ FIX: lọc theo công ty phải dựa trên stock.company_id
     */
    public function list(?int $warehouseId = null, ?int $categoryId = null, ?string $search = null, ?int $companyId = null): LengthAwarePaginator
    {
        $query = Product::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        // ✅ company filter: product có tồn kho ở company đó
        if (! empty($companyId)) {
            $query->whereHas('stocks', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $query->with(['category', 'prices', 'mainImage']);

        // ✅ total qty (tất cả company)
        $query->withSum('stocks as stocks_sum_qty', 'qty');

        // ✅ qty theo kho (nếu có)
        if (! empty($warehouseId)) {
            $query->withSum(['stocks as warehouse_qty' => function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }], 'qty');
        }

        // ✅ qty theo company (để list hiển thị đúng theo công ty)
        if (! empty($companyId)) {
            $query->withSum(['stocks as company_qty' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }], 'qty');
        }

        return $query->orderByDesc('id')->paginate(20);
    }

    /**
     * Lấy dữ liệu dropdown cho form sản phẩm (danh mục, kho, thương hiệu, loại giá).
     */
    public function formSelections(): array
    {
        return [
            'categories' => $this->categories->allOrdered(),
            'warehouses' => $this->warehouseService->optionsWithCompanies(), // ✅ warehouses có companies()
            'brands' => $this->brandService->options(),
            'priceTiers' => PriceTier::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Tạo sản phẩm mới kèm đồng bộ giá, media, tồn kho trong transaction.
     */
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $product = $this->repo->create($this->productPayload($data));

            $product->is_serialized = ! empty($data['is_serialized']);
            $product->save();

            $this->syncPrices($product, $data);
            $this->syncMedia($product, $data);

            $this->applyStocks($product, $data);

            $this->syncProductTotalQty($product);

            return $product->fresh(['stocks', 'mainImage.media.metadata']);
        });
    }

    /**
     * Cập nhật sản phẩm kèm đồng bộ giá, media, tồn kho trong transaction.
     */
    public function update(Product $product, array $data)
    {
        return DB::transaction(function () use ($product, $data) {
            $this->repo->update($product, $this->productPayload($data));

            $product->is_serialized = ! empty($data['is_serialized']);
            $product->save();

            $this->syncPrices($product, $data);
            $this->syncMedia($product, $data);

            $this->applyStocks($product, $data);

            $this->syncProductTotalQty($product);

            return $product->fresh(['stocks', 'mainImage.media.metadata']);
        });
    }

    /**
     * Xoá sản phẩm.
     */
    public function delete(Product $product)
    {
        return $this->repo->delete($product);
    }

    /**
     * Chuẩn bị dữ liệu cho form tạo/sửa sản phẩm (tồn theo kho, gallery, giá tier, serial).
     */
    public function prepareFormData(?Product $product): array
    {
        $warehouseQty = []; // [companyId][warehouseId] => qty
        $totalQty = 0;

        if ($product) {
            $product->loadMissing('stocks');
            foreach ($product->stocks as $stock) {
                $cid = (int) ($stock->company_id ?? 0);
                $wid = (int) $stock->warehouse_id;
                $qty = (int) $stock->qty;

                $warehouseQty[$cid][$wid] = $qty;
                $totalQty += $qty;
            }
        }

        $existingGallery = $product ? $product->allMedia->where('usage_type', 'gallery') : collect();
        $defaultGalleryIds = old('gallery_ids', $existingGallery->pluck('media_id')->implode(','));

        $tierPrices = [];
        if ($product) {
            $product->loadMissing('prices');
            $tierPrices = $product->prices
                ->pluck('price', 'price_tier_id')
                ->map(fn ($v) => (string) $v)
                ->toArray();
        }

        $serialsByWarehouse = [];
        if ($product && $product->is_serialized) {
            $serialsByWarehouse = $this->getSerialsByCompanyWarehouse($product);
        }

        return [
            'warehouseQty' => $warehouseQty,
            'totalQty' => $totalQty,
            'existingGallery' => $existingGallery,
            'defaultGalleryIds' => $defaultGalleryIds,
            'tierPrices' => $tierPrices,
            'serialsByWarehouse' => $serialsByWarehouse,
        ];
    }

    /**
     * Lọc các trường được phép lưu vào bảng sản phẩm.
     */
    private function productPayload(array $data): array
    {
        return Arr::only($data, [
            'name', 'sku', 'unit', 'price',
            'price_agent', 'price_retail', 'category_id', 'brand_id',
            'description',
        ]);
    }

    /**
     * Đồng bộ media của sản phẩm (ảnh chính và gallery).
     */
    private function syncMedia(Product $product, array $data): void
    {
        $this->syncMainImage($product, $data);
        $this->syncGallery($product, $data);
    }

    /**
     * Đồng bộ ảnh chính của sản phẩm theo main_image_id.
     */
    private function syncMainImage(Product $product, array $data): void
    {
        if (! array_key_exists('main_image_id', $data)) {
            return;
        }

        $product->allMedia()->where('usage_type', 'main_image')->delete();

        if (! empty($data['main_image_id'])) {
            $media = MediaFile::find($data['main_image_id']);
            if ($media) {
                $product->attachMedia($media, 'main_image');
            }
        }
    }

    /**
     * Đồng bộ danh sách ảnh gallery của sản phẩm.
     */
    private function syncGallery(Product $product, array $data): void
    {
        if (! array_key_exists('gallery_ids', $data)) {
            return;
        }

        $product->allMedia()->where('usage_type', 'gallery')->delete();

        $galleryIds = $this->normalizeGalleryIds($data['gallery_ids']);
        if (! empty($galleryIds)) {
            MediaFile::query()
                ->whereIn('id', $galleryIds)
                ->get()
                ->each(fn (MediaFile $media) => $product->attachMedia($media, 'gallery'));
        }
    }

    /**
     * Chuẩn hoá gallery_ids (chuỗi CSV hoặc mảng) thành mảng ID sạch.
     */
    private function normalizeGalleryIds(null|array|string $galleryIds): array
    {
        if (empty($galleryIds)) {
            return [];
        }
        if (is_array($galleryIds)) {
            return array_filter($galleryIds);
        }

        return array_filter(array_map('trim', explode(',', $galleryIds)));
    }

    /**
     * Đồng bộ giá theo loại giá (tier): xoá tier bỏ trống, upsert tier có giá.
     */
    private function syncPrices(Product $product, array $data): void
    {
        if (! array_key_exists('prices', $data) || ! is_array($data['prices'])) {
            return;
        }

        $incoming = [];
        foreach ($data['prices'] as $tierId => $price) {
            if ($price === null || $price === '') {
                continue;
            }
            $incoming[(int) $tierId] = (float) $price;
        }

        $product->prices()
            ->whereNotIn('price_tier_id', array_keys($incoming))
            ->delete();

        foreach ($incoming as $tierId => $price) {
            ProductPrice::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'price_tier_id' => $tierId,
                ],
                [
                    'price' => $price,
                    'effective_from' => null,
                    'effective_to' => null,
                ]
            );
        }
    }

    /**
     * ✅ stocks[companyId][warehouseId][qty|serials]
     */
    private function applyStocks(Product $product, array $data): void
    {
        if (empty($data['stocks']) || ! is_array($data['stocks'])) {
            return;
        }

        $stocksByCompany = $data['stocks'];

        $hasCompanyInStock = \Illuminate\Support\Facades\Schema::hasColumn('crm_product_stock', 'company_id');
        $hasCompanyInSerialState = \Illuminate\Support\Facades\Schema::hasColumn('crm_serial_unit_states', 'company_id');

        $costBeforeVat = (float) ($data['price_agent'] ?? $product->price_agent ?? 0);
        $costVatPercent = (float) ($data['cost_vat_percent'] ?? $product->cost_vat_percent ?? $product->vat_percent ?? 0);

        foreach ($stocksByCompany as $companyId => $warehouses) {
            $companyId = (int) $companyId;
            if (! is_array($warehouses)) {
                continue;
            }

            foreach ($warehouses as $warehouseId => $stockData) {
                $warehouseId = (int) $warehouseId;
                if (! is_array($stockData)) {
                    $stockData = [];
                }

                $desiredQty = (int) ($stockData['qty'] ?? 0);
                if ($desiredQty < 0) {
                    $desiredQty = 0;
                }

                // ===== SERIALIZED =====
                if (! empty($product->is_serialized)) {
                    $serials = json_decode($stockData['serials'] ?? '[]', true);
                    if (! is_array($serials)) {
                        $serials = [];
                    }

                    // Nếu DB chưa có company_id trong serial states -> dùng hàm cũ theo warehouse
                    if ($hasCompanyInSerialState) {
                        $this->syncSerialUnitsInCompanyWarehouse($product, $companyId, $warehouseId, $serials);
                    } else {
                        $this->syncSerialUnitsInWarehouse($product, $warehouseId, $serials);
                    }

                    $desiredQty = count($serials);

                    if ($hasCompanyInStock) {
                        ProductStock::updateOrCreate(
                            ['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'company_id' => $companyId],
                            ['qty' => $desiredQty]
                        );
                    } else {
                        ProductStock::updateOrCreate(
                            ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                            ['qty' => $desiredQty]
                        );
                    }

                    // Với hàng serial vẫn tạo/đồng bộ lô để có giá vốn và FIFO báo cáo.
                    app(\App\Services\StockLotService::class)->syncManualStock(
                        $product,
                        $companyId,
                        $warehouseId,
                        $desiredQty,
                        $costBeforeVat,
                        $costVatPercent
                    );

                    continue;
                }

                // ===== NON-SERIALIZED =====
                // Tồn tổng vẫn nằm ở crm_product_stock.
                // Nếu số lượng tăng: tạo lô mới theo giá vốn hiện tại.
                // Nếu số lượng giảm: trừ các lô cũ nhất trước.
                app(\App\Services\StockLotService::class)->syncManualStock(
                    $product,
                    $companyId,
                    $warehouseId,
                    $desiredQty,
                    $costBeforeVat,
                    $costVatPercent
                );
            }
        }
    }

    /**
     * Lấy danh sách serial hiện có (in_stock/reserved) của sản phẩm theo công ty + kho.
     */
    private function getExistingSerialCodesInCompanyWarehouse(int $productId, ?int $companyId, int $warehouseId): array
    {
        return DB::table('crm_serial_unit_states as sus')
            ->join('crm_serial_units as su', 'su.id', '=', 'sus.serial_unit_id')
            ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('su.product_id', $productId)
            ->where('sus.warehouse_id', $warehouseId)
            ->when($companyId !== null, fn ($q) => $q->where('sus.company_id', $companyId))
            ->whereIn('sus.state', ['in_stock', 'reserved'])
            ->where('sui.is_primary', 1)
            ->where('si.type', 'serial')
            ->pluck('si.code')
            ->map(fn ($v) => strtoupper(trim((string) $v)))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Gom danh sách serial của sản phẩm theo [company_id][warehouse_id].
     */
    protected function getSerialsByCompanyWarehouse(Product $product): array
    {
        $rows = DB::table('crm_serial_unit_states as sus')
            ->join('crm_serial_units as su', 'su.id', '=', 'sus.serial_unit_id')
            ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('su.product_id', $product->id)
            ->whereNotNull('sus.warehouse_id')
            ->whereNotNull('sus.company_id')
            ->whereIn('sus.state', ['in_stock', 'reserved'])
            ->where('sui.is_primary', 1)
            ->where('si.type', 'serial')
            ->select('sus.company_id', 'sus.warehouse_id', 'si.code')
            ->orderBy('sus.company_id')
            ->orderBy('sus.warehouse_id')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $cid = (int) $r->company_id;
            $wid = (int) $r->warehouse_id;
            $code = strtoupper(trim((string) $r->code));
            if ($code === '') {
                continue;
            }

            $result[$cid] ??= [];
            $result[$cid][$wid] ??= [];
            $result[$cid][$wid][] = $code;
        }

        foreach ($result as $cid => $byWid) {
            foreach ($byWid as $wid => $list) {
                $result[$cid][$wid] = array_values(array_unique($list));
            }
        }

        return $result;
    }

    /**
     * Đồng bộ serial theo kho (schema cũ chưa có company_id trong serial states).
     * Delegate sang bản theo công ty với companyId = null để tránh lặp logic.
     */
    private function syncSerialUnitsInWarehouse(Product $product, int $warehouseId, array $serials): void
    {
        $this->syncSerialUnitsInCompanyWarehouse($product, null, $warehouseId, $serials);
    }

    /**
     * Đồng bộ serial theo công ty + kho: thêm serial mới, gỡ serial không còn trong danh sách.
     * companyId = null nghĩa là schema cũ chưa có cột company_id — bỏ qua mọi thao tác theo công ty.
     */
    private function syncSerialUnitsInCompanyWarehouse(Product $product, ?int $companyId, int $warehouseId, array $serials): void
    {
        $serials = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            $serials
        ))));

        if (! empty($serials)) {
            $this->validateSerialsForProduct($product, $serials);
        }

        $existing = $this->getExistingSerialCodesInCompanyWarehouse($product->id, $companyId, $warehouseId);

        $toAdd = array_values(array_diff($serials, $existing));
        if (! empty($toAdd)) {
            $companyNote = $companyId !== null ? " company #{$companyId}" : '';
            $event = InventoryEvent::create([
                'event_type' => 'receive',
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'note' => "SYNC serial(add) product #{$product->id}{$companyNote} warehouse #{$warehouseId}",
            ]);

            foreach ($toAdd as $serialCode) {
                $serialUnit = SerialUnit::create(['product_id' => $product->id]);

                $identifier = SerialIdentifier::firstOrCreate([
                    'type' => 'serial',
                    'code' => $serialCode,
                ]);

                DB::table('crm_serial_unit_identifiers')->insert([
                    'serial_unit_id' => $serialUnit->id,
                    'serial_identifier_id' => $identifier->id,
                    'is_primary' => 1,
                ]);

                SerialEventLine::create([
                    'event_id' => $event->id,
                    'serial_unit_id' => $serialUnit->id,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => $warehouseId,
                ]);

                $stateValues = [
                    'warehouse_id' => $warehouseId,
                    'state' => 'in_stock',
                    'last_event_id' => $event->id,
                    'synced_at' => now(),
                ];
                if ($companyId !== null) {
                    $stateValues['company_id'] = $companyId;
                }

                SerialUnitState::updateOrCreate(
                    ['serial_unit_id' => $serialUnit->id],
                    $stateValues
                );
            }
        }

        $toRemove = array_values(array_diff($existing, $serials));
        if (! empty($toRemove)) {
            $unitIds = DB::table('crm_serial_unit_states as sus')
                ->join('crm_serial_units as su', 'su.id', '=', 'sus.serial_unit_id')
                ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->where('su.product_id', $product->id)
                ->where('sus.warehouse_id', $warehouseId)
                ->when($companyId !== null, fn ($q) => $q->where('sus.company_id', $companyId))
                ->whereIn('sus.state', ['in_stock', 'reserved'])
                ->where('sui.is_primary', 1)
                ->where('si.type', 'serial')
                ->whereIn('si.code', $toRemove)
                ->pluck('sus.serial_unit_id')
                ->unique()
                ->values()
                ->toArray();

            if (! empty($unitIds)) {
                $removedValues = [
                    'warehouse_id' => null,
                    'state' => 'removed',
                    'synced_at' => now(),
                ];
                if ($companyId !== null) {
                    $removedValues['company_id'] = null;
                }

                DB::table('crm_serial_unit_states')
                    ->whereIn('serial_unit_id', $unitIds)
                    ->update($removedValues);
            }
        }
    }

    /**
     * Kiểm tra tính hợp lệ của serial (độ dài, ký tự, không trùng sản phẩm khác).
     */
    protected function validateSerialsForProduct(Product $product, array $serials): void
    {
        foreach ($serials as $serial) {
            $serial = strtoupper(trim($serial));

            if (strlen($serial) < 6 || strlen($serial) > 50) {
                throw new \Exception("Serial '{$serial}' độ dài không hợp lệ (6-50 ký tự)");
            }

            if (! preg_match('/^[A-Za-z0-9\-_\/]+$/', $serial)) {
                throw new \Exception("Serial '{$serial}' chứa ký tự không hợp lệ");
            }

            $existsOtherProduct = DB::table('crm_serial_unit_identifiers as sui')
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->join('crm_serial_units as su', 'su.id', '=', 'sui.serial_unit_id')
                ->where('si.type', 'serial')
                ->where('si.code', $serial)
                ->where('su.product_id', '!=', $product->id)
                ->exists();

            if ($existsOtherProduct) {
                throw new \Exception("Serial '{$serial}' đã tồn tại ở sản phẩm khác");
            }
        }
    }

    /**
     * Đồng bộ tổng tồn kho vào cột qty/quantity/stock_qty của bảng sản phẩm (nếu có).
     */
    private function syncProductTotalQty(Product $product): void
    {
        $sum = ProductStock::query()
            ->where('product_id', $product->id)
            ->sum('qty');

        if (Schema::hasColumn('crm_product_catalog', 'qty')) {
            $product->qty = (int) $sum;
            $product->save();

            return;
        }

        if (Schema::hasColumn('crm_product_catalog', 'quantity')) {
            $product->quantity = (int) $sum;
            $product->save();

            return;
        }

        if (Schema::hasColumn('crm_product_catalog', 'stock_qty')) {
            $product->stock_qty = (int) $sum;
            $product->save();

            return;
        }
    }
}
