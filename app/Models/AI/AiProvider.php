<?php

declare(strict_types=1);

namespace App\Models\AI;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
        if (! $this->hasReadableKey()) {
            return 'Không đọc được key cũ — vui lòng nhập lại API key';
        }

        $key = (string) ($this->api_key ?? '');
        if ($key === '') {
            return 'Chưa thiết lập';
        }

        $tail = mb_substr($key, -4);

        return '••••••••••••'.$tail;
    }

    /**
     * Key được mã hoá theo APP_KEY. Đổi APP_KEY (hoặc DB chép từ server khác) thì key cũ
     * không giải mã được — trả false thay vì để lỗi làm sập trang cài đặt.
     */
    public function hasReadableKey(): bool
    {
        if (($this->attributes['api_key'] ?? null) === null || $this->attributes['api_key'] === '') {
            return true;
        }

        try {
            $this->api_key;

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Ghi key mới mà KHÔNG giải mã key cũ (Eloquent sẽ giải mã giá trị cũ để so sánh khi save(),
     * nên key cũ hỏng sẽ làm lỗi). Mã hoá bằng cùng cơ chế với cast 'encrypted'.
     */
    public function replaceApiKey(string $key): void
    {
        $cipher = Crypt::encryptString($key);

        self::query()->whereKey($this->getKey())->toBase()->update([
            'api_key' => $cipher,
            'updated_at' => now(),
        ]);

        $this->attributes['api_key'] = $cipher;
        $this->syncOriginalAttribute('api_key');
    }

    public static function activeDefault(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
