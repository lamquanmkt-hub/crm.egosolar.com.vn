<?php

declare(strict_types=1);

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiMessage extends Model
{
    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
