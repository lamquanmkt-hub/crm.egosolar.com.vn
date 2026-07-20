<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest validate dữ liệu tạo/cập nhật phương thức thanh toán.
 */
class PaymentMethodRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true; // Check permission if needed
    }

    /**
     * Quy tắc validate dữ liệu phương thức thanh toán.
     */
    public function rules(): array
    {
        $id = $this->route('id'); // Lấy ID từ route nếu là update

        return [
            'method_name' => [
                'required', 'string', 'max:100',
                Rule::unique('crm_payment_methods', 'method_name')->ignore($id),
            ],
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('crm_payment_methods', 'code')->ignore($id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [
            'method_name.required' => 'Tên phương thức không được để trống.',
            'code.unique' => 'Mã phương thức đã tồn tại.',
        ];
    }
}
