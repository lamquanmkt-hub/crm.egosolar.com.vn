<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'opening_balance',
        'current_balance',
        'note',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'cash' => 'Tiền mặt',
            'bank' => 'Ngân hàng',
            'ewallet' => 'Ví điện tử',
            default => 'Khác',
        };
    }
}