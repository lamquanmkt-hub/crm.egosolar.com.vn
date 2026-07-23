<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestApprovalLog extends Model
{
    protected $fillable = [
        'leave_request_id',
        'action',
        'from_approver_id',
        'to_approver_id',
        'action_by',
        'note',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function fromApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_approver_id');
    }

    public function toApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_approver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
