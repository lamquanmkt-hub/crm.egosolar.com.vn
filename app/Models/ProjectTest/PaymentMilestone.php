<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentMilestone extends Model
{
    protected $table = 'project_test_payment_milestones';

    protected $guarded = [];

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
