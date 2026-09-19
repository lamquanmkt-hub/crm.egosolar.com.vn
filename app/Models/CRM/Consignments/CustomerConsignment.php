<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use App\Models\Core\Warehouse;
use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConsignment extends Model
{
    protected $table = 'customer_consignments';

    protected $fillable = [
        'code', 'order_id', 'company_id', 'customer_id', 'sales_user_id',
        'consignment_warehouse_id', 'status', 'consigned_at', 'expires_at',
        'storage_location', 'terms_note', 'receiver_name', 'receiver_phone',
        'shipping_address', 'created_by', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at', 'approval_note', 'rejected_by',
        'rejected_at', 'reject_reason', 'revision_requested_by',
        'revision_requested_at', 'revision_reason', 'warehouse_confirmed_by',
        'warehouse_confirmed_at', 'warehouse_issue_date', 'warehouse_issue_note',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'consigned_at' => 'date',
        'expires_at' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'warehouse_confirmed_at' => 'datetime',
        'warehouse_issue_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_PENDING_APPROVAL => 'Chờ Admin/Giám đốc duyệt',
        self::STATUS_REVISION_REQUESTED => 'Yêu cầu chỉnh sửa',
        self::STATUS_APPROVED => 'Đã duyệt - Chờ Kho xuất',
        self::STATUS_ACTIVE => 'Đã xuất ký gửi',
        self::STATUS_COMPLETED => 'Đã hoàn tất',
        self::STATUS_REJECTED => 'Đã từ chối',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function consignmentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'consignment_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function revisionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_requested_by');
    }

    public function warehouseConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerConsignmentItem::class, 'consignment_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(CustomerConsignmentRelease::class, 'consignment_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerConsignmentActivity::class, 'consignment_id')->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function getIssuedQuantityAttribute(): int
    {
        return (int) $this->items->sum('issued_quantity');
    }

    public function getTotalValueAttribute(): float
    {
        return (float) $this->items->sum(
            fn (CustomerConsignmentItem $item) => (int) $item->quantity * (float) $item->unit_price
        );
    }
}
