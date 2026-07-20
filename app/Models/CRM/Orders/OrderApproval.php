<?php

namespace App\Models\CRM\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderApproval extends Model
{
    use HasFactory;

    protected $table = 'crm_order_approvals';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'level',
        'status',
        'approved_by',
        'approved_at',
        'responded_at',
        'rejection_reason',
        'debt_checked',
        'debt_note',
    ];

    // THÊM ĐOẠN NÀY
    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getLevelName(): string
    {
        return match ($this->level) {
            'sales' => 'Sales',
            'sales_manager' => 'Sales Manager',
            'accounting' => 'Kế toán',
            'management' => 'Ban Giám đốc',
            'warehouse' => 'Kho',
            default => (string) $this->level,
        };
    }
}
