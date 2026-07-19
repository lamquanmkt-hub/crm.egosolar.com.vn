<?php
declare(strict_types=1);
namespace App\Http\Requests\Push;
use Illuminate\Foundation\Http\FormRequest;
final class SubscribePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
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
