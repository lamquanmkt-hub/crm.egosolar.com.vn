<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentFeedback extends Model
{
    protected $table = 'content_feedbacks';

    protected $fillable = [
        'content_calendar_id',
        'user_id',
        'message',
        'image_path',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Content\ContentCalendar::class, 'content_calendar_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
