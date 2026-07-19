<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PriceTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // nếu có policy/permission thì thay ở đây
    }
    public function rules(): array
    {
        $tierId = $this->route('price_tier')?->id;
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('crm_price_tiers', 'code')->ignore($tierId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge([
            'priority' => (int) $this->input('priority', 0),
            'is_active' => (bool) $this->input('is_active', true),
        ]);
    }
}
