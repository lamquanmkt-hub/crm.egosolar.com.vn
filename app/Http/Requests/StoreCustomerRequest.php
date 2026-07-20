<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * FormRequest validate dữ liệu tạo mới khách hàng.
 */
class StoreCustomerRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hoá dữ liệu trước khi validate
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = preg_replace('/\D+/', '', (string) $this->input('phone'));
            $this->merge(['phone' => substr($phone, 0, 10)]);
        }

        $this->merge([
            'is_potential' => $this->boolean('is_potential'),
        ]);
    }

    /**
     * Quy tắc validate dữ liệu khách hàng.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^0(3|5|7|8|9)\d{8}$/'],

            'facebook_name' => 'nullable|string|max:255',
            'facebook_link' => 'nullable|url|max:511',
            'zalo_id' => 'nullable|string|max:255',

            'customer_type_id' => 'nullable|exists:crm_customer_types,id',
            'region_id' => 'nullable|exists:crm_regions,id',

            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'nickname' => 'nullable|string|max:255',
            'group_note' => 'nullable|string|max:255',
            'owner_id' => 'nullable|exists:users,id',
            'customer_status' => 'nullable|in:lead,member,retail',
            'is_potential' => 'nullable|boolean',

            // billing info
            'billing_company_name' => 'nullable|string|max:255',
            'billing_tax_code' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string|max:500',
            'billing_email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Kiểm tra trùng số điện thoại với khách hàng hiện có sau khi validate.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $phone = (string) $this->input('phone');
            if (empty($phone)) {
                return;
            }

            $existing = \App\Models\CRM\Customers\Customer::query()
                ->with('assignedUser')
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                $ownerName = 'Chưa phân công';

                if ($existing->assignedUser) {
                    $ownerName = $existing->assignedUser->name
                        ?? $existing->assignedUser->email
                        ?? $ownerName;
                }

                $existingName = $existing->name ?? 'Khách hàng';

                $validator->errors()->add(
                    'phone',
                    "SĐT {$phone} đã tồn tại: {$existingName}. Phụ trách: {$ownerName}."
                );
            }
        });
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên khách hàng là bắt buộc.',
            'name.max' => 'Tên khách hàng không được vượt quá 255 ký tự.',

            'phone.required' => 'Số điện thoại là bắt buộc.',
            'phone.regex' => 'Số điện thoại phải đủ 10 số và bắt đầu bằng 03/05/07/08/09.',

            'facebook_name.max' => 'Tên Facebook không được vượt quá 255 ký tự.',
            'facebook_link.url' => 'Link Facebook không hợp lệ.',
            'facebook_link.max' => 'Link Facebook không được vượt quá 511 ký tự.',
            'customer_type_id.exists' => 'Loại khách không tồn tại.',
            'region_id.exists' => 'Khu vực không tồn tại.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'nickname.max' => 'Biệt danh không được vượt quá 255 ký tự.',
            'group_note.max' => 'Ghi chú nhóm không được vượt quá 255 ký tự.',
            'owner_id.exists' => 'Người phụ trách không tồn tại.',
            'customer_status.in' => 'Trạng thái khách hàng không hợp lệ.',

            'billing_company_name.max' => 'Tên công ty / cá nhân xuất hoá đơn không được vượt quá 255 ký tự.',
            'billing_tax_code.max' => 'Mã số thuế không được vượt quá 50 ký tự.',
            'billing_address.max' => 'Địa chỉ xuất hoá đơn không được vượt quá 500 ký tự.',
            'billing_email.email' => 'Email nhận hoá đơn không hợp lệ.',
            'billing_email.max' => 'Email nhận hoá đơn không được vượt quá 255 ký tự.',
        ];
    }

    /**
     * Trả về JSON 422 kèm danh sách lỗi khi validate thất bại với request AJAX.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->ajax() || $this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
