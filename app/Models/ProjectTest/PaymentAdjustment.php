<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentAdjustment extends Model
{
    protected $table = 'project_test_payment_adjustments';

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Nguồn: migration `2026_07_30_163500_create_project_payment_adjustments_table`
     * + điểm create() duy nhất trong ProjectPaymentController.
     */
    protected $fillable = [
        'project_id',
        'transaction_id',
        'adjustment_code',
        'original_amount',
        'correct_amount',
        'delta_amount',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

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
