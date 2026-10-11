<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Tasks\Task;
use App\Models\User;
use App\Services\Hr\Kpi\HcnsAdminTasks;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\Sources\AdminDeadlineSource;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI HCNS — HC09 Thủ tục hành chính đúng hạn (đợt hcns-kpi P2).
 * Luồng thật qua module Công việc dùng chung + bộ đo nguồn; dữ liệu giả trong transaction trên egosolar_test.
 */
final class HcnsAdminDeadlineKpiTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    private User $director;

    private User $hcns;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(HcnsAdminTasks::ready(), 'DB test chưa áp migration nguồn HCNS.');
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00'));
        $this->director = $this->person('management', 'Giám đốc');
        $this->hcns = $this->person('hr', 'Chuyên viên HCNS');
        $this->reviewer = $this->person('hr', 'Trưởng phòng HCNS');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnskpi-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function task(array $overrides = []): int
    {
        return (int) DB::table('tasks')->insertGetId($overrides + [
            'title' => 'HCNS-KPI-TEST thủ tục '.Str::random(5),
            'task_type' => HcnsAdminTasks::TYPE,
            'hcns_procedure_type' => 'business_license',
            'requester_id' => $this->director->id,
            'assignee_id' => $this->hcns->id,
            'approver_id' => $this->reviewer->id,
            'priority' => 'medium',
            'status' => 'new',
            'progress_percent' => 0,
            'company_id' => EgoCompanyLock::id(),
            'due_at' => '2026-10-10 17:00:00',
            'original_due_at' => '2026-10-10 17:00:00',
            'submission_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rows(?User $user = null, ?Carbon $now = null, array $ruleOverrides = []): array
    {
        $rules = app(HcnsKpiRules::class)->defaults();
        $rules[AdminDeadlineSource::CODE] = array_merge($rules[AdminDeadlineSource::CODE], $ruleOverrides);

        return app(AdminDeadlineSource::class)->rows((int) ($user ?? $this->hcns)->id, self::MONTH, $rules, $now ?? now());
    }

    private function stateOf(int $taskId, array $rows): ?string
    {
        return collect($rows)->firstWhere('source_id', $taskId)['kpi_state'] ?? null;
    }

    public function test_assigning_requires_type_deadline_and_independent_reviewer(): void
    {
        $base = ['title' => 'HCNS-KPI-TEST Gia hạn giấy phép', 'assignee_ids' => [$this->hcns->id], 'priority' => 'medium', 'hcns_task' => '1'];

        $this->actingAs($this->director)->post(route('tasks.store'), $base + ['due_at' => '2026-10-12', 'approver_id' => $this->reviewer->id])
            ->assertSessionHasErrors('hcns_procedure_type');
        $this->actingAs($this->director)->post(route('tasks.store'), $base + ['hcns_procedure_type' => 'permit', 'due_at' => '2026-10-12', 'approver_id' => $this->hcns->id])
            ->assertSessionHasErrors('approver_id');

        $this->actingAs($this->director)->post(route('tasks.store'), $base + [
            'hcns_procedure_type' => 'permit', 'due_at' => '2026-10-12', 'due_time' => '16:30', 'approver_id' => $this->reviewer->id,
        ])->assertRedirect();

        $task = Task::query()->where('title', 'HCNS-KPI-TEST Gia hạn giấy phép')->firstOrFail();
        $this->assertSame(HcnsAdminTasks::TYPE, $task->task_type);
        $this->assertSame('2026-10-12 16:30:00', $task->due_at->toDateTimeString());
        $this->assertSame('2026-10-12 16:30:00', $task->original_due_at->toDateTimeString());
        // Module Công việc không ghi company_id (null = công ty mặc định); không gán để tránh đổi hành vi việc khác.
        $this->assertContains($task->company_id === null ? null : (int) $task->company_id, [null, EgoCompanyLock::id()]);
        $this->assertSame((int) $this->reviewer->id, (int) $task->approver_id);
    }

    public function test_draft_then_submit_return_resubmit_and_confirm_keeps_first_submission(): void
    {
        $id = $this->task();

        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['intent' => 'draft', 'result_note' => 'Đang chờ Sở trả biên nhận'])->assertRedirect();
        $task = Task::findOrFail($id);
        $this->assertSame('in_progress', $task->status);
        $this->assertNotNull($task->draft_saved_at);
        $this->assertNull($task->first_submitted_at, 'Lưu nháp không phải lần gửi.');
        $this->assertSame('waiting', $this->stateOf($id, $this->rows()));

        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['result_note' => 'Đã nộp hồ sơ, biên nhận số 123'])->assertRedirect();
        Carbon::setTestNow('2026-10-09 09:00:00');
        $this->actingAs($this->reviewer)->post(route('tasks.return-revision', $id), ['revision_reason' => 'Thiếu ảnh biên nhận'])->assertRedirect();
        Carbon::setTestNow('2026-10-11 08:00:00');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['result_note' => 'Bổ sung ảnh biên nhận'])->assertRedirect();

        $task = Task::findOrFail($id);
        $this->assertSame('2026-10-08 10:00:00', $task->first_submitted_at->toDateTimeString());
        $this->assertSame('2026-10-11 08:00:00', $task->last_submitted_at->toDateTimeString());
        $this->assertSame(2, (int) $task->submission_count);
        $this->assertSame('pending', $this->stateOf($id, $this->rows()), 'Đã gửi nhưng chưa xác nhận: chưa tính.');

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->actingAs($this->reviewer)->post(route('tasks.approve', $id))->assertRedirect();
        $task = Task::findOrFail($id);
        $this->assertSame((int) $this->reviewer->id, (int) $task->approved_by);
        $this->assertSame('2026-10-15 09:00:00', $task->approved_at->toDateTimeString());

        // Bản được chấp nhận gửi 11/10 > hạn 10/10 17:00 → trễ; theo lần gửi đầu (08/10) → đúng hạn.
        $this->assertSame('lost', $this->stateOf($id, $this->rows()));
        $this->assertSame('earned', $this->stateOf($id, $this->rows(null, null, ['measure_point' => 'first_submission'])));
    }

    public function test_slow_reviewer_does_not_make_on_time_submission_late(): void
    {
        $id = $this->task();
        Carbon::setTestNow('2026-10-10 17:00:00');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['result_note' => 'Nộp đúng giờ hạn'])->assertRedirect();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->actingAs($this->reviewer)->post(route('tasks.approve', $id))->assertRedirect();

        $rows = $this->rows();
        $this->assertSame('earned', $this->stateOf($id, $rows), 'Nộp đúng giờ hạn (biên) là đúng hạn dù duyệt sau 10 ngày.');
    }

    public function test_assignee_cannot_confirm_own_procedure(): void
    {
        $exec = $this->person('management', 'Giám đốc tự làm');
        $id = $this->task(['assignee_id' => $exec->id, 'approver_id' => $this->reviewer->id, 'status' => 'submitted', 'first_submitted_at' => now(), 'last_submitted_at' => now(), 'submission_count' => 1]);

        $this->actingAs($exec)->post(route('tasks.approve', $id))->assertSessionHas('error');
        $this->assertSame('submitted', Task::findOrFail($id)->status);
    }

    public function test_cancel_needs_reason_and_delete_is_blocked(): void
    {
        $id = $this->task();

        $this->actingAs($this->director)->delete(route('tasks.destroy', $id))->assertSessionHas('error');
        $this->assertNotNull(Task::find($id), 'Thủ tục HCNS không bị xóa khỏi mẫu số.');

        $this->actingAs($this->hcns)->post(route('tasks.cancel', $id), ['cancel_reason' => 'Muốn hủy'])->assertForbidden();
        $this->actingAs($this->director)->post(route('tasks.cancel', $id), ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->actingAs($this->director)->post(route('tasks.cancel', $id), ['cancel_reason' => 'Cơ quan dừng tiếp nhận hồ sơ'])->assertRedirect();

        $task = Task::findOrFail($id);
        $this->assertSame('cancelled', $task->status);
        $this->assertSame((int) $this->director->id, (int) $task->cancelled_by);
        $this->assertSame('excluded', $this->stateOf($id, $this->rows()), 'Hủy trước hạn: loại có lý do.');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['result_note' => 'x'])->assertSessionHas('error');
    }

    public function test_edit_cannot_force_status_or_drop_type_and_due_change_is_logged(): void
    {
        $id = $this->task(['status' => 'submitted', 'first_submitted_at' => '2026-10-04 08:00:00', 'last_submitted_at' => '2026-10-04 08:00:00', 'submission_count' => 1]);

        $this->actingAs($this->director)->put(route('tasks.update', $id), [
            'title' => 'HCNS-KPI-TEST sửa', 'assignee_ids' => [$this->hcns->id], 'priority' => 'medium', 'status' => 'approved',
            'hcns_procedure_type' => 'permit', 'due_at' => '2026-10-12', 'due_time' => '17:00', 'approver_id' => $this->reviewer->id,
        ])->assertRedirect();

        $task = Task::findOrFail($id);
        $this->assertSame('submitted', $task->status, 'Sửa phiếu không tự duyệt.');
        $this->assertSame(HcnsAdminTasks::TYPE, $task->task_type);
        $this->assertNull($task->approved_by);
        $log = DB::table('task_activity_logs')->where('task_id', $id)->where('action', 'due_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-10-12 17:00:00', json_decode((string) $log->meta, true)['due_at']);

        $this->actingAs($this->hcns)->patch(route('tasks.status', $id), ['status' => 'in_progress'])->assertSessionHas('error');
        $this->assertSame('submitted', Task::findOrFail($id)->status);
    }

    public function test_measure_boundaries_overdue_and_period(): void
    {
        $now = Carbon::parse('2026-11-02 09:00:00');
        $approved = fn (string $submitted) => ['status' => 'approved', 'first_submitted_at' => $submitted, 'last_submitted_at' => $submitted,
            'submission_count' => 1, 'approved_by' => $this->reviewer->id, 'approved_at' => '2026-10-25 09:00:00'];

        $onBoundary = $this->task($approved('2026-10-10 17:00:00'));
        $oneMinuteLate = $this->task($approved('2026-10-10 17:01:00'));
        $overdueOpen = $this->task(['status' => 'in_progress']);
        $pending = $this->task(['status' => 'submitted', 'first_submitted_at' => '2026-10-09 10:00:00', 'last_submitted_at' => '2026-10-09 10:00:00', 'submission_count' => 1]);
        $dateOnly = $this->task($approved('2026-10-20 22:00:00') + ['due_at' => '2026-10-20 00:00:00', 'original_due_at' => '2026-10-20 00:00:00']);
        $nextMonth = $this->task(['due_at' => '2026-11-05 17:00:00', 'original_due_at' => '2026-11-05 17:00:00']);
        $otherCompany = $this->task($approved('2026-10-09 09:00:00') + ['company_id' => EgoCompanyLock::id() + 1000]);
        $normalTask = $this->task($approved('2026-10-09 09:00:00') + ['task_type' => null]);
        $selfConfirmed = $this->task(array_merge($approved('2026-10-09 09:00:00'), ['approved_by' => $this->hcns->id]));
        $cancelledLate = $this->task(['status' => 'cancelled', 'cancelled_at' => '2026-10-15 09:00:00', 'cancelled_by' => $this->director->id, 'cancel_reason' => 'Hủy sau hạn']);

        $rows = $this->rows(null, $now);
        $ids = collect($rows)->pluck('source_id')->all();

        $this->assertSame('earned', $this->stateOf($onBoundary, $rows), 'Đúng bằng giờ hạn là đúng hạn.');
        $this->assertSame('lost', $this->stateOf($oneMinuteLate, $rows));
        $this->assertSame('lost', $this->stateOf($overdueOpen, $rows), 'Quá hạn chưa nộp vẫn ở mẫu số.');
        $this->assertSame('pending', $this->stateOf($pending, $rows));
        $this->assertSame('earned', $this->stateOf($dateOnly, $rows), 'Hạn chỉ có ngày tính hết 23:59:59 (quy tắc nháp).');
        $this->assertSame('pending', $this->stateOf($selfConfirmed, $rows), 'Tự xác nhận không được tính.');
        $this->assertSame('lost', $this->stateOf($cancelledLate, $rows), 'Hủy sau hạn khi chưa nộp vẫn tính trễ.');
        $this->assertNotContains($nextMonth, $ids, 'Hạn tháng 11 không thuộc kỳ 10.');
        $this->assertNotContains($otherCompany, $ids);
        $this->assertNotContains($normalTask, $ids, 'Việc thường không vào KPI HC09.');

        $summary = app(AdminDeadlineSource::class)->summarize($rows, self::MONTH, $now);
        $this->assertSame(2, $summary['numerator']);
        $this->assertSame(5, $summary['denominator']);
        $this->assertSame(40.0, $summary['value']);
        $this->assertSame('incomplete', $summary['data_status'], 'Còn việc chờ xác nhận nên số đo chưa đủ.');
    }

    public function test_due_change_accepted_only_before_the_deadline_passed(): void
    {
        $submitted = ['status' => 'approved', 'first_submitted_at' => '2026-10-14 10:00:00', 'last_submitted_at' => '2026-10-14 10:00:00',
            'submission_count' => 1, 'approved_by' => $this->reviewer->id, 'approved_at' => '2026-10-14 15:00:00'];
        $extendedEarly = $this->task($submitted + ['due_at' => '2026-10-15 17:00:00']);
        $extendedLate = $this->task($submitted + ['due_at' => '2026-10-15 17:00:00']);
        foreach ([[$extendedEarly, '2026-10-08 09:00:00'], [$extendedLate, '2026-10-12 09:00:00']] as [$taskId, $changedAt]) {
            DB::table('task_activity_logs')->insert(['task_id' => $taskId, 'user_id' => $this->director->id, 'action' => 'due_changed',
                'meta' => json_encode(['old_due_at' => '2026-10-10 17:00:00', 'due_at' => '2026-10-15 17:00:00']), 'created_at' => $changedAt, 'updated_at' => $changedAt]);
        }

        $rows = $this->rows(null, Carbon::parse('2026-11-01 09:00:00'));
        $this->assertSame('earned', $this->stateOf($extendedEarly, $rows), 'Gia hạn trước hạn gốc: dùng hạn mới.');
        $this->assertSame('lost', $this->stateOf($extendedLate, $rows), 'Gia hạn sau khi đã quá hạn: giữ hạn gốc.');
        $this->assertSame('earned', $this->stateOf($extendedLate, $this->rows(null, Carbon::parse('2026-11-01 09:00:00'), ['deadline_basis' => 'current'])));
    }

    public function test_reassigned_task_counts_for_current_assignee_only(): void
    {
        $other = $this->person('hr', 'Nhân viên mới nhận');
        $id = $this->task();

        $this->actingAs($this->director)->put(route('tasks.update', $id), [
            'title' => 'HCNS-KPI-TEST đổi người', 'assignee_ids' => [$other->id], 'priority' => 'medium', 'status' => 'new',
            'hcns_procedure_type' => 'business_license', 'due_at' => '2026-10-10', 'due_time' => '17:00', 'approver_id' => $this->reviewer->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($this->stateOf($id, $this->rows()));
        $this->assertSame('waiting', $this->stateOf($id, $this->rows($other)));
    }

    public function test_eight_of_ten_on_time_is_eighty_percent_measure(): void
    {
        foreach (range(1, 10) as $i) {
            $submitted = $i <= 8 ? '2026-10-09 10:00:00' : '2026-10-11 10:00:00';
            $this->task(['status' => 'approved', 'first_submitted_at' => $submitted, 'last_submitted_at' => $submitted, 'submission_count' => 1,
                'approved_by' => $this->reviewer->id, 'approved_at' => '2026-10-12 09:00:00']);
        }
        $now = Carbon::parse('2026-11-01 09:00:00');
        $summary = app(AdminDeadlineSource::class)->summarize($this->rows(null, $now), self::MONTH, $now);

        $this->assertSame([8, 10, 80.0, 'complete'], [$summary['numerator'], $summary['denominator'], $summary['value'], $summary['data_status']]);
    }

    public function test_non_hcns_tasks_keep_old_behaviour(): void
    {
        $id = (int) DB::table('tasks')->insertGetId([
            'title' => 'HCNS-KPI-TEST việc thường', 'requester_id' => $this->director->id, 'assignee_id' => $this->hcns->id,
            'priority' => 'medium', 'status' => 'new', 'progress_percent' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), [])->assertRedirect();
        $this->assertSame('submitted', Task::findOrFail($id)->status, 'Việc thường vẫn nộp không cần ghi chú.');
        $this->actingAs($this->director)->delete(route('tasks.destroy', $id))->assertRedirect(route('tasks.index'));
        $this->assertNull(Task::find($id), 'Việc thường vẫn xóa được như cũ.');
    }
}
