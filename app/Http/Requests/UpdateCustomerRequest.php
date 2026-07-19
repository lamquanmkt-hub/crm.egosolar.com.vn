<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->hasAnyRole(['admin', 'marketing'])) {
            return true;
        }

        if ($user->hasRole('Sales')) {
            return $user->id === optional($this->route('customer'))->owner_id
                || $user->id === optional($this->route('customer_model'))->owner_id;
        }

        return false;
    }

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

    public function rules(): array
    {
        return [
            'name'                 => 'sometimes|required|string|max:255',
            'phone'                => ['required', 'regex:/^0(3|5|7|8|9)\d{8}$/'],

            'facebook_name'        => 'nullable|string|max:255',
            'facebook_link'        => 'nullable|url|max:511',
            'zalo_id'              => 'nullable|string|max:255',
            'customer_type_id'     => 'nullable|exists:crm_customer_types,id',
            'region_id'            => 'nullable|exists:crm_regions,id',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string',
            'nickname'             => 'nullable|string|max:255',
            'group_note'           => 'nullable|string|max:255',
            'owner_id'             => 'nullable|exists:users,id',
            'customer_status'      => 'nullable|in:lead,member,retail',
            'is_potential'         => 'nullable|boolean',

            // billing info
            'billing_company_name' => 'nullable|string|max:255',
            'billing_tax_code'     => 'nullable|string|max:50',
            'billing_address'      => 'nullable|string|max:500',
            'billing_email'        => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return array_merge(
            (new StoreCustomerRequest())->messages(),
            [
                'phone.required' => 'Số điện thoại là bắt buộc.',
                'phone.regex'    => 'Số điện thoại phải đủ 10 số và bắt đầu bằng 03/05/07/08/09.',
            ]
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->ajax() || $this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}