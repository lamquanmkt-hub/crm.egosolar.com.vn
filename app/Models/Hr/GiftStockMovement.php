<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftStockMovement extends Model
{
    protected $table = 'hr_gift_stock_movements';

    protected $fillable = [
        'company_id', 'gift_id', 'movement_type', 'quantity', 'balance_after',
        'source_type', 'source_id', 'source_item_id', 'source_code', 'description',
        'created_by', 'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'occurred_at' => 'datetime',
    ];

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
