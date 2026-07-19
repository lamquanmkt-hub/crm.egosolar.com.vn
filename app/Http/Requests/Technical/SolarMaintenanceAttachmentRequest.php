<?php

namespace App\Http\Requests\Technical;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolarMaintenanceAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
