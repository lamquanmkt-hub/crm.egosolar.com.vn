<?php

declare(strict_types=1);

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validate dữ liệu đăng ký nhận thông báo đẩy (Web Push).
 */
final class SubscribePushSubscriptionRequest extends FormRequest
{
    /**
     * Chỉ cho phép người dùng đã đăng nhập.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Quy tắc validate dữ liệu đăng ký thông báo đẩy.
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048'], // endpoint thường dài
            'publicKey' => ['required', 'string', 'max:1024'],
            'authToken' => ['required', 'string', 'max:1024'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ];
    }
}
