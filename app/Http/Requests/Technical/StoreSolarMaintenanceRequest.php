<?php

namespace App\Http\Requests\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolarMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'customer_name' => ['nullable', 'string', 'max:190'],
            'site_name' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(SolarMaintenanceSchedule::TYPES))],
            'priority' => ['required', Rule::in(array_keys(SolarMaintenanceSchedule::PRIORITIES))],
            'scheduled_date' => ['required', 'date'],
            'rounds_count' => ['nullable', 'integer', 'min:1', 'max:24'],
            'round_interval_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'round_dates' => ['nullable', 'array'],
            'round_dates.*' => ['nullable', 'date'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'round_assignees' => ['nullable', 'array'],
            'round_assignees.*' => ['nullable', 'array'],
            'round_assignees.*.*' => ['integer', 'distinct', 'exists:users,id'],
            'system_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'inverter_info' => ['nullable', 'string', 'max:255'],
            'issue_note' => ['nullable', 'string', 'max:5000'],
            'technical_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.exists' => 'Công trình đã chọn không còn tồn tại.',
            'type.in' => 'Loại lịch không hợp lệ.',
            'priority.in' => 'Mức ưu tiên không hợp lệ.',
            'assigned_user_ids.*.exists' => 'Có kỹ thuật viên không còn tồn tại.',
        ];
    }
}
