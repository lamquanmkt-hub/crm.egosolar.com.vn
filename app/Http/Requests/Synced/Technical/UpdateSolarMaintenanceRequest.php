<?php

namespace App\Http\Requests\Synced\Technical;

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
            'status' => ['nullable', Rule::in(array_values(array_diff(
                array_keys(SolarMaintenanceSchedule::STATUSES),
                ['pending_approval', 'approved', 'revision_requested', 'completed']
            )))],
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
            'plan_checklist' => ['nullable', 'array'],
            'plan_checklist.*' => ['string', Rule::in(['system', 'inverter', 'panels', 'electrical'])],
            'external_labor_enabled' => ['nullable', 'boolean'],
            'external_labor_name' => ['nullable', 'string', 'max:255'],
            'external_labor_contact' => ['nullable', 'string', 'max:255'],
            'external_labor_estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'execution_fault_note' => ['nullable', 'string', 'max:10000'],
            'incident_kind' => ['nullable', Rule::in(['none', 'warranty_free', 'maintenance_free', 'warranty_paid', 'maintenance_paid'])],
            'incident_material_note' => ['nullable', 'string', 'max:5000'],
            'incident_replacement_reason' => ['nullable', 'string', 'max:5000'],
            'incident_estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'completion_actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'completion_state' => ['nullable', Rule::in(['completed', 'needs_followup'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
