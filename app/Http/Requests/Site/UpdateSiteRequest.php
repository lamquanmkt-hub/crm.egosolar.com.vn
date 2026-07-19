<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
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

    public function rules(): array
    {
        $siteId = $this->route('id');

        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'name')->ignore($siteId),
            ],

            'status' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\+\s\(\)]+$/'],
            'note' => ['nullable', 'string', 'max:2000'],

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
            'finance_note' => ['nullable', 'string', 'max:5000'],

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
            'planned.*.qty'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên công trình là bắt buộc.',
            'name.max' => 'Tên công trình không được quá :max ký tự.',
            'name.unique' => 'Tên công trình này đã tồn tại.',

            'address.max' => 'Địa chỉ không được quá :max ký tự.',
            'contact_name.max' => 'Tên người liên hệ không được quá :max ký tự.',
            'contact_phone.max' => 'Số điện thoại không được quá :max ký tự.',
            'contact_phone.regex' => 'Số điện thoại không hợp lệ.',
            'note.max' => 'Ghi chú không được quá :max ký tự.',

            'system_kwp.numeric' => 'Công suất DC phải là số.',
            'system_kw_ac.numeric' => 'Công suất AC phải là số.',

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
            'finance_note.max' => 'Ghi chú tài chính không được quá :max ký tự.',

            'payment_terms.array' => 'Danh sách đợt thanh toán không hợp lệ.',
            'payment_terms.*.percent.numeric' => 'Phần trăm thanh toán phải là số.',
            'payment_terms.*.percent.max' => 'Phần trăm thanh toán không được vượt quá 100%.',
            'payment_terms.*.amount.numeric' => 'Số tiền đợt thanh toán phải là số.',
            'payment_terms.*.due_date.date' => 'Ngày dự kiến thanh toán không hợp lệ.',

            'devices.array' => 'Danh sách thiết bị không hợp lệ.',
            'devices.*.qty.min' => 'Số lượng thiết bị tối thiểu là 1.',
            'devices.*.power_kw.numeric' => 'kW thiết bị phải là số.',
            'devices.*.capacity_kwh.numeric' => 'kWh thiết bị phải là số.',
            'devices.*.warranty_to.date' => 'Ngày bảo hành thiết bị không hợp lệ.',

            'planned.array' => 'Danh sách vật tư dự kiến không hợp lệ.',
            'planned.*.qty.numeric' => 'Số lượng vật tư phải là số.',
            'planned.*.qty.min' => 'Số lượng vật tư không được âm.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên công trình',
            'status' => 'trạng thái',
            'address' => 'địa chỉ',
            'contact_name' => 'người liên hệ',
            'contact_phone' => 'số điện thoại',
            'note' => 'ghi chú',

            'system_kwp' => 'công suất DC',
            'system_kw_ac' => 'công suất AC',
            'system_type' => 'loại hệ',
            'phase' => 'điện áp',
            'installed_at' => 'ngày lắp đặt',
            'deployment_started_at' => 'ngày bắt đầu triển khai',
            'completed_at' => 'ngày hoàn thành',
            'warranty_to' => 'bảo hành đến',
            'warranty_reminder_1_at' => 'mốc bảo hành 1',
            'warranty_reminder_2_at' => 'mốc bảo hành 2',
            'warranty_reminder_3_at' => 'mốc bảo hành 3',
            'technician_name' => 'phụ trách kỹ thuật',
            'monitoring_link' => 'link monitoring',
            'monitoring_account' => 'tài khoản monitoring',
            'stage' => 'giai đoạn',

            'contract_amount' => 'giá trị hợp đồng',
            'contract_signed_at' => 'ngày ký hợp đồng',
            'finance_note' => 'ghi chú tài chính',
            'payment_terms' => 'đợt thanh toán',

            'devices' => 'thiết bị',
            'planned' => 'vật tư dự kiến',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string)($this->name ?? '')),
            'address' => $this->address !== null ? trim((string)$this->address) : null,
            'contact_name' => $this->contact_name !== null ? trim((string)$this->contact_name) : null,
            'contact_phone' => $this->contact_phone !== null
                ? preg_replace('/\s+/', '', (string)$this->contact_phone)
                : null,
            'note' => $this->note !== null ? trim((string)$this->note) : null,
            'finance_note' => $this->finance_note !== null ? trim((string)$this->finance_note) : null,
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key === null && is_array($validated)) {
            foreach (['devices', 'planned', 'payment_terms'] as $arrKey) {
                if (array_key_exists($arrKey, $validated) && is_array($validated[$arrKey])) {
                    // giữ nguyên array
                }
            }

            $scalar = $validated;

            foreach (['devices', 'planned', 'payment_terms'] as $arrKey) {
                unset($scalar[$arrKey]);
            }

            $scalar = array_filter($scalar, fn($v) => !($v === null || $v === ''));

            foreach (['devices', 'planned', 'payment_terms'] as $arrKey) {
                if (array_key_exists($arrKey, $validated)) {
                    $scalar[$arrKey] = $validated[$arrKey];
                }
            }

            return $scalar;
        }

        return $validated;
    }
}