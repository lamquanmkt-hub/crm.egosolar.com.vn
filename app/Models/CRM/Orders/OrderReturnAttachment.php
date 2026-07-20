<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturnAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'order_return_attachments';

    protected $fillable = [
        'order_return_id', 'order_return_item_id', 'category', 'file_path',
        'original_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }
}
