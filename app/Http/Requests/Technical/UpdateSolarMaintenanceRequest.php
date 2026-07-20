<?php

namespace App\Http\Requests\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest validate dữ liệu cập nhật lịch bảo trì điện mặt trời.
 */
class UpdateSolarMaintenanceRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validate dữ liệu lịch bảo trì điện mặt trời.
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(array_keys(SolarMaintenanceSchedule::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(SolarMaintenanceSchedule::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(SolarMaintenanceSchedule::PRIORITIES))],
            'scheduled_date' => ['nullable', 'date'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'leader_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_user_ids' => ['nullable', 'array'],
            'member_user_ids.*' => ['integer', 'distinct', 'different:leader_user_id', 'exists:users,id'],
            'system_kwp' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'inverter_info' => ['nullable', 'string', 'max:255'],
            'issue_note' => ['nullable', 'string', 'max:5000'],
            'technical_note' => ['nullable', 'string', 'max:5000'],
            'result_note' => ['nullable', 'string', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
