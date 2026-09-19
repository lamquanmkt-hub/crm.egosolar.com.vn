<?php

namespace App\Http\Requests\Synced\Technical;

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
        return [
            'category' => [
                'required',
                Rule::in([
                    'before', 'during', 'after', 'fault', 'serial', 'report', 'video',
                    'contract', 'survey', 'handover', 'acceptance', 'diagram', 'datasheet',
                    'warranty', 'invoice', 'overview', 'other',
                ]),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'work_item_id' => ['nullable', 'integer', 'exists:solar_maintenance_work_items,id'],
            'checklist_item_id' => ['nullable', 'integer', 'exists:solar_maintenance_checklist_items,id'],
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
