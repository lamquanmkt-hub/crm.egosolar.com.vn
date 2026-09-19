<?php

namespace App\Http\Requests\Technical;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest validate tệp đính kèm của lịch bảo trì điện mặt trời.
 */
class SolarMaintenanceAttachmentRequest extends FormRequest
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
        $schedule = $this->route('schedule');
        $scheduleId = is_object($schedule) && method_exists($schedule, 'getKey')
            ? (int) $schedule->getKey()
            : (int) $schedule;

        return [
            'category' => [
                'required',
                Rule::in([
                    'before', 'during', 'after', 'fault', 'serial', 'report', 'video', 'checklist',
                    'contract', 'survey', 'handover', 'acceptance', 'diagram', 'datasheet',
                    'warranty', 'invoice', 'overview', 'other',
                ]),
            ],
            'checklist_item_id' => [
                Rule::requiredIf(fn () => $this->input('category') === 'checklist'),
                'nullable',
                'integer',
                Rule::exists('solar_maintenance_checklist_items', 'id')->where(
                    fn ($query) => $query->where(
                        'maintenance_schedule_id',
                        $scheduleId
                    )
                ),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_customer_visible' => ['nullable', 'boolean'],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4',
                'max:102400',
            ],
        ];
    }
}
