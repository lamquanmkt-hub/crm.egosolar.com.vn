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


        ];
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [];
    }
}
