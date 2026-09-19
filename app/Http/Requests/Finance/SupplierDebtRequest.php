<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Contracts\Services\SupplierDebtServiceInterface;
use Illuminate\Foundation\Http\FormRequest;

class SupplierDebtRequest extends FormRequest
{
    /**
     * Quyền truy cập đã được kiểm soát tại route:
     * admin | accounting | warehouse | kho
     *
     * Không chặn lại role Kho ở FormRequest.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Chuẩn hóa dữ liệu trước khi validation.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('supplier_name')) {
            $payload['supplier_name'] = trim(
                (string) $this->input('supplier_name')
            );
        }

        if ($this->has('company_name')) {
            $payload['company_name'] = trim(
                (string) $this->input('company_name')
            );
        }

        if ($this->has('document_no')) {
            $value = trim(
                (string) $this->input('document_no')
            );

            $payload['document_no'] =
                $value !== '' ? $value : null;
        }

        /*
        |--------------------------------------------------------------------------
        | Tiền tệ
        |--------------------------------------------------------------------------
        | Form có thể gửi:
        | 2.608.200
        | 2,608,200
        | 2608200
        |
        | Dùng đúng bộ normalize hiện có của SupplierDebtService.
        */
        if ($this->has('total_amount')) {
            try {
                $payload['total_amount'] =
                    app(SupplierDebtServiceInterface::class)
                        ->normalizeMoneyInput(
                            $this->input('total_amount')
                        );
            } catch (\Throwable $e) {
                $value = trim(
                    (string) $this->input('total_amount')
                );

                $value = preg_replace(
                    '/[\s\x{00A0}]+/u',
                    '',
                    $value
                ) ?? '';

                $value = preg_replace(
                    '/[^0-9,.-]/u',
                    '',
                    $value
                ) ?? '';

                /*
                 * Fallback cho format tiền VN:
                 * 2.608.200 => 2608200
                 */
                if (
                    substr_count($value, '.') >= 1 &&
                    ! str_contains($value, ',')
                ) {
                    $parts = explode('.', $value);

                    $last = end($parts);

                    if (
                        count($parts) > 1 &&
                        strlen((string) $last) === 3
                    ) {
                        $value = str_replace('.', '', $value);
                    }
                }

                $payload['total_amount'] =
                    str_replace(',', '.', $value);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | debt_month
        |--------------------------------------------------------------------------
        | Form chuẩn gửi YYYY-MM.
        | Nếu dữ liệu cũ gửi YYYY-MM-01 thì tự đưa về YYYY-MM.
        */
        if ($this->has('debt_month')) {
            $month = trim(
                (string) $this->input('debt_month')
            );

            if (
                preg_match(
                    '/^(\d{4}-\d{2})-\d{2}$/',
                    $month,
                    $matches
                )
            ) {
                $month = $matches[1];
            }

            $payload['debt_month'] = $month;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * Validation cho tạo / sửa công nợ NCC.
     */
    public function rules(): array
    {
        return [
            'supplier_name' => [
                'required',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | QUAN TRỌNG
            |--------------------------------------------------------------------------
            | Không dùng Rule::in danh sách công ty cứng.
            |
            | Form đã là select lấy từ companyOptions của hệ thống.
            | Trước đây warehouse/kho có thể bị lệch company context,
            | dẫn tới đúng tên công ty trên form nhưng vẫn nhận validation.in.
            */
            'company_name' => [
                'required',
                'string',
                'max:255',
            ],

            'document_no' => [
                'nullable',
                'string',
                'max:100',
            ],

            'document_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'debt_month' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],

            'total_amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'bank_info' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'note' => [
                'nullable',
                'string',
                'max:20000',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],

            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,zip,rar',
            ],
        ];
    }

    /**
     * Không để giao diện hiện các key như:
     * validation.in
     * validation.required
     */
    public function messages(): array
    {
        return [
            'supplier_name.required' =>
                'Vui lòng nhập tên nhà cung cấp.',

            'supplier_name.max' =>
                'Tên nhà cung cấp tối đa 255 ký tự.',

            'company_name.required' =>
                'Vui lòng chọn công ty.',

            'company_name.string' =>
                'Công ty được chọn không hợp lệ.',

            'company_name.max' =>
                'Tên công ty tối đa 255 ký tự.',

            'document_no.max' =>
                'Số chứng từ tối đa 100 ký tự.',

            'document_date.date_format' =>
                'Ngày chứng từ không hợp lệ.',

            'debt_month.required' =>
                'Vui lòng chọn tháng công nợ.',

            'debt_month.regex' =>
                'Tháng công nợ không hợp lệ.',

            'total_amount.required' =>
                'Vui lòng nhập tổng tiền công nợ.',

            'total_amount.numeric' =>
                'Tổng tiền công nợ phải là số.',

            'total_amount.gt' =>
                'Tổng tiền công nợ phải lớn hơn 0.',

            'bank_info.max' =>
                'Thông tin ngân hàng quá dài.',

            'note.max' =>
                'Ghi chú quá dài.',

            'attachments.array' =>
                'Danh sách tệp đính kèm không hợp lệ.',

            'attachments.*.file' =>
                'Tệp đính kèm không hợp lệ.',

            'attachments.*.max' =>
                'Mỗi tệp đính kèm tối đa 20MB.',

            'attachments.*.mimes' =>
                'Định dạng tệp không được hỗ trợ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_name' => 'nhà cung cấp',
            'company_name' => 'công ty',
            'document_no' => 'số chứng từ',
            'document_date' => 'ngày chứng từ',
            'debt_month' => 'tháng công nợ',
            'total_amount' => 'tổng tiền',
            'bank_info' => 'thông tin ngân hàng',
            'note' => 'ghi chú',
            'attachments' => 'tệp đính kèm',
        ];
    }
}
