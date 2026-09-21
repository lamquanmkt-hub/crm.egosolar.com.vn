<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentMilestone extends Model
{
    protected $table = 'project_test_payment_milestones';

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Danh sách lấy từ migration `2026_07_30_112500_create_project_payment_tracking_tables`
     * và đối chiếu với toàn bộ điểm gọi create()/fill() trong
     * ProjectPaymentController. `id` và timestamps cố tình không nằm trong danh sách.
     */
    protected $fillable = [
        'project_id',
        'sequence',
        'title',
        'percentage',
        'amount',
        'due_date',
        'condition_text',
        'note',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'milestone_id')->latest('paid_at')->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getConfirmedAmountAttribute(): float
    {
        if ($this->relationLoaded('transactions')) {
            return (float) $this->transactions->where('status', 'confirmed')->sum('amount');
        }

        return (float) $this->transactions()->where('status', 'confirmed')->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->confirmed_amount);
    }
}
