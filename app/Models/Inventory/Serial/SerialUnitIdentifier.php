<?php

namespace App\Models\Inventory\Serial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialUnitIdentifier extends Model
{
    use HasFactory;

    protected $table = 'crm_serial_unit_identifiers';

    protected $fillable = [
        'serial_unit_id',
        'serial_identifier_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Serial unit
     */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    /**
     * Identifier (serial number, IMEI, etc.)
     */
    public function identifier(): BelongsTo
    {
        return $this->belongsTo(SerialIdentifier::class, 'serial_identifier_id');
    }

    /**
     * Scope: Chỉ lấy identifier chính
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: Lọc theo serial unit
     */
    public function scopeOfSerialUnit($query, int $serialUnitId)
    {
        return $query->where('serial_unit_id', $serialUnitId);
    }

    /**
     * Scope: Lọc theo identifier
     */
    public function scopeOfIdentifier($query, int $identifierId)
    {
        return $query->where('serial_identifier_id', $identifierId);
    }

    /**
     * Đặt làm identifier chính
     * Tự động bỏ primary của các identifier khác trong cùng serial unit
     */
    public function setPrimary(): bool
    {
        // Bỏ primary của các identifier khác
        static::where('serial_unit_id', $this->serial_unit_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Đặt cái này làm primary
        return $this->update(['is_primary' => true]);
    }
}
