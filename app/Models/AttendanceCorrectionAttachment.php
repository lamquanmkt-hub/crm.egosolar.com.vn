<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionAttachment extends Model
{
    protected $fillable = [
        'attendance_correction_request_id',
        'uploaded_by',
        'disk',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class, 'attendance_correction_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
