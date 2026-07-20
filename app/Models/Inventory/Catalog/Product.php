<?php

namespace App\Models\Inventory\Catalog;

use App\Models\CRM\Orders\OrderItem;
use App\Models\Inventory\Pricing\ProductPrice;
use App\Models\Inventory\Serial\SerialUnit;
use App\Models\Inventory\Serial\SerialUnitState;
use App\Models\Inventory\Stock\ProductStock;
use App\Models\Inventory\Stock\StockMovement;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Product extends Model
{
    use HasMedia;

    protected $table = 'crm_product_catalog';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'unit',
        'category_id',
        'brand_id',
        'price',
        'price_agent',
        'price_retail',
        'quantity',
        'description',
        'is_serialized',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_agent' => 'decimal:2',
        'price_retail' => 'decimal:2',
        'is_serialized' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==================== ATTRIBUTES ====================
    protected function quantity(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (array_key_exists('stocks_sum_qty', $attributes)) {
                    return (int) $attributes['stocks_sum_qty'];
                }
                if ($this->relationLoaded('stocks')) {
                    return (int) $this->stocks->sum('qty');
                }

                return (int) ($value ?? 0);
            }
        );
    }

    // ==================== BASIC RELATIONSHIPS ====================
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    // ==================== MEDIA RELATIONSHIPS ====================
    public function images(): MorphMany
    {
        return $this->mediaRelations('gallery');
    }

    public function mainImage(): MorphOne
    {
        return $this->mediaRelation('main_image');
    }

    // ==================== SERIAL RELATIONSHIPS ====================
    /**
     * Tất cả serial units của sản phẩm này
     */
    public function serialUnits(): HasMany
    {
        return $this->hasMany(SerialUnit::class, 'product_id');
    }

    /**
     * Trạng thái hiện tại của tất cả serial units
     */
    public function serialStates(): HasManyThrough
    {
        return $this->hasManyThrough(
            SerialUnitState::class,
            SerialUnit::class,
            'product_id',
            'serial_unit_id',
            'id',
            'id'
        );
    }

    // ==================== SERIAL HELPER METHODS ====================
    /**
     * Kiểm tra sản phẩm có quản lý serial không
     */
    public function isSerialized(): bool
    {
        return (bool) $this->is_serialized;
    }

    /**
     * Kiểm tra sản phẩm KHÔNG quản lý serial
     */
    public function isNonSerialized(): bool
    {
        return ! $this->is_serialized;
    }

    /**
     * Đếm số serial units theo trạng thái
     */
    public function countSerialUnits(?string $state = null, ?int $warehouseId = null): int
    {
        if (! $this->isSerialized()) {
            return 0;
        }
        $query = SerialUnitState::query()
            ->whereHas('serialUnit', fn ($q) => $q->where('product_id', $this->id));
        if ($state) {
            $query->where('current_state', $state);
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->count();
    }

    /**
     * Đếm serial units có sẵn để bán trong kho
     */
    public function countAvailableSerials(?int $warehouseId = null): int
    {
        return $this->countSerialUnits(SerialUnitState::STATE_IN_STOCK, $warehouseId);
    }

    /**
     * Lấy danh sách serial units có sẵn để bán
     */
    public function getAvailableSerials(?int $warehouseId = null, ?int $limit = null)
    {
        if (! $this->isSerialized()) {
            return collect();
        }
        $query = SerialUnitState::query()
            ->with(['serialUnit.identifiers.serialIdentifier'])
            ->whereHas('serialUnit', fn ($q) => $q->where('product_id', $this->id))
            ->where('current_state', SerialUnitState::STATE_IN_STOCK)
            ->whereNotNull('warehouse_id');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Lấy danh sách mã serial có sẵn
     */
    public function getAvailableSerialCodes(?int $warehouseId = null): array
    {
        $serials = $this->getAvailableSerials($warehouseId);

        return $serials->mapWithKeys(function ($state) {
            $unit = $state->serialUnit;
            $primaryIdentifier = $unit->identifiers
                ->firstWhere('is_primary', true);
            $code = $primaryIdentifier
                ? $primaryIdentifier->serialIdentifier->code
                : ($unit->identifiers->first()?->serialIdentifier?->code ?? "SN#{$unit->id}");

            return [$unit->id => $code];
        })->toArray();
    }

    /**
     * Kiểm tra có đủ serial để xuất không
     */
    public function hasEnoughSerials(int $quantity, ?int $warehouseId = null): bool
    {
        if (! $this->isSerialized()) {
            if ($warehouseId) {
                $stock = $this->stocks()->where('warehouse_id', $warehouseId)->first();

                return $stock && $stock->qty >= $quantity;
            }

            return $this->quantity >= $quantity;
        }

        return $this->countAvailableSerials($warehouseId) >= $quantity;
    }

    /**
     * Lấy tồn kho thực tế (xét cả serial và non-serial)
     */
    public function getActualStock(?int $warehouseId = null): int
    {
        if ($this->isSerialized()) {
            return $this->countAvailableSerials($warehouseId);
        }
        if ($warehouseId) {
            $stock = $this->stocks()->where('warehouse_id', $warehouseId)->first();

            return $stock ? (int) $stock->qty : 0;
        }

        return (int) $this->quantity;
    }

    // ==================== SCOPES ====================
    public function scopeSerialized($query)
    {
        return $query->where('is_serialized', true);
    }

    public function scopeNonSerialized($query)
    {
        return $query->where(function ($q) {
            $q->where('is_serialized', false)
                ->orWhereNull('is_serialized');
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($sub) {
                $sub->where(function ($s) {
                    $s->where('is_serialized', false)
                        ->orWhereNull('is_serialized');
                })->whereHas('stocks', fn ($s) => $s->where('qty', '>', 0));
            })
                ->orWhere(function ($sub) {
                    $sub->where('is_serialized', true)
                        ->whereHas('serialUnits.state', fn ($s) => $s->where('current_state', SerialUnitState::STATE_IN_STOCK)
                            ->whereNotNull('warehouse_id')
                        );
                });
        });
    }

    public function scopeInStockAt($query, int $warehouseId)
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where(function ($sub) use ($warehouseId) {
                $sub->where(function ($s) {
                    $s->where('is_serialized', false)
                        ->orWhereNull('is_serialized');
                })->whereHas('stocks', fn ($s) => $s->where('warehouse_id', $warehouseId)
                    ->where('qty', '>', 0)
                );
            })
                ->orWhere(function ($sub) use ($warehouseId) {
                    $sub->where('is_serialized', true)
                        ->whereHas('serialUnits.state', fn ($s) => $s->where('current_state', SerialUnitState::STATE_IN_STOCK)
                            ->where('warehouse_id', $warehouseId)
                        );
                });
        });
    }
}
