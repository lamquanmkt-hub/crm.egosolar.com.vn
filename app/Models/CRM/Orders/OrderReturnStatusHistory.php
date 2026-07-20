<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'order_return_status_histories';

    protected $fillable = [
        'order_return_id', 'from_status', 'to_status', 'action', 'reason',
        'note', 'changed_by', 'metadata', 'created_at',
    ];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
