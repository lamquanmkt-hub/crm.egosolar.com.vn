<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftReceipt extends Model
{
    protected $table = 'hr_gift_receipts';

    protected $fillable = [
        'company_id', 'code', 'receipt_date', 'status', 'supplier_name', 'note',
        'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'posted_at',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GiftReceiptItem::class, 'receipt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
