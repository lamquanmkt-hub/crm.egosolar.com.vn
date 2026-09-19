<?php

declare(strict_types=1);

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftRequestItem extends Model
{
    protected $table = 'hr_gift_request_items';

    protected $fillable = ['gift_request_id', 'gift_id', 'quantity', 'unit_cost_snapshot', 'note'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(GiftRequest::class, 'gift_request_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }
}
