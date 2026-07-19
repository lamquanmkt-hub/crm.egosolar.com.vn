<?php

namespace App\Http\Requests\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSolarMaintenanceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $manualStatuses = array_values(array_diff(
            array_keys(SolarMaintenanceSchedule::STATUSES),
            ['pending_approval', 'approved', 'revision_requested']
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
