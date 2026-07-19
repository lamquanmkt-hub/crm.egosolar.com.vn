<?php

namespace App\Models\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected $appends = [
        'attachment_url',
        'is_image',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->sender();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function getIsImageAttribute(): bool
    {
        return $this->attachment_mime && strpos($this->attachment_mime, 'image/') === 0;
    }
}
