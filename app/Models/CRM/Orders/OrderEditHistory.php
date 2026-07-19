<?php

namespace App\Models\CRM\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEditHistory extends Model
{
    protected $table = 'crm_order_edit_histories';

    protected $fillable = [
        'order_id',
        'user_id',
        'user_name',
        'user_role',
        'changes',
        'note',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}