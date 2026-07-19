<?php
namespace App\Models\Inventory\Serial;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Stock\InventoryEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bảng CACHE trạng thái hiện tại của Serial Unit
 *
 * Nguồn sự thật (source of truth): crm_inventory_events + crm_serial_event_lines
 * Bảng này chỉ để query nhanh, không phải nguồn dữ liệu chính.
 *
 * @property int $serial_unit_id PK - FK to crm_serial_units
 * @property int|null $warehouse_id FK to crm_warehouses (NULL = không còn trong kho)
 * @property string $state Trạng thái: in_stock, reserved, sold, returned, damaged, scrap, unknown
 * @property int|null $last_event_id FK to crm_inventory_events (event cuối cập nhật)
 * @property \Carbon\Carbon $synced_at Thời điểm sync cache
 *
 * @property-read SerialUnit $serialUnit
 * @property-read Warehouse|null $warehouse
 * @property-read InventoryEvent|null $lastEvent
 * @property-read string $state_name
 * @property-read string $state_badge_class
 * @property-read bool $is_in_warehouse
 * @property-read bool $is_available
 */
class SerialUnitState extends Model
{
    /**
     * Table name
     */
    protected $table = 'crm_serial_unit_states';
    /**
     * Primary key là serial_unit_id (không phải id)
     */
    protected $primaryKey = 'serial_unit_id';
    /**
     * PK không auto-increment vì là FK
     */
    public $incrementing = false;
    /**
     * Không dùng timestamps mặc định (created_at, updated_at)
     * Bảng này chỉ có synced_at
     */
    public $timestamps = false;
    /**
     * Fillable fields
     */
    protected $fillable = [
        'serial_unit_id',
        'warehouse_id',
        'state',
        'last_event_id',
        'synced_at',
    ];
    /**
     * Casts
     */
    protected $casts = [
        'serial_unit_id' => 'integer',
        'warehouse_id' => 'integer',
        'last_event_id' => 'integer',
        'synced_at' => 'datetime',
    ];
    // ==================== CONSTANTS ====================
    /**
     * Các trạng thái hợp lệ
     */
    public const STATE_IN_STOCK = 'in_stock';      // Trong kho, sẵn bán
    public const STATE_RESERVED = 'reserved';      // Đã giữ cho đơn hàng
    public const STATE_SOLD = 'sold';              // Đã bán
    public const STATE_RETURNED = 'returned';      // Khách trả lại
    public const STATE_DAMAGED = 'damaged';        // Hư hỏng
    public const STATE_SCRAP = 'scrap';            // Thanh lý
    public const STATE_UNKNOWN = 'unknown';        // Chưa xác định
    /**
     * Map state => tên tiếng Việt
     */
    public const STATES = [
        self::STATE_IN_STOCK => 'Trong kho',
        self::STATE_RESERVED => 'Đã giữ',
        self::STATE_SOLD => 'Đã bán',
        self::STATE_RETURNED => 'Trả lại',
        self::STATE_DAMAGED => 'Hư hỏng',
        self::STATE_SCRAP => 'Thanh lý',
        self::STATE_UNKNOWN => 'Chưa xác định',
    ];
    /**
     * Các state còn trong kho (có warehouse_id)
     */
    public const STATES_IN_WAREHOUSE = [
        self::STATE_IN_STOCK,
        self::STATE_RESERVED,
        self::STATE_RETURNED,
        self::STATE_DAMAGED,
    ];
    /**
     * Các state có thể bán
     */
    public const STATES_AVAILABLE = [
        self::STATE_IN_STOCK,
    ];
    // ==================== RELATIONSHIPS ====================
    /**
     * Serial Unit sở hữu state này
     */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }
    /**
     * Kho hiện tại (nullable)
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    /**
     * Event cuối cùng đã cập nhật state
     */
    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(InventoryEvent::class, 'last_event_id');
    }
    // ==================== SCOPES ====================
    /**
     * Lọc theo kho
     */
    public function scopeInWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }
    /**
     * Lọc theo state
     */
    public function scopeWithState(Builder $query, string $state): Builder
    {
        return $query->where('state', $state);
    }
    /**
     * Lọc theo nhiều state
     */
    public function scopeWithStates(Builder $query, array $states): Builder
    {
        return $query->whereIn('state', $states);
    }
    /**
     * Serial còn trong kho (có warehouse_id)
     */
    public function scopeInAnyWarehouse(Builder $query): Builder
    {
        return $query->whereNotNull('warehouse_id');
    }
    /**
     * Serial không còn trong kho
     */
    public function scopeOutOfWarehouse(Builder $query): Builder
    {
        return $query->whereNull('warehouse_id');
    }
    /**
     * Serial có thể bán (in_stock)
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereIn('state', self::STATES_AVAILABLE)
            ->whereNotNull('warehouse_id');
    }
    /**
     * Lọc theo sản phẩm (thông qua serial_unit)
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->whereHas('serialUnit', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        });
    }
    /**
     * Lọc sản phẩm + kho
     */
    public function scopeForProductInWarehouse(Builder $query, int $productId, int $warehouseId): Builder
    {
        return $query->forProduct($productId)->inWarehouse($warehouseId);
    }
    // ==================== ACCESSORS ====================
    /**
     * Tên trạng thái tiếng Việt
     */
    public function getStateNameAttribute(): string
    {
        return self::STATES[$this->state] ?? $this->state;
    }
    /**
     * Màu badge theo state (Bootstrap)
     */
    public function getStateBadgeClassAttribute(): string
    {
        return match ($this->state) {
            self::STATE_IN_STOCK => 'bg-success',
            self::STATE_RESERVED => 'bg-warning text-dark',
            self::STATE_SOLD => 'bg-info',
            self::STATE_RETURNED => 'bg-secondary',
            self::STATE_DAMAGED => 'bg-danger',
            self::STATE_SCRAP => 'bg-dark',
            default => 'bg-light text-dark',
        };
    }
    /**
     * Icon theo state (Bootstrap Icons)
     */
    public function getStateIconAttribute(): string
    {
        return match ($this->state) {
            self::STATE_IN_STOCK => 'bi-box-seam',
            self::STATE_RESERVED => 'bi-bookmark-check',
            self::STATE_SOLD => 'bi-bag-check',
            self::STATE_RETURNED => 'bi-arrow-return-left',
            self::STATE_DAMAGED => 'bi-exclamation-triangle',
            self::STATE_SCRAP => 'bi-trash',
            default => 'bi-question-circle',
        };
    }
    /**
     * Kiểm tra còn trong kho không
     */
    public function getIsInWarehouseAttribute(): bool
    {
        return $this->warehouse_id !== null;
    }
    /**
     * Kiểm tra có thể bán không
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->state === self::STATE_IN_STOCK && $this->warehouse_id !== null;
    }
    // ==================== INSTANCE METHODS ====================
    /**
     * Cập nhật state từ inventory event
     */
    public function syncFromEvent(InventoryEvent $event, ?int $warehouseId, string $state): self
    {
        $this->update([
            'warehouse_id' => $warehouseId,
            'state' => $state,
            'last_event_id' => $event->id,
            'synced_at' => now(),
        ]);
        return $this;
    }
    /**
     * Đánh dấu đã bán
     */
    public function markAsSold(?int $eventId = null): self
    {
        return $this->updateState(self::STATE_SOLD, null, $eventId);
    }
    /**
     * Đánh dấu đã giữ (reserve cho đơn hàng)
     */
    public function markAsReserved(?int $eventId = null): self
    {
        return $this->updateState(self::STATE_RESERVED, $this->warehouse_id, $eventId);
    }
    /**
     * Đánh dấu trả về kho
     */
    public function markAsReturned(int $warehouseId, ?int $eventId = null): self
    {
        return $this->updateState(self::STATE_RETURNED, $warehouseId, $eventId);
    }
    /**
     * Đánh dấu hư hỏng
     */
    public function markAsDamaged(?int $eventId = null): self
    {
        return $this->updateState(self::STATE_DAMAGED, $this->warehouse_id, $eventId);
    }
    /**
     * Đánh dấu thanh lý
     */
    public function markAsScrapped(?int $eventId = null): self
    {
        return $this->updateState(self::STATE_SCRAP, null, $eventId);
    }
    /**
     * Khôi phục về in_stock
     */
    public function markAsInStock(int $warehouseId, ?int $eventId = null): self
    {
        return $this->updateState(self::STATE_IN_STOCK, $warehouseId, $eventId);
    }
    /**
     * Chuyển kho
     */
    public function transferToWarehouse(int $newWarehouseId, ?int $eventId = null): self
    {
        return $this->updateState($this->state, $newWarehouseId, $eventId);
    }
    /**
     * Helper cập nhật state
     */
    protected function updateState(string $state, ?int $warehouseId, ?int $eventId): self
    {
        $this->update([
            'state' => $state,
            'warehouse_id' => $warehouseId,
            'last_event_id' => $eventId,
            'synced_at' => now(),
        ]);
        return $this;
    }
    // ==================== STATIC METHODS ====================
    /**
     * Tạo hoặc cập nhật state cho serial unit
     */
    public static function syncState(
        int $serialUnitId,
        string $state,
        ?int $warehouseId = null,
        ?int $eventId = null
    ): self {
        return static::updateOrCreate(
            ['serial_unit_id' => $serialUnitId],
            [
                'state' => $state,
                'warehouse_id' => $warehouseId,
                'last_event_id' => $eventId,
                'synced_at' => now(),
            ]
        );
    }
    /**
     * Đếm serial theo state trong kho
     */
    public static function countByState(int $warehouseId): array
    {
        return static::query()
            ->inWarehouse($warehouseId)
            ->selectRaw('state, COUNT(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();
    }
    /**
     * Đếm serial available của sản phẩm trong kho
     */
    public static function countAvailable(int $productId, int $warehouseId): int
    {
        return static::query()
            ->forProductInWarehouse($productId, $warehouseId)
            ->available()
            ->count();
    }
    /**
     * Lấy danh sách serial available của sản phẩm trong kho
     */
    public static function getAvailableSerials(int $productId, int $warehouseId, int $limit = 100)
    {
        return static::query()
            ->forProductInWarehouse($productId, $warehouseId)
            ->available()
            ->with(['serialUnit.identifiers.serialIdentifier'])
            ->limit($limit)
            ->get();
    }
    /**
     * Lấy danh sách serial codes (chỉ trả về mảng codes)
     */
    public static function getAvailableSerialCodes(int $productId, int $warehouseId): array
    {
        return static::getAvailableSerials($productId, $warehouseId)
            ->map(fn($state) => $state->serialUnit?->primary_code)
            ->filter()
            ->values()
            ->toArray();
    }
    /**
     * Kiểm tra serial có available không
     */
    public static function isSerialAvailable(int $serialUnitId): bool
    {
        return static::query()
            ->where('serial_unit_id', $serialUnitId)
            ->available()
            ->exists();
    }
    /**
     * Batch reserve nhiều serial cho đơn hàng
     */
    public static function reserveSerials(array $serialUnitIds, ?int $eventId = null): int
    {
        return static::query()
            ->whereIn('serial_unit_id', $serialUnitIds)
            ->where('state', self::STATE_IN_STOCK)
            ->update([
                'state' => self::STATE_RESERVED,
                'last_event_id' => $eventId,
                'synced_at' => now(),
            ]);
    }
    /**
     * Batch release nhiều serial (hủy reserve)
     */
    public static function releaseSerials(array $serialUnitIds, ?int $eventId = null): int
    {
        return static::query()
            ->whereIn('serial_unit_id', $serialUnitIds)
            ->where('state', self::STATE_RESERVED)
            ->update([
                'state' => self::STATE_IN_STOCK,
                'last_event_id' => $eventId,
                'synced_at' => now(),
            ]);
    }
    /**
     * Batch mark as sold nhiều serial
     */
    public static function sellSerials(array $serialUnitIds, ?int $eventId = null): int
    {
        return static::query()
            ->whereIn('serial_unit_id', $serialUnitIds)
            ->whereIn('state', [self::STATE_IN_STOCK, self::STATE_RESERVED])
            ->update([
                'state' => self::STATE_SOLD,
                'warehouse_id' => null,
                'last_event_id' => $eventId,
                'synced_at' => now(),
            ]);
    }
    /**
     * Rebuild cache từ event history cho 1 serial
     */
    public static function rebuildFromHistory(int $serialUnitId): ?self
    {
        $lastLine = SerialEventLine::query()
            ->where('serial_unit_id', $serialUnitId)
            ->orderByDesc('id')
            ->with('event')
            ->first();
        if (!$lastLine || !$lastLine->event) {
            return null;
        }
        $warehouseId = match ($lastLine->event->event_type) {
            'receive', 'return', 'transfer' => $lastLine->to_warehouse_id,
            'ship', 'scrap' => null,
            default => $lastLine->to_warehouse_id ?? $lastLine->from_warehouse_id,
        };
        $state = match ($lastLine->event->event_type) {
            'receive' => self::STATE_IN_STOCK,
            'reserve' => self::STATE_RESERVED,
            'unreserve' => self::STATE_IN_STOCK,
            'ship' => self::STATE_SOLD,
            'return' => self::STATE_RETURNED,
            'damage' => self::STATE_DAMAGED,
            'scrap' => self::STATE_SCRAP,
            'transfer' => self::STATE_IN_STOCK,
            default => self::STATE_UNKNOWN,
        };
        return static::syncState($serialUnitId, $state, $warehouseId, $lastLine->event->id);
    }
    /**
     * Rebuild tất cả cache (batch job)
     */
    public static function rebuildAll(): int
    {
        $count = 0;
        SerialUnit::query()
            ->select('id')
            ->chunkById(500, function ($units) use (&$count) {
                foreach ($units as $unit) {
                    if (static::rebuildFromHistory($unit->id)) {
                        $count++;
                    }
                }
            });
        return $count;
    }
}
