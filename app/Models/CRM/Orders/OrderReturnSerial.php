<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Models\Inventory\Serial\SerialUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnSerial extends Model
{
    protected $table = 'order_return_serials';
    protected $fillable = [
        'order_return_item_id', 'serial_unit_id', 'old_state', 'inspected_state',
        'final_state', 'inspected_by', 'inspected_at', 'note',
    ];
    protected $casts = ['inspected_at' => 'datetime'];

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(OrderReturnItem::class, 'order_return_item_id');
    }

    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }
}
