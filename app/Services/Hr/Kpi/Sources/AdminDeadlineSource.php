<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\HcnsAdminTasks;
use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HC09 — Thủ tục hành chính hoàn thành đúng hạn (nguồn: Công việc dùng chung, task_type = hcns_admin).
 *
 * Chỉ đo nghiệp vụ; điểm chính thức do phiên bản quy tắc được duyệt quyết định. Mỗi việc trả về:
 *   kpi_state: earned (đúng hạn, đã xác nhận) | lost (trễ / quá hạn chưa hoàn tất) | pending (chờ xác nhận)
 *              | waiting (còn hạn) | excluded (loại theo quy tắc, có lý do) | missing (thiếu mốc).
 * Mẫu số = earned + lost. Việc quá hạn chưa nộp luôn ở mẫu số; người kiểm chậm không làm việc thành trễ
 * vì mốc đo là giờ nộp của người làm, không phải giờ duyệt.
 */
final class AdminDeadlineSource
{
    public const CODE = 'admin_deadline';

    /** @return array<int, array> các dòng của một người trong kỳ (theo hạn hiệu lực) */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        if (! HcnsAdminTasks::ready()) {
            return [];
        }
        $now ??= now();
        [$start, $end] = $this->bounds($month);

        $tasks = DB::table('tasks')
            ->where('task_type', HcnsAdminTasks::TYPE)
            ->where('assignee_id', $userId)
            // Công việc dùng chung chưa từng ghi company_id (null = công ty mặc định); loại bản ghi của công ty khác.
            ->where(fn ($q) => $q->where('company_id', EgoCompanyLock::id())->orWhereNull('company_id'))
            ->where(function ($q) use ($start, $end): void {
                // Lấy rộng ±1 tháng rồi lọc theo hạn hiệu lực (hạn có thể đổi qua tháng).
                $q->whereBetween('due_at', [$start->copy()->subMonth(), $end->copy()->addMonth()])
                    ->orWhereBetween('original_due_at', [$start->copy()->subMonth(), $end->copy()->addMonth()]);
            })
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $dueEvents = $this->dueEvents($tasks->pluck('id')->all());
        $names = $this->names($tasks);

