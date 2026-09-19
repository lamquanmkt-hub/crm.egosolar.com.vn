<?php

namespace App\Services\Hr;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use Carbon\Carbon;

class AttendanceCorrectionService
{
    /**
     * Áp dụng giờ đã duyệt và tính lại toàn bộ chỉ số công từ nguồn cài đặt hiện tại.
     */
    public function apply(
        AttendanceCorrectionRequest $correction,
        AttendanceRecord $record,
        Carbon $checkInAt,
        ?Carbon $checkOutAt,
        string $reviewerName
    ): void {
        $setting = AttendanceSetting::first();
        $workDate = Carbon::parse($record->work_date)->toDateString();
        $workStartTime = $this->normalizeTime($setting?->work_start_time, '08:30:00');
        $workEndTime = $this->normalizeTime($setting?->work_end_time, '18:00:00');
        $graceMinutes = max((int) ($setting?->late_grace_minutes ?? 0), 0);
        $minWorkMinutes = max((int) ($setting?->min_work_minutes ?? 480), 0);

        $allowedCheckIn = Carbon::parse($workDate.' '.$workStartTime)->addMinutes($graceMinutes);
        $lateMinutes = $checkInAt->gt($allowedCheckIn)
            ? (int) $allowedCheckIn->diffInMinutes($checkInAt)
            : 0;

        $workMinutes = 0;
        $earlyLeaveMinutes = 0;
        $status = $lateMinutes > 0 ? 'late' : 'checked_in';

        if ($checkOutAt) {
            $workMinutes = max(0, (int) $checkInAt->diffInMinutes($checkOutAt));
            $standardCheckOut = Carbon::parse($workDate.' '.$workEndTime);
            $earlyLeaveMinutes = $checkOutAt->lt($standardCheckOut)
                ? (int) $checkOutAt->diffInMinutes($standardCheckOut)
                : 0;

            $status = $earlyLeaveMinutes > 0 || ($minWorkMinutes > 0 && $workMinutes < $minWorkMinutes)
                ? 'early_leave'
                : 'completed';
        }

        $tagStart = '[Điều chỉnh công #'.$correction->id.']';
        $tagEnd = '[/Điều chỉnh công #'.$correction->id.']';
        $currentNote = (string) ($record->note ?? '');
        $pattern = '/\s*'.preg_quote($tagStart, '/').'.*?'.preg_quote($tagEnd, '/').'\s*/su';
        $cleanNote = trim((string) preg_replace($pattern, "\n", $currentNote));
        $auditNote = implode(' | ', [
            'Đã được HR duyệt',
            'Giờ vào: '.$checkInAt->format('H:i'),
            'Giờ ra: '.($checkOutAt?->format('H:i') ?? 'Chưa có'),
            'Người duyệt: '.$reviewerName,
        ]);

        $record->forceFill([
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'work_minutes' => $workMinutes,
            'status' => $status,
            'note' => trim($cleanNote === ''
                ? $tagStart.' '.$auditNote.' '.$tagEnd
                : $cleanNote."\n".$tagStart.' '.$auditNote.' '.$tagEnd),
        ])->save();
    }

    /** @return array<string, mixed> */
    public function snapshot(AttendanceRecord $record): array
    {
        return [
            'check_in_at' => $record->check_in_at?->toDateTimeString(),
            'check_out_at' => $record->check_out_at?->toDateTimeString(),
            'late_minutes' => (int) $record->late_minutes,
            'early_leave_minutes' => (int) $record->early_leave_minutes,
            'work_minutes' => (int) $record->work_minutes,
            'status' => (string) $record->status,
            'note' => $record->note,
        ];
    }

    private function normalizeTime(?string $time, string $fallback): string
    {
        $time = trim((string) $time);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return $fallback;
    }
}
