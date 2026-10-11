<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Tasks\Task;
use App\Models\User;
use App\Services\Hr\Kpi\HcnsAdminTasks;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Quy tắc nguồn KPI HCNS theo phiên bản (P1/P7) và khóa nguồn theo kỳ lương đã chốt (P8).
 * Nháp không đổi điểm chính thức; chỉ phiên bản được người khác duyệt mới lấy điểm từ nguồn; kỳ chốt khóa nguồn HCNS.
 */
final class HcnsKpiRuleVersionLockTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    private User $director;

    private User $director2;

    private User $hcns;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
        DB::table('hr_kpi_rule_versions')->delete();
        $this->director = $this->person('management', 'Giám đốc');
        $this->director2 = $this->person('management', 'Phó giám đốc');
        $this->hcns = $this->person('hr', 'Chuyên viên HCNS');
        $this->reviewer = $this->person('hr', 'Người kiểm');
        DB::table('hr_salary_profiles')->insert(['user_id' => $this->hcns->id, 'effective_month' => '2026-01', 'base_salary' => 8000000,
            'kpi_salary' => 2000000, 'kpi_template' => 'hcns', 'insurance_enabled' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnsrv-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function task(array $overrides): int
    {
        return (int) DB::table('tasks')->insertGetId($overrides + [
            'title' => 'HCNS-KPI-TEST thủ tục '.Str::random(5), 'task_type' => HcnsAdminTasks::TYPE, 'hcns_procedure_type' => 'permit',
            'requester_id' => $this->director->id, 'assignee_id' => $this->hcns->id, 'approver_id' => $this->reviewer->id,
            'priority' => 'medium', 'status' => 'new', 'progress_percent' => 0, 'company_id' => EgoCompanyLock::id(),
            'due_at' => '2026-10-10 17:00:00', 'original_due_at' => '2026-10-10 17:00:00', 'submission_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function approvedTask(string $submittedAt): int
    {
        return $this->task(['status' => 'approved', 'first_submitted_at' => $submittedAt, 'last_submitted_at' => $submittedAt,
            'submission_count' => 1, 'approved_by' => $this->reviewer->id, 'approved_at' => '2026-10-12 09:00:00']);
    }

    /** 9 tiêu chí nhập tay 100%; HC07 đánh dấu “Không phát sinh”. */
    private function manualScores(): void
    {
        $template = app(HrKpiEvaluator::class)->template('hcns');
        foreach (app(HrKpiEvaluator::class)->criteria((int) $template->id) as $c) {
            DB::table('hr_kpi_scores')->insert(['user_id' => $this->hcns->id, 'payroll_month' => self::MONTH, 'criterion_id' => $c->id,
                'achievement' => $c->code === 'documents' ? null : 100, 'not_applicable' => $c->code === 'documents' ? 1 : 0,
                'updated_by' => $this->director->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function rulesInput(): array
    {
        return ['rules' => [
            'admin_deadline' => ['period_by' => 'due_month', 'measure_point' => 'accepted_submission', 'deadline_basis' => 'current_if_changed_before_original',
                'date_only_due' => 'end_of_day', 'cancelled' => 'exclude_if_before_due'],
            'documents' => ['period_by' => 'first_submission_month', 'pass_rule' => 'all_checks'],
        ], 'note' => 'HCNS-KPI-TEST bản nháp', 'month' => self::MONTH];
    }

    private function applyRules(): int
    {
        $this->actingAs($this->director)->post(route('hr.kpi.rules.draft'), $this->rulesInput())->assertRedirect();
        $id = (int) DB::table('hr_kpi_rule_versions')->where('status', 'draft')->value('id');
        $this->actingAs($this->director2)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => self::MONTH])->assertRedirect()->assertSessionHas('success');

        return $id;
    }

    public function test_draft_rules_need_executive_and_do_not_change_official_score(): void
    {
        $this->approvedTask('2026-10-09 10:00:00');
        $this->approvedTask('2026-10-11 10:00:00');
        $this->manualScores();
        $before = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');

        $this->actingAs($this->hcns)->post(route('hr.kpi.rules.draft'), $this->rulesInput())->assertForbidden();
        $bad = $this->rulesInput();
        $bad['rules']['admin_deadline']['measure_point'] = 'approved_at';
        $this->actingAs($this->director)->post(route('hr.kpi.rules.draft'), $bad)->assertSessionHasErrors('rules.admin_deadline.measure_point');
        $this->actingAs($this->director)->post(route('hr.kpi.rules.draft'), $this->rulesInput())->assertRedirect();

        $this->assertSame('draft', DB::table('hr_kpi_rule_versions')->value('status'));
        $this->assertSame($before, app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns'));
        $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'tab' => 'settings', 'month' => self::MONTH]))
            ->assertOk()->assertSee('data-hk-rules', false)->assertSee('1/2 = 50%');
    }

    public function test_approval_needs_another_executive_and_open_period(): void
    {
        $this->actingAs($this->director)->post(route('hr.kpi.rules.draft'), $this->rulesInput());
        $id = (int) DB::table('hr_kpi_rule_versions')->where('status', 'draft')->value('id');

        $this->actingAs($this->hcns)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => self::MONTH])->assertForbidden();
        $this->actingAs($this->director)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => self::MONTH])->assertSessionHas('error');
        DB::table('hr_payroll_periods')->insert(['payroll_month' => '2026-11', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->director2)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => self::MONTH])->assertSessionHas('error');
        $this->assertSame('draft', DB::table('hr_kpi_rule_versions')->where('id', $id)->value('status'));

        $this->actingAs($this->director2)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => '2026-12'])->assertSessionHas('success');
        $row = DB::table('hr_kpi_rule_versions')->find($id);
        $this->assertSame(['applied', '2026-12', (int) $this->director2->id], [$row->status, $row->effective_month, (int) $row->approved_by]);
    }

    public function test_applied_rules_take_source_score_and_ignore_manual_entry(): void
    {
        $this->approvedTask('2026-10-09 10:00:00');
        $this->approvedTask('2026-10-11 10:00:00');
        $this->manualScores();
        $this->applyRules();

        $result = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');
        // HC09 nguồn 1/2 = 50% → 50/80 = 62,5% đạt; HC07 không phát sinh đã xác nhận → N/A (loại khỏi mẫu số).
        // Σw = 100 − 11,11 = 88,89; điểm = (88,89 − 11,11) × 100% + 11,11 × 62,5% = 77,78 + 6,94375 = 84,72375 → 95,31%.
        $this->assertSame('ok', $result['status']);
        $this->assertSame(95.31, $result['percent']);

        // Nhập tay % đạt cho HC09 sau khi áp dụng không được lưu (nguồn tự động là duy nhất).
        $template = app(HrKpiEvaluator::class)->template('hcns');
        $hc09 = (int) DB::table('hr_kpi_criteria')->where('template_id', $template->id)->where('code', 'admin_deadline')->value('id');
        $this->actingAs($this->director)->post(route('hr.kpi.store'), ['month' => self::MONTH, 'template' => 'hcns',
            'scores' => [$this->hcns->id => [$hc09 => ['achievement' => 120, 'note' => 'thử ghi đè']]]])->assertRedirect();
        $this->assertNull(DB::table('hr_kpi_scores')->where('user_id', $this->hcns->id)->where('criterion_id', $hc09)->value('achievement'));
        $this->assertSame(95.31, app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns')['percent']);

        $html = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH]))->assertOk()->getContent();
        $this->assertStringContainsString('data-hk-official', $html);
        $this->assertStringContainsString('data-hk-source-cell', $html);
    }

    public function test_applied_rules_with_pending_source_block_official_total(): void
    {
        $this->approvedTask('2026-10-09 10:00:00');
        $this->task(['status' => 'submitted', 'first_submitted_at' => '2026-10-09 10:00:00', 'last_submitted_at' => '2026-10-09 10:00:00', 'submission_count' => 1]);
        $this->manualScores();
        $this->applyRules();

        $result = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['rate'], 'Nguồn chưa đủ thì không sinh hệ số lương KPI.');
    }

    public function test_closed_payroll_period_locks_hcns_sources_only(): void
    {
        $locked = $this->task(['status' => 'submitted', 'first_submitted_at' => '2026-10-09 10:00:00', 'last_submitted_at' => '2026-10-09 10:00:00', 'submission_count' => 1]);
        $normal = (int) DB::table('tasks')->insertGetId(['title' => 'HCNS-KPI-TEST việc thường', 'requester_id' => $this->director->id,
            'assignee_id' => $this->hcns->id, 'priority' => 'medium', 'status' => 'submitted', 'progress_percent' => 100,
            'due_at' => '2026-10-10 17:00:00', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::MONTH, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->reviewer)->post(route('tasks.approve', $locked))->assertSessionHas('error');
        $this->actingAs($this->reviewer)->post(route('tasks.return-revision', $locked), ['revision_reason' => 'Thử sau chốt'])->assertSessionHas('error');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $locked), ['result_note' => 'Sửa sau chốt'])->assertSessionHas('error');
        $this->actingAs($this->director)->post(route('tasks.cancel', $locked), ['cancel_reason' => 'Hủy sau chốt kỳ'])->assertSessionHas('error');
        $this->actingAs($this->director)->put(route('tasks.update', $locked), ['title' => 'x', 'assignee_ids' => [$this->hcns->id], 'priority' => 'medium',
            'status' => 'submitted', 'hcns_procedure_type' => 'permit', 'due_at' => '2026-11-20', 'due_time' => '17:00', 'approver_id' => $this->reviewer->id])
            ->assertSessionHas('error');
        $this->assertSame(['submitted', '2026-10-10 17:00:00'], [Task::findOrFail($locked)->status, Task::findOrFail($locked)->due_at->toDateTimeString()]);

        $this->actingAs($this->director)->post(route('tasks.store'), ['title' => 'HCNS-KPI-TEST giao vào kỳ đã chốt', 'assignee_ids' => [$this->hcns->id],
            'priority' => 'medium', 'hcns_task' => '1', 'hcns_procedure_type' => 'permit', 'due_at' => '2026-10-20', 'due_time' => '17:00',
            'approver_id' => $this->reviewer->id])->assertSessionHas('error');
        $this->assertFalse(Task::query()->where('title', 'HCNS-KPI-TEST giao vào kỳ đã chốt')->exists());

        $this->actingAs($this->director)->post(route('tasks.approve', $normal))->assertSessionHas('success');
        $this->assertSame('approved', Task::findOrFail($normal)->status, 'Việc thường không bị khóa theo kỳ KPI HCNS.');
    }

    public function test_closed_period_locks_submitted_document_but_not_handover_steps(): void
    {
        $id = (int) DB::table('hr_document_handovers')->insertGetId(['code' => 'HS-TEST-L', 'title' => 'HCNS-KPI-TEST công văn chốt kỳ', 'priority' => 'normal',
            'status' => 'received', 'created_by' => $this->hcns->id, 'company_id' => EgoCompanyLock::id(), 'kpi_tracked' => 1,
            'processor_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'classification' => 'internal', 'routed_to_note' => 'Kế toán',
            'created_at' => '2026-10-02 09:00:00', 'updated_at' => now()]);
        DB::table('hr_kpi_source_reviews')->insert(['company_id' => EgoCompanyLock::id(), 'criterion_code' => 'documents', 'source_type' => 'hr_document_handover',
            'source_id' => $id, 'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'status' => 'submitted', 'revision' => 1,
            'fingerprint' => 'x', 'first_submitted_at' => '2026-10-03 09:00:00', 'submitted_at' => '2026-10-03 09:00:00', 'submission_count' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::MONTH, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.return', $id), ['reason' => 'Thử trả sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->put(route('hr.document-handovers.update', $id), ['title' => 'Đổi tên sau chốt', 'kpi_tracked' => '1',
            'processor_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id])->assertSessionHas('error');
        $this->actingAs($this->hcns)->post(route('hr.document-handovers.files.upload', $id), ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('x.pdf', 3)]])
            ->assertSessionHas('error');
        $this->assertSame('HCNS-KPI-TEST công văn chốt kỳ', DB::table('hr_document_handovers')->where('id', $id)->value('title'));
        $this->assertSame('submitted', DB::table('hr_kpi_source_reviews')->where('source_id', $id)->value('status'));

        $this->actingAs($this->hcns)->post(route('hr.document-handovers.status', $id), ['status' => 'sent'])->assertRedirect();
        $this->assertSame('sent', DB::table('hr_document_handovers')->where('id', $id)->value('status'), 'Bước giao nhận vẫn chạy, không ảnh hưởng kết quả kiểm.');
    }
}
