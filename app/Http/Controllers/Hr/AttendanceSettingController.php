<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Controller cài đặt chấm công: giờ làm việc, ngày làm việc trong tuần và ngày nghỉ lễ.
 */
class AttendanceSettingController extends Controller
{
    /**
     * Hiển thị form cài đặt chấm công; tự tạo bản ghi mặc định nếu chưa có, kèm danh sách ngày nghỉ theo tháng.
     */
    public function edit(Request $request)
    {
        $setting = AttendanceSetting::first();

        if (!$setting) {
            $setting = new AttendanceSetting();

            $this->safeSet($setting, 'work_start_time', '08:30:00');
            $this->safeSet($setting, 'work_end_time', '18:00:00');
            $this->safeSet($setting, 'late_grace_minutes', 5);
            $this->safeSet($setting, 'late_penalty_per_time', 0);
            $this->safeSet($setting, 'min_work_minutes', 480);
            $this->safeSet($setting, 'require_gps', 1);

            $this->safeSet($setting, 'workday_monday', 1);
            $this->safeSet($setting, 'workday_tuesday', 1);
            $this->safeSet($setting, 'workday_wednesday', 1);
            $this->safeSet($setting, 'workday_thursday', 1);
            $this->safeSet($setting, 'workday_friday', 1);
            $this->safeSet($setting, 'saturday_mode', 'odd');
            $this->safeSet($setting, 'saturday_custom_dates', null);
            $this->safeSet($setting, 'workday_sunday', 0);

            $setting->save();
        }

        $holidayMonth = $request->input('holiday_month', now()->format('Y-m'));

        try {
            $holidayStart = Carbon::createFromFormat('Y-m', $holidayMonth)->startOfMonth();
        } catch (\Throwable $e) {
            $holidayStart = now()->startOfMonth();
            $holidayMonth = $holidayStart->format('Y-m');
        }

        $holidayEnd = (clone $holidayStart)->endOfMonth();

        $holidays = AttendanceHoliday::query()
            ->whereBetween('holiday_date', [$holidayStart->toDateString(), $holidayEnd->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        return view('hr.attendance.settings', compact(
            'setting',
            'holidays',
            'holidayMonth',
            'holidayStart',
            'holidayEnd'
        ));
    }

    /**
     * Lưu cài đặt chấm công và đồng bộ danh sách ngày nghỉ (thêm mới / xoá).
     */
    public function update(Request $request)
    {
        $request->validate([
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'late_penalty_per_time' => ['nullable', 'integer', 'min:0'],
            'min_work_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'require_gps' => ['nullable'],

            'workday_monday' => ['nullable'],
            'workday_tuesday' => ['nullable'],
            'workday_wednesday' => ['nullable'],
            'workday_thursday' => ['nullable'],
            'workday_friday' => ['nullable'],
            'workday_sunday' => ['nullable'],
            'saturday_mode' => ['required', 'in:off,all,odd,even,custom'],
            'saturday_custom_dates' => ['nullable'],

            'holiday_month' => ['nullable', 'date_format:Y-m'],
            'delete_holiday_ids' => ['nullable', 'array'],
            'delete_holiday_ids.*' => ['integer'],

            'new_holiday_date' => ['nullable', 'array'],
            'new_holiday_date.*' => ['nullable', 'date'],
            'new_holiday_name' => ['nullable', 'array'],
            'new_holiday_name.*' => ['nullable', 'string', 'max:255'],
            'new_holiday_type' => ['nullable', 'array'],
            'new_holiday_type.*' => ['nullable', 'in:holiday,compensatory,company,other'],
            'new_holiday_is_paid' => ['nullable', 'array'],
            'new_holiday_note' => ['nullable', 'array'],
            'new_holiday_note.*' => ['nullable', 'string', 'max:1000'],
        ], [
            'work_start_time.required' => 'Vui lòng nhập giờ bắt đầu làm.',
            'work_start_time.date_format' => 'Giờ bắt đầu làm không đúng định dạng.',
            'work_end_time.required' => 'Vui lòng nhập giờ kết thúc làm.',
            'work_end_time.date_format' => 'Giờ kết thúc làm không đúng định dạng.',
            'late_grace_minutes.required' => 'Vui lòng nhập số phút cho phép đi muộn.',
            'min_work_minutes.required' => 'Vui lòng nhập số phút tối thiểu tính đủ công.',
            'saturday_mode.required' => 'Vui lòng chọn chính sách làm việc thứ 7.',
        ]);

        $setting = AttendanceSetting::first();

        if (!$setting) {
            $setting = new AttendanceSetting();
        }

        $this->safeSet($setting, 'work_start_time', $request->work_start_time . ':00');
        $this->safeSet($setting, 'work_end_time', $request->work_end_time . ':00');
        $this->safeSet($setting, 'late_grace_minutes', (int) $request->late_grace_minutes);
        $this->safeSet($setting, 'late_penalty_per_time', (int) ($request->late_penalty_per_time ?? 0));
        $this->safeSet($setting, 'min_work_minutes', (int) $request->min_work_minutes);
        $this->safeSet($setting, 'require_gps', $request->has('require_gps') ? 1 : 0);

        $this->safeSet($setting, 'workday_monday', $request->has('workday_monday') ? 1 : 0);
        $this->safeSet($setting, 'workday_tuesday', $request->has('workday_tuesday') ? 1 : 0);
        $this->safeSet($setting, 'workday_wednesday', $request->has('workday_wednesday') ? 1 : 0);
        $this->safeSet($setting, 'workday_thursday', $request->has('workday_thursday') ? 1 : 0);
        $this->safeSet($setting, 'workday_friday', $request->has('workday_friday') ? 1 : 0);
        $this->safeSet($setting, 'saturday_mode', $request->input('saturday_mode', 'odd'));
        $this->safeSet($setting, 'saturday_custom_dates', $this->normalizeCustomDates($request->input('saturday_custom_dates')));
        $this->safeSet($setting, 'workday_sunday', $request->has('workday_sunday') ? 1 : 0);

        $setting->save();

        if ($request->filled('delete_holiday_ids')) {
            AttendanceHoliday::whereIn('id', $request->input('delete_holiday_ids', []))->delete();
        }

        $dates = $request->input('new_holiday_date', []);
        $names = $request->input('new_holiday_name', []);
        $types = $request->input('new_holiday_type', []);
        $notes = $request->input('new_holiday_note', []);
        $paidIndexes = $request->input('new_holiday_is_paid', []);

        foreach ($dates as $index => $date) {
            if (blank($date)) {
                continue;
            }

            $name = trim($names[$index] ?? '');

            if ($name === '') {
                $name = 'Ngày nghỉ';
            }

            AttendanceHoliday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'type' => $types[$index] ?? 'holiday',
                    'is_paid' => isset($paidIndexes[$index]) ? 1 : 0,
                    'note' => $notes[$index] ?? null,
                ]
            );
        }

        return redirect()
            ->route('hr.attendance.settings', [
                'holiday_month' => $request->input('holiday_month', now()->format('Y-m')),
            ])
            ->with('success', 'Đã lưu cài đặt chấm công thành công.');
    }

    /**
     * Gán giá trị cho cột của bản ghi cài đặt chỉ khi cột tồn tại trong bảng.
     */
    private function safeSet(AttendanceSetting $setting, string $column, mixed $value): void
    {
        if (Schema::hasColumn($setting->getTable(), $column)) {
            $setting->{$column} = $value;
        }
    }

    /**
     * Chuẩn hoá danh sách ngày thứ 7 tuỳ chọn (chuỗi hoặc mảng) về mảng ngày Y-m-d duy nhất.
     *
     * @return array|null Mảng ngày hợp lệ hoặc null nếu rỗng
     */
    private function normalizeCustomDates(mixed $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,;]+/', (string) $value);
        }

        $dates = [];

        foreach ($items as $item) {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            try {
                $dates[] = Carbon::parse($item)->toDateString();
            } catch (\Throwable $e) {
            }
        }

        $dates = array_values(array_unique($dates));

        return count($dates) ? $dates : null;
    }
}
