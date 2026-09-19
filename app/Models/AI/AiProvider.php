<?php

declare(strict_types=1);

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiProvider extends Model
{
    protected $table = 'ai_providers';

    protected $fillable = [
        'name',
        'provider_type',
        'base_url',
        'model',
        'fast_model',
        'api_key',
        'organization',
        'project',
        'timeout_seconds',
        'max_output_tokens',
        'temperature',
        'is_active',
        'is_default',
        'extra_config',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'extra_config' => 'array',
            'timeout_seconds' => 'integer',
            'max_output_tokens' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'provider_id');
    }

    public function maskedKey(): string
    {
        $key = (string) ($this->api_key ?? '');
        if ($key === '') {
            return 'Chưa thiết lập';
        }

        $tail = mb_substr($key, -4);

        return '••••••••••••'.$tail;
    }

    public static function activeDefault(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
