<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Models\Concerns\LockedToEgoInternational;
use App\Models\Core\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturn extends Model
{
    use LockedToEgoInternational;
    use SoftDeletes;

    protected $table = 'order_returns';

    protected $fillable = [
        'return_code', 'order_id', 'company_id', 'customer_id', 'receiving_warehouse_id',
        'type', 'reason', 'reason_code', 'reason_detail', 'status',
        'requested_by', 'submitted_by', 'approved_by', 'received_by', 'inspected_by', 'completed_by',
        'submitted_at', 'approved_at', 'received_at', 'inspected_at', 'completed_at',
        'total_return_amount', 'refund_amount', 'restocking_fee', 'shipping_fee',
        'refund_method', 'financial_status', 'inventory_status', 'invoice_adjustment_status',
        'note', 'metadata', 'inventory_posted_at', 'stock_in_reference',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'completed_at' => 'datetime',
        'inventory_posted_at' => 'datetime',
        'total_return_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'restocking_fee' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function receivingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class, 'order_return_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OrderReturnAttachment::class, 'order_return_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(OrderReturnApproval::class, 'order_return_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderReturnStatusHistory::class, 'order_return_id')->latest('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'order_return_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'draft' => 'Nháp',
            'pending_sales_manager' => 'Chờ Sales Manager duyệt',
            'pending_accounting' => 'Chờ kế toán kiểm tra',
            'pending_management' => 'Chờ Ban Giám đốc duyệt',
            'approved_waiting_return' => 'Đã duyệt - chờ hàng về',
            'return_in_transit' => 'Hàng đang vận chuyển về',
            'received' => 'Kho đã nhận',
            'inspecting' => 'Đang kiểm tra',
            'inspected' => 'Đã kiểm tra',
            'stocked_in' => 'Đã xử lý kho',
            'pending_refund' => 'Chờ xử lý tài chính',
            'completed' => 'Hoàn tất',
            'revision_requested' => 'Yêu cầu chỉnh sửa',
            'rejected' => 'Từ chối',
            'cancelled' => 'Đã hủy',
        ][$this->status] ?? $this->status;
    }
}
