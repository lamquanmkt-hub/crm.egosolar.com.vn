<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Payroll\PayrollGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm soát bảng lương:
 * - Không ai tự sửa dòng lương / hồ sơ lương của chính mình; hệ số KPI nhập tay chỉ Ban Giám đốc.
 * - Duyệt luôn tính lại trước; số liệu đổi kể từ lần tính trước thì dừng để xem lại.
 * - Tháng đã duyệt lương thì khoá điểm KPI.
 */
final class PayrollAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2031-03';

    private User $hr;

    private User $director;

    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('hr_payroll_periods')->where('payroll_month', self::MONTH)->delete();
        $this->hr = $this->userWithRole('hr');
        $this->director = $this->userWithRole('management');
        $this->clerk = $this->userWithRole('warehouse', ['name' => 'Kế toán kho Test']);
    }

    private function profile(User $user, array $override = []): void
    {
        DB::table('hr_salary_profiles')->insert($override + [
            'user_id' => $user->id, 'effective_month' => self::MONTH, 'base_salary' => 7000000, 'kpi_salary' => 3000000,
            'decision_bonus' => 0, 'travel_allowance' => 0, 'phone_allowance' => 0, 'meal_allowance' => 0,
            'insurance_salary' => null, 'insurance_enabled' => false, 'dependents' => 0, 'pit_mode' => 'progressive',
            'kpi_template' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function generate(): object
    {
        return app(PayrollGenerator::class)->generate(self::MONTH, (int) $this->hr->id);
    }

    private function line(object $period, User $user): object
    {
        return DB::table('hr_payroll_lines')->where('period_id', $period->id)->where('user_id', $user->id)->first();
    }

    public function test_hr_cannot_edit_own_payroll_line_but_can_edit_others(): void
    {
        $this->profile($this->hr);
        $this->profile($this->clerk);
        $period = $this->generate();

        $this->actingAs($this->hr)
            ->put(route('hr.payroll.lines.update', $this->line($period, $this->hr)->id), ['manual' => ['other_income' => 9000000]])
            ->assertForbidden();

        $this->actingAs($this->hr)
            ->put(route('hr.payroll.lines.update', $this->line($period, $this->clerk)->id), ['manual' => ['other_income' => 500000]])
            ->assertRedirect();
        $clerkLine = $this->line($period, $this->clerk);
        $this->assertEqualsWithDelta(500000, (float) $clerkLine->other_income, 0.01);
        $this->assertSame($this->hr->id, (int) $clerkLine->manual_updated_by);
    }

    public function test_only_directors_can_override_kpi_rate(): void
    {
        $this->profile($this->clerk);
        $period = $this->generate();
        $lineId = $this->line($period, $this->clerk)->id;

        $this->actingAs($this->hr)->put(route('hr.payroll.lines.update', $lineId), ['manual' => ['kpi_rate' => 150]]);
        $this->assertEqualsWithDelta(1.0, (float) DB::table('hr_payroll_lines')->where('id', $lineId)->value('kpi_rate'), 0.0001);

        $this->actingAs($this->director)->put(route('hr.payroll.lines.update', $lineId), ['manual' => ['kpi_rate' => 80]]);
        $this->assertEqualsWithDelta(0.8, (float) DB::table('hr_payroll_lines')->where('id', $lineId)->value('kpi_rate'), 0.0001);
    }

    public function test_hr_cannot_save_own_salary_profile(): void
    {
        $payload = [
            'effective_month' => self::MONTH, 'base_salary' => 99000000, 'kpi_salary' => 0, 'decision_bonus' => 0,
            'travel_allowance' => 0, 'phone_allowance' => 0, 'meal_allowance' => 0, 'dependents' => 0, 'pit_mode' => 'progressive',
        ];

        $this->actingAs($this->hr)->post(route('hr.payroll.profiles.store'), ['user_id' => $this->hr->id] + $payload)->assertForbidden();
        $this->actingAs($this->hr)->post(route('hr.payroll.profiles.store'), ['user_id' => $this->clerk->id] + $payload)->assertRedirect();
        $this->assertDatabaseHas('hr_salary_profiles', ['user_id' => $this->clerk->id, 'effective_month' => self::MONTH]);
        $this->assertDatabaseMissing('hr_salary_profiles', ['user_id' => $this->hr->id, 'effective_month' => self::MONTH]);
    }

    public function test_approve_recalculates_and_stops_when_numbers_changed(): void
    {
        $this->profile($this->clerk);
        $period = $this->generate();
        // Đủ công cả tháng (HR nhập tay ngày tính lương = công chuẩn; giá trị tay giữ nguyên khi tính lại).
        app(PayrollGenerator::class)->updateManual($this->line($period, $this->clerk), ['paid_days' => (float) $period->standard_days], null);

        // Sau lần tính, hồ sơ lương bị sửa (tăng lương) mà chưa bấm Tính lại.
        DB::table('hr_salary_profiles')->where('user_id', $this->clerk->id)->update(['base_salary' => 8000000]);

        $this->actingAs($this->director)->post(route('hr.payroll.approve', $period->id))->assertSessionHas('error');
        $this->assertSame('draft', DB::table('hr_payroll_periods')->where('id', $period->id)->value('status'));
        $this->assertEqualsWithDelta(11000000, (float) $this->line($period, $this->clerk)->net_salary, 1);

        // Lần duyệt thứ hai, số liệu không đổi => duyệt và khoá.
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $period->id))->assertSessionHas('success');
        $this->assertSame('approved', DB::table('hr_payroll_periods')->where('id', $period->id)->value('status'));
    }

    public function test_employee_sees_own_kpi_page(): void
    {
        $template = DB::table('hr_kpi_templates')->where('code', 'ke_toan_kho')->first();
        $criteria = DB::table('hr_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
        $this->profile($this->clerk, ['kpi_template' => 'ke_toan_kho']);
        foreach ($criteria as $c) {
            DB::table('hr_kpi_scores')->insert(['user_id' => $this->clerk->id, 'payroll_month' => self::MONTH, 'criterion_id' => $c->id,
                'achievement' => 93, 'note' => 'Nhận xét GĐ', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($this->clerk)->get(route('hr.kpi-my', ['month' => self::MONTH]))
            ->assertOk()->assertSee($criteria->first()->name)->assertSee('Nhận xét GĐ')->assertSee('Tạm tính')->assertSee('93%');

        $this->actingAs($this->hr)->get(route('hr.kpi-my', ['month' => self::MONTH]))
            ->assertOk()->assertSee('chưa được áp dụng bộ KPI nào');
    }

    public function test_kpi_scores_are_locked_once_payroll_is_approved(): void
    {
        $template = DB::table('hr_kpi_templates')->where('code', 'ke_toan_kho')->first();
        $criterion = DB::table('hr_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->first();
        $this->profile($this->clerk, ['kpi_template' => 'ke_toan_kho']);
        $period = $this->generate();
        DB::table('hr_payroll_periods')->where('id', $period->id)->update(['status' => 'approved']);

        $this->actingAs($this->director)->post(route('hr.kpi.store'), [
            'month' => self::MONTH, 'template' => 'ke_toan_kho',
            'scores' => [$this->clerk->id => [$criterion->id => ['achievement' => 50]]],
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('hr_kpi_scores', ['user_id' => $this->clerk->id, 'payroll_month' => self::MONTH]);
    }
}
