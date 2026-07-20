<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * FormRequest validate dữ liệu tạo/cập nhật thương hiệu.
 */
class BrandRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true; // nếu có Policy thì thay bằng check permission/policy
    }

    /**
     * Quy tắc validate dữ liệu thương hiệu.
     */
    public function rules(): array
    {
        $brandId = $this->route('brand')?->id; // route-model binding

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('crm_brands', 'name')->ignore($brandId),
            ],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('crm_brands', 'slug')->ignore($brandId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Sinh slug từ tên nếu chưa nhập và ép kiểu is_active trước khi validate.
     */
    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        $slug = $this->input('slug');

        $this->merge([
            'slug' => filled($slug) ? Str::slug($slug) : (filled($name) ? Str::slug($name) : null),
            'is_active' => (bool) $this->input('is_active', true),
        ]);
    }
}
