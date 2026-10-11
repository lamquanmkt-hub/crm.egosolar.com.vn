<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\TechnicalKpiConfig;
use App\Models\User;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI kỹ thuật — luồng nguồn → xác nhận → điểm → danh sách nguồn (dự án KPI tự động, 10/2026).
 *
 * Dữ liệu hoàn toàn giả, nằm trong transaction (rollback sau mỗi test), chỉ chạy trên DB egosolar_test.
 * Kỳ chấm điểm dùng 2031 (sau bản sửa đổi 01/10/2026 → bộ 25/15/15/10/5/30); "hiện tại" được cố định.
 */
class TechnicalKpiSourceFlowTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const MONTH = '2031-03';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2031-05-15 10:00:00'));
        // Retain regression coverage for the historical project-handover reader.
        // Default KS01 manager-assignment policy is covered in TechnicalAssignedWorkKpiTest.
        config(['technical_kpi_standard.revisions.0.overrides.timeline.source_mode' => 'project_handover']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, array $attributes = []): User
    {
        return $this->userWithRole($role, $attributes + [
            'name' => 'KPI giả '.Str::upper(Str::random(5)),
            'email' => 'kpi-'.Str::lower(Str::random(12)).'@example.test',
        ]);
    }

    private function evaluator(?string $month = self::MONTH): KpiStandardEvaluator
    {
        return app(KpiStandardEvaluator::class)->useConfig(null, $month);
    }

    private function kpiResult(int $userId, array $months = [self::MONTH], ?KpiStandardEvaluator $evaluator = null): array
    {
        $evaluator ??= $this->evaluator($months[0]);
        $data = $evaluator->collect($userId, $months);

        return $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments'], $data['assigned_work']);
    }

    private function criterion(array $result, string $code): array
    {
        return collect($result['criteria'])->firstWhere('code', $code);
    }

    /** Công trình có bước thi công giao cho kỹ sư và bước nghiệm thu (tùy chọn đã duyệt bởi người có ID). */
    private function siteWithHandover(User $engineer, array $acceptance = [], array $site = [], string $assignedStep = 'construction'): int
    {
        $siteId = $this->makeSite(null, $site);
        $this->makeWorkflowAssignment($siteId, $engineer->id, [
            'step_code' => $assignedStep,
            'status' => 'approved',
            'due_at' => null,
            'approved_at' => '2031-03-01 09:00:00',
        ]);
        DB::table('project_workflow_steps')->insert(array_merge([
            'site_id' => $siteId,
            'step_code' => 'acceptance',
            'sequence' => 6,
            'status' => 'approved',
            'due_at' => '2031-03-12 17:00:00',
            'approved_at' => '2031-03-10 10:00:00',
            'approved_by' => $this->person('technical_manager')->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $acceptance));

        return $siteId;
    }

    /*
    |--------------------------------------------------------------------------
    | Bộ quy tắc theo kỳ
    |--------------------------------------------------------------------------
    */

    private function config(int $version, string $status, ?string $effective, bool $approved = true, array $criteria = []): TechnicalKpiConfig
    {
        return TechnicalKpiConfig::create([
            'version' => $version,
            'version_name' => 'Giả lập v'.$version,
            'status' => $status,
            'is_current' => $status === 'applied',
            'effective_date' => $effective,
            'salary_structure' => ['base_salary_rate' => 0.7, 'kpi_salary_rate' => 0.3, 'allow_exceed_100' => false],
            'criteria_config' => $criteria,
            'payout_tiers' => [],
            'approved_at' => $approved ? now() : null,
        ]);
    }

    public function test_config_for_old_month_is_not_replaced_by_newer_or_future_version(): void
    {
        $v1 = $this->config(1, 'archived', '2026-09-01');
        $v2 = $this->config(2, 'applied', '2026-10-01');
        $this->config(3, 'draft', '2026-11-01', approved: false);

        $this->assertSame($v1->id, TechnicalKpiConfig::getActiveForDate('2026-09-01')?->id, 'Tháng 09 giữ phiên bản cũ đã bị thay thế.');
        $this->assertSame($v2->id, TechnicalKpiConfig::getActiveForDate('2026-10-01')?->id);
        $this->assertSame($v2->id, TechnicalKpiConfig::getActiveForDate('2026-12-01')?->id, 'Bản nháp chưa duyệt không được áp dụng.');
        $this->assertNull(TechnicalKpiConfig::getActiveForDate('2026-08-01'), 'Không có bản hiệu lực thì không mượn bản tương lai.');
    }

    public function test_standard_weights_switch_from_october_2026_without_changing_september(): void
    {
        $sep = app(KpiStandardEvaluator::class)->forMonth('2026-09');
        $this->assertSame([0.30, 0.25, 0.15, 0.15, 0.15, null], array_column($sep->criteria(), 'weight'));

        $oct = app(KpiStandardEvaluator::class)->forMonth('2026-10');
        $this->assertSame([0.25, 0.15, 0.15, 0.10, 0.05, 0.30], array_column($oct->criteria(), 'weight'));
        $this->assertEqualsWithDelta(1.0, $oct->weightTotal(), 1e-9);
        $this->assertFalse(collect($oct->criteria())->firstWhere('code', 'warranty')['pending_confirmation']);
    }

    public function test_approved_september_config_is_stale_for_october_and_does_not_drop_warranty(): void
    {
        $oldCriteria = collect(config('technical_kpi_standard.criteria'))->map(fn ($c) => [
            'no' => $c['no'], 'code' => $c['code'], 'name' => $c['name'],
            'weight' => $c['weight'] ?? 0, 'is_calculated' => $c['weight'] !== null, 'status' => $c['weight'] !== null ? 'applied' : 'draft',
        ])->all();
        $this->config(1, 'applied', '2026-09-01', criteria: $oldCriteria);

        $sep = app(KpiStandardEvaluator::class)->useConfig(TechnicalKpiConfig::getActiveForDate('2026-09-01'), '2026-09');
        $this->assertFalse($sep->rule()['config_stale']);
        $this->assertNull(collect($sep->criteria())->firstWhere('code', 'warranty')['weight']);

        $oct = app(KpiStandardEvaluator::class)->useConfig(TechnicalKpiConfig::getActiveForDate('2026-10-01'), '2026-10');
        $this->assertTrue($oct->rule()['config_stale'], 'Cấu hình 09 cũ hơn bộ tiêu chí 10 phải bị đánh dấu.');
        $this->assertEqualsWithDelta(0.30, collect($oct->criteria())->firstWhere('code', 'warranty')['weight'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $oct->weightTotal(), 1e-9);
    }

    /*
    |--------------------------------------------------------------------------
    | KS01 — Tiến độ
    |--------------------------------------------------------------------------
    */

    public function test_timeline_on_time_and_late_use_confirmed_handover(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer); // duyệt 10/03, hạn 12/03 → đúng hạn
        $this->siteWithHandover($engineer, ['approved_at' => '2031-03-15 10:00:00']); // trễ

        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertSame(1, $timeline['passed']);
        $this->assertSame(2, $timeline['total']);
        $this->assertSame('fail', $timeline['status']);
        $this->assertEqualsWithDelta(0.125, $timeline['score'], 1e-9, 'Trọng số tiến độ 25% từ 10/2026.');
        $this->assertSame(['fail', 'pass'], collect($timeline['evidence'])->pluck('state')->sort()->values()->all());
    }

    public function test_self_recommitted_deadline_does_not_rescue_late_handover(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer, ['approved_at' => '2031-03-15 10:00:00', 'recommitted_due_at' => '2031-03-20 17:00:00']);

        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertSame(0, $timeline['passed']);
        $this->assertSame(1, $timeline['total']);
        $this->assertStringContainsString('không thay hạn KPI', $timeline['evidence'][0]['detail']);
    }

    public function test_manager_due_change_is_traced(): void
    {
        $engineer = $this->person('ky_thuat');
        $siteId = $this->siteWithHandover($engineer);
        $stepId = (int) DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->value('id');
        DB::table('project_workflow_events')->insert([
            'site_id' => $siteId, 'workflow_step_id' => $stepId, 'action' => 'step_assigned',
            'payload' => json_encode(['due_at' => '2031-03-12 17:00:00', 'previous_due_at' => '2031-03-08 17:00:00']),
            'created_at' => now(),
        ]);

        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertStringContainsString('người giao việc đổi hạn 1 lần', $timeline['evidence'][0]['detail']);
        $this->assertSame('pass', $timeline['evidence'][0]['state']);
    }

    public function test_missing_deadline_or_unconfirmed_handover_is_never_counted_as_pass(): void
    {
        $engineer = $this->person('ky_thuat');
        // 1) Không có hạn ở đâu cả.
        $this->siteWithHandover($engineer, ['due_at' => null]);
        // 2) Nghiệm thu do hệ thống tự đánh dấu khi chuyển dữ liệu (không người duyệt), không có ngày cũ.
        $this->siteWithHandover($engineer, ['approved_by' => null], ['target_completion_at' => '2031-03-05']);
        // 3) Có ngày bàn giao nhập tay nhưng nghiệm thu chưa duyệt.
        $this->siteWithHandover($engineer, ['status' => 'submitted', 'approved_at' => null, 'approved_by' => null], ['handover_at' => '2031-03-09']);

        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertNull($timeline['rate'], 'Thiếu nguồn không được thành đạt.');
        $this->assertSame('no_data', $timeline['status']);
        $states = collect($timeline['evidence'])->pluck('state')->all();
        $this->assertNotContains('pass', $states);
        $this->assertContains('missing', $states);
        $this->assertNotEmpty($timeline['missing']);
    }

    public function test_open_overdue_stays_listed_in_later_months_and_policy_controls_denominator(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer, ['status' => 'in_progress', 'due_at' => '2031-02-10 17:00:00', 'approved_at' => null, 'approved_by' => null]);

        foreach (['2031-03', '2031-04'] as $month) {
            $timeline = $this->criterion($this->kpiResult($engineer->id, [$month]), 'timeline');
            $this->assertSame(1, $timeline['open_overdue'], 'Việc quá hạn còn mở không được biến mất ở tháng '.$month);
            $this->assertSame('overdue', $timeline['evidence'][0]['state']);
            $this->assertNull($timeline['rate'], 'Mặc định chưa đưa vào mẫu số (chờ D07).');
        }

        config(['technical_kpi_standard.timeline.open_overdue_policy' => 'count_as_late']);
        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertSame(0, $timeline['passed']);
        $this->assertSame(1, $timeline['total']);
    }

    public function test_survey_only_assignment_is_not_counted_for_installation_timeline(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer, [], [], 'survey');

        $timeline = $this->criterion($this->kpiResult($engineer->id), 'timeline');
        $this->assertNull($timeline['rate']);
        $this->assertSame('info', $timeline['evidence'][0]['state']);
    }

    public function test_handover_outside_period_does_not_count_in_month(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer, ['approved_at' => '2031-04-02 10:00:00', 'due_at' => '2031-04-05 17:00:00']);

        $this->assertNull($this->criterion($this->kpiResult($engineer->id, ['2031-03']), 'timeline')['rate']);
        $this->assertSame(1, $this->criterion($this->kpiResult($engineer->id, ['2031-04']), 'timeline')['passed']);
    }

    public function test_same_site_in_workflow_and_legacy_project_counts_once_per_quarter(): void
    {
        $engineer = $this->person('ky_thuat');
        $siteId = $this->siteWithHandover($engineer, ['approved_at' => '2031-01-10 10:00:00', 'due_at' => '2031-01-12 17:00:00']);
        DB::table('project_test_projects')->insert([
            'code' => 'DA-GIA-'.Str::upper(Str::random(4)), 'name' => 'Dự án giả', 'created_by' => $engineer->id,
            'legacy_site_id' => $siteId, 'company_id' => $this->companyId(), 'lead_technician_id' => $engineer->id,
            'status' => 'completed', 'installed_at' => '2031-02-05', 'target_completion_at' => '2031-02-10',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $timeline = $this->criterion($this->kpiResult($engineer->id, ['2031-01', '2031-02', '2031-03']), 'timeline');
        $this->assertSame(1, $timeline['total'], 'Một công trình ở hai nguồn chỉ tính một lần trong quý.');
    }

    /*
    |--------------------------------------------------------------------------
    | KS06 — Bảo hành (30%)
    |--------------------------------------------------------------------------
    */

    private function ticket(User $engineer, array $overrides = []): int
    {
        $id = (int) DB::table('solar_maintenance_schedules')->insertGetId(array_merge([
            'schedule_code' => 'BH-'.Str::upper(Str::random(6)),
            'company_id' => $this->companyId(),
            'type' => 'incident',
            'status' => 'completed',
            'scheduled_date' => '2031-03-10',
            'assigned_to' => $engineer->id,
            'customer_reported_at' => '2031-03-10 08:00:00',
            'first_responded_at' => '2031-03-10 08:05:00',
            'execution_finished_at' => '2031-03-11 08:00:00',
            'complaint_status' => 'none',
            'complaint_checked_by' => $this->person('technical_manager')->id,
            'complaint_checked_at' => '2031-03-12 09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        DB::table('solar_maintenance_assignees')->insert([
            'maintenance_schedule_id' => $id, 'user_id' => $engineer->id, 'role' => 'leader',
            'assignment_role' => 'leader', 'is_leader' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_warranty_sla_boundaries_and_complaints(): void
    {
        $cases = [
            'đúng biên 5 phút / 24 giờ' => [[], 'pass'],
            'phản hồi trễ 1 giây' => [['first_responded_at' => '2031-03-10 08:05:01'], 'fail'],
            'hoàn tất trễ 1 phút' => [['execution_finished_at' => '2031-03-11 08:01:00'], 'fail'],
            'có khiếu nại' => [['complaint_status' => 'has_complaint', 'complaint_note' => 'Khách phàn nàn giả lập'], 'fail'],
            'chưa kiểm tra khiếu nại' => [['complaint_status' => null, 'complaint_checked_by' => null, 'complaint_checked_at' => null], 'missing'],
            'thiếu mốc phản hồi' => [['first_responded_at' => null], 'missing'],
            'quá 24 giờ chưa hoàn tất' => [['status' => 'in_progress', 'execution_finished_at' => null, 'complaint_status' => null, 'complaint_checked_by' => null], 'fail'],
            'thiếu giờ khách báo' => [['customer_reported_at' => null], 'missing'],
            'phiếu đã hủy' => [['status' => 'cancelled', 'cancelled_at' => '2031-03-10 09:00:00'], 'excluded'],
        ];

        foreach ($cases as $label => [$overrides, $expected]) {
            $engineer = $this->person('ky_thuat');
            $this->ticket($engineer, $overrides);
            $warranty = $this->criterion($this->kpiResult($engineer->id), 'warranty');
            $this->assertSame($expected, $warranty['evidence'][0]['state'] ?? null, $label);
            if ($expected === 'missing' || $expected === 'excluded') {
                $this->assertNull($warranty['rate'], $label.': thiếu dữ liệu không được thành đạt/0.');
            }
        }
    }

    public function test_warranty_types_outside_scope_and_other_engineers_are_not_counted(): void
    {
        $engineer = $this->person('ky_thuat');
        $other = $this->person('ky_thuat');
        $this->ticket($engineer, ['type' => 'periodic']);
        $this->ticket($other);

        $warranty = $this->criterion($this->kpiResult($engineer->id), 'warranty');
        $this->assertSame([], $warranty['evidence']);
        $this->assertSame('no_data', $warranty['status']);
    }

    public function test_total_stays_incomplete_without_warranty_source_and_is_traceable_with_it(): void
    {
        $engineer = $this->person('ky_thuat');
        foreach (['timeline', 'quality', 'survey', 'hse', 'evn_app'] as $code) {
            DB::table('technical_kpi_monthly_scores')->insert([
                'user_id' => $engineer->id, 'payroll_month' => self::MONTH, 'criterion_code' => $code,
                'plan_value' => 2, 'actual_value' => 2, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $without = $this->kpiResult($engineer->id);
        $this->assertNull($without['total'], 'Không được bỏ 30% bảo hành để ra tổng 100%.');
        $this->assertContains('THỜI GIAN XỬ LÝ BẢO HÀNH SP', $without['missing_summary']);
        $this->assertEqualsWithDelta(0.70, $without['partial_weight'], 1e-9);

        $this->ticket($engineer);
        $with = $this->kpiResult($engineer->id);
        $this->assertEqualsWithDelta(1.0, $with['total'], 1e-9);
        $this->assertSame('Tài liệu chuẩn hiệu lực 01/10/2026 — chưa có phiên bản cấu hình được duyệt có hiệu lực cho kỳ này', $with['rule']['label']);
        $this->assertNotEmpty($this->criterion($with, 'warranty')['evidence']);
    }

    public function test_warranty_sla_form_records_timestamps_and_enforces_roles(): void
    {
        $engineer = $this->person('ky_thuat');
        $manager = $this->person('technical_manager');
        $outsider = $this->person('sales');
        $id = $this->ticket($engineer, [
            'status' => 'in_progress', 'customer_reported_at' => null, 'first_responded_at' => null,
            'execution_finished_at' => null, 'complaint_status' => null, 'complaint_checked_by' => null, 'complaint_checked_at' => null,
        ]);
        $url = route('projects-unified.maintenance.warranty-sla.store', $id);

        $this->actingAs($outsider)->post($url, ['action' => 'responded'])->assertForbidden();
        $this->actingAs($engineer)->post($url, ['action' => 'responded'])->assertStatus(422); // chưa có giờ khách báo

        $this->actingAs($engineer)->post($url, ['action' => 'reported', 'customer_reported_at' => '2031-05-15 09:58:00'])->assertRedirect();
        $this->actingAs($engineer)->post($url, ['action' => 'reported', 'customer_reported_at' => '2031-05-15 09:00:00'])->assertStatus(422);
        $this->actingAs($engineer)->post($url, ['action' => 'responded'])->assertRedirect();
        $row = DB::table('solar_maintenance_schedules')->find($id);
        $this->assertSame('2031-05-15 10:00:00', (string) $row->first_responded_at, 'Mốc phản hồi = thời điểm thao tác thật.');
        $this->assertSame($engineer->id, (int) $row->first_responded_by);

        $this->actingAs($engineer)->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertForbidden();
        $this->actingAs($manager)->post($url, ['action' => 'verify', 'complaint_status' => 'has_complaint'])->assertSessionHasErrors('complaint_note');
        $this->actingAs($manager)->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertStatus(422);
        $this->actingAs($engineer)->post(route('projects-unified.maintenance.approval.complete', $id), ['result_note' => 'Đã sửa xong theo ca kiểm thử'])->assertRedirect();
        $this->actingAs($manager)->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertRedirect();
        $row = DB::table('solar_maintenance_schedules')->find($id);
        $this->assertSame('none', $row->complaint_status);
        $this->assertSame($manager->id, (int) $row->complaint_checked_by);

        $this->actingAs($manager)->post($url, ['action' => 'reported', 'customer_reported_at' => '2031-05-15 08:00:00'])->assertStatus(422);
        $this->assertSame(3, DB::table('solar_maintenance_audit_logs')->where('maintenance_schedule_id', $id)->where('action', 'like', 'warranty_sla_%')->count());
    }

    public function test_maintenance_detail_shows_warranty_sla_block_only_for_warranty_types(): void
    {
        $engineer = $this->person('ky_thuat');
        $manager = $this->person('technical_manager');
        $warrantyId = $this->ticket($engineer, ['status' => 'in_progress', 'execution_finished_at' => null, 'complaint_status' => null, 'complaint_checked_by' => null]);
        $periodicId = $this->ticket($engineer, ['type' => 'periodic']);

        $this->actingAs($manager)->get(route('projects-unified.maintenance.show', $warrantyId))
            ->assertOk()->assertSee('Mốc bảo hành tính KPI')->assertSee('data-kpi-warranty-sla="'.$warrantyId.'"', false);
        $this->actingAs($manager)->get(route('projects-unified.maintenance.show', $periodicId))
            ->assertOk()->assertDontSee('Mốc bảo hành tính KPI');
    }

    public function test_quality_requires_explicit_review_not_an_empty_complaint_field(): void
    {
        $manager = $this->person('technical_manager');
        foreach ([
            [[], null],
            [['kpi_customer_complaint' => null], null],
            [['kpi_customer_complaint' => false], null],
            [['kpi_customer_complaint' => false, 'kpi_reviewed_by' => $manager->id, 'kpi_reviewed_at' => '2031-03-10 09:00:00'], 1.0],
            [['kpi_customer_complaint' => true, 'kpi_customer_complaint_note' => 'Phản ánh giả lập', 'kpi_reviewed_by' => $manager->id, 'kpi_reviewed_at' => '2031-03-10 09:00:00'], 0.0],
        ] as [$data, $expectedRate]) {
            $engineer = $this->person('ky_thuat');
            $this->siteWithHandover($engineer, ['data' => json_encode($data)]);
            $quality = $this->criterion($this->kpiResult($engineer->id), 'quality');
            if ($expectedRate !== null) {
                $this->assertEqualsWithDelta($expectedRate, $quality['rate'], 1e-9);
            } else {
                $this->assertNull($quality['rate']);
            }
            if ($expectedRate === null) {
                $this->assertSame('no_data', $quality['status']);
                $this->assertSame('missing', $quality['evidence'][0]['state']);
                $this->assertNull($quality['score']);
            }
        }
    }

    public function test_one_unreviewed_project_keeps_quality_incomplete_even_if_another_passes(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->siteWithHandover($engineer, ['data' => json_encode([
            'kpi_customer_complaint' => false, 'kpi_reviewed_by' => $this->person('technical_manager')->id,
            'kpi_reviewed_at' => '2031-03-10 09:00:00',
        ])]);
        $this->siteWithHandover($engineer);
        $quality = $this->criterion($this->kpiResult($engineer->id), 'quality');
        $this->assertNull($quality['rate']);
        $this->assertNull($quality['score']);
        $this->assertSame(1, $quality['passed']);
        $this->assertSame(['missing', 'pass'], collect($quality['evidence'])->pluck('state')->sort()->values()->all());
    }

    public function test_legacy_acceptance_and_manual_evidence_without_complaint_confirmation_do_not_pass(): void
    {
        $engineer = $this->person('ky_thuat');
        $projectId = DB::table('project_test_projects')->insertGetId([
            'code' => 'CL-'.Str::upper(Str::random(6)), 'company_id' => $this->companyId(),
            'name' => 'Dự án chất lượng giả lập', 'lead_technician_id' => $engineer->id,
            'created_by' => $engineer->id, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_test_acceptances')->insert([
            'project_id' => $projectId, 'submitted_by' => $engineer->id, 'accepted_at' => '2031-03-10',
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quality = $this->criterion($this->kpiResult($engineer->id), 'quality');
        $this->assertNull($quality['rate']);
        $this->assertSame('missing', $quality['evidence'][0]['state']);

        $siteId = $this->siteWithHandover($engineer);
        DB::table('technical_kpi_project_evidence')->insert([
            'site_id' => $siteId, 'user_id' => $engineer->id, 'payroll_month' => self::MONTH,
            'quality_first_pass' => 1, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertNull($this->criterion($this->kpiResult($engineer->id), 'quality')['rate']);
    }

    public function test_sla_writes_and_scoring_exclude_tickets_outside_company_scope(): void
    {
        $engineer = $this->person('ky_thuat');
        $id = $this->ticket($engineer, ['company_id' => $this->companyId() === 1 ? 2 : 1, 'complaint_checked_at' => null]);
        $url = route('projects-unified.maintenance.warranty-sla.store', $id);
        foreach ([$engineer, $this->person('technical_manager'), $this->person('admin')] as $actor) {
            $this->actingAs($actor)->post($url, ['action' => 'reported', 'customer_reported_at' => '2031-03-10 08:00:00'])->assertForbidden();
        }
        $this->assertSame([], $this->criterion($this->kpiResult($engineer->id), 'warranty')['evidence']);
        $this->actingAs($this->person('technical_manager'))->get(route('projects-unified.maintenance.show', $id))
            ->assertOk()->assertDontSee('name="action" value="reported"', false);
    }

    public function test_manager_can_record_actual_response_time_with_reason_but_executor_cannot(): void
    {
        $engineer = $this->person('ky_thuat');
        $manager = $this->person('technical_manager');
        $id = $this->ticket($engineer, ['status' => 'in_progress', 'first_responded_at' => null, 'execution_finished_at' => null, 'complaint_checked_at' => null]);
        $url = route('projects-unified.maintenance.warranty-sla.store', $id);
        $payload = ['action' => 'responded', 'first_responded_at' => '2031-03-10 08:04:00', 'response_recording_reason' => 'Đối chiếu nhật ký cuộc gọi giả lập'];
        $this->actingAs($engineer)->post($url, $payload)->assertForbidden();
        $this->actingAs($manager)->post($url, array_diff_key($payload, ['response_recording_reason' => true]))->assertSessionHasErrors('response_recording_reason');
        $this->actingAs($manager)->post($url, $payload)->assertRedirect();
        $this->assertSame('2031-03-10 08:04:00', (string) DB::table('solar_maintenance_schedules')->where('id', $id)->value('first_responded_at'));
        $audit = json_decode(DB::table('solar_maintenance_audit_logs')->where('maintenance_schedule_id', $id)->where('action', 'warranty_sla_responded')->value('new_values'), true);
        $this->assertSame($payload['response_recording_reason'], $audit['response_recording_reason']);
    }

    public function test_sla_rejects_inverted_times_and_self_verification_and_cancelled_tickets(): void
    {
        $manager = $this->person('technical_manager');
        $id = $this->ticket($manager, ['complaint_checked_at' => null]);
        $url = route('projects-unified.maintenance.warranty-sla.store', $id);
        $this->actingAs($manager)->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertForbidden();
        $this->actingAs($this->person('admin'))->post($url, ['action' => 'reported', 'customer_reported_at' => '2031-03-10 08:06:00'])->assertStatus(422);

        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['first_responded_at' => null]);
        $this->actingAs($this->person('admin'))->post($url, ['action' => 'responded', 'first_responded_at' => '2031-03-10 07:59:00', 'response_recording_reason' => 'Giờ giả lập sai'])->assertStatus(422);
        $this->actingAs($this->person('admin'))->post($url, ['action' => 'responded', 'first_responded_at' => '2031-03-11 08:01:00', 'response_recording_reason' => 'Giờ phản hồi sau hoàn tất'])->assertStatus(422);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['status' => 'cancelled']);
        $this->actingAs($this->person('admin'))->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertStatus(422);
    }

    public function test_completed_ticket_without_execution_time_and_mixed_missing_tickets_stay_incomplete(): void
    {
        $engineer = $this->person('ky_thuat');
        $this->ticket($engineer);
        $id = $this->ticket($engineer, ['execution_finished_at' => null]);
        $warranty = $this->criterion($this->kpiResult($engineer->id), 'warranty');
        $this->assertNull($warranty['rate']);
        $this->assertNull($warranty['score']);
        $this->assertSame(['missing', 'pass'], collect($warranty['evidence'])->pluck('state')->sort()->values()->all());
        $this->assertNull(DB::table('solar_maintenance_schedules')->where('id', $id)->value('execution_finished_at'));
    }

    public function test_confirmed_warranty_cannot_be_reopened_through_either_service(): void
    {
        $engineer = $this->person('ky_thuat');
        $manager = $this->person('technical_manager');
        $id = $this->ticket($engineer);
        $this->actingAs($manager)->post(route('projects-unified.maintenance.approval.reopen', $id), ['comment' => 'Thử thay mốc đã khóa'])->assertSessionHasErrors('comment');
        try {
            app(\App\Services\Technical\SolarMaintenanceApprovalService::class)->reopen(\App\Models\SolarMaintenanceSchedule::findOrFail($id), $manager, 'Thử đường dịch vụ cũ');
            $this->fail('Không được mở lại phiếu đã xác nhận KPI.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
        }
        $this->assertSame('2031-03-11 08:00:00', (string) DB::table('solar_maintenance_schedules')->where('id', $id)->value('execution_finished_at'));
    }

    public function test_october_project_and_warranty_flow_matches_hand_calculation(): void
    {
        $engineer = $this->person('ky_thuat');
        $manager = $this->person('technical_manager');
        $month = '2026-10';
        Carbon::setTestNow('2026-10-01 08:00:00');
        $siteId = $this->siteWithHandover($engineer, [
            'due_at' => '2026-10-02 17:00:00', 'approved_at' => '2026-10-02 10:00:00',
            'data' => json_encode(['kpi_customer_complaint' => false, 'kpi_reviewed_by' => $manager->id, 'kpi_reviewed_at' => '2026-10-02 10:00:00']),
        ], ['lead_engineer_id' => $engineer->id]);
        $id = $this->ticket($engineer, [
            'site_id' => $siteId, 'scheduled_date' => '2026-10-01', 'status' => 'in_progress',
            'customer_reported_at' => null, 'first_responded_at' => null, 'execution_finished_at' => null,
            'complaint_status' => null, 'complaint_checked_by' => null, 'complaint_checked_at' => null,
        ]);
        $url = route('projects-unified.maintenance.warranty-sla.store', $id);
        $this->actingAs($manager)->post($url, ['action' => 'reported', 'customer_reported_at' => '2026-10-01 08:00:00'])->assertRedirect();
        Carbon::setTestNow('2026-10-01 08:05:00');
        $this->actingAs($engineer)->post($url, ['action' => 'responded'])->assertRedirect();
        Carbon::setTestNow('2026-10-02 08:00:00');
        $this->actingAs($engineer)->post(route('projects-unified.maintenance.approval.complete', $id), ['result_note' => 'Đã xử lý xong ca bảo hành giả lập'])->assertRedirect();
        $row = DB::table('solar_maintenance_schedules')->find($id);
        $this->assertSame('2026-10-02 08:00:00', (string) $row->execution_finished_at);
        $this->actingAs($manager)->post($url, ['action' => 'verify', 'complaint_status' => 'none'])->assertRedirect();
        Carbon::setTestNow('2026-10-03 12:00:00');

        // Ba tiêu chí ngoài phạm vi ca này được nhập giả lập rõ ràng, không suy từ công trình.
        foreach (['survey', 'hse', 'evn_app'] as $code) {
            DB::table('technical_kpi_monthly_scores')->insert([
                'user_id' => $engineer->id, 'payroll_month' => $month, 'criterion_code' => $code,
                'plan_value' => 1, 'actual_value' => 1, 'updated_by' => $manager->id,
                'note' => 'Số liệu giả lập cho ca đối chiếu', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $result = $this->kpiResult($engineer->id, [$month]);
        foreach (['timeline' => 0.25, 'quality' => 0.15, 'survey' => 0.15, 'hse' => 0.10, 'evn_app' => 0.05, 'warranty' => 0.30] as $code => $expected) {
            $this->assertEqualsWithDelta($expected, $this->criterion($result, $code)['score'], 1e-9, $code);
        }
        $this->assertEqualsWithDelta(1.0, $result['total'], 1e-9, 'Tính tay: 25 + 15 + 15 + 10 + 5 + 30 = 100 điểm.');

        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['first_responded_at' => '2026-10-01 08:05:01']);
        $late = $this->kpiResult($engineer->id, [$month]);
        $this->assertEqualsWithDelta(0.70, $late['total'], 1e-9, 'Trễ phản hồi 1 giây: 100 - 30 = 70 điểm.');
        DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->update(['data' => null]);
        $this->assertNull($this->kpiResult($engineer->id, [$month])['total'], 'Chất lượng chưa xác nhận: không được ra tổng KPI.');
    }

    public function test_october_config_can_be_saved_with_warranty_and_only_leadership_can_approve(): void
    {
        $oldCriteria = collect($this->evaluator('2026-09')->criteria())->map(fn ($c) => $c + [
            'is_calculated' => $c['weight'] !== null, 'status' => $c['weight'] !== null ? 'applied' : 'draft',
        ])->all();
        $v1 = $this->config(1, 'applied', '2026-09-01', criteria: $oldCriteria);
        $admin = $this->person('admin');
        $criteria = collect($this->evaluator('2026-10')->criteria())->map(fn ($c) => [
            'no' => $c['no'], 'code' => $c['code'], 'name' => $c['name'],
            'weight' => $c['weight'] * 100, 'is_calculated' => 1, 'status' => 'applied',
        ])->all();
        $payload = [
            'version_name' => 'Cấu hình tháng 10 giả lập', 'effective_date' => '2026-10-01',
            'base_salary_rate' => 70, 'kpi_salary_rate' => 30, 'round_rule' => '1000',
            'criteria' => $criteria, 'payout_tiers' => ['above_100' => ['rate' => 1]],
        ];
        $this->actingAs($this->person('ky_thuat'))->post(route('ky-thuat.kpis.config.draft'), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('ky-thuat.kpis.config.draft'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $draft = TechnicalKpiConfig::where('version_name', $payload['version_name'])->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->approved_at);
        $this->assertEqualsWithDelta(0.30, collect($draft->criteria_config)->firstWhere('code', 'warranty')['weight'], 1e-9);
        $this->assertTrue(collect($draft->criteria_config)->firstWhere('code', 'warranty')['is_calculated']);
        $html = $this->actingAs($admin)->get(route('ky-thuat.kpis.config', ['version_id' => $draft->id]))->assertOk()->getContent();
        $this->assertStringContainsString('name="criteria[5][weight]"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="criteria\[5\]\[is_calculated\]"[^>]*disabled/s', $html);

        $this->actingAs($this->person('technical_manager'))->post(route('ky-thuat.kpis.config.approve', $draft->id))->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
        $this->actingAs($admin)->post(route('ky-thuat.kpis.config.approve', $draft->id))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($v1->id, TechnicalKpiConfig::getActiveForDate('2026-09-01')?->id);
        $this->assertSame($draft->id, TechnicalKpiConfig::getActiveForDate('2026-10-01')?->id);
    }

    public function test_october_draft_and_approval_reject_old_weights_that_omit_warranty(): void
    {
        $admin = $this->person('admin');
        $criteria = collect($this->evaluator('2026-09')->criteria())->map(fn ($c) => [
            'no' => $c['no'], 'code' => $c['code'], 'name' => $c['name'],
            'weight' => ($c['weight'] ?? 0) * 100, 'is_calculated' => $c['weight'] !== null,
            'status' => $c['weight'] !== null ? 'applied' : 'draft',
        ])->all();
        $payload = ['version_name' => 'Sai bộ tháng 10 giả lập', 'effective_date' => '2026-10-01',
            'base_salary_rate' => 70, 'kpi_salary_rate' => 30, 'round_rule' => '1000', 'criteria' => $criteria, 'payout_tiers' => ['above_100' => ['rate' => 1]]];
        $this->actingAs($admin)->post(route('ky-thuat.kpis.config.draft'), $payload)->assertSessionHasErrors('criteria_weight');
        $invalid = $this->config(99, 'draft', '2026-10-01', false, array_map(fn ($c) => array_replace($c, ['weight' => $c['weight'] / 100]), $criteria));
        $this->actingAs($admin)->post(route('ky-thuat.kpis.config.approve', $invalid->id))->assertSessionHasErrors('criteria_weight');
        $this->assertSame('draft', $invalid->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Quyền xem trang KPI
    |--------------------------------------------------------------------------
    */

    public function test_manager_cannot_open_engineer_outside_scope_by_url_but_admin_can(): void
    {
        $deptA = DB::table('departments')->insertGetId(['name' => 'Phòng KT giả A', 'created_at' => now(), 'updated_at' => now()]);
        $deptB = DB::table('departments')->insertGetId(['name' => 'Phòng KT giả B', 'created_at' => now(), 'updated_at' => now()]);
        $manager = $this->person('technical_manager', ['department_id' => $deptA]);
        $this->person('ky_thuat', ['department_id' => $deptA]);
        $outside = $this->person('ky_thuat', ['department_id' => $deptB]);
        $technician = $this->person('ky_thuat', ['department_id' => $deptA]);

        $this->actingAs($manager)->get(route('ky-thuat.kpis.index', ['month' => self::MONTH, 'user_id' => $outside->id]))->assertForbidden();
        $this->actingAs($this->person('admin'))->get(route('ky-thuat.kpis.index', ['month' => self::MONTH, 'user_id' => $outside->id]))->assertOk();

        // Kỹ thuật viên đổi user_id vẫn chỉ thấy KPI của chính mình.
        $html = (string) $this->actingAs($technician)->get(route('ky-thuat.kpis.index', ['month' => self::MONTH, 'user_id' => $outside->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString($outside->name, $html);
    }

    public function test_kpi_page_shows_rule_and_source_list_for_engineer(): void
    {
        $engineer = $this->person('ky_thuat');
        $siteId = $this->siteWithHandover($engineer, [], ['project_code' => 'CT-NGUON-GIA']);
        $scheduleId = $this->ticket($engineer, ['schedule_code' => 'BH-NGUON-GIA']);

        $html = (string) $this->actingAs($this->person('admin'))
            ->get(route('ky-thuat.kpis.index', ['month' => self::MONTH, 'user_id' => $engineer->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Bộ quy tắc kỳ này:', $html);
        $this->assertStringContainsString('data-kpx-criterion="6" data-kpx-status="pass"', $html);
        $this->assertStringContainsString('data-kpx-sources="timeline"', $html);
        $this->assertStringContainsString('CT-NGUON-GIA', $html);
        $this->assertStringContainsString('BH-NGUON-GIA', $html);
        $this->assertStringContainsString('href="'.e(route('projects-unified.show', ['site' => $siteId, 'step' => 'acceptance']).'#workflow').'"', $html);
        $this->assertStringContainsString('href="'.e(route('projects-unified.maintenance.show', ['schedule' => $scheduleId])).'"', $html);
    }
}
