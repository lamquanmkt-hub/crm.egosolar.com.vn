<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use App\Models\Inventory\Serial\SerialUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsignmentSerial extends Model
{
    protected $table = 'customer_consignment_serials';

    protected $fillable = ['consignment_item_id', 'serial_unit_id', 'status', 'released_at'];

    protected $casts = ['released_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignmentItem::class, 'consignment_item_id');
    }

    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    public function getCodeAttribute(): string
    {
        return (string) ($this->serialUnit?->primary_code ?: ('SN#'.$this->serial_unit_id));
    }
}
