<?php

namespace App\Models\Core;

use App\Models\CRM\Orders\Order;
use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Serial\SerialEventLine;
use App\Models\Inventory\Serial\SerialUnitState;
use App\Models\Inventory\Stock\ProductStock;
use App\Models\Inventory\Stock\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Warehouse extends Model
{

    public function companies()
    {
        return $this->belongsToMany(
            Company::class,
            'company_warehouse',
            'warehouse_id',
            'company_id'
        )->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }


        protected $table = 'crm_warehouses';

    protected $fillable = [
        'company_id',
        'name',
        'location',
        'is_active', // nếu có
    ];

    // ==================== BASIC RELATIONSHIPS ====================

    /**
     * Người quản lý kho (user)
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Tồn kho sản phẩm (non-serialized)
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'warehouse_id');
    }

    /**
     * Đơn hàng xuất từ kho này
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'warehouse_id');
    }

    /**
     * Lịch sử dịch chuyển kho (non-serialized)
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    // ==================== SERIAL RELATIONSHIPS ====================

    /**
     * Trạng thái serial units hiện có trong kho này
     */
    public function serialStates(): HasMany
    {
        return $this->hasMany(SerialUnitState::class, 'warehouse_id');
    }

    /**
     * Serial units hiện có trong kho (chỉ in_stock)
     */
    public function availableSerialStates(): HasMany
    {
        return $this->hasMany(SerialUnitState::class, 'warehouse_id')
            ->where('current_state', SerialUnitState::STATE_IN_STOCK);
    }

    /**
     * Serial event lines - hàng nhập vào kho này
     */
    public function incomingSerialLines(): HasMany
    {
        return $this->hasMany(SerialEventLine::class, 'to_warehouse_id');
    }

    /**
     * Serial event lines - hàng xuất từ kho này
     */
    public function outgoingSerialLines(): HasMany
    {
        return $this->hasMany(SerialEventLine::class, 'from_warehouse_id');
    }

    // ==================== INVENTORY HELPER METHODS ====================

    /**
     * Kiểm tra kho có tồn kho không (bao gồm cả serial và non-serial)
     */
    public function hasInventory(): bool
    {
        $hasNonSerialStock = $this->stocks()
            ->where('qty', '>', 0)
            ->exists();

        if ($hasNonSerialStock) {
            return true;
        }

        return $this->serialStates()
            ->where('current_state', SerialUnitState::STATE_IN_STOCK)
            ->exists();
    }

    /**
     * Kiểm tra kho có serial units không
     */
    public function hasSerialInventory(): bool
    {
        return $this->serialStates()
            ->where('current_state', SerialUnitState::STATE_IN_STOCK)
            ->exists();
    }

    /**
     * Đếm số serial units theo trạng thái
     */
    public function countSerialsByState(): array
    {
        return $this->serialStates()
            ->selectRaw('current_state, COUNT(*) as count')
            ->groupBy('current_state')
            ->pluck('count', 'current_state')
            ->toArray();
    }

    /**
     * Đếm serial units có sẵn để bán
     */
    public function countAvailableSerials(?int $productId = null): int
    {
        $query = $this->availableSerialStates();

        if ($productId) {
            $query->whereHas('serialUnit', fn($q) => $q->where('product_id', $productId));
        }

        return $query->count();
    }

    /**
     * Lấy danh sách serial units có sẵn để bán
     */
    public function getAvailableSerials(?int $productId = null, ?int $limit = null)
    {
        $query = $this->availableSerialStates()
            ->with(['serialUnit.identifiers.serialIdentifier', 'serialUnit.product']);

        if ($productId) {
            $query->whereHas('serialUnit', fn($q) => $q->where('product_id', $productId));
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Lấy danh sách mã serial có sẵn cho sản phẩm
     */
    public function getAvailableSerialCodes(int $productId): array
    {
        $serials = $this->getAvailableSerials($productId);

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
    public function hasEnoughSerials(int $productId, int $quantity): bool
    {
        return $this->countAvailableSerials($productId) >= $quantity;
    }

    /**
     * Kiểm tra có đủ tồn kho không (xét cả serial và non-serial)
     */
    public function hasEnoughStock(int $productId, int $quantity): bool
    {
        $product = Product::find($productId);

        if (!$product) {
            return false;
        }

        if ($product->isSerialized()) {
            return $this->hasEnoughSerials($productId, $quantity);
        }

        $stock = $this->stocks()
            ->where('product_id', $productId)
            ->first();

        return $stock && $stock->qty >= $quantity;
    }

    /**
     * Lấy tồn kho thực tế của sản phẩm (xét cả serial và non-serial)
     */
    public function getActualStock(int $productId): int
    {
        $product = Product::find($productId);

        if (!$product) {
            return 0;
        }

        if ($product->isSerialized()) {
            return $this->countAvailableSerials($productId);
        }

        $stock = $this->stocks()
            ->where('product_id', $productId)
            ->first();

        return $stock ? (int) $stock->qty : 0;
    }

    /**
     * Lấy báo cáo tồn kho tổng hợp
     */
    public function getInventoryReport(): Collection
    {
        // Non-serialized products
        $nonSerialStock = $this->stocks()
            ->with('product')
            ->where('qty', '>', 0)
            ->get()
            ->map(fn($stock) => [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name ?? 'N/A',
                'product_sku' => $stock->product->sku ?? 'N/A',
                'is_serialized' => false,
                'quantity' => $stock->qty,
                'serial_units' => [],
            ]);

        // Serialized products
        $serialStock = $this->availableSerialStates()
            ->with(['serialUnit.product', 'serialUnit.identifiers.serialIdentifier'])
            ->get()
            ->groupBy('serialUnit.product_id')
            ->map(function ($states, $productId) {
                $firstUnit = $states->first()->serialUnit;
                $product = $firstUnit->product;

                return [
                    'product_id' => $productId,
                    'product_name' => $product->name ?? 'N/A',
                    'product_sku' => $product->sku ?? 'N/A',
                    'is_serialized' => true,
                    'quantity' => $states->count(),
                    'serial_units' => $states->map(function ($state) {
                        $unit = $state->serialUnit;
                        $primaryId = $unit->identifiers->firstWhere('is_primary', true);
                        $primaryCode = $primaryId?->serialIdentifier?->code
                            ?? $unit->identifiers->first()?->serialIdentifier?->code
                            ?? "SN#{$unit->id}";

                        return [
                            'serial_unit_id' => $unit->id,
                            'code' => $primaryCode,
                            'state' => $state->current_state,
                        ];
                    })->values()->toArray(),
                ];
            })->values();

        return $nonSerialStock->concat($serialStock)
            ->sortBy('product_name')
            ->values();
    }

    // ==================== SCOPES ====================

    public function scopeWithInventory($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('stocks', fn($s) => $s->where('qty', '>', 0))
                ->orWhereHas('serialStates', fn($s) =>
                $s->where('current_state', SerialUnitState::STATE_IN_STOCK)
                );
        });
    }

    public function scopeWithSerialInventory($query)
    {
        return $query->whereHas('serialStates', fn($s) =>
        $s->where('current_state', SerialUnitState::STATE_IN_STOCK)
        );
    }

    public function scopeHasProduct($query, int $productId)
    {
        return $query->where(function ($q) use ($productId) {
            $q->whereHas('stocks', fn($s) =>
            $s->where('product_id', $productId)
                ->where('qty', '>', 0)
            )->orWhereHas('serialStates', fn($s) =>
            $s->where('current_state', SerialUnitState::STATE_IN_STOCK)
                ->whereHas('serialUnit', fn($u) => $u->where('product_id', $productId))
            );
        });
    }
}
