<?php

namespace App\Http\Requests\Synced\Technical;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validate dữ liệu duyệt/từ chối lịch bảo trì điện mặt trời.
 */
class SolarMaintenanceApprovalRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
