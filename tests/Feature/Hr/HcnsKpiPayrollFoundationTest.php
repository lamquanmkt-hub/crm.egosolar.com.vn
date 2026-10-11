<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Tasks\Task;
use App\Models\User;
use App\Services\Hr\Kpi\HcnsAdminTasks;
use App\Services\Hr\Kpi\HcnsKpiChecklist;
use App\Services\Hr\Kpi\HcnsKpiSnapshots;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nền tính lương KPI HCNS (bước 2): checklist khớp bộ chấm, phiên bản trọng số/chỉ tiêu/bậc theo kỳ,
 * bảng lương HR dùng cùng bộ chấm, ảnh chụp khi chốt và bảo vệ minh chứng HC09.
 * Kỳ thử 09/2026 (đã kết thúc); dữ liệu giả trong transaction trên egosolar_test.
 */
final class HcnsKpiPayrollFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-09';

    private User $director;

    private User $director2;

    private User $hr;

    private User $hcns;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-10-03 09:00:00'));
        foreach (['hr_kpi_rule_versions', 'hr_kpi_period_snapshots', 'hr_salary_profiles', 'hr_payroll_lines', 'hr_payroll_periods', 'attendance_settings'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('attendance_settings')->insert(['workday_monday' => 1, 'workday_tuesday' => 1, 'workday_wednesday' => 1, 'workday_thursday' => 1,
            'workday_friday' => 1, 'saturday_mode' => 'off', 'workday_sunday' => 0, 'late_penalty_per_time' => 100000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attendance_holidays')->whereBetween('holiday_date', ['2026-08-01', '2026-10-31'])->delete();
        DB::table('attendance_holidays')->insert(['holiday_date' => '2026-09-02', 'name' => 'Quốc khánh', 'is_paid' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->director = $this->person('management', 'Giám đốc');
        $this->director2 = $this->person('management', 'Phó giám đốc');
        $this->hr = $this->person('hr', 'Kế toán lương HR');
        $this->hcns = $this->person('hr', 'Chuyên viên HCNS');
        $this->reviewer = $this->person('hr', 'Người kiểm');
        DB::table('hr_salary_profiles')->insert(['user_id' => $this->hcns->id, 'effective_month' => '2026-01', 'base_salary' => 8000000,
            'kpi_salary' => 2000000, 'kpi_template' => 'hcns', 'insurance_enabled' => 0, 'pit_mode' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        $this->attendSeptember($this->hcns);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnspf-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function attendSeptember(User $user): void
    {
        for ($d = Carbon::parse('2026-09-01'); $d->month === 9; $d->addDay()) {
            if ($d->isWeekend() || $d->toDateString() === '2026-09-02') {
                continue;
            }
            DB::table('attendance_records')->insert(['user_id' => $user->id, 'work_date' => $d->toDateString(), 'check_in_at' => $d->toDateString().' 08:00:00',
                'check_out_at' => $d->toDateString().' 17:30:00', 'late_minutes' => 0, 'work_minutes' => 480, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function task(array $overrides = []): int
    {
        return (int) DB::table('tasks')->insertGetId($overrides + [
            'title' => 'HCNS-KPI-TEST thủ tục '.Str::random(5), 'task_type' => HcnsAdminTasks::TYPE, 'hcns_procedure_type' => 'permit',
            'requester_id' => $this->director->id, 'assignee_id' => $this->hcns->id, 'approver_id' => $this->reviewer->id,
            'priority' => 'medium', 'status' => 'new', 'progress_percent' => 0, 'company_id' => EgoCompanyLock::id(),
            'due_at' => '2026-09-15 17:00:00', 'original_due_at' => '2026-09-15 17:00:00', 'submission_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** 1 việc đúng hạn + 3 việc trễ → HC09 = 1/4 = 25%. */
    private function septemberTasks(): void
    {
        foreach (['2026-09-14 10:00:00', '2026-09-16 10:00:00', '2026-09-17 10:00:00', '2026-09-18 10:00:00'] as $submitted) {
            $this->task(['status' => 'approved', 'first_submitted_at' => $submitted, 'last_submitted_at' => $submitted, 'submission_count' => 1,
                'approved_by' => $this->reviewer->id, 'approved_at' => '2026-09-20 09:00:00']);
        }
    }

    /** Nhập tay 100% cho mọi tiêu chí; HC07 đánh dấu “Không phát sinh”. */
    private function manualScores(): void
    {
        $template = app(HrKpiEvaluator::class)->template('hcns');
        $scores = [];
        foreach (app(HrKpiEvaluator::class)->criteria((int) $template->id) as $c) {
            $scores[$this->hcns->id][$c->id] = $c->code === 'documents' ? ['na' => 1, 'note' => 'Không có công văn trong tháng'] : ['achievement' => 100];
        }
        $this->actingAs($this->director)->post(route('hr.kpi.store'), ['month' => self::MONTH, 'template' => 'hcns', 'scores' => $scores])->assertRedirect();
    }

    private function settingsPayload(float $hc09Weight): array
    {
        $template = app(HrKpiEvaluator::class)->template('hcns');
        $criteria = [];
        foreach (DB::table('hr_kpi_criteria')->where('template_id', $template->id)->get() as $c) {
            $criteria[$c->id] = ['name' => $c->name, 'group_name' => $c->group_name, 'target_text' => $c->target_text,
                'weight' => $c->code === 'admin_deadline' ? $hc09Weight : (float) $c->weight, 'calc_type' => $c->calc_type,
                'target_value' => $c->target_value, 'unit' => $c->unit, 'is_active' => 1];
        }

        return ['month' => self::MONTH, 'template' => 'hcns', 'max_achievement' => 120, 'max_payout_rate' => '', 'criteria' => $criteria,
            'tiers' => [['min' => 95, 'mode' => 'fixed', 'rate' => 110, 'label' => 'Xuất sắc'], ['min' => 80, 'mode' => 'fixed', 'rate' => 100, 'label' => 'Đạt'],
                ['min' => 65, 'mode' => 'fixed', 'rate' => 85, 'label' => 'Cần cải thiện'], ['min' => 0, 'mode' => 'fixed', 'rate' => 0, 'label' => 'Không đạt']]];
    }

    /** Giám đốc lưu nháp trọng số HC09 = 20; Phó giám đốc duyệt từ 09/2026. */
    private function applyVersion(): int
    {
        $this->actingAs($this->director)->post(route('hr.kpi.settings.save'), $this->settingsPayload(20))->assertRedirect()->assertSessionHas('success');
        $id = (int) DB::table('hr_kpi_rule_versions')->where('status', 'draft')->value('id');
        $this->actingAs($this->director2)->post(route('hr.kpi.rules.approve', $id), ['effective_month' => self::MONTH])->assertSessionHas('success');

        return $id;
    }

    public function test_checklist_layers_match_the_evaluator(): void
    {
        $this->septemberTasks();
        $this->manualScores();
        $ev = app(HrKpiEvaluator::class);
        $eff = $ev->effective('hcns', self::MONTH);
        $scores = $ev->scores([$this->hcns->id], self::MONTH)->get($this->hcns->id, collect());
        $block = collect(app(HcnsKpiChecklist::class)->forUser((int) $this->hcns->id, self::MONTH, $eff['template'], $eff['criteria'], $scores, $this->director)['blocks'])->firstWhere('code', 'admin_deadline');

        // Số đo 1/4 = 25%; xem trước 25 ÷ 80 = 31,25%; chính thức (chưa duyệt) = nhập tay 100%.
        $this->assertSame([1, 4, 25.0], [$block['summary']['numerator'], $block['summary']['denominator'], $block['summary']['value']]);
        $this->assertSame(31.25, $block['preview_achievement']);
        $this->assertSame(round(11.11 * 0.3125, 2), $block['preview_points']);
        $this->assertSame(['manual', 100.0], [$block['official_score']['from'], (float) $block['official_score']['achievement']]);
        $this->assertSame(100.0, $ev->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns')['percent']);
    }

    public function test_settings_for_hcns_save_a_draft_and_keep_live_settings(): void
    {
        $this->septemberTasks();
        $this->manualScores();
        $hc09 = DB::table('hr_kpi_criteria')->where('code', 'admin_deadline')->first();
        $before = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');

        $this->actingAs($this->hr)->post(route('hr.kpi.settings.save'), $this->settingsPayload(20))->assertForbidden();
        $this->actingAs($this->director)->post(route('hr.kpi.settings.save'), $this->settingsPayload(20))->assertRedirect()->assertSessionHas('success');

        $this->assertSame((float) $hc09->weight, (float) DB::table('hr_kpi_criteria')->where('id', $hc09->id)->value('weight'), 'Cài đặt gốc không đổi.');
        $draft = DB::table('hr_kpi_rule_versions')->where('status', 'draft')->first();
        $this->assertSame(20.0, (float) json_decode($draft->config, true)['criteria'][(string) $hc09->id]['weight']);
        $this->assertSame($before, app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns'), 'Bản nháp không đổi điểm chính thức.');

        $html = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'tab' => 'settings', 'month' => self::MONTH]))->assertOk()->getContent();
        $this->assertStringContainsString('data-hk-settings-source', $html);
        $this->assertStringContainsString('data-hk-total="preview"', $html);
    }

    public function test_applied_version_changes_only_months_from_its_effective_month(): void
    {
        $this->septemberTasks();
        $this->manualScores();
        $this->applyVersion();

        // HC09 nguồn 25% → 31,25% đạt, trọng số 20; HC07 không phát sinh (đã xác nhận) → loại; các tiêu chí khác 100%.
        // Σw = 11,12 + 6 × 11,11 + 20 = 97,78; điểm = 77,78 + 20 × 31,25% = 84,03 → 85,94% → bậc “Đạt” 100%.
        $sep = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');
        $this->assertSame([85.94, 1.0, 'ok'], [$sep['percent'], $sep['rate'], $sep['status']]);

        // Tháng 08 chưa có phiên bản duyệt: vẫn cài đặt gốc + nhập tay (chưa nhập → chưa đủ).
        $aug = app(HrKpiEvaluator::class)->effective('hcns', '2026-08');
        $this->assertNull($aug['version']);
        $this->assertSame(11.11, (float) $aug['criteria']->firstWhere('code', 'admin_deadline')->weight);
    }

    public function test_payroll_uses_same_kpi_snapshots_on_approval_and_reopen_releases_it(): void
    {
        $this->septemberTasks();
        $this->manualScores();
        $this->applyVersion();

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH])->assertRedirect();
        $line = DB::table('hr_payroll_lines')->where('user_id', $this->hcns->id)->first();
        // Cùng bộ chấm: 85,94% → hệ số 100%; lương KPI = 2.000.000 × 1 × 1,00 (không làm tròn nghìn, không nhân % lần hai).
        $this->assertSame([85.94, 1.0, 2000000.0], [(float) $line->kpi_percent, (float) $line->kpi_rate, (float) $line->kpi_pay]);

        $this->actingAs($this->hr)->post(route('hr.payroll.approve', $line->period_id))->assertForbidden();
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $line->period_id))->assertSessionHas('success');
        $snap = HcnsKpiSnapshots::current((int) $this->hcns->id, self::MONTH);
        $this->assertNotNull($snap);
        $this->assertSame([85.94, 1.0], [(float) $snap->total, (float) $snap->rate]);
        $payload = json_decode($snap->payload, true);
        $this->assertSame(20.0, (float) collect($payload['rows'])->firstWhere('criterion.code', 'admin_deadline')['criterion']['weight']);
        $this->assertSame('1/4', collect($payload['checklist']['blocks'])->firstWhere('code', 'admin_deadline')['summary']['numerator'].'/'.collect($payload['checklist']['blocks'])->firstWhere('code', 'admin_deadline')['summary']['denominator']);

        // Nguồn bị đổi sau chốt (giả lập sửa thẳng DB) → kỳ đã chốt vẫn trả đúng số đã chụp.
        DB::table('tasks')->where('assignee_id', $this->hcns->id)->update(['last_submitted_at' => '2026-09-10 10:00:00']);
        $this->assertSame(85.94, app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns')['percent']);
        $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH]))->assertOk()->assertSee('data-hk-snapshot', false);

        // Mở khóa → ảnh chụp không còn hiện hành, KPI tính lại từ nguồn hiện tại (4/4 đúng hạn → 120 trần… 100% → tổng 100%).
        $this->actingAs($this->director)->post(route('hr.payroll.reopen', $line->period_id), ['reason' => 'Kiểm tra lại số liệu'])->assertRedirect();
        $this->assertNull(HcnsKpiSnapshots::current((int) $this->hcns->id, self::MONTH));
        $this->assertSame(1, DB::table('hr_kpi_period_snapshots')->where('user_id', $this->hcns->id)->whereNotNull('superseded_at')->count());
        $this->assertNotSame(85.94, app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns')['percent']);
    }

    public function test_incomplete_source_blocks_payroll_approval(): void
    {
        $this->septemberTasks();
        $this->task(['status' => 'submitted', 'first_submitted_at' => '2026-09-14 10:00:00', 'last_submitted_at' => '2026-09-14 10:00:00', 'submission_count' => 1]);
        $this->manualScores();
        $this->applyVersion();

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);
        $line = DB::table('hr_payroll_lines')->where('user_id', $this->hcns->id)->first();
        $this->assertNull($line->kpi_rate);
        $this->assertStringContainsString('Chưa có KPI', (string) $line->warnings);
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $line->period_id))->assertSessionHas('error');
        $this->assertSame('draft', DB::table('hr_payroll_periods')->where('id', $line->period_id)->value('status'));
        $this->assertNull(HcnsKpiSnapshots::current((int) $this->hcns->id, self::MONTH));
    }

    public function test_hcns_evidence_is_private_and_served_only_to_people_who_can_view_the_task(): void
    {
        Carbon::setTestNow('2026-09-10 09:00:00');
        $id = $this->task();
        $outsider = $this->person('sales', 'Người ngoài');
        $this->actingAs($this->hcns)->post(route('tasks.submit', $id), ['result_note' => 'Đã nộp hồ sơ',
            'result_attachments' => [UploadedFile::fake()->create('bien-nhan.pdf', 20, 'application/pdf')]])->assertRedirect();

        $file = DB::table('task_attachments')->where('task_id', $id)->first();
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->file_path);
        Storage::disk('public')->assertMissing($file->file_path);
        $this->actingAs($this->hcns)->get(route('tasks.attachments.file', [$id, $file->id]))->assertOk();
        $this->actingAs($this->reviewer)->get(route('tasks.attachments.file', [$id, $file->id, 'download' => 1]))->assertOk()->assertDownload('bien-nhan.pdf');
        $this->actingAs($outsider)->get(route('tasks.attachments.file', [$id, $file->id]))->assertForbidden();
        $this->assertStringNotContainsString('storage/'.$file->file_path, $this->actingAs($this->hcns)->get(route('tasks.show', $id))->getContent());
    }

    public function test_old_public_files_move_safely_when_a_task_becomes_hcns_or_by_command(): void
    {
        $normal = (int) DB::table('tasks')->insertGetId(['title' => 'HCNS-KPI-TEST việc thường', 'requester_id' => $this->director->id,
            'assignee_id' => $this->hcns->id, 'priority' => 'medium', 'status' => 'new', 'progress_percent' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('public')->put('task_attachments/'.$normal.'/cu.pdf', 'noi-dung-cu');
        $fileId = (int) DB::table('task_attachments')->insertGetId(['task_id' => $normal, 'uploaded_by' => $this->hcns->id, 'type' => 'task',
            'file_name' => 'cu.pdf', 'file_path' => 'task_attachments/'.$normal.'/cu.pdf', 'file_mime' => 'application/pdf', 'file_size' => 11, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->hcns)->get(route('tasks.attachments.file', [$normal, $fileId]))->assertOk();

        $this->actingAs($this->director)->put(route('tasks.update', $normal), ['title' => 'HCNS-KPI-TEST việc thường', 'assignee_ids' => [$this->hcns->id],
            'priority' => 'medium', 'status' => 'new', 'hcns_task' => '1', 'hcns_procedure_type' => 'permit', 'due_at' => '2026-10-20', 'due_time' => '17:00',
            'approver_id' => $this->reviewer->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('local', DB::table('task_attachments')->where('id', $fileId)->value('disk'));
        Storage::disk('local')->assertExists('task_attachments/'.$normal.'/cu.pdf');
        Storage::disk('public')->assertMissing('task_attachments/'.$normal.'/cu.pdf');
        $this->assertSame('noi-dung-cu', $this->actingAs($this->hcns)->get(route('tasks.attachments.file', [$normal, $fileId]))->streamedContent());

        // Thủ tục HCNS cũ còn tệp public: lệnh mặc định chỉ liệt kê, --apply mới chuyển.
        $old = $this->task();
        Storage::disk('public')->put('task_attachments/'.$old.'/x.pdf', 'abc');
        $oldFile = (int) DB::table('task_attachments')->insertGetId(['task_id' => $old, 'uploaded_by' => $this->hcns->id, 'type' => 'result',
            'file_name' => 'x.pdf', 'file_path' => 'task_attachments/'.$old.'/x.pdf', 'file_mime' => 'application/pdf', 'file_size' => 3, 'created_at' => now(), 'updated_at' => now()]);
        Artisan::call('hcns:secure-task-attachments');
        $this->assertNull(DB::table('task_attachments')->where('id', $oldFile)->value('disk'));
        Artisan::call('hcns:secure-task-attachments', ['--apply' => true]);
        $this->assertSame('local', DB::table('task_attachments')->where('id', $oldFile)->value('disk'));
        Storage::disk('public')->assertMissing('task_attachments/'.$old.'/x.pdf');
    }
}
