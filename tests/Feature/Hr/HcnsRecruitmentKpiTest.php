<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Kpi\HcnsKpiChecklist;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Kpi\RecruitmentKpi;
use App\Services\Hr\Kpi\Sources\ProbationQuitSource;
use App\Services\Hr\Kpi\Sources\RecruitmentCostSource;
use App\Services\Hr\Kpi\Sources\RecruitmentTimeSource;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI HCNS — HC01 thời gian tuyển dụng, HC02 chi phí tuyển dụng, HC03 nghỉ trong thử việc (đợt hcns-kpi P5).
 * Luồng thật qua trang Tuyển dụng; dữ liệu giả trong transaction trên egosolar_test, tệp trên disk giả.
 */
final class HcnsRecruitmentKpiTest extends TestCase
{
    use DatabaseTransactions;

    private User $hcns;

    private User $reviewer;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(RecruitmentKpi::costsReady(), 'DB test chưa áp migration tuyển dụng HC01–HC03.');
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-10-09 09:00:00'));
        $this->hcns = $this->person('hr', 'HCNS tuyển dụng');
        $this->reviewer = $this->person('hr', 'Trưởng phòng HCNS');
        $this->director = $this->person('management', 'Giám đốc');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnsrec-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function rules(array $override = []): array
    {
        return array_replace_recursive(app(HcnsKpiRules::class)->defaults(), $override);
    }

