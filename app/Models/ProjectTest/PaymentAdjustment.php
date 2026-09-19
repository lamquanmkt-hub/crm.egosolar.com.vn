<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentAdjustment extends Model
{
    protected $table = 'project_test_payment_adjustments';

    protected $guarded = [];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'correct_amount' => 'decimal:2',
        'delta_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function transaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
