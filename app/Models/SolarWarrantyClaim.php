<?php

namespace App\Models;

use App\Models\CRM\Orders\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarWarrantyClaim extends Model
{
    use SoftDeletes;

    protected $table = 'crm_serial_warranty_claims';

    protected $fillable = [
        'claim_code', 'company_id', 'site_id', 'maintenance_schedule_id',
        'serial_unit_id', 'serial_code', 'customer_id', 'order_id',
        'claim_type', 'priority', 'status', 'approval_status',
        'assigned_to', 'assigned_name', 'received_at', 'resolved_at',
        'issue_description', 'diagnosis', 'proposed_solution', 'resolution',
        'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'approval_note',
        'replacement_serial_unit_id', 'replacement_serial_code',
        'returned_serial_unit_id', 'returned_serial_code', 'returned_at',
        'customer_confirmed_at', 'closed_at', 'closed_by',
        'is_chargeable', 'estimated_cost', 'actual_cost', 'cost',
        'internal_note', 'created_by',
    ];

    protected $casts = [
        'received_at' => 'date',
        'resolved_at' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_chargeable' => 'boolean',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    public const TYPES = [
        'warranty' => 'Bảo hành thiết bị',
        'replacement' => 'Đề xuất đổi hàng bảo hành',
        'incident' => 'Sự cố hệ thống',
        'paid_repair' => 'Sửa chữa tính phí',
        'inspection' => 'Kiểm tra kỹ thuật',
    ];

    public const PRIORITIES = [
        'low' => 'Thấp',
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    public const STATUSES = [
        'received' => 'Mới tiếp nhận',
        'eligibility_check' => 'Kiểm tra điều kiện BH',
        'diagnosing' => 'Đang chẩn đoán',
        'solution_proposed' => 'Đã đề xuất phương án',
        'pending_approval' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'waiting_stock' => 'Chờ kho chuẩn bị',
        'replacing' => 'Đang thay thế / xử lý',
        'waiting_customer' => 'Chờ khách xác nhận',
        'completed' => 'Hoàn thành',
        'rejected' => 'Từ chối bảo hành',
        'cancelled' => 'Đã hủy',
    ];

    public const TRANSITIONS = [
        'received' => ['eligibility_check', 'diagnosing', 'cancelled'],
        'eligibility_check' => ['diagnosing', 'rejected', 'cancelled'],
        'diagnosing' => ['solution_proposed', 'rejected', 'cancelled'],
        'solution_proposed' => ['pending_approval', 'diagnosing', 'cancelled'],
        'pending_approval' => ['approved', 'diagnosing', 'rejected'],
        'approved' => ['waiting_stock', 'replacing', 'cancelled'],
        'waiting_stock' => ['replacing', 'cancelled'],
        'replacing' => ['waiting_customer', 'waiting_stock'],
        'waiting_customer' => ['completed', 'replacing'],
        'completed' => ['replacing'],
        'rejected' => ['diagnosing'],
        'cancelled' => ['received'],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id')->withoutGlobalScopes();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id')->withoutGlobalScopes();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(SolarWarrantyStockMovement::class, 'warranty_claim_id')->latest('id');
    }
}
