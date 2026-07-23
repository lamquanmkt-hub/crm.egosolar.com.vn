<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service đồng bộ ghi chú chấm công từ đơn nghỉ phép/làm online đã duyệt.
 */
class AttendanceLeaveNoteService
{
    /**
     * Ghi chú đơn nghỉ đã duyệt vào bản ghi chấm công từng ngày trong khoảng nghỉ.
     *
     * @return int Số bản ghi chấm công đã cập nhật
     */
    public function sync(LeaveRequest $leave, ?User $approver = null): int
    {
        if ((string) $leave->status !== 'approved') {
            return 0;
        }

        $leave->loadMissing(['user', 'approver']);

        $start = Carbon::parse($leave->start_date)->startOfDay();
        $end = Carbon::parse($leave->end_date)->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $changed = 0;

        DB::transaction(function () use ($leave, $approver, $start, $end, &$changed) {
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $date = $day->toDateString();

                $record = AttendanceRecord::firstOrNew([
                    'user_id' => $leave->user_id,
                    'work_date' => $date,
                ]);

                if (! $record->exists) {
                    $record->status = 'absent';
                    $record->late_minutes = 0;
                    $record->early_leave_minutes = 0;
                    $record->work_minutes = 0;
                }

                $record->note = $this->mergeTaggedNote((string) ($record->note ?? ''), $leave, $approver);
                $record->save();

                $changed++;
            }
        });

        return $changed;
    }

    /**
     * Đồng bộ tất cả đơn nghỉ đã duyệt (lọc theo khoảng ngày nếu có), xử lý theo chunk.
     *
     * @return int Tổng số bản ghi chấm công đã cập nhật
     */
    public function syncAllApproved(?string $from = null, ?string $to = null): int
    {
        $query = LeaveRequest::query()
            ->with(['user', 'approver'])
            ->where('status', 'approved');

        if ($from) {
            $query->where('end_date', '>=', Carbon::parse($from)->toDateString());
        }

        if ($to) {
            $query->where('start_date', '<=', Carbon::parse($to)->toDateString());
        }

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($leaves) use (&$count) {
            foreach ($leaves as $leave) {
                $count += $this->sync($leave, $leave->approver);
            }
        });

        return $count;
    }

    /**
     * Gộp ghi chú mới vào ghi chú hiện có, thay thế block cũ theo tag [Đơn HR #id] để tránh trùng.
     */
    private function mergeTaggedNote(string $currentNote, LeaveRequest $leave, ?User $approver = null): string
    {
        $tagStart = '[Đơn HR #'.$leave->id.']';
        $tagEnd = '[/Đơn HR #'.$leave->id.']';

        $pattern = '/\s*'.preg_quote($tagStart, '/').'.*?'.preg_quote($tagEnd, '/').'\s*/su';
        $clean = trim((string) preg_replace($pattern, "\n", $currentNote));

        $newNote = $tagStart.' '.$this->buildNoteText($leave, $approver).' '.$tagEnd;

        return trim($clean === '' ? $newNote : ($clean."\n".$newNote));
    }

    /**
     * Tạo nội dung ghi chú từ đơn nghỉ: loại, ngày, số ngày, lý do, người duyệt.
     */
    private function buildNoteText(LeaveRequest $leave, ?User $approver = null): string
    {
        $type = match ((string) $leave->request_type) {
            'wfh' => 'Làm online đã duyệt',
            'business_trip' => 'Công tác đã duyệt',
            'late' => 'Đi trễ đã duyệt',
            'early_leave' => 'Về sớm đã duyệt',
            default => 'Nghỉ phép đã duyệt',
        };
        $leaveType = $this->leaveTypeLabel((string) ($leave->leave_type ?? ''));

        $dateText = Carbon::parse($leave->start_date)->format('d/m/Y');

        if (Carbon::parse($leave->end_date)->toDateString() !== Carbon::parse($leave->start_date)->toDateString()) {
            $dateText .= ' - '.Carbon::parse($leave->end_date)->format('d/m/Y');
        }

        $parts = [
            $type,
            'Loại: '.$leaveType,
            'Ngày: '.$dateText,
            'Số ngày: '.rtrim(rtrim(number_format((float) $leave->days, 2, '.', ''), '0'), '.'),
        ];

        $reason = $this->shorten((string) ($leave->reason ?? ''), 260);

        if ($reason !== '') {
            $parts[] = 'Lý do: '.$reason;
        }

        $approvedBy = $approver?->name ?: $leave->approver?->name;

        if ($approvedBy) {
            $parts[] = 'Người duyệt: '.$approvedBy;
        }

        if ($leave->approved_at) {
            $parts[] = 'Duyệt lúc: '.Carbon::parse($leave->approved_at)->format('d/m/Y H:i');
        }

        $approvalNote = $this->shorten((string) ($leave->approval_note ?? ''), 220);

        if ($approvalNote !== '') {
            $parts[] = 'Ghi chú duyệt: '.$approvalNote;
        }

        return implode(' | ', $parts);
    }

    /**
     * Chuyển mã loại nghỉ phép sang nhãn tiếng Việt.
     */
    private function leaveTypeLabel(string $value): string
    {
        return match ($value) {
            'annual' => 'Nghỉ phép năm',
            'unpaid' => 'Nghỉ không lương',
            'sick' => 'Nghỉ ốm',
            'personal' => 'Nghỉ việc riêng',
            'wfh' => 'Làm online',
            'business_trip' => 'Công tác',
            'late' => 'Đi trễ',
            'early_leave' => 'Về sớm',
            default => $value !== '' ? $value : '-',
        };
    }

    /**
     * Rút gọn chuỗi về giới hạn ký tự (bỏ HTML, gộp khoảng trắng, thêm dấu ...).
     */
    private function shorten(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit, 'UTF-8').'...';
        }

        if (! function_exists('mb_strlen') && strlen($text) > $limit) {
            return substr($text, 0, $limit).'...';
        }

        return $text;
    }
}
