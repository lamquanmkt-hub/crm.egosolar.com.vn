<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'project_test_payment_transactions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function milestone()
    {
        return $this->belongsTo(PaymentMilestone::class, 'milestone_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
