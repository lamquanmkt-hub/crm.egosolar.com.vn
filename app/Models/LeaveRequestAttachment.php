<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestAttachment extends Model
{
    protected $fillable = [
        'leave_request_id',
        'uploaded_by',
        'disk',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
