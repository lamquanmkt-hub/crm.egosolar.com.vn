<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Services\Finance\SupplierDebtService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho tạo/cập nhật công nợ nhà cung cấp.
 *
 * Chuẩn hóa số tiền nhập tay ("15.000.000" → "15000000") trước khi validate,
 * dùng chung cho cả store và update (rule giống hệt nhau).
 */
class SupplierDebtRequest extends FormRequest
{
    /**
     * Authorize: quyền được kiểm ở middleware/controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hóa tiền trước khi chạy rule numeric.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('total_amount')) {
            $this->merge([
                'total_amount' => app(SupplierDebtService::class)
                    ->normalizeMoneyInput($this->input('total_amount')),
            ]);
        }
    }

    /**
     * Quy tắc validate công nợ NCC.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', Rule::in(app(SupplierDebtService::class)->companyOptions())],
            'document_no' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'debt_month' => ['required', 'date_format:Y-m'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'bank_info' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ];
    }
}
