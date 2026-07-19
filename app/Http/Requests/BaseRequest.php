<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base Form Request với các method helper chung
 * Giúp tuân thủ DRY principle
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lấy danh sách rule validation
     * Phải override method này ở class con
     */
    abstract public function rules(): array;

    /**
     * Lấy custom messages
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Lấy custom attributes
     */
    public function attributes(): array
    {
        return [];
    }
}
