<?php

declare(strict_types=1);
namespace App\Models\Inventory\Serial;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class SerialUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_serial_units';

    protected $fillable = [
        'product_id',
    ];

    /**
     * Sản phẩm của serial unit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Các identifier của serial unit (serial number, IMEI, etc.)
     */
    public function identifiers(): BelongsToMany
    {
        return $this->belongsToMany(
            SerialIdentifier::class,
            'crm_serial_unit_identifiers',
            'serial_unit_id',
            'serial_identifier_id'
        )->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Chi tiết các identifier
     */
    public function unitIdentifiers(): HasMany
    {
        return $this->hasMany(SerialUnitIdentifier::class, 'serial_unit_id');
    }

    /**
     * Identifier chính (primary)
     */
    public function primaryIdentifier()
    {
        return $this->hasOneThrough(
            SerialIdentifier::class,
            SerialUnitIdentifier::class,
            'serial_unit_id',
            'id',
            'id',
            'serial_identifier_id'
        )->where('crm_serial_unit_identifiers.is_primary', true);
    }

    /**
     * Các dòng sự kiện (nhập/xuất/chuyển kho)
     */
    public function eventLines(): HasMany
    {
        return $this->hasMany(SerialEventLine::class, 'serial_unit_id');
    }

    /**
     * Các order item chứa serial unit này
     */
    public function orderItemSerialUnits(): HasMany
    {
        return $this->hasMany(OrderItemSerialUnit::class, 'serial_unit_id');
    }

    /**
     * Lấy mã serial chính
     */
    public function getPrimaryCodeAttribute(): ?string
    {
        $primary = $this->unitIdentifiers()
            ->where('is_primary', true)
            ->with('identifier')
            ->first();

        return $primary?->identifier?->code;
    }

    /**
     * Lấy kho hiện tại dựa trên event line cuối cùng
     */
    public function getCurrentWarehouseAttribute(): ?int
    {
        $lastEvent = $this->eventLines()
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastEvent?->to_warehouse_id;
    }

    /**
     * Scope: Lọc theo sản phẩm
     */
    public function scopeOfProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Lọc theo kho hiện tại
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->whereHas('eventLines', function ($q) use ($warehouseId) {
            $q->where('to_warehouse_id', $warehouseId)
                ->whereRaw('crm_serial_event_lines.id = (
                  SELECT MAX(sel2.id) FROM crm_serial_event_lines sel2
                  WHERE sel2.serial_unit_id = crm_serial_units.id
              )');
        });
    }

    /**
     * Scope: Tìm theo mã serial
     */
    public function scopeByCode($query, string $code)
    {
        return $query->whereHas('identifiers', function ($q) use ($code) {
            $q->where('code', $code);
        });
    }
}
