<?php
declare(strict_types=1);
namespace App\Http\Requests\Push;
use Illuminate\Foundation\Http\FormRequest;
final class UnsubscribePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048'],
        ];
    }
}
