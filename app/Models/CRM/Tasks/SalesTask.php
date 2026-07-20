<?php

namespace App\Models\CRM\Tasks;

use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Leads\Lead;
use App\Models\CRM\Orders\Order;
use App\Models\Tasks\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTask extends Model
{
    use HasFactory;

    protected $table = 'crm_sales_tasks';

    protected $fillable = [
        'customer_id',
        'lead_id',
        'order_id',
        'title',
        'description',
        'task_type',
        'priority',
        'due_date',
        'status',
        'assigned_to',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Khách hàng
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Lead
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // Đơn hàng
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Người được giao
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Người tạo
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Comments
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id');
    }
}
