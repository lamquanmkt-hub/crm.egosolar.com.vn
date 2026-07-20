<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Services\Finance\SupplierDebtService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request cho tạo/cập nhật một đợt thanh toán của công nợ nhà cung cấp.
 *
 * Khi tạo mới, số đợt (payment_round) có thể bỏ trống để hệ thống tự đánh số;
 * khi cập nhật thì bắt buộc — điều khiển bằng route hiện tại (PUT = cập nhật).
 */
class SupplierDebtPaymentRoundRequest extends FormRequest
{
    /** Các định dạng file đính kèm được phép. */
    private const ALLOWED_ATTACHMENT_MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx';

    /** Dung lượng tối đa mỗi file đính kèm (KB). */
    private const MAX_ATTACHMENT_SIZE_KB = 10240;

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
        if ($this->has('amount')) {
            $this->merge([
                'amount' => app(SupplierDebtService::class)
                    ->normalizeMoneyInput($this->input('amount')),
            ]);
        }
    }

    /**
     * Quy tắc validate đợt thanh toán.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_round' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'max:'.self::MAX_ATTACHMENT_SIZE_KB,
                'mimes:'.self::ALLOWED_ATTACHMENT_MIMES,
            ],
        ];
    }
}