    /** Yêu cầu tuyển đã duyệt, bật theo dõi KPI với mốc tiếp nhận. */
    private function request(int $quantity = 1, string $received = '2026-10-01 08:00', array $extra = []): int
    {
        $this->actingAs($this->hcns)->post(route('hr.recruitment.requests.store'), $extra + ['department' => 'Kỹ thuật', 'position' => 'HCNS-KPI-TEST Kỹ sư '.Str::random(3),
            'quantity' => $quantity, 'approval_status' => 'approved'])->assertRedirect();
        $id = (int) DB::table('hr_recruitment_requests')->orderByDesc('id')->value('id');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $id), ['kpi_tracked' => '1', 'owner_id' => $this->hcns->id,
            'reviewer_id' => $this->reviewer->id, 'request_received_at' => $received])->assertSessionHasNoErrors();

        return $id;
    }

    private function candidate(int $requestId, string $name = 'Ứng viên', ?string $offerStart = null): int
    {
        $id = (int) DB::table('hr_recruitment_candidates')->insertGetId(['recruitment_request_id' => $requestId, 'full_name' => 'HCNS-KPI-TEST '.$name,
            'status' => 'hired', 'created_at' => now(), 'updated_at' => now()]);
        if ($offerStart) {
            DB::table('hr_recruitment_offers')->insert(['candidate_id' => $id, 'start_date' => $offerStart, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function hire(int $requestId, int $candidateId): int
    {
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $requestId), ['candidate_id' => $candidateId])->assertSessionHasNoErrors()->assertRedirect();

        return (int) DB::table('hr_recruitment_hires')->orderByDesc('id')->value('id');
    }

    private function start(int $hireId, string $date, ?User $employee = null, array $extra = [])
    {
        $hire = DB::table('hr_recruitment_hires')->find($hireId);

        return $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $hireId), $extra + [
            'candidate_id' => $hire->candidate_id, 'employee_user_id' => ($employee ?? $this->person('sales', 'Nhân viên mới'))->id,
            'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'status' => 'started', 'actual_start_date' => $date,
        ]);
    }

    private function evidence(int $hireId): void
    {
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.files.upload', $hireId),
            ['attachments' => [UploadedFile::fake()->createWithContent('quyet-dinh-nhan-viec.txt', 'TEST quyết định nhận việc')]])->assertSessionHasNoErrors();
    }

    private function confirmHire(int $hireId, string $criterion = 'recruit_time'): void
    {
        $checks = $criterion === 'recruit_time' ? ['link' => 'pass', 'received' => 'pass', 'start_date' => 'pass'] : ['dates' => 'pass', 'outcome' => 'pass', 'evidence' => 'pass'];
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$hireId, $criterion, 'submit']))->assertSessionHas('success');
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.hires.kpi', [$hireId, $criterion, 'confirm']), ['checks' => $checks])->assertSessionHas('success');
    }

    private function startedConfirmed(int $requestId, string $date, string $name = 'Ứng viên'): int
    {
        $id = $this->hire($requestId, $this->candidate($requestId, $name));
        $this->start($id, $date)->assertSessionHasNoErrors();
        $this->evidence($id);
        $this->confirmHire($id);

        return $id;
    }

    private function review(int $id, string $criterion, string $type = RecruitmentKpi::HIRE_TYPE): ?object
    {
        return DB::table('hr_kpi_source_reviews')->where('source_type', $type)->where('source_id', $id)->where('criterion_code', $criterion)->first();
    }

    private function timeRows(string $month, ?Carbon $now = null): array
    {
        return app(RecruitmentTimeSource::class)->rows((int) $this->hcns->id, $month, $this->rules(), $now ?? now());
    }

    private function doc(string $type, string $status, int $amount, ?string $approvedAt): int
    {
        return (int) DB::table('payment_requests')->insertGetId(['code' => 'TEST-'.Str::upper(Str::random(6)), 'doc_type' => $type, 'created_by' => $this->director->id,
            'receiver_name' => 'HCNS-KPI-TEST TopCV', 'reason' => 'Phí đăng tin tuyển dụng', 'payment_content' => 'Phí đăng tin tuyển dụng', 'amount' => $amount,
            'status' => $status, 'accounting_approved_at' => $approvedAt, 'company_id' => EgoCompanyLock::id(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function link(int $requestId, int $docId, int $amount)
    {
        return $this->actingAs($this->hcns)->post(route('hr.recruitment.costs.store', $requestId), ['payment_request_id' => $docId, 'allocated_amount' => $amount]);
    }

    private function confirmCost(int $costId): void
    {
        $this->actingAs($this->hcns)->post(route('hr.recruitment.costs.kpi', [$costId, 'submit']))->assertSessionHas('success');
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.costs.kpi', [$costId, 'confirm']), ['checks' => ['document' => 'pass', 'allocation' => 'pass']])->assertSessionHas('success');
    }

    private function outsider(): User
    {
        $role = 'kpi_outsider_'.Str::lower(Str::random(6));
        $user = $this->person($role, 'Người ngoài');
        DB::table('roles')->where('name', $role)->update(['page_access_enabled' => 1]);

        return $user;
    }

    // ---------------------------------------------------------------- tests

    public function test_request_kpi_setup_records_approval_needs_independent_reviewer_and_real_received_time(): void
    {
        $this->actingAs($this->hcns)->post(route('hr.recruitment.requests.store'), ['department' => 'KD', 'position' => 'HCNS-KPI-TEST Sales', 'quantity' => 1, 'approval_status' => 'approved'])->assertRedirect();
        $req = DB::table('hr_recruitment_requests')->orderByDesc('id')->first();
        $this->assertSame([(int) $this->hcns->id, '2026-10-09 09:00:00'], [(int) $req->approved_by, (string) $req->approved_at], 'Server ghi người/giờ duyệt.');
        $this->actingAs($this->hcns)->get(route('hr.recruitment.requests.kpi', $req->id))->assertOk()->assertSee('data-rk-tracked="0"', false);

        $base = ['kpi_tracked' => '1', 'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'request_received_at' => '2026-10-01 08:00'];
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req->id), ['reviewer_id' => $this->hcns->id] + $base)->assertSessionHasErrors('reviewer_id');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req->id), ['request_received_at' => '2026-10-10 08:00'] + $base)->assertSessionHasErrors('request_received_at');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req->id), $base)->assertSessionHasNoErrors();
        $req = DB::table('hr_recruitment_requests')->find($req->id);
        $this->assertSame([1, '2026-10-01 08:00:00', EgoCompanyLock::id()], [(int) $req->kpi_tracked, (string) $req->request_received_at, (int) $req->company_id]);
        $this->actingAs($this->reviewer)->put(route('hr.recruitment.requests.kpi.update', $req->id), $base)->assertForbidden();
        $this->actingAs($this->outsider())->get(route('hr.recruitment.requests.kpi', $req->id))->assertForbidden();

        // Bỏ duyệt → xóa dấu duyệt; yêu cầu chưa duyệt không thêm lượt tuyển.
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.update', $req->id), ['department' => 'KD', 'position' => 'HCNS-KPI-TEST Sales', 'quantity' => 1, 'approval_status' => 'pending'])->assertRedirect();
        $this->assertNull(DB::table('hr_recruitment_requests')->where('id', $req->id)->value('approved_at'));
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $req->id), ['candidate_id' => $this->candidate((int) $req->id)])->assertSessionHasErrors('candidate_id');
    }

    public function test_hc01_hire_uses_actual_start_not_expected_and_needs_independent_confirmation(): void
    {
        $req = $this->request();
        $other = $this->request();
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $req), ['candidate_id' => $this->candidate($other, 'Ứng viên yêu cầu khác')])->assertSessionHasErrors('candidate_id');

        $cand = $this->candidate($req, 'Nguyễn Văn A', '2026-10-15');
        $id = $this->hire($req, $cand);
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $req), ['candidate_id' => $cand])->assertSessionHasErrors('candidate_id');
        $hire = DB::table('hr_recruitment_hires')->find($id);
        $this->assertSame(['pending', '2026-10-15', (int) $this->hcns->id, EgoCompanyLock::id()], [$hire->status, (string) $hire->expected_start_date, (int) $hire->owner_id, (int) $hire->company_id]);
        $row = $this->timeRows('2026-10')[0];
        $this->assertSame('waiting', $row['kpi_state']);
        $this->assertStringContainsString('ngày dự kiến không dùng', $row['reason']);

        $employee = $this->person('sales', 'Nhân viên mới A');
        $this->start($id, '2026-10-08', null, ['employee_user_id' => ''])->assertSessionHasErrors('employee_user_id');
        $this->start($id, '2026-09-30', $employee)->assertSessionHasErrors('actual_start_date');
        $this->start($id, '2026-10-12', $employee)->assertSessionHasErrors('actual_start_date');
        $this->start($id, '2026-10-08', $employee, ['reviewer_id' => $employee->id])->assertSessionHasErrors('reviewer_id');
        $this->start($id, '2026-10-08', $employee)->assertSessionHasNoErrors();

        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'submit']))->assertSessionHas('error');
        $this->evidence($id);
        $file = DB::table('hr_recruitment_attachments')->where('entity', 'hires')->where('record_id', $id)->first();
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);
        $this->actingAs($this->reviewer)->get(route('hr.recruitment.attachments.preview', ['hires', $id, $file->id]))->assertOk();
        $this->actingAs($this->reviewer)->get(route('hr.recruitment.attachments.download', ['hires', $id, $file->id]))->assertDownload('quyet-dinh-nhan-viec.txt');
        $this->actingAs($this->outsider())->get(route('hr.recruitment.attachments.download', ['hires', $id, $file->id]))->assertForbidden();

        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'submit']))->assertSessionHas('success');
        $this->assertSame('pending', $this->timeRows('2026-10')[0]['kpi_state']);
        $pass = ['checks' => ['link' => 'pass', 'received' => 'pass', 'start_date' => 'pass']];
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'confirm']), $pass)->assertForbidden();
        $this->actingAs($employee)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'confirm']), $pass)->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'confirm']), ['checks' => ['link' => 'pass']])->assertSessionHasErrors('checks');
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'confirm']), $pass)->assertSessionHas('success');

        $row = $this->timeRows('2026-10')[0];
        $this->assertSame(['counted', 7, 'Được tính — 7 ngày'], [$row['kpi_state'], $row['days'], $row['state_label']], '01/10 08:00 → 08/10 = 7 ngày lịch.');
        $this->actingAs($this->reviewer)->get(route('hr.recruitment.hires.show', $id))->assertOk()->assertSee('data-kpi-review-status="confirmed"', false);
        $this->actingAs($this->hcns)->get(route('hr.recruitment.onboarding'))->assertOk();
        $this->actingAs($this->hcns)->get(route('hr.employees.show', $employee->id))->assertOk()->assertSee('data-emp-kpi-hires', false);
    }

    public function test_hc01_multiple_slots_no_show_cancel_and_cross_month_average(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        $req = $this->request(4, '2026-10-01 08:00');
        $a = $this->startedConfirmed($req, '2026-10-21', 'A');
        $g = $this->startedConfirmed($req, '2026-10-26', 'G');
        $c = $this->startedConfirmed($req, '2026-11-04', 'C');
        $b = $this->hire($req, $this->candidate($req, 'B'));
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $req), ['candidate_id' => $this->candidate($req, 'Thừa suất')])->assertSessionHasErrors('candidate_id');

        $hb = DB::table('hr_recruitment_hires')->find($b);
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $b), ['candidate_id' => $hb->candidate_id, 'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id,
            'status' => 'no_show'])->assertSessionHasErrors('cancel_reason');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $b), ['candidate_id' => $hb->candidate_id, 'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id,
            'status' => 'no_show', 'cancel_reason' => 'Ứng viên nhận việc nơi khác'])->assertSessionHasNoErrors();
        $e = $this->hire($req, $this->candidate($req, 'E', '2026-10-20'));
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.store', $req), ['candidate_id' => $this->candidate($req, 'F')])->assertSessionHasErrors('candidate_id');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.update', $req), ['department' => 'Kỹ thuật', 'position' => 'X', 'quantity' => 3, 'approval_status' => 'approved'])
            ->assertSessionHasErrors('quantity');

        $oct = collect($this->timeRows('2026-10'))->keyBy('source_id');
        $this->assertSame(['counted', 'counted', 'excluded'], [$oct[$a]['kpi_state'], $oct[$g]['kpi_state'], $oct[$e]['kpi_state']]);
        $this->assertStringContainsString('Chưa nhận việc trong tháng', $oct[$e]['reason']);
        $this->assertArrayNotHasKey($c, $oct->all(), 'Lượt nhận việc tháng 11 không vào tháng 10.');
        $sum = app(RecruitmentTimeSource::class)->summarize($oct->values()->all(), '2026-10', now());
        $this->assertSame([45, 2, 22.5, 'complete'], [$sum['numerator'], $sum['denominator'], $sum['value'], $sum['data_status']], '(20 + 25) ÷ 2 = 22,5 ngày.');

        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.cancel', $e), ['cancel_reason' => 'Dừng tuyển vị trí'])->assertSessionHas('success');
        $nov = collect($this->timeRows('2026-11'))->keyBy('source_id');
        $this->assertSame(['counted', 34, 'excluded'], [$nov[$c]['kpi_state'], $nov[$c]['days'], $nov[$b]['kpi_state']]);
        $this->assertStringContainsString('không nhận việc', $nov[$b]['reason']);
        $this->assertSame('cancelled', DB::table('hr_recruitment_hires')->where('id', $e)->value('status'));
        $this->assertTrue(DB::table('hr_kpi_source_logs')->where('source_type', RecruitmentKpi::HIRE_TYPE)->where('source_id', $e)->where('action', 'cancelled')->exists());
    }

    public function test_hc01_change_after_confirmation_needs_resubmit_even_if_reverted(): void
    {
        $req = $this->request();
        $id = $this->startedConfirmed($req, '2026-10-08');
        $employee = DB::table('hr_recruitment_hires')->where('id', $id)->value('employee_user_id');
        $base = ['candidate_id' => DB::table('hr_recruitment_hires')->where('id', $id)->value('candidate_id'), 'employee_user_id' => $employee,
            'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'status' => 'started'];

        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $id), ['actual_start_date' => '2026-10-07'] + $base)->assertSessionHasNoErrors();
        $this->assertSame('changed', $this->review($id, 'recruit_time')->status);
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $id), ['actual_start_date' => '2026-10-08'] + $base)->assertSessionHasNoErrors();
        $this->assertSame('changed', $this->review($id, 'recruit_time')->status, 'Đổi về như cũ không làm xác nhận sống lại.');
        $this->assertSame('pending', $this->timeRows('2026-10')[0]['kpi_state']);
        $this->confirmHire($id);
        $this->assertSame(2, (int) $this->review($id, 'recruit_time')->submission_count);

        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $id), ['status' => 'no_show', 'cancel_reason' => 'x'] + $base)->assertSessionHasErrors('status');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req), ['kpi_tracked' => '1', 'owner_id' => $this->hcns->id,
            'reviewer_id' => $this->reviewer->id, 'request_received_at' => '2026-10-02 08:00'])->assertSessionHasNoErrors();
        $this->assertSame('changed', $this->review($id, 'recruit_time')->status, 'Đổi mốc tiếp nhận của yêu cầu → lượt phải gửi lại.');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req), ['kpi_tracked' => '0', 'owner_id' => $this->hcns->id,
            'reviewer_id' => $this->reviewer->id, 'request_received_at' => '2026-10-02 08:00'])->assertSessionHasErrors('kpi_tracked');
    }

    public function test_hc02_cost_only_actual_spend_allocation_advance_refund_and_hires(): void
    {
        $reqA = $this->request(2);
        $reqB = $this->request(1);
        $pr1 = $this->doc('payment_request', 'accounting_approved', 3000000, '2026-10-05 10:00:00');
        $this->link($reqA, $pr1, 2000000)->assertSessionHasNoErrors();
        $this->link($reqB, $pr1, 1500000)->assertSessionHasErrors('allocated_amount');
        $this->link($reqB, $pr1, 1000000)->assertSessionHasNoErrors();
        $this->link($reqA, $pr1, 1)->assertSessionHasErrors('payment_request_id');
        $this->link($reqA, $this->doc('salary_advance', 'accounting_approved', 100000, '2026-10-05 10:00:00'), 100000)->assertSessionHasErrors('payment_request_id');

        $adv = $this->doc('advance', 'accounting_approved', 2000000, '2026-10-03 10:00:00');
        DB::table('advance_settlements')->insert(['payment_request_id' => $adv, 'created_by' => $this->director->id, 'status' => 'waiting_settlement', 'created_at' => now(), 'updated_at' => now()]);
        $this->link($reqA, $adv, 2000000)->assertSessionHasNoErrors();
        $advCost = (int) DB::table('hr_recruitment_costs')->where('payment_request_id', $adv)->value('id');
        $this->actingAs($this->hcns)->post(route('hr.recruitment.costs.kpi', [$advCost, 'submit']))->assertSessionHas('error');
        $row = collect(app(RecruitmentCostSource::class)->rows((int) $this->hcns->id, '2026-10', $this->rules(), now()))->firstWhere('source_id', $advCost);
        $this->assertSame('missing', $row['kpi_state']);
        $this->assertStringContainsString('chưa quyết toán', $row['reason']);
        DB::table('advance_settlements')->where('payment_request_id', $adv)->update(['status' => 'completed', 'actual_spent_amount' => 1200000, 'completed_at' => '2026-10-06 15:00:00']);
        $row = collect(app(RecruitmentCostSource::class)->rows((int) $this->hcns->id, '2026-10', $this->rules(), now()))->firstWhere('source_id', $advCost);
        $this->assertStringContainsString('vượt số thực chi', $row['reason'], 'Tạm ứng chỉ tính số đã quyết toán.');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.costs.update', $advCost), ['allocated_amount' => 1200000, 'reviewer_id' => $this->reviewer->id])->assertSessionHasNoErrors();

        $refund = $this->doc('refund_request', 'accounting_approved', 500000, '2026-10-07 10:00:00');
        $this->link($reqA, $refund, 500000)->assertSessionHasNoErrors();
        $pending = $this->doc('payment_request', 'submitted', 900000, null);
        $this->link($reqA, $pending, 900000)->assertSessionHasNoErrors();

        foreach (DB::table('hr_recruitment_costs')->where('payment_request_id', '!=', $pending)->pluck('id') as $costId) {
            $this->confirmCost((int) $costId);
        }
        $rows = app(RecruitmentCostSource::class)->rows((int) $this->hcns->id, '2026-10', $this->rules(), now());
        $byDoc = collect($rows)->where('group', 'cost')->keyBy('doc_id');
        $this->assertSame([-500000.0, 'counted'], [$byDoc[$refund]['amount'], $byDoc[$refund]['kpi_state']], 'Hoàn tiền trừ ra.');
        $this->assertSame('missing', $byDoc[$pending]['kpi_state']);
        $this->assertStringContainsString('chưa được kế toán duyệt chi', $byDoc[$pending]['reason']);
        $sum = app(RecruitmentCostSource::class)->summarize($rows, '2026-10', now());
        $this->assertSame([3700000.0, 0, null, 'incomplete'], [$sum['numerator'], $sum['denominator'], $sum['value'], $sum['data_status']], '2.000.000 + 1.000.000 + 1.200.000 − 500.000; chưa có vị trí.');
        DB::table('hr_recruitment_costs')->where('payment_request_id', $pending)->update(['removed_at' => now(), 'remove_reason' => 'test']);
        $sum = app(RecruitmentCostSource::class)->summarize(app(RecruitmentCostSource::class)->rows((int) $this->hcns->id, '2026-10', $this->rules(), now()), '2026-10', now());
        $this->assertSame(['no_items', false], [$sum['data_status'], $sum['allow_na']], 'Có chi phí mà chưa có vị trí tuyển thành công → chưa đủ, không cho “không phát sinh”.');
        $this->assertStringContainsString('Chưa có vị trí tuyển thành công', $sum['measure_text']);

        $this->startedConfirmed($reqA, '2026-10-08');
        $this->startedConfirmed($reqB, '2026-10-08');
        $sum = app(RecruitmentCostSource::class)->summarize(app(RecruitmentCostSource::class)->rows((int) $this->hcns->id, '2026-10', $this->rules(), now()), '2026-10', now());
        $this->assertSame([3700000.0, 2, 1850000.0], [$sum['numerator'], $sum['denominator'], $sum['value']], '3.700.000 ÷ 2 vị trí.');

        $pr1A = (int) DB::table('hr_recruitment_costs')->where('request_id', $reqA)->where('payment_request_id', $pr1)->value('id');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.costs.update', $pr1A), ['allocated_amount' => 1900000, 'reviewer_id' => $this->hcns->id])->assertSessionHasErrors('reviewer_id');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.costs.update', $pr1A), ['allocated_amount' => 1900000, 'reviewer_id' => $this->reviewer->id])->assertSessionHasNoErrors();
        $this->assertSame('changed', $this->review($pr1A, 'recruit_cost', RecruitmentKpi::COST_TYPE)->status);
        $this->actingAs($this->hcns)->post(route('hr.recruitment.costs.remove', $pr1A), ['remove_reason' => 'Phân bổ nhầm đợt'])->assertSessionHas('success');
        $this->assertSame(['adjusted', 'removed'], DB::table('hr_kpi_source_logs')->where('source_type', RecruitmentKpi::COST_TYPE)->where('source_id', $pr1A)
            ->whereIn('action', ['adjusted', 'removed'])->orderBy('id')->pluck('action')->all());
        $this->actingAs($this->reviewer)->get(route('hr.recruitment.costs.show', $pr1A))->assertOk()->assertSee('Đã gỡ', false);
        $this->actingAs($this->hcns)->get(route('hr.recruitment.requests.kpi', $reqA))->assertOk()->assertSee('data-rk-cost="'.$advCost.'"', false);
    }

    public function test_hc03_probation_outcomes_cohort_and_no_inference_from_inactive(): void
    {
        $req = $this->request(6, '2026-07-01 08:00');
        $make = function (string $start, string $end, ?string $outcome, array $extra = [], ?User $employee = null) use ($req) {
            $id = $this->hire($req, $this->candidate($req, 'TV '.$start));
            $this->start($id, $start, $employee)->assertSessionHasNoErrors();
            $this->evidence($id);
            $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $id), $extra + ['probation_start_date' => $start, 'probation_end_date' => $end, 'probation_outcome' => $outcome])
                ->assertSessionHasNoErrors();

            return $id;
        };
        $passed = $make('2026-08-01', '2026-09-30', 'passed', [], $this->director);
        $resigned = $make('2026-08-03', '2026-10-02', 'left', ['probation_left_date' => '2026-09-15', 'probation_left_type' => 'resigned', 'probation_reason' => 'Chuyển nơi ở']);
        $unknown = $make('2026-08-05', '2026-09-30', null);
        $terminated = $make('2026-08-07', '2026-10-06', 'left', ['probation_left_date' => '2026-09-20', 'probation_left_type' => 'terminated', 'probation_reason' => 'Không đạt yêu cầu']);
        $ongoing = $make('2026-10-01', '2026-11-30', 'in_probation');

        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $ongoing), ['probation_start_date' => '2026-10-01', 'probation_end_date' => '2026-11-30', 'probation_outcome' => 'passed'])
            ->assertSessionHasErrors('probation_outcome');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $ongoing), ['probation_start_date' => '2026-10-01', 'probation_end_date' => '2026-11-30', 'probation_outcome' => 'left',
            'probation_left_date' => '2026-09-25', 'probation_left_type' => 'resigned', 'probation_reason' => 'x'])->assertSessionHasErrors('probation_left_date');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $ongoing), ['probation_start_date' => '2026-10-01', 'probation_end_date' => '2026-11-30', 'probation_outcome' => 'left',
            'probation_left_date' => '2026-10-05', 'probation_left_type' => 'resigned'])->assertSessionHasErrors('probation_reason');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $ongoing), ['probation_start_date' => '2026-09-25', 'probation_end_date' => '2026-11-30'])
            ->assertSessionHasErrors('probation_start_date');

        // Nhân viên của lượt tuyển (Giám đốc) không tự xác nhận kết quả thử việc của mình.
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$passed, 'probation_quit', 'submit']))->assertSessionHas('success');
        $this->actingAs($this->director)->post(route('hr.recruitment.hires.kpi', [$passed, 'probation_quit', 'confirm']), ['checks' => ['dates' => 'pass', 'outcome' => 'pass', 'evidence' => 'pass']])->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.hires.kpi', [$passed, 'probation_quit', 'confirm']), ['checks' => ['dates' => 'pass', 'outcome' => 'pass', 'evidence' => 'pass']])->assertSessionHas('success');
        $this->confirmHire($resigned, 'probation_quit');
        $this->confirmHire($terminated, 'probation_quit');
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$unknown, 'probation_quit', 'submit']))->assertSessionHas('error');
        DB::table('users')->where('id', DB::table('hr_recruitment_hires')->where('id', $unknown)->value('employee_user_id'))->update(['is_active' => 0]);

        $rows = fn (array $r = []) => collect(app(ProbationQuitSource::class)->rows((int) $this->hcns->id, '2026-09', $this->rules($r), now()))->keyBy('source_id');
        $sep = $rows();
        $this->assertSame(['counted', 'lost', 'missing', 'lost'], [$sep[$passed]['kpi_state'], $sep[$resigned]['kpi_state'], $sep[$unknown]['kpi_state'], $sep[$terminated]['kpi_state']]);
        $this->assertStringContainsString('không suy từ tài khoản', $sep[$unknown]['reason'], 'Tài khoản ngừng hoạt động không tự thành “nghỉ”.');
        $this->assertArrayNotHasKey($ongoing, $sep->all());
        $sum = app(ProbationQuitSource::class)->summarize($sep->values()->all(), '2026-09', now());
        $this->assertSame([2, 3, 66.67, 'incomplete'], [$sum['numerator'], $sum['denominator'], $sum['value'], $sum['data_status']]);
        $only = app(ProbationQuitSource::class)->summarize($rows(['probation_quit' => ['left_types' => 'resigned_only']])->values()->all(), '2026-09', now());
        $this->assertSame([1, 3, 33.33], [$only['numerator'], $only['denominator'], $only['value']], 'Chỉ tính tự nghỉ: công ty chấm dứt vẫn trong cohort.');
        $this->assertSame('waiting', collect(app(ProbationQuitSource::class)->rows((int) $this->hcns->id, '2026-11', $this->rules(), now()))->firstWhere('source_id', $ongoing)['kpi_state']);
        $empty = app(ProbationQuitSource::class)->summarize([], '2026-12', now());
        $this->assertSame(['no_items', null], [$empty['data_status'], $empty['value']], 'Cohort 0 → chưa đủ, không tự thành 0%/100%.');
    }

    public function test_evaluator_reports_incomplete_instead_of_falling_back_and_na_needs_reason(): void
    {
        $template = app(HrKpiEvaluator::class)->template('hcns');
        $criteria = app(HrKpiEvaluator::class)->criteria((int) $template->id);
        $ids = $criteria->pluck('id', 'code');
        $manual = fn (array $extra = []) => collect($criteria->mapWithKeys(fn ($c) => [$c->id => (object) ['achievement' => 100, 'actual_value' => null, 'not_applicable' => 0]])->all())
            ->replace($extra);
        $version = fn (array $rules) => DB::table('hr_kpi_rule_versions')->insertGetId(['template_code' => 'hcns', 'version' => 800 + random_int(1, 150), 'status' => 'applied',
            'effective_month' => '2026-10', 'rules' => json_encode($this->rules($rules)), 'created_by' => $this->director->id, 'approved_by' => $this->director->id,
            'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        // Lượt tuyển đã gửi kiểm nhưng chưa xác nhận.
        $req = $this->request();
        $id = $this->hire($req, $this->candidate($req));
        $this->start($id, '2026-10-08')->assertSessionHasNoErrors();
        $this->evidence($id);
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.kpi', [$id, 'recruit_time', 'submit']));

        $v = $version([]);
        $ev = app(HrKpiEvaluator::class);
        $over = $ev->sourceOverrides($template, $criteria, (int) $this->hcns->id, '2026-10', $manual());
        $this->assertArrayNotHasKey((int) $ids['recruit_time'], $over, 'Phiên bản chưa chọn nguồn cho HC01 → giữ chấm tay.');

        DB::table('hr_kpi_rule_versions')->where('id', $v)->update(['rules' => json_encode($this->rules(['recruit_time' => ['adoption' => 'source'], 'recruit_cost' => ['adoption' => 'source']]))]);
        $over = $ev->sourceOverrides($template, $criteria, (int) $this->hcns->id, '2026-10', $manual());
        $this->assertNull($over[(int) $ids['recruit_time']]['achievement']);
        $result = $ev->score($template, $criteria, $manual(), $over);
        $this->assertSame('incomplete', $result['status'], 'Chọn nguồn mà còn chờ xác nhận → chưa đủ, không dùng 100% nhập tay.');
        $this->assertContains($criteria->firstWhere('code', 'recruit_time')->name, $result['missing']);

        // HC02: có chi phí, chưa có vị trí tuyển thành công đã xác nhận → “Không phát sinh” không được nhận.
        $this->link($req, $this->doc('payment_request', 'accounting_approved', 800000, '2026-10-05 10:00:00'), 800000)->assertSessionHasNoErrors();
        $na = (object) ['achievement' => null, 'actual_value' => null, 'not_applicable' => 1];
        $over = $ev->sourceOverrides($template, $criteria, (int) $this->hcns->id, '2026-10', $manual([(int) $ids['recruit_cost'] => $na]));
        $this->assertFalse($over[(int) $ids['recruit_cost']]['not_applicable']);
        $this->assertNull($over[(int) $ids['recruit_cost']]['achievement']);

        // N/A trên bảng KPI HCNS phải có lý do.
        $this->actingAs($this->director)->post(route('hr.kpi.store'), ['month' => '2026-10', 'template' => 'hcns',
            'scores' => [$this->hcns->id => [$ids['documents'] => ['na' => 1]]]])->assertSessionHasErrors('scores.'.$this->hcns->id.'.'.$ids['documents'].'.note');

        // Checklist: HC01–HC03 có khối nguồn; link chứng từ theo quyền module ĐNTT.
        $checklist = app(HcnsKpiChecklist::class)->forUser((int) $this->hcns->id, '2026-10', $template, $criteria, collect(), $this->hcns, now());
        $blocks = collect($checklist['blocks'])->keyBy('code');
        $this->assertTrue($blocks['recruit_time']['auto'] && $blocks['recruit_cost']['auto'] && $blocks['probation_quit']['auto']);
        $this->assertTrue($blocks['recruit_time']['official'] && ! $blocks['probation_quit']['official']);
        $costRow = collect($blocks['recruit_cost']['rows'])->firstWhere('group', 'cost');
        $this->assertTrue($costRow['doc_can_open']);
        $outsiderView = app(HcnsKpiChecklist::class)->forViewer($checklist, $this->outsider());
        $this->assertFalse(collect(collect($outsiderView['blocks'])->firstWhere('code', 'recruit_cost')['rows'])->firstWhere('group', 'cost')['doc_can_open']);
    }

    public function test_locked_payroll_month_blocks_recruitment_sources_but_not_future_hiring(): void
    {
        Carbon::setTestNow('2026-10-09 09:00:00');
        $req = $this->request(3, '2026-09-01 08:00');
        $hire = $this->startedConfirmed($req, '2026-09-20');
        $cost = (function () use ($req) {
            $this->link($req, $this->doc('payment_request', 'accounting_approved', 600000, '2026-09-10 10:00:00'), 600000)->assertSessionHasNoErrors();

            return (int) DB::table('hr_recruitment_costs')->orderByDesc('id')->value('id');
        })();
        $this->confirmCost($cost);
        DB::table('hr_payroll_periods')->insert(['payroll_month' => '2026-09', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $h = DB::table('hr_recruitment_hires')->find($hire);
        $base = ['candidate_id' => $h->candidate_id, 'employee_user_id' => $h->employee_user_id, 'owner_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'status' => 'started'];

        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $hire), ['actual_start_date' => '2026-09-21'] + $base)->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.update', $hire), ['actual_start_date' => '2026-10-02'] + $base)->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->post(route('hr.recruitment.hires.files.upload', $hire), ['attachments' => [UploadedFile::fake()->createWithContent('b.txt', 'TEST')]])->assertSessionHasErrors('kpi');
        $this->actingAs($this->reviewer)->post(route('hr.recruitment.hires.kpi', [$hire, 'recruit_time', 'return']), ['reason' => 'Trả sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.costs.update', $cost), ['allocated_amount' => 500000, 'reviewer_id' => $this->reviewer->id])->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->post(route('hr.recruitment.costs.remove', $cost), ['remove_reason' => 'Gỡ sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->put(route('hr.recruitment.requests.kpi.update', $req), ['kpi_tracked' => '1', 'owner_id' => $this->hcns->id,
            'reviewer_id' => $this->reviewer->id, 'request_received_at' => '2026-09-02 08:00'])->assertSessionHasErrors('request_received_at');
        $this->assertSame(['2026-09-20', 'confirmed', '600000.00'], [(string) DB::table('hr_recruitment_hires')->where('id', $hire)->value('actual_start_date'),
            $this->review($hire, 'recruit_time')->status, (string) DB::table('hr_recruitment_costs')->where('id', $cost)->value('allocated_amount')]);

        // Kỳ sau vẫn tuyển bình thường: lượt mới tháng 10, kết quả thử việc thuộc cohort tháng 11.
        $next = $this->startedConfirmed($req, '2026-10-05', 'Kỳ sau');
        $this->assertSame('counted', collect($this->timeRows('2026-10'))->firstWhere('source_id', $next)['kpi_state']);
        $this->actingAs($this->hcns)->put(route('hr.recruitment.hires.probation', $hire), ['probation_start_date' => '2026-09-20', 'probation_end_date' => '2026-11-19', 'probation_outcome' => 'in_probation'])
            ->assertSessionHasNoErrors();

        // Bản ghi đã gắn KPI không xóa được ở trang tuyển dụng.
        $this->actingAs($this->hcns)->delete(route('hr.recruitment.requests.destroy', $req))->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->delete(route('hr.recruitment.candidates.destroy', $h->candidate_id))->assertSessionHasErrors('kpi');
        $this->assertNotNull(DB::table('hr_recruitment_requests')->find($req));
        $this->actingAs($this->hcns)->put(route('hr.recruitment.candidates.update', $h->candidate_id), ['full_name' => 'HCNS-KPI-TEST đổi', 'recruitment_request_id' => ''])
            ->assertSessionHasErrors('recruitment_request_id');
    }
}
