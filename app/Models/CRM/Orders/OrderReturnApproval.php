<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnApproval extends Model
{
    protected $table = 'order_return_approvals';
    protected $fillable = ['order_return_id', 'level', 'action', 'status', 'approver_id', 'comment'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
