<?php

namespace App\Models\CRM\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends Model
{
    use HasFactory;

    protected $table = 'crm_order_notifications';

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // -------------------------
    // Relationships
    // -------------------------
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // -------------------------
    // Helpers
    // -------------------------
    // Đánh dấu đã đọc
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    // Kiểm tra unread
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
