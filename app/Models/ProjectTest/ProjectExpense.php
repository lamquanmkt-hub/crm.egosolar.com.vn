<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    protected $table = 'project_test_expenses';

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Nguồn: migration `2026_08_13_134500_create_project_expenses_and_repair_finance`
     * + điểm create() duy nhất trong ProjectExpenseController.
     */
    protected $fillable = [
        'project_id',
        'expense_code',
        'expense_date',
        'category',
        'description',
        'amount',
        'payee_name',
        'note',
        'proof_path',
        'status',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
