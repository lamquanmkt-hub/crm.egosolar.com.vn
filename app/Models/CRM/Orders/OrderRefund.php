<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRefund extends Model
{
    use SoftDeletes;
    protected $table = 'order_refunds';
    protected $fillable = [
        'refund_code', 'order_return_id', 'order_id', 'amount', 'method', 'status',
        'bank_information', 'requested_by', 'approved_by', 'processed_by',
        'requested_at', 'approved_at', 'processed_at', 'attachment_path', 'note',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'bank_information' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }
}
