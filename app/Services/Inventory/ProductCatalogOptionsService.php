<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nạp dữ liệu tra cứu cho form/màn sản phẩm: bảng giá (tier), giá theo tier
 * của sản phẩm, kho theo công ty (pivot company_warehouse) và cây danh mục.
 *
 * Các method dò nhiều tên bảng ứng viên (crm_product_prices/product_prices/...)
 * để tương thích dữ liệu production cũ — giữ nguyên khi tách.
 *
 * Tách nguyên trạng từ ProductController (P1b refactor) — hành vi chốt bằng
 * ProductPagesCharacterizationTest.
 */
class ProductCatalogOptionsService
{
    /**
     * ✅ LOAD LOẠI GIÁ (TIER)
     */
    public function loadPriceTiers()
    {
        $candidates = [
            'crm_price_tiers',
            'price_tiers',
            'crm_price_types',
            'crm_price_levels',
            'crm_price_groups',
            'price_types',
        ];

        foreach ($candidates as $t) {
            if ($this->tableExists($t) && Schema::hasColumn($t, 'name')) {
                $q = DB::table($t);

                if (Schema::hasColumn($t, 'is_active')) {
                    $q->where(function ($x) {
                        $x->where('is_active', 1)->orWhereNull('is_active');
                    });
                }

                if (Schema::hasColumn($t, 'priority')) {
                    $q->orderBy('priority');
                } elseif (Schema::hasColumn($t, 'sort')) {
                    $q->orderBy('sort');
                }

                return $q->orderBy('id')->get();
            }
        }

        return collect();
    }

    /**
     * ✅ GẮN GIÁ THEO LOẠI GIÁ VÀO $product->prices
     * để view dùng: $product->prices->firstWhere('price_tier_id', ...)
     */
    public function attachTierPricesToProducts($products): void
    {
        try {
            $items = method_exists($products, 'items') ? $products->items() : (is_iterable($products) ? $products : []);
            $ids = collect($items)->pluck('id')->filter()->values();
            if ($ids->isEmpty()) {
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
                    $this->tableExists($t)
                    && Schema::hasColumn($t, 'product_id')
                    && (Schema::hasColumn($t, 'price_tier_id') || Schema::hasColumn($t, 'tier_id'))
                    && (Schema::hasColumn($t, 'price') || Schema::hasColumn($t, 'value'))
                ) {
                    $table = $t;
                    break;
                }
            }

            if (! $table) {
                foreach ($items as $p) {
                    try {
                        $p->setRelation('prices', collect());
                    } catch (\Throwable $e) {
                    }
                }

                return;
            }

            $tierCol = Schema::hasColumn($table, 'price_tier_id') ? 'price_tier_id' : 'tier_id';
            $priceCol = Schema::hasColumn($table, 'price') ? 'price' : 'value';
            $hasVatCol = Schema::hasColumn($table, 'vat_percent');
            $hasAfterVatCol = Schema::hasColumn($table, 'price_after_vat');
            $hasEffectiveTo = Schema::hasColumn($table, 'effective_to');

            $rowsQuery = DB::table($table)
                ->whereIn('product_id', $ids->all());

            if ($hasEffectiveTo) {
                $rowsQuery->where(function ($q) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                });
            }

            $rows = $rowsQuery
                ->orderByDesc('id')
                ->get();

            $grouped = $rows->groupBy('product_id')->map(function ($col) use ($tierCol, $priceCol, $hasVatCol, $hasAfterVatCol) {
                return $col
                    ->unique($tierCol)
                    ->map(function ($r) use ($tierCol, $priceCol, $hasVatCol, $hasAfterVatCol) {
                        $price = (float) ($r->{$priceCol} ?? 0);
                        $vatPercent = $hasVatCol ? (float) ($r->vat_percent ?? 0) : 0;
                        $afterVat = $hasAfterVatCol
                            ? (float) ($r->price_after_vat ?? 0)
                            : round($price * (1 + $vatPercent / 100), 2);

                        if ($afterVat <= 0 && $price > 0) {
                            $afterVat = round($price * (1 + $vatPercent / 100), 2);
                        }

                        return (object) [
                            'price_tier_id' => (int) ($r->{$tierCol} ?? 0),
                            'price' => $price,
                            'vat_percent' => $vatPercent,
                            'price_after_vat' => $afterVat,
                        ];
                    })
                    ->values();
            });

