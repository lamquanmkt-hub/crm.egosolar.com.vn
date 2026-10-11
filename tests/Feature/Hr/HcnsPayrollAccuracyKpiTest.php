<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Kpi\HcnsKpiChecklist;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Kpi\PayrollAudits;
use App\Services\Hr\Kpi\Sources\PayrollAccuracySource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI HCNS HC04 — đối soát độ chính xác bảng lương HR: chưa kiểm, kiểm không lỗi, lỗi chưa xác nhận, trả bổ sung/gửi lại,
 * nhiều lỗi cùng đơn vị, lỗi đã sửa, mẫu số 0, khác công ty, tự xác nhận, quyền đọc tệp, sửa sau chốt và lớp điểm chính thức.
 * Tháng 2031 để không chạm kỳ lương/demo có sẵn trong DB test.
 */
final class HcnsPayrollAccuracyKpiTest extends TestCase
{
    use DatabaseTransactions;

    private const PAYROLL = '2031-03';

    private const KPI = '2031-04';

    private User $director;

    private User $owner;

    private User $checker;

    private User $reviewer;

    private User $sales;

    private int $periodId;

    /** @var array<int, int> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2031-05-05 09:00:00'));
        Storage::fake('local');
        // Phiên bản quy tắc có sẵn trong DB test (demo) không được ảnh hưởng ca thử — rollback sau mỗi test.
        DB::table('hr_kpi_rule_versions')->delete();
        $this->director = $this->person('management', 'Giám đốc');
        $this->owner = $this->person('hr', 'HCNS chịu KPI');
        $this->checker = $this->person('hr', 'Người nhập');
        $this->reviewer = $this->person('hr', 'Người kiểm');
        $this->sales = $this->person('sales', 'Sales');
        DB::table('hr_salary_profiles')->insert(['user_id' => $this->owner->id, 'effective_month' => '2031-01', 'base_salary' => 8000000,
            'kpi_salary' => 2000000, 'kpi_template' => 'hcns', 'insurance_enabled' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->periodId = (int) DB::table('hr_payroll_periods')->insertGetId(['payroll_month' => self::PAYROLL, 'status' => 'draft', 'standard_days' => 22,
            'created_at' => now(), 'updated_at' => now()]);
        foreach (['An', 'Bình', 'Chi', 'Dũng'] as $i => $name) {
            $u = $this->person('sales', 'NV '.$name);
            $this->lines[$i] = (int) DB::table('hr_payroll_lines')->insertGetId([
                'period_id' => $this->periodId, 'user_id' => $u->id, 'employee_name' => 'HCNS-KPI-TEST NV '.$name, 'department_name' => $i < 2 ? 'Kinh doanh' : 'Kỹ thuật',
                'standard_days' => 22, 'paid_days' => 22, 'base_salary' => 10000000, 'base_pay' => 10000000, 'kpi_salary' => 2000000, 'kpi_rate' => 1,
                'kpi_pay' => 2000000, 'gross_income' => 12000000, 'insurance_employee' => 1050000, 'pit' => 100000, 'net_salary' => 10850000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnspa-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function applyRules(array $payroll): void
    {
        DB::table('hr_kpi_rule_versions')->insert(['template_code' => 'hcns', 'version' => (int) DB::table('hr_kpi_rule_versions')->max('version') + 1, 'status' => 'applied',
            'effective_month' => '2031-01', 'rules' => json_encode(['payroll_accuracy' => $payroll + ['adoption' => 'preview_only']]),
            'created_by' => $this->director->id, 'approved_by' => $this->director->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function createAudit(array $extra = []): object
    {
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.store'), $extra + [
            'period_id' => $this->periodId, 'title' => 'Đối soát 03/2031', 'scope' => 'all', 'owner_id' => $this->owner->id,
            'checker_id' => $this->checker->id, 'reviewer_id' => $this->reviewer->id,
        ])->assertRedirect();

        return DB::table('hr_payroll_audits')->orderByDesc('id')->first();
    }

    private function allComponents(): array
    {
        return array_keys(PayrollAudits::components());
    }

    /** @param array<int, string|null> $results line index => result */
    private function saveResults(object $audit, array $results, ?User $as = null)
    {
        $payload = ['result' => [], 'components' => []];
        foreach ($results as $i => $result) {
            $payload['result'][$this->lines[$i]] = $result;
            $payload['components'][$this->lines[$i]] = $this->allComponents();
        }

        return $this->actingAs($as ?? $this->checker)->post(route('hr.payroll.audits.items', $audit->id), $payload);
    }

