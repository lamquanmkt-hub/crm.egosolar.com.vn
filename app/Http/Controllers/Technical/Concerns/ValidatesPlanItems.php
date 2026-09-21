<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical\Concerns;

use App\Models\Technical\TechnicalPlanItem;
use Illuminate\Http\Request;

/**
 * Quy tắc hợp lệ cho MỘT dòng kế hoạch — dùng chung cho màn hình nhân viên và
 * màn hình điều phối của trưởng phòng, nên hai nơi không thể lệch nhau.
 *
 * Lưu ý bảo mật: validate ở đây chỉ kiểm tra HÌNH DẠNG dữ liệu. Việc "công việc
 * nguồn có thực sự được giao cho người đó không" luôn được kiểm tra lại ở
 * `TechnicalWeekPlanService::resolveSource()` bằng feed phía server.
 */
trait ValidatesPlanItems
{
    /**
     * Quy tắc cho MỘT dòng kế hoạch, dùng chung cho form phẳng (một dòng) và
     * form mảng (nhiều dòng). `$prefix` là tiền tố khoá khi validate mảng
     * (ví dụ `items.*.`), để thông báo lỗi bám đúng ô nhập của từng dòng.
     *
     * @return array<string, array<int, string>>
     */
    protected function planItemRules(bool $withSource = true, string $prefix = ''): array
    {
        $dayParts = implode(',', array_keys((array) config('technical.day_parts')));
        $priorities = implode(',', array_keys((array) config('technical.priorities')));

        $rules = [
            $prefix.'plan_date' => ['required', 'date'],
            $prefix.'day_part' => ['required', 'string', 'in:'.$dayParts],
            $prefix.'start_time' => ['nullable', 'date_format:H:i'],
            $prefix.'end_time' => ['nullable', 'date_format:H:i', 'after:'.$prefix.'start_time'],
            $prefix.'title' => ['required', 'string', 'min:3', 'max:255'],
            $prefix.'objective' => ['nullable', 'string', 'max:2000'],
            $prefix.'note' => ['nullable', 'string', 'max:2000'],
            $prefix.'estimated_minutes' => ['nullable', 'integer', 'min:15', 'max:'.(int) config('technical.week_plan.hard_limit_minutes', 960)],
            $prefix.'priority' => ['required', 'string', 'in:'.$priorities],
            $prefix.'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];

        if ($withSource) {
            $rules[$prefix.'source_type'] = ['required', 'string', 'in:'.implode(',', array_keys(TechnicalPlanItem::SOURCE_LABELS))];
            $rules[$prefix.'source_id'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function planItemMessages(string $prefix = ''): array
    {
        return [
            $prefix.'plan_date.required' => 'Vui lòng chọn ngày thực hiện.',
            $prefix.'plan_date.date' => 'Ngày thực hiện không hợp lệ.',
            $prefix.'day_part.required' => 'Vui lòng chọn buổi làm việc.',
            $prefix.'day_part.in' => 'Buổi làm việc không hợp lệ.',
            $prefix.'end_time.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
            $prefix.'title.required' => 'Vui lòng nhập nội dung công việc.',
            $prefix.'title.min' => 'Nội dung công việc quá ngắn.',
            $prefix.'priority.required' => 'Vui lòng chọn mức độ ưu tiên.',
            $prefix.'estimated_minutes.min' => 'Thời gian dự kiến tối thiểu 15 phút.',
            $prefix.'source_type.required' => 'Vui lòng chọn nguồn công việc.',
            $prefix.'source_type.in' => 'Nguồn công việc không hợp lệ.',
        ];
    }

    /** @return array<string, mixed> */
    protected function validateItem(Request $request, bool $withSource = true): array
    {
        $data = $request->validate(
            $this->planItemRules($withSource),
            $this->planItemMessages(),
        );

        return $this->normalisePlanItemTimes($data, 'start_time');
    }

    /**
     * Chuẩn hoá giờ theo buổi. Tách riêng để form một dòng và form nhiều dòng
     * áp dụng ĐÚNG MỘT quy tắc, không thể lệch nhau.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $timeErrorKey  khoá lỗi hiển thị cạnh ô "Giờ bắt đầu"
     * @return array<string, mixed>
     */
    protected function normalisePlanItemTimes(array $data, string $timeErrorKey): array
    {
        // Buổi "Giờ cụ thể" bắt buộc có giờ bắt đầu.
        if (($data['day_part'] ?? null) === 'custom' && empty($data['start_time'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $timeErrorKey => 'Chọn "Giờ cụ thể" thì phải nhập giờ bắt đầu.',
            ]);
        }

        if (($data['day_part'] ?? null) !== 'custom') {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }

    /** Ánh xạ khoá gộp "source_type|source_id" của ô chọn về hai trường riêng. */
    protected function normalisePlanSourceKey(Request $request): void
    {
        $key = trim((string) $request->input('work_item_key', ''));

        if ($key === '' || trim((string) $request->input('source_type', '')) !== '') {
            return;
        }

        $parts = explode('|', $key, 2);

        if (count($parts) === 2) {
            $request->merge(['source_type' => $parts[0], 'source_id' => $parts[1]]);
        }
    }
}
