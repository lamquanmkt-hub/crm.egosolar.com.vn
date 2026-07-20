<?php

namespace App\Models\Inventory\Serial;

use App\Models\Core\Warehouse;
use App\Models\Inventory\Stock\InventoryEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialEventLine extends Model
{
    use HasFactory;

    protected $table = 'crm_serial_event_lines';

    protected $fillable = [
        'event_id',
        'serial_unit_id',
        'from_warehouse_id',
        'to_warehouse_id',
    ];

    /**
     * Sự kiện kho
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(InventoryEvent::class, 'event_id');
    }

    /**
     * Serial unit được di chuyển
     */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    /**
     * Kho xuất
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * Kho nhập
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Kiểm tra đây là nhập kho
     */
    public function isInbound(): bool
    {
        return $this->to_warehouse_id !== null && $this->from_warehouse_id === null;
    }

    /**
     * Kiểm tra đây là xuất kho
     */
    public function isOutbound(): bool
    {
        return $this->from_warehouse_id !== null && $this->to_warehouse_id === null;
    }

    /**
     * Kiểm tra đây là chuyển kho
     */
    public function isTransfer(): bool
    {
        return $this->from_warehouse_id !== null && $this->to_warehouse_id !== null;
    }

    /**
     * Lấy mô tả hành động
     */
    public function getActionDescriptionAttribute(): string
    {
        if ($this->isInbound()) {
            return "Nhập vào kho {$this->toWarehouse?->name}";
        }
        if ($this->isOutbound()) {
            return "Xuất từ kho {$this->fromWarehouse?->name}";
        }
        if ($this->isTransfer()) {
            return "Chuyển từ {$this->fromWarehouse?->name} đến {$this->toWarehouse?->name}";
        }

        return 'Không xác định';
    }

    /**
     * Scope: Lọc theo event
     */
    public function scopeOfEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope: Lọc theo serial unit
     */
    public function scopeOfSerialUnit($query, int $serialUnitId)
    {
        return $query->where('serial_unit_id', $serialUnitId);
    }

    /**
     * Scope: Lọc theo kho xuất
     */
    public function scopeFromWarehouse($query, int $warehouseId)
    {
        return $query->where('from_warehouse_id', $warehouseId);
    }

    /**
     * Scope: Lọc theo kho nhập
     */
    public function scopeToWarehouse($query, int $warehouseId)
    {
        return $query->where('to_warehouse_id', $warehouseId);
    }

    /**
     * Scope: Chỉ lấy nhập kho
     */
    public function scopeInboundOnly($query)
    {
        return $query->whereNotNull('to_warehouse_id')
            ->whereNull('from_warehouse_id');
    }

    /**
     * Scope: Chỉ lấy xuất kho
     */
    public function scopeOutboundOnly($query)
    {
        return $query->whereNotNull('from_warehouse_id')
            ->whereNull('to_warehouse_id');
    }

    /**
     * Scope: Chỉ lấy chuyển kho
     */
    public function scopeTransferOnly($query)
    {
        return $query->whereNotNull('from_warehouse_id')
            ->whereNotNull('to_warehouse_id');
    }

    /**
     * Scope: Liên quan đến kho (nhập hoặc xuất)
     */
    public function scopeInvolvingWarehouse($query, int $warehouseId)
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('from_warehouse_id', $warehouseId)
                ->orWhere('to_warehouse_id', $warehouseId);
        });
    }
}
