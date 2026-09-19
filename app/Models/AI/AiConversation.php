<?php

declare(strict_types=1);

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'provider_id',
        'title',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }
}
