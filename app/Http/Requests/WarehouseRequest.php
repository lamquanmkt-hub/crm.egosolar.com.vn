<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'location' => ['nullable','string','max:255'],

            // ✅ kho thuộc nhiều công ty
            'company_ids' => ['required','array','min:1'],
            'company_ids.*' => ['integer','exists:companies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_ids.required' => 'Vui lòng chọn ít nhất 1 công ty.',
            'company_ids.array' => 'Dữ liệu công ty không hợp lệ.',
        ];
    }
}
