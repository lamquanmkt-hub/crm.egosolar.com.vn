<?php

declare(strict_types=1);

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftReceiptItem extends Model
{
    protected $table = 'hr_gift_receipt_items';

    protected $fillable = ['receipt_id', 'gift_id', 'quantity', 'unit_cost', 'line_total', 'note'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GiftReceipt::class, 'receipt_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }
}
