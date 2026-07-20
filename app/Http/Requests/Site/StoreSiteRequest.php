<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validate dữ liệu tạo mới công trình.
 */
class StoreSiteRequest extends FormRequest
{
    /**
     * Chỉ cho phép các vai trò nội bộ (sales, kỹ thuật, admin, kế toán, kho...).
     */
    public function authorize(): bool
    {
        return (bool) optional($this->user())->hasAnyRole([
            'sales',
            'ky_thuat',
            'admin',
            'accounting',
            'warehouse',
            'management',
            'manager',
        ]);
    }

    /**
     * Quy tắc validate dữ liệu công trình.
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],

            'address' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],

            'system_kwp' => ['nullable', 'numeric', 'min:0'],
            'system_kw_ac' => ['nullable', 'numeric', 'min:0'],
            'system_type' => ['nullable', 'string', 'max:50'],
            'phase' => ['nullable', 'string', 'max:50'],

            'installed_at' => ['nullable', 'date'],
            'deployment_started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'warranty_to' => ['nullable', 'date'],
            'warranty_reminder_1_at' => ['nullable', 'date'],
            'warranty_reminder_2_at' => ['nullable', 'date'],
            'warranty_reminder_3_at' => ['nullable', 'date'],

            'technician_name' => ['nullable', 'string', 'max:255'],
            'monitoring_link' => ['nullable', 'string', 'max:255'],
            'monitoring_account' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:50'],

            'contract_amount' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost_note' => ['nullable', 'string'],
            'contract_signed_at' => ['nullable', 'date'],
            'finance_note' => ['nullable', 'string'],

            'payment_terms' => ['nullable', 'array'],
            'payment_terms.*.id' => ['nullable', 'integer'],
            'payment_terms.*.name' => ['nullable', 'string', 'max:255'],
            'payment_terms.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms.*.due_date' => ['nullable', 'date'],
            'payment_terms.*.note' => ['nullable', 'string', 'max:1000'],

            'devices' => ['nullable', 'array'],
            'devices.*.type' => ['nullable', 'string', 'max:50'],
            'devices.*.brand' => ['nullable', 'string', 'max:255'],
            'devices.*.model' => ['nullable', 'string', 'max:255'],
            'devices.*.serial' => ['nullable', 'string', 'max:255'],
            'devices.*.power_kw' => ['nullable', 'numeric', 'min:0'],
            'devices.*.capacity_kwh' => ['nullable', 'numeric', 'min:0'],
            'devices.*.qty' => ['nullable', 'integer', 'min:1'],
            'devices.*.warranty_to' => ['nullable', 'date'],

            'planned' => ['nullable', 'array'],
            'planned.*.name' => ['nullable', 'string', 'max:255'],
            'planned.*.unit' => ['nullable', 'string', 'max:50'],
            'planned.*.qty' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên công trình là bắt buộc.',
            'name.max' => 'Tên công trình không được quá :max ký tự.',

            'installed_at.date' => 'Ngày lắp đặt không hợp lệ.',
            'deployment_started_at.date' => 'Ngày bắt đầu triển khai không hợp lệ.',
            'completed_at.date' => 'Ngày hoàn thành không hợp lệ.',
            'warranty_to.date' => 'Ngày bảo hành không hợp lệ.',
            'warranty_reminder_1_at.date' => 'Mốc bảo hành 1 không hợp lệ.',
            'warranty_reminder_2_at.date' => 'Mốc bảo hành 2 không hợp lệ.',
            'warranty_reminder_3_at.date' => 'Mốc bảo hành 3 không hợp lệ.',

            'contract_amount.numeric' => 'Giá trị hợp đồng phải là số.',
            'contract_amount.min' => 'Giá trị hợp đồng không được âm.',
            'contract_signed_at.date' => 'Ngày ký hợp đồng không hợp lệ.',

            'payment_terms.array' => 'Danh sách đợt thanh toán không hợp lệ.',
            'payment_terms.*.percent.numeric' => 'Phần trăm thanh toán phải là số.',
            'payment_terms.*.percent.max' => 'Phần trăm thanh toán không được vượt quá 100%.',
            'payment_terms.*.amount.numeric' => 'Số tiền đợt thanh toán phải là số.',
            'payment_terms.*.due_date.date' => 'Ngày dự kiến thanh toán không hợp lệ.',
        ];
    }

    /**
     * Trim chuỗi và chuẩn hoá số điện thoại, dữ liệu đầu vào trước khi validate.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) ($this->name ?? '')),
            'address' => $this->address !== null ? trim((string) $this->address) : null,
            'contact_name' => $this->contact_name !== null ? trim((string) $this->contact_name) : null,
            'contact_phone' => $this->contact_phone !== null
                ? preg_replace('/\s+/', '', (string) $this->contact_phone)
                : null,
            'note' => $this->note !== null ? trim((string) $this->note) : null,
            'finance_note' => $this->finance_note !== null ? trim((string) $this->finance_note) : null,
        ]);
    }
}