            foreach ($items as $p) {
                $list = $grouped->get($p->id, collect());
                try {
                    $p->setRelation('prices', collect($list));
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
            // không làm gì để tránh crash list
        }
    }

    /**
     * ✅ LOAD tier prices để edit
     */
    public function loadTierPricesForProduct(int $productId): array
    {
        $candidateTables = [
            'crm_product_prices',
            'product_prices',
            'crm_prices',
        ];

        $table = null;
        foreach ($candidateTables as $t) {
            if (
                $this->tableExists($t)
                && Schema::hasColumn($t, 'product_id')
                && (Schema::hasColumn($t, 'price_tier_id') || Schema::hasColumn($t, 'tier_id'))
                && (Schema::hasColumn($t, 'price') || Schema::hasColumn($t, 'value'))
            ) {
                $table = $t;
                break;
            }
        }

        if (! $table) {
            return [];
        }

        $tierCol = Schema::hasColumn($table, 'price_tier_id') ? 'price_tier_id' : 'tier_id';
        $priceCol = Schema::hasColumn($table, 'price') ? 'price' : 'value';

        $hasVatCol = Schema::hasColumn($table, 'vat_percent');
        $hasAfterVatCol = Schema::hasColumn($table, 'price_after_vat');
        $hasEffectiveTo = Schema::hasColumn($table, 'effective_to');

        $q = DB::table($table)->where('product_id', $productId);

        if ($hasEffectiveTo) {
            $q->where(function ($x) {
                $x->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
        }

        $rows = $q->orderByDesc('id')->get();

        $out = [];

        foreach ($rows as $r) {
            $tierId = (int) ($r->{$tierCol} ?? 0);

            if ($tierId <= 0 || isset($out[$tierId])) {
                continue;
            }

            $beforeVat = (float) ($r->{$priceCol} ?? 0);
            $vatPercent = $hasVatCol ? (float) ($r->vat_percent ?? 0) : 0;
            $afterVat = $hasAfterVatCol
                ? (float) ($r->price_after_vat ?? 0)
                : ($beforeVat * (1 + $vatPercent / 100));

            $out[$tierId] = [
                'before_vat' => $beforeVat,
                'price' => $beforeVat,
                'vat_percent' => $vatPercent,
                'after_vat' => $afterVat,
            ];
        }

        return $out;
    }

    /**
     * ✅ Load kho theo pivot company_warehouse (kho dùng chung sẽ hiện ở cả 2)
     */
    public function loadCompanyWarehouses(array $companyIds): array
    {
        $pivot = 'company_warehouse';

        if (! $this->tableExists($pivot)) {
            $all = Warehouse::query()->orderBy('name')->get();
            $out = [];
            foreach ($companyIds as $cid) {
                $out[$cid] = $all;
            }

            return $out;
        }

        $rows = DB::table($pivot)
            ->whereIn('company_id', $companyIds)
            ->get();

        $warehouseIds = $rows->pluck('warehouse_id')->unique()->values()->all();

        $warehouses = Warehouse::query()
            ->whereIn('id', $warehouseIds)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($companyIds as $cid) {
            $out[$cid] = collect();
        }

        foreach ($rows as $r) {
            $cid = (int) $r->company_id;
            $wid = (int) $r->warehouse_id;
            if (isset($out[$cid]) && $warehouses->has($wid)) {
                $out[$cid]->push($warehouses->get($wid));
            }
        }

        foreach ($out as $cid => $col) {
            $out[$cid] = $col->sortBy('name')->values();
        }

        return $out;
    }

    /**
     * ✅ Build danh mục dạng cây (cha -> con) để dropdown thụt lề
     */
    public function buildCategoryOptions()
    {
        $all = ProductCategory::query()
            ->select('id', 'name', 'parent_id')
            ->orderBy('name')
            ->get();

        $byParent = $all->groupBy(function ($item) {
            return (int) ($item->parent_id ?? 0);
        });

        $out = collect();
        $walk = function ($parentId, $level) use (&$walk, &$out, $byParent) {
            $children = $byParent->get((int) $parentId, collect());
            foreach ($children as $c) {
                $c->level = $level;
                $out->push($c);
                $walk($c->id, $level + 1);
            }
        };

        $walk(0, 0);

        return $out;
    }

    /**
     * Kiểm tra bảng có tồn tại trong DB hay không; trả về false nếu có lỗi.
     */
    public function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
