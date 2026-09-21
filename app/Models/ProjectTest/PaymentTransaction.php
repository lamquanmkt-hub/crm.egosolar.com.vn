<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'project_test_payment_transactions';

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Nguồn: migration `2026_07_30_112500_create_project_payment_tracking_tables`
     * + các điểm create() trong ProjectPaymentController.
     */
    protected $fillable = [
        'project_id',
        'milestone_id',
        'transaction_code',
        'paid_at',
        'amount',
        'payment_method',
        'receiving_account',
        'reference_no',
        'payer_name',
        'proof_path',
        'note',
        'status',
        'recorded_by',
        'confirmed_by',
        'confirmed_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

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
