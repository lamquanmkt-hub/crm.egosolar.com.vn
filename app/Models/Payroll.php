<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'user_id',
        'payroll_month',
        'standard_days',
        'working_days',
        'basic_salary',
        'allowance',
        'commission',
        'bonus',
        'advance',
        'other_deduction',
        'net_salary',
        'note',
        'created_by',
    ];

    protected $casts = [
        'standard_days' => 'decimal:2',
        'working_days' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'commission' => 'decimal:2',
        'bonus' => 'decimal:2',
        'advance' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
