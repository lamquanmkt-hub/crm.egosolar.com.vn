<?php

namespace App\Models\Inventory\Serial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerialIdentifier extends Model
{
    use HasFactory;

    protected $table = 'crm_serial_identifiers';

    protected $fillable = [
        'type',
        'code',
    ];

    /**
     * Các loại identifier phổ biến
     */
    public const TYPE_SERIAL = 'serial';

    public const TYPE_IMEI = 'imei';

    public const TYPE_MAC = 'mac';

    public const TYPE_BARCODE = 'barcode';

    public const TYPE_QR = 'qr';

    public const TYPES = [
        self::TYPE_SERIAL => 'Serial Number',
        self::TYPE_IMEI => 'IMEI',
        self::TYPE_MAC => 'MAC Address',
        self::TYPE_BARCODE => 'Barcode',
        self::TYPE_QR => 'QR Code',
    ];

    /**
     * Các serial unit sử dụng identifier này
     */
    public function serialUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            SerialUnit::class,
            'crm_serial_unit_identifiers',
            'serial_identifier_id',
            'serial_unit_id'
        )->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Chi tiết liên kết với serial units
     */
    public function unitIdentifiers(): HasMany
    {
        return $this->hasMany(SerialUnitIdentifier::class, 'serial_identifier_id');
    }

    /**
     * Lấy tên loại identifier
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Scope: Lọc theo loại
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Tìm theo mã
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope: Tìm kiếm mã (like)
     */
    public function scopeSearchCode($query, string $search)
    {
        return $query->where('code', 'like', "%{$search}%");
    }

    /**
     * Kiểm tra mã đã tồn tại chưa
     */
    public static function codeExists(string $code, ?string $type = null): bool
    {
        $query = static::where('code', $code);
        if ($type) {
            $query->where('type', $type);
        }

        return $query->exists();
    }
}
