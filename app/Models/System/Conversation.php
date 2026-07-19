<?php

namespace App\Models\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
        'type',
        'name',
        'is_internal',
        'department_id',
        'task_id',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