        return $tasks
            ->map(fn ($task) => $this->evaluate($task, $rules, $now, $dueEvents->get($task->id, collect()), $names))
            ->filter(fn ($row) => $row['deadline'] !== null && $row['deadline']->between($start, $end))
            ->values()
            ->all();
    }

    /**
     * Đánh giá một việc theo quy tắc. $dueEvents: các lần đổi hạn (created_at, meta.due_at) theo thứ tự.
     */
    public function evaluate(object $task, array $rules, Carbon $now, ?Collection $dueEvents = null, array $names = []): array
    {
        $r = (array) ($rules[self::CODE] ?? []);
        $tz = config('app.timezone');
        $due = $task->due_at ? Carbon::parse($task->due_at, $tz) : null;
        $original = ! empty($task->original_due_at) ? Carbon::parse($task->original_due_at, $tz) : $due;
        $dueEvents ??= $this->dueEvents([(int) $task->id])->get($task->id, collect());

        $deadline = $this->deadline($due, $original, (string) ($r['deadline_basis'] ?? 'current'), $dueEvents);
        if ($deadline && ($r['date_only_due'] ?? 'end_of_day') === 'end_of_day' && $deadline->format('H:i:s') === '00:00:00') {
            $deadline = $deadline->copy()->endOfDay();
        }

        $first = ! empty($task->first_submitted_at) ? Carbon::parse($task->first_submitted_at, $tz) : null;
        $last = ! empty($task->last_submitted_at) ? Carbon::parse($task->last_submitted_at, $tz) : null;
        $approvedAt = ! empty($task->approved_at) ? Carbon::parse($task->approved_at, $tz) : null;
        $status = (string) ($task->status ?? 'new');
        $measurePoint = (string) ($r['measure_point'] ?? 'accepted_submission');
        $measured = $measurePoint === 'first_submission' ? $first : ($status === 'approved' ? $last : null);
        $measureLabel = $measurePoint === 'first_submission' ? 'lần gửi đầu' : 'lần gửi được chấp nhận';

        [$state, $reason] = $this->state($task, $status, $deadline, $first, $measured, $measureLabel, (string) ($r['cancelled'] ?? 'exclude_if_before_due'), $now);

        return [
            'code' => self::CODE,
            'source_id' => (int) $task->id,
            'title' => (string) ($task->title ?? ''),
            'procedure' => HcnsAdminTasks::procedureTypes()[(string) ($task->hcns_procedure_type ?? '')] ?? 'Chưa chọn loại thủ tục',
            'url' => route('tasks.show', (int) $task->id),
            'owner_id' => (int) $task->assignee_id,
            'requester_id' => $task->requester_id ? (int) $task->requester_id : null,
            'owner_name' => $names[(int) $task->assignee_id] ?? '—',
            'reviewer_id' => $task->approver_id ? (int) $task->approver_id : null,
            'reviewer_name' => $task->approver_id ? ($names[(int) $task->approver_id] ?? '—') : null,
            'deadline' => $deadline,
            'due_at' => $due,
            'original_due_at' => $original,
            'due_changed' => $due && $original && ! $due->equalTo($original),
            'first_submitted_at' => $first,
            'last_submitted_at' => $last,
            'submission_count' => (int) ($task->submission_count ?? 0),
            'draft_saved_at' => ! empty($task->draft_saved_at) ? Carbon::parse($task->draft_saved_at, $tz) : null,
            'measured_at' => $measured,
            'measure_label' => $measureLabel,
            'approved_by' => ! empty($task->approved_by) ? (int) $task->approved_by : null,
            'approved_by_name' => ! empty($task->approved_by) ? ($names[(int) $task->approved_by] ?? '—') : null,
            'approved_at' => $approvedAt,
            'cancelled_at' => ! empty($task->cancelled_at) ? Carbon::parse($task->cancelled_at, $tz) : null,
            'cancel_reason' => $task->cancel_reason ?? null,
            'work_status' => $status,
            'work_label' => $this->workLabel($status, $task),
            'kpi_state' => $state,
            'reason' => $reason,
        ];
    }

    /** Tổng hợp số đo từ các dòng. value = % đúng hạn trên việc đã có kết quả (null nếu mẫu số 0). */
    public function summarize(array $rows, string $month, ?Carbon $now = null): array
    {
        $now ??= now();
        $counts = array_fill_keys(['earned', 'lost', 'pending', 'waiting', 'excluded', 'missing'], 0);
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
        }
        $denominator = $counts['earned'] + $counts['lost'];
        [, $end] = $this->bounds($month);

        $data = match (true) {
            $rows === [] => 'no_items',
            $counts['pending'] + $counts['missing'] > 0 => 'incomplete',
            $counts['waiting'] > 0 || $now->lessThanOrEqualTo($end) => 'open',
            default => 'complete',
        };

        return [
            'numerator' => $counts['earned'],
            'denominator' => $denominator,
            'value' => $denominator > 0 ? round($counts['earned'] / $denominator * 100, 2) : null,
            'counts' => $counts,
            'data_status' => $data,
        ];
    }

    private function state(object $task, string $status, ?Carbon $deadline, ?Carbon $first, ?Carbon $measured, string $measureLabel, string $cancelRule, Carbon $now): array
    {
        $fmt = fn (?Carbon $t) => $t ? $t->format('H:i d/m/Y') : '—';

        if ($deadline === null) {
            return ['missing', 'Chưa có hạn hoàn thành — không đo được đúng hạn.'];
        }

        if ($status === 'cancelled') {
            $cancelledAt = ! empty($task->cancelled_at) ? Carbon::parse($task->cancelled_at) : null;
            $why = 'Đã hủy'.($task->cancel_reason ? ': '.$task->cancel_reason : '');

            return match ($cancelRule) {
                'exclude' => ['excluded', $why.' — loại khỏi mẫu số (quy tắc nháp).'],
                'count_as_late' => ['lost', $why.' — tính không đúng hạn (quy tắc nháp).'],
                default => ($cancelledAt && $cancelledAt->greaterThan($deadline) && ! ($first && $first->lessThanOrEqualTo($deadline)))
                    ? ['lost', $why.' — hủy sau hạn khi chưa nộp: vẫn tính không đúng hạn.']
                    : ['excluded', $why.' — hủy trước hạn: loại khỏi mẫu số.'],
            };
        }

        if ($status === 'approved') {
            if ((int) ($task->approved_by ?? 0) > 0 && (int) $task->approved_by === (int) $task->assignee_id) {
                return ['pending', 'Người thực hiện tự xác nhận — cần người kiểm khác xác nhận.'];
            }
            if (empty($task->approved_by)) {
                return ['missing', 'Đã duyệt trước khi có ghi nhận người/giờ xác nhận — cần xác nhận lại.'];
            }
            if ($measured === null) {
                return ['missing', 'Thiếu mốc '.$measureLabel.' (việc nộp trước khi hệ thống ghi mốc).'];
            }

            return $measured->lessThanOrEqualTo($deadline)
                ? ['earned', 'Đúng hạn: '.$measureLabel.' '.$fmt($measured).' ≤ hạn '.$fmt($deadline).'.']
                : ['lost', 'Trễ hạn: '.$measureLabel.' '.$fmt($measured).' > hạn '.$fmt($deadline).'.'];
        }

        if ($status === 'submitted') {
            return ['pending', 'Đã gửi '.$fmt(! empty($task->last_submitted_at) ? Carbon::parse($task->last_submitted_at) : null).' — chờ người kiểm xác nhận.'];
        }

        // Chưa nộp / đang sửa bổ sung.
        if ($now->lessThanOrEqualTo($deadline)) {
            return ['waiting', 'Còn hạn đến '.$fmt($deadline).' — chưa tính, chưa mất điểm.'];
        }
        if ($measureLabel === 'lần gửi đầu' && $first && $first->lessThanOrEqualTo($deadline)) {
            return ['pending', 'Đã gửi lần đầu đúng hạn, đang trả bổ sung — chờ nộp lại và xác nhận.'];
        }

        return ['lost', ($status === 'revision' || $status === 'rejected' ? 'Quá hạn, đang trả bổ sung chưa có bản được chấp nhận' : 'Quá hạn chưa nộp kết quả')
            .' (hạn '.$fmt($deadline).').'];
    }

    /** Hạn hiệu lực theo phương án quy tắc. */
    private function deadline(?Carbon $due, ?Carbon $original, string $basis, Collection $events): ?Carbon
    {
        if ($due === null && $original === null) {
            return null;
        }

        return match ($basis) {
            'original' => $original ?? $due,
            'current' => $due ?? $original,
            default => $this->deadlineAcceptedBeforeOriginal($due, $original, $events),
        };
    }

    /** Đổi hạn trước khi hạn đang hiệu lực trôi qua thì nhận hạn mới; đổi sau khi đã quá hạn thì giữ hạn cũ. */
    private function deadlineAcceptedBeforeOriginal(?Carbon $due, ?Carbon $original, Collection $events): ?Carbon
    {
        $effective = $original ?? $due;
        foreach ($events as $event) {
            $changedAt = Carbon::parse($event->created_at);
            $newDue = $event->due_at ? Carbon::parse($event->due_at) : null;
            if ($newDue && $effective && $changedAt->lessThanOrEqualTo($effective)) {
                $effective = $newDue;
            }
        }

        return $effective;
    }

    /** @return Collection<int, Collection> task_id => lần đổi hạn (created_at, due_at) */
    private function dueEvents(array $taskIds): Collection
    {
        if ($taskIds === [] || ! SchemaCache::hasTable('task_activity_logs')) {
            return collect();
        }

        return DB::table('task_activity_logs')
            ->whereIn('task_id', $taskIds)
            ->where('action', 'due_changed')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['task_id', 'created_at', 'meta'])
            ->map(function ($log) {
                $meta = json_decode((string) $log->meta, true) ?: [];

                return (object) ['task_id' => (int) $log->task_id, 'created_at' => $log->created_at, 'due_at' => $meta['due_at'] ?? null];
            })
            ->groupBy('task_id');
    }

    private function names(Collection $tasks): array
    {
        $ids = $tasks->flatMap(fn ($t) => [$t->assignee_id, $t->approver_id, $t->approved_by ?? null])->filter()->unique()->all();

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    private function workLabel(string $status, object $task): string
    {
        return match ($status) {
            'approved' => 'Đã xác nhận hoàn thành',
            'submitted' => 'Đã gửi, chờ kiểm tra',
            'revision', 'rejected' => 'Trả bổ sung',
            'cancelled' => 'Đã hủy',
            'in_progress' => ! empty($task->draft_saved_at) ? 'Đang làm (đã lưu nháp)' : 'Đang làm',
            default => 'Mới giao',
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', config('app.timezone'));

        return [$start, $start->copy()->endOfMonth()];
    }
}
