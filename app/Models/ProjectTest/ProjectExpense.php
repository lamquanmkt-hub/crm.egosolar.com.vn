<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    protected $table = 'project_test_expenses';

    protected $guarded = [];

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
