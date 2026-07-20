<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validate dữ liệu tạo/cập nhật kho.
 */
class WarehouseRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validate dữ liệu kho.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],

            // ✅ kho thuộc nhiều công ty
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
        ];
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [
            'company_ids.required' => 'Vui lòng chọn ít nhất 1 công ty.',
            'company_ids.array' => 'Dữ liệu công ty không hợp lệ.',
        ];
    }
}
