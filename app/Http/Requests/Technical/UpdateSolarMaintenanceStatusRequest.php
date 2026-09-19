<?php

namespace App\Http\Requests\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest validate dữ liệu cập nhật trạng thái lịch bảo trì điện mặt trời.
 */
class UpdateSolarMaintenanceStatusRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validate: chỉ cho phép các trạng thái chuyển thủ công (ngoài luồng duyệt).
     */
    public function rules(): array
    {
        $manualStatuses = array_values(array_diff(
            array_keys(SolarMaintenanceSchedule::STATUSES),
            ['pending_approval', 'approved', 'revision_requested', 'completed']
        ));

        return [
            'status' => ['required', Rule::in($manualStatuses)],
            'result_note' => ['nullable', 'string', 'max:10000'],
            'reason' => [
                Rule::requiredIf(fn () => in_array($this->input('status'), ['postponed', 'cancelled'], true)),
                'nullable', 'string', 'max:1000',
            ],
        ];
    }
}
