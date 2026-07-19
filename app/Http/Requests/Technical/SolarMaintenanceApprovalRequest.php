<?php

namespace App\Http\Requests\Technical;

use Illuminate\Foundation\Http\FormRequest;

class SolarMaintenanceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
