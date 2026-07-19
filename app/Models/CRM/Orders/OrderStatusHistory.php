<?php
namespace App\Models\CRM\Orders;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;
    protected $table = 'crm_order_status_history';
    public $timestamps = false; // table uses custom changed_at timestamp
    // Nếu có column 'changed_at' thay vì 'created_at'
    const CREATED_AT = 'changed_at';
    const UPDATED_AT = null; // ← Không có updated_at
    protected $fillable = [
        'order_id',
        'status_type_id',
        'from_department',
        'to_department',
        'note',
        'changed_by',
        'changed_at',
    ];
    protected $dates = [
        'changed_at',
    ];
    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    public function statusType(): BelongsTo
    {
        return $this->belongsTo(OrderStatusType::class, 'status_type_id');
    }
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
    public function getDepartmentName(): string
    {
        return match($this->to_department) {
            'sales' => 'Sales',
            'ketoan' => 'Kế toán',
            'duyet1' => 'Duyệt cấp 1',
            'duyet2' => 'Giám đốc',
            'kho' => 'Kho',
            'completed' => 'Hoàn tất',
            default => $this->to_department,
        };
    }
}