    private function item(object $audit, int $i): object
    {
        return DB::table('hr_payroll_audit_items')->where('audit_id', $audit->id)->where('line_id', $this->lines[$i])->first();
    }

    private function addError(object $audit, int $i, string $component, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->checker)->post(route('hr.payroll.audits.errors.store', [$audit->id, $this->item($audit, $i)->id]), $extra + [
            'component' => $component, 'error_type' => 'kpi', 'description' => 'Sai hệ số KPI (thử)', 'expected_amount' => 1800000,
            'recorded_amount' => 2000000, 'source_url' => '/nhan-su/kpi?month=2031-03', 'resolution_status' => 'open',
        ]);
    }

    private function confirm(object $audit, ?User $as = null, string $value = 'pass')
    {
        return $this->actingAs($as ?? $this->reviewer)->post(route('hr.payroll.audits.kpi.confirm', $audit->id), [
            'checks' => ['scope' => $value, 'results' => $value, 'errors' => $value], 'note' => $value === 'fail' ? 'Thiếu chứng cứ' : null,
        ]);
    }

    private function measure(): array
    {
        $rules = app(HcnsKpiRules::class)->forMonth(self::KPI)['rules'];
        $src = app(PayrollAccuracySource::class);
        $rows = $src->rows((int) $this->owner->id, self::KPI, $rules);

        return [$rows, $src->summarize($rows, self::KPI, now(), $rules)];
    }

    public function test_no_audit_is_not_100_percent_and_cannot_be_marked_not_applicable(): void
    {
        [$rows, $sum] = $this->measure();
        $this->assertSame([], $rows);
        $this->assertSame('no_items', $sum['data_status']);
        $this->assertNull($sum['value']);
        $this->assertFalse($sum['allow_na'], 'Có bảng lương kỳ 03 → chưa đối soát là chưa kiểm, không phải không phát sinh.');
        $this->assertStringContainsString('chưa có đợt đối soát', $sum['measure_text']);

        // Phiên bản áp dụng chọn nguồn + người chấm đánh “không phát sinh” vẫn không thành N/A.
        $this->applyRules(['adoption' => 'source']);
        DB::table('hr_kpi_rule_versions')->update(['rules' => json_encode(['payroll_accuracy' => ['adoption' => 'source']])]);
        $ev = app(HrKpiEvaluator::class);
        $eff = $ev->effective('hcns', self::KPI);
        $criterion = $eff['criteria']->firstWhere('code', 'payroll_accuracy');
        DB::table('hr_kpi_scores')->insert(['user_id' => $this->owner->id, 'payroll_month' => self::KPI, 'criterion_id' => $criterion->id, 'achievement' => null,
            'not_applicable' => 1, 'note' => 'Không phát sinh', 'updated_by' => $this->director->id, 'created_at' => now(), 'updated_at' => now()]);
        $over = $ev->sourceOverrides($eff['template'], $eff['criteria'], (int) $this->owner->id, self::KPI, $ev->scores([(int) $this->owner->id], self::KPI)->get($this->owner->id, collect()));
        $this->assertNull($over[(int) $criterion->id]['achievement']);
        $this->assertFalse($over[(int) $criterion->id]['not_applicable']);
    }

    public function test_line_unit_counts_one_wrong_per_line_and_undetermined_stays_missing(): void
    {
        $audit = $this->createAudit();
        $this->assertSame(PayrollAudits::SCOPES['all'], PayrollAudits::SCOPES[$audit->scope]);
        $this->saveResults($audit, [0 => 'ok', 1 => 'error', 2 => 'ok', 3 => 'undetermined'])->assertSessionHasNoErrors();
        // Hai lỗi cùng một dòng (khác khoản) — đơn vị dòng vẫn chỉ 1 phần sai.
        $this->addError($audit, 1, 'kpi_pay')->assertSessionHasNoErrors();
        $this->addError($audit, 1, 'pit', ['error_type' => 'tax', 'description' => 'Thuế TNCN tính thiếu người phụ thuộc'])->assertSessionHasNoErrors();

        [$rows, $sum] = $this->measure();
        $this->assertSame(['missing' => 4], array_filter(array_count_values(array_column($rows, 'kpi_state'))), 'Chưa gửi kiểm → chưa tính.');
        $this->assertNull($sum['value']);

        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id))->assertSessionHasNoErrors();
        [$rows, $sum] = $this->measure();
        $this->assertSame(['pending' => 3, 'missing' => 1], array_count_values(array_column($rows, 'kpi_state')), 'Lỗi chưa được xác nhận → chờ, chưa tính.');
        $this->assertSame('incomplete', $sum['data_status']);

        $this->confirm($audit)->assertSessionHasNoErrors();
        [$rows, $sum] = $this->measure();
        $states = array_count_values(array_column($rows, 'kpi_state'));
        $this->assertSame(['earned' => 2, 'lost' => 1, 'missing' => 1], $states);
        $this->assertSame(2, $sum['numerator']);
        $this->assertSame(3, $sum['denominator']);
        $this->assertSame(66.67, $sum['value']);
        $this->assertSame('incomplete', $sum['data_status'], 'Dòng “chưa xác định” làm số đo chưa đủ.');
        $this->assertStringContainsString('2/3 dòng đã kiểm không lỗi = 66,67%', $sum['measure_text']);
        $lost = collect($rows)->firstWhere('kpi_state', 'lost');
        $this->assertStringContainsString('2 lỗi đã xác nhận', $lost['reason']);
    }

    public function test_component_unit_counts_distinct_wrong_components(): void
    {
        $this->applyRules(['unit' => 'component', 'coverage' => 'sample']);
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'ok', 1 => 'error']);
        // Hai lỗi cùng khoản KPI → một khoản sai; thêm lỗi khoản thuế → hai khoản sai.
        $this->addError($audit, 1, 'kpi_pay');
        $this->addError($audit, 1, 'kpi_pay', ['description' => 'Sai thêm phần thưởng KPI quý']);
        $this->addError($audit, 1, 'pit', ['error_type' => 'tax']);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);

        [$rows, $sum] = $this->measure();
        $n = count($this->allComponents());
        $this->assertSame(2 * $n, $sum['denominator']);
        $this->assertSame(2 * $n - 2, $sum['numerator']);
        $this->assertSame('excluded', collect($rows)->firstWhere('source_id', -$audit->id)['kpi_state'], 'Kiểm mẫu: 2 dòng chưa kiểm không vào mẫu số.');
        $this->assertStringContainsString('khoản đã kiểm không lỗi', $sum['measure_text']);
    }

    public function test_return_resubmit_and_edit_after_confirmation_invalidate_review(): void
    {
        $audit = $this->createAudit(['scope' => 'department', 'scope_department' => 'Kinh doanh']);
        $this->saveResults($audit, [0 => 'ok', 1 => 'ok']);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $first = DB::table('hr_kpi_source_reviews')->where('source_type', PayrollAudits::SOURCE_TYPE)->where('source_id', $audit->id)->first();

        $this->actingAs($this->reviewer)->post(route('hr.payroll.audits.kpi.return', $audit->id), ['reason' => 'Thiếu bảng đối chiếu chấm công'])->assertSessionHasNoErrors();
        $this->assertSame('returned', DB::table('hr_kpi_source_reviews')->where('id', $first->id)->value('status'));
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.files.upload', $audit->id), ['files' => [UploadedFile::fake()->image('doi-chieu.png')]])->assertSessionHasNoErrors();
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id))->assertSessionHasNoErrors();
        $again = DB::table('hr_kpi_source_reviews')->where('id', $first->id)->first();
        $this->assertSame(2, (int) $again->submission_count);
        $this->assertSame((string) $first->first_submitted_at, (string) $again->first_submitted_at);

        $this->confirm($audit)->assertSessionHasNoErrors();
        [, $sum] = $this->measure();
        $this->assertSame(100.0, $sum['value']);

        // Sửa sau xác nhận (ghi lỗi) → bản xác nhận hết hiệu lực, không tự sống lại.
        $this->saveResults($audit, [0 => 'ok', 1 => 'error']);
        $this->addError($audit, 1, 'paid_days', ['error_type' => 'attendance']);
        $this->assertSame('changed', DB::table('hr_kpi_source_reviews')->where('id', $first->id)->value('status'));
        [$rows, $sum] = $this->measure();
        $this->assertSame(['pending' => 2], array_count_values(array_column($rows, 'kpi_state')));
        $this->assertTrue(DB::table('hr_kpi_source_review_events')->where('review_id', $first->id)->where('action', 'invalidated')->exists());
    }

    public function test_fixed_and_removed_errors_keep_history_and_follow_rule(): void
    {
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'error', 1 => 'ok', 2 => 'ok', 3 => 'ok']);
        $this->addError($audit, 0, 'kpi_pay', ['resolution_status' => 'fixed', 'resolution' => 'Sửa hệ số KPI, tính lại dòng'])->assertSessionHasNoErrors();
        $error = DB::table('hr_payroll_audit_errors')->where('audit_id', $audit->id)->first();
        $this->assertNotNull($error->fixed_at);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);

        [, $sum] = $this->measure();
        $this->assertSame(['numerator' => 3, 'denominator' => 4], ['numerator' => $sum['numerator'], 'denominator' => $sum['denominator']], 'Nháp: lỗi đã sửa vẫn tính sai.');
        $this->assertSame('complete', $sum['data_status']);

        $this->applyRules(['fixed_errors' => 'exclude_fixed_before_approval']);
        [$rows, $sum] = $this->measure();
        $this->assertSame(4, $sum['numerator'], 'Phương án “lỗi đã sửa trước khi duyệt lương không tính”.');
        $this->assertStringContainsString('Lỗi đã sửa trước khi duyệt lương', collect($rows)->firstWhere('line_id', $this->lines[0])['reason']);
        DB::table('hr_kpi_rule_versions')->delete();

        // Gỡ lỗi (ghi nhầm) cần lý do; lỗi vẫn còn trong DB và lịch sử, dòng phải đổi kết quả rồi gửi lại.
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.errors.remove', [$audit->id, $error->id]), ['remove_reason' => ''])->assertSessionHasErrors('remove_reason');
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.errors.remove', [$audit->id, $error->id]), ['remove_reason' => 'Ghi nhầm dòng'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('hr_payroll_audit_errors')->where('id', $error->id)->value('removed_at'));
        $this->assertSame(1, DB::table('hr_payroll_audit_errors')->where('audit_id', $audit->id)->count());
        $this->assertTrue(DB::table('hr_kpi_source_logs')->where('source_type', PayrollAudits::SOURCE_TYPE)->where('source_id', $audit->id)->where('action', 'error_removed')->exists());
        $this->assertNotSame([], app(PayrollAudits::class)->missing(DB::table('hr_payroll_audits')->find($audit->id)), 'Dòng “Có lỗi” không còn lỗi → chưa gửi được.');
        $this->saveResults($audit, [0 => 'ok', 1 => 'ok', 2 => 'ok', 3 => 'ok'])->assertSessionHasNoErrors();
        // Dòng đã từng có lỗi không bỏ kết quả về “chưa kiểm” được (giữ lịch sử).
        $this->saveResults($audit, [0 => null])->assertSessionHasErrors('result');
    }

    public function test_zero_denominator_when_payroll_line_changed_after_check(): void
    {
        $audit = $this->createAudit(['scope' => 'lines', 'line_ids' => [$this->lines[2]]]);
        $this->saveResults($audit, [2 => 'ok']);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);
        [, $sum] = $this->measure();
        $this->assertSame(100.0, $sum['value']);

        // Bảng lương tính lại sau khi kiểm → phần “đúng” cũ không còn khớp số mới: thiếu dữ liệu, mẫu số 0, không phải 100%.
        DB::table('hr_payroll_lines')->where('id', $this->lines[2])->update(['net_salary' => 10900000]);
        [$rows, $sum] = $this->measure();
        $this->assertSame('missing', $rows[0]['kpi_state']);
        $this->assertStringContainsString('đã đổi sau lần kiểm', $rows[0]['reason']);
        $this->assertSame(0, $sum['denominator']);
        $this->assertNull($sum['value']);
        $this->assertStringContainsString('mẫu số 0', $sum['measure_text']);
    }

    public function test_other_company_audit_is_not_counted_or_visible(): void
    {
        $audit = $this->createAudit();
        DB::table('hr_payroll_audits')->where('id', $audit->id)->update(['company_id' => 999]);
        [$rows] = $this->measure();
        $this->assertSame([], $rows);
        $this->actingAs($this->reviewer)->get(route('hr.payroll.audits.show', $audit->id))->assertNotFound();
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.items', $audit->id), ['result' => []])->assertNotFound();
    }

    public function test_independent_review_blocks_self_confirmation(): void
    {
        // Người kiểm không được trùng HCNS chịu KPI hay người nhập.
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.store'), ['period_id' => $this->periodId, 'title' => 'X', 'scope' => 'all',
            'owner_id' => $this->owner->id, 'checker_id' => $this->checker->id, 'reviewer_id' => $this->owner->id])->assertSessionHasErrors('reviewer_id');
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.store'), ['period_id' => $this->periodId, 'title' => 'X', 'scope' => 'all',
            'owner_id' => $this->owner->id, 'checker_id' => $this->checker->id, 'reviewer_id' => $this->checker->id])->assertSessionHasErrors('reviewer_id');

        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'ok']);
        // Giám đốc nhập và gửi thay → không tự xác nhận được dù có quyền duyệt.
        $this->actingAs($this->director)->post(route('hr.payroll.audits.kpi.submit', $audit->id))->assertSessionHasNoErrors();
        $this->confirm($audit, $this->director)->assertForbidden();
        $this->confirm($audit, $this->checker)->assertForbidden();
        $this->confirm($audit, $this->owner)->assertForbidden();
        $this->confirm($audit, $this->sales)->assertForbidden();
        $this->assertSame('submitted', DB::table('hr_kpi_source_reviews')->where('source_id', $audit->id)->where('source_type', PayrollAudits::SOURCE_TYPE)->value('status'));
        // HCNS chịu KPI không phải người nhập → không ghi/sửa kết quả của chính mình.
        $this->saveResults($audit, [0 => 'error'], $this->owner)->assertForbidden();
        $this->confirm($audit)->assertSessionHasNoErrors();
    }

    public function test_evidence_files_follow_payroll_permission(): void
    {
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'error']);
        $this->addError($audit, 0, 'kpi_pay', ['files' => [UploadedFile::fake()->image('bang-cham-cong.png')]])->assertSessionHasNoErrors();
        $file = DB::table('hr_kpi_source_files')->where('source_type', PayrollAudits::ERROR_FILE_TYPE)->first();
        $this->assertNotNull($file);
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($this->reviewer)->get(route('hr.payroll.audits.files.show', [$audit->id, $file->id]))->assertOk();
        $this->actingAs($this->sales)->get(route('hr.payroll.audits.files.show', [$audit->id, $file->id]))->assertForbidden();
        $other = $this->createAudit(['scope' => 'department', 'scope_department' => 'Kỹ thuật']);
        $this->actingAs($this->reviewer)->get(route('hr.payroll.audits.files.show', [$other->id, $file->id]))->assertNotFound();
        $this->actingAs($this->sales)->get(route('hr.payroll.audits.show', $audit->id))->assertForbidden();
        $this->actingAs($this->sales)->post(route('hr.payroll.audits.store'), ['period_id' => $this->periodId])->assertForbidden();
        $html = $this->actingAs($this->reviewer)->get(route('hr.payroll.audits.show', $audit->id))->assertOk()->getContent();
        $this->assertStringNotContainsString('/storage/hcns-kpi', $html);
        $this->assertStringContainsString(route('hr.payroll.audits.files.show', [$audit->id, $file->id]), $html);

        // Xem tệp rồi thao tác bị chặn → quay về trang đợt đối soát (không mở lại tệp), trang hiện lý do và giữ ô đã nhập.
        $this->actingAs($this->checker)->get(route('hr.payroll.audits.show', $audit->id))->assertOk();
        $this->actingAs($this->checker)->get(route('hr.payroll.audits.files.show', [$audit->id, $file->id]))->assertOk();
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.items', $audit->id), ['result' => [$this->lines[0] => 'ok'],
            'components' => [$this->lines[0] => $this->allComponents()], 'item_note' => [$this->lines[0] => 'Ghi chú dòng']])
            ->assertRedirect(route('hr.payroll.audits.show', $audit->id))->assertSessionHasErrors('result');
        $this->actingAs($this->checker)->get(route('hr.payroll.audits.show', $audit->id))->assertOk()->assertSee('còn 1 lỗi', false);
    }

    public function test_closed_kpi_period_blocks_every_write_path(): void
    {
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'error', 1 => 'ok']);
        $this->addError($audit, 0, 'kpi_pay', ['files' => [UploadedFile::fake()->image('cc.png')]]);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);
        $error = DB::table('hr_payroll_audit_errors')->where('audit_id', $audit->id)->first();
        $file = DB::table('hr_kpi_source_files')->where('source_type', PayrollAudits::ERROR_FILE_TYPE)->where('source_id', $error->id)->first();
        $before = app(PayrollAudits::class)->fingerprint(DB::table('hr_payroll_audits')->find($audit->id));

        // Kỳ KPI 04/2031 đã chốt lương (bảng lương 04 duyệt) → đợt đối soát lương 03 bị khóa.
        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::KPI, 'status' => 'approved', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(self::KPI, PayrollAudits::lockedMonth(DB::table('hr_payroll_audits')->find($audit->id)));
        $this->saveResults($audit, [0 => 'ok', 1 => 'error'])->assertSessionHasErrors('kpi');
        $this->addError($audit, 1, 'pit')->assertSessionHasErrors('kpi');
        $this->actingAs($this->checker)->put(route('hr.payroll.audits.errors.update', [$audit->id, $error->id]), ['component' => 'kpi_pay', 'error_type' => 'other',
            'description' => 'Đổi sau chốt', 'resolution_status' => 'open'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.errors.remove', [$audit->id, $error->id]), ['remove_reason' => 'Gỡ sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->checker)->delete(route('hr.payroll.audits.files.delete', [$audit->id, $file->id]))->assertSessionHasErrors('kpi');
        $this->actingAs($this->director)->put(route('hr.payroll.audits.update', $audit->id), ['title' => 'Đổi', 'owner_id' => $this->checker->id, 'checker_id' => $this->checker->id,
            'reviewer_id' => $this->reviewer->id, 'scope' => 'all'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->reviewer)->post(route('hr.payroll.audits.kpi.return', $audit->id), ['reason' => 'Trả sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.cancel', $audit->id), ['cancel_reason' => 'Hủy sau chốt'])->assertSessionHasErrors('kpi');

        $this->assertSame($before, app(PayrollAudits::class)->fingerprint(DB::table('hr_payroll_audits')->find($audit->id)));
        $this->assertSame('confirmed', DB::table('hr_kpi_source_reviews')->where('source_id', $audit->id)->where('source_type', PayrollAudits::SOURCE_TYPE)->value('status'));
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_official_score_only_with_applied_source_adoption(): void
    {
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'ok', 1 => 'ok', 2 => 'ok', 3 => 'ok']);
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);
        $ev = app(HrKpiEvaluator::class);
        $eff = $ev->effective('hcns', self::KPI);
        $criterion = $eff['criteria']->firstWhere('code', 'payroll_accuracy');
        DB::table('hr_kpi_scores')->insert(['user_id' => $this->owner->id, 'payroll_month' => self::KPI, 'criterion_id' => $criterion->id, 'achievement' => 50,
            'not_applicable' => 0, 'updated_by' => $this->director->id, 'created_at' => now(), 'updated_at' => now()]);
        $scores = fn () => $ev->scores([(int) $this->owner->id], self::KPI)->get($this->owner->id, collect());

        // Chưa có phiên bản áp dụng: nguồn chỉ xem trước, chính thức vẫn là số nhập tay.
        $this->assertSame([], $ev->sourceOverrides($eff['template'], $eff['criteria'], (int) $this->owner->id, self::KPI, $scores()));
        $checklist = app(HcnsKpiChecklist::class)->forUser((int) $this->owner->id, self::KPI, $eff['template'], $eff['criteria'], $scores(), $this->reviewer);
        $block = collect($checklist['blocks'])->firstWhere('code', 'payroll_accuracy');
        $this->assertTrue($block['auto']);
        $this->assertSame(100.0, $block['summary']['value']);
        $this->assertFalse($block['official']);
        $this->assertStringContainsString('Nhập tay: 50% đạt', $block['official_score']['text']);

        // Áp dụng chọn nguồn: 4/4 = 100% ÷ mục tiêu 99 → 101,01% đạt; bỏ qua số nhập tay.
        $this->applyRules(['adoption' => 'source']);
        DB::table('hr_kpi_rule_versions')->update(['rules' => json_encode(['payroll_accuracy' => ['adoption' => 'source']])]);
        $eff = $ev->effective('hcns', self::KPI);
        $over = $ev->sourceOverrides($eff['template'], $eff['criteria'], (int) $this->owner->id, self::KPI, $scores());
        $this->assertEqualsWithDelta(101.01, $over[(int) $criterion->id]['achievement'], 0.01);

        // Nguồn còn chờ (đợt mới chưa gửi) → chưa đủ, không quay về số nhập tay.
        $this->createAudit(['scope' => 'lines', 'line_ids' => [$this->lines[0]]]);
        $over = $ev->sourceOverrides($eff['template'], $eff['criteria'], (int) $this->owner->id, self::KPI, $scores());
        $this->assertNull($over[(int) $criterion->id]['achievement']);
    }

    public function test_closing_kpi_payroll_snapshots_hc04_and_later_source_changes_do_not_alter_it(): void
    {
        $audit = $this->createAudit(['scope' => 'department', 'scope_department' => 'Kinh doanh']);
        $this->saveResults($audit, [0 => 'ok', 1 => 'error']);
        $this->addError($audit, 1, 'kpi_pay');
        $this->actingAs($this->checker)->post(route('hr.payroll.audits.kpi.submit', $audit->id));
        $this->confirm($audit);
        // Kỳ lương 04/2031 (kỳ KPI chứa đợt đối soát) có dòng của HCNS chịu KPI → chốt lương chụp KPI.
        $kpiPeriod = (int) DB::table('hr_payroll_periods')->insertGetId(['payroll_month' => self::KPI, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_payroll_lines')->insert(['period_id' => $kpiPeriod, 'user_id' => $this->owner->id, 'employee_name' => $this->owner->name, 'kpi_template' => 'hcns',
            'kpi_salary' => 2000000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_payroll_periods')->where('id', $kpiPeriod)->update(['status' => 'approved', 'approved_at' => now()]);
        $this->assertSame(1, app(\App\Services\Hr\Kpi\HcnsKpiSnapshots::class)->capture($kpiPeriod, (int) $this->director->id));

        $snap = \App\Services\Hr\Kpi\HcnsKpiSnapshots::current((int) $this->owner->id, self::KPI);
        $block = collect(json_decode((string) $snap->payload, true)['checklist']['blocks'])->firstWhere('code', 'payroll_accuracy');
        $this->assertSame('1/2 dòng đã kiểm không lỗi = 50% (kỳ lương 03/2031)', $block['summary']['measure_text']);
        $this->assertSame(['earned' => 1, 'lost' => 1], array_count_values(array_column($block['rows'], 'kpi_state')));
        // Sau chốt mọi đường ghi bị khóa; ảnh chụp không đổi.
        $this->saveResults($audit, [0 => 'ok', 1 => 'ok'])->assertSessionHasErrors('kpi');
        $this->assertSame($snap->payload, \App\Services\Hr\Kpi\HcnsKpiSnapshots::current((int) $this->owner->id, self::KPI)->payload);
    }

    public function test_payroll_page_and_kpi_checklist_show_audit_with_links(): void
    {
        $audit = $this->createAudit();
        $this->saveResults($audit, [0 => 'ok', 1 => 'error']);
        $this->addError($audit, 1, 'kpi_pay');

        $page = $this->actingAs($this->reviewer)->get(route('hr.payroll.index', ['month' => self::PAYROLL]))->assertOk()->getContent();
        $this->assertStringContainsString('data-pa-audits', $page);
        $this->assertStringContainsString($audit->code, $page);
        $this->assertStringContainsString('KPI tháng 04/2031', $page);

        $show = $this->actingAs($this->checker)->get(route('hr.payroll.audits.show', $audit->id))->assertOk()->getContent();
        $this->assertStringContainsString('id="kpi-hc04"', $show);
        $this->assertStringContainsString('data-pa-line="'.$this->lines[1].'"', $show);
        $this->assertStringContainsString('Sai hệ số KPI (thử)', $show);

        $kpi = $this->actingAs($this->reviewer)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::KPI, 'employee' => $this->owner->id]))->assertOk()->getContent();
        $this->assertStringContainsString('data-hk-block="payroll_accuracy"', $kpi);
        $this->assertStringContainsString(route('hr.payroll.audits.show', $audit->id).'#pa-line-'.$this->lines[1], $kpi);
        $this->assertStringContainsString('Đợt chưa gửi kiểm', $kpi);
    }
}
