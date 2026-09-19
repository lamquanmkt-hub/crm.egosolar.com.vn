<?php

namespace App\Models;

use App\Models\Core\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarWarrantyStockMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'movement_code', 'company_id', 'warranty_claim_id', 'site_id',
        'movement_type', 'status', 'warehouse_id', 'product_id',
        'serial_unit_id', 'serial_code', 'related_serial_unit_id',
        'related_serial_code', 'quantity', 'requested_by', 'approved_by',
        'completed_by', 'requested_at', 'approved_at', 'completed_at', 'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const TYPES = [
        'warranty_out' => 'Xuất đổi bảo hành',
        'faulty_return' => 'Thu hồi hàng lỗi',
        'supplier_send' => 'Gửi nhà cung cấp',
        'replacement_receive' => 'Nhận hàng thay thế',
    ];

    public const STATUSES = [
        'pending' => 'Chờ xử lý',
        'approved' => 'Đã duyệt',
        'completed' => 'Đã hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(SolarWarrantyClaim::class, 'warranty_claim_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id')->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
