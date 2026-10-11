<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Luồng bảng lương tự động tháng 09/2026 (Thứ 2 – Thứ 6, lễ 02/09 có lương => công chuẩn 22):
 * hồ sơ lương → chấm công / nghỉ phép / đi trễ → chấm KPI → tạo bảng lương → sửa tay → duyệt → phiếu lương.
 */
final class PayrollFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-09';

    private User $hr;

    private User $director;

    private User $warehouse;

    private User $office;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('attendance_settings')->delete();
        DB::table('attendance_settings')->insert([
            'workday_monday' => 1, 'workday_tuesday' => 1, 'workday_wednesday' => 1, 'workday_thursday' => 1, 'workday_friday' => 1,
            'saturday_mode' => 'off', 'workday_sunday' => 0, 'late_penalty_per_time' => 100000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance_holidays')->whereBetween('holiday_date', ['2026-08-01', '2026-10-31'])->delete();
        DB::table('attendance_holidays')->insert(['holiday_date' => '2026-09-02', 'name' => 'Quốc khánh', 'is_paid' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_salary_profiles')->delete();
        DB::table('hr_payroll_lines')->delete();
        DB::table('hr_payroll_periods')->delete();

        $this->hr = $this->userWithRole('hr');
        $this->director = $this->userWithRole('management');
        $this->warehouse = $this->userWithRole('accounting', ['name' => 'Kế toán kho Test']);
        $this->office = $this->userWithRole('sales', ['name' => 'Nhân viên Test']);

        $this->profile($this->warehouse, ['base_salary' => 7000000, 'kpi_salary' => 3000000, 'insurance_enabled' => 0, 'kpi_template' => 'ke_toan_kho']);
        $this->profile($this->office, ['base_salary' => 11000000, 'insurance_enabled' => 0]);
    }

    private function profile(User $user, array $values): void
    {
        DB::table('hr_salary_profiles')->insert($values + [
            'user_id' => $user->id, 'effective_month' => '2026-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Check-in mọi ngày làm việc (trừ lễ và các ngày bỏ qua). */
    private function attend(User $user, array $skip = [], array $late = []): void
    {
        for ($d = Carbon::parse('2026-09-01'); $d->month === 9; $d->addDay()) {
            $date = $d->toDateString();
            if ($d->isWeekend() || $date === '2026-09-02' || in_array($date, $skip, true)) {
                continue;
            }
            DB::table('attendance_records')->insert([
                'user_id' => $user->id, 'work_date' => $date,
                'check_in_at' => $date.' 08:00:00', 'check_out_at' => $date.' 17:30:00',
                'late_minutes' => in_array($date, $late, true) ? 15 : 0, 'work_minutes' => 480, 'status' => 'completed',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function leave(User $user, string $type, string $from, string $to, float $days): void
    {
        DB::table('leave_requests')->insert([
            'user_id' => $user->id, 'request_type' => 'leave', 'leave_type' => $type,
            'start_date' => $from, 'end_date' => $to, 'days' => $days, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Chấm toàn bộ tiêu chí KPI Kế toán kho cùng một % đạt. */
    private function scoreWarehouse(float $achievement): void
    {
        $templateId = DB::table('hr_kpi_templates')->where('code', 'ke_toan_kho')->value('id');
        $scores = [];
        foreach (DB::table('hr_kpi_criteria')->where('template_id', $templateId)->pluck('id') as $id) {
            $scores[$this->warehouse->id][$id] = ['achievement' => $achievement];
        }

        $this->actingAs($this->director)
            ->post(route('hr.kpi.store'), ['month' => self::MONTH, 'template' => 'ke_toan_kho', 'scores' => $scores])
            ->assertRedirect();
    }

    private function line(User $user): object
    {
        return DB::table('hr_payroll_lines')->where('user_id', $user->id)->first();
    }

    public function test_generates_payroll_from_attendance_leave_late_and_kpi(): void
    {
        $this->attend($this->warehouse, skip: ['2026-09-10'], late: ['2026-09-15', '2026-09-16']);
        $this->leave($this->warehouse, 'annual', '2026-09-10', '2026-09-10', 1);
        $this->attend($this->office, skip: ['2026-09-21', '2026-09-22', '2026-09-23']);
        $this->leave($this->office, 'unpaid', '2026-09-21', '2026-09-22', 2);
        $this->scoreWarehouse(93);

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH])->assertRedirect(route('hr.payroll.index', ['month' => self::MONTH]));

        $period = DB::table('hr_payroll_periods')->where('payroll_month', self::MONTH)->first();
        $this->assertSame('draft', $period->status);
        $this->assertEquals(22, $period->standard_days);

        $w = $this->line($this->warehouse);
        $this->assertEquals(20, $w->worked_days);
        $this->assertEquals(1, $w->paid_leave_days);
        $this->assertEquals(1, $w->holiday_days);
        $this->assertEquals(22, $w->paid_days);
        // Bảng bậc Kế toán kho: 90% – 100% => hưởng 100% lương KPI.
        $this->assertEquals(93, $w->kpi_percent);
        $this->assertEquals(1.0, $w->kpi_rate);
        $this->assertEquals(3000000, $w->kpi_pay);
        $this->assertEquals(2, $w->late_count);
        $this->assertEquals(200000, $w->late_penalty);
        $this->assertEquals(10000000 - 200000, $w->net_salary);

        $o = $this->line($this->office);
        $this->assertEquals(3, $o->unpaid_days, '2 ngày nghỉ không lương + 1 ngày vắng không đơn');
        $this->assertEquals(19, $o->paid_days);
        $this->assertEquals(1.0, (float) $o->kpi_rate, 'Không gán bộ KPI => hưởng đủ hiệu suất');
        $this->assertEquals(9500000, $o->net_salary);
    }

    public function test_kpi_below_floor_pays_no_kpi_salary(): void
    {
        $this->attend($this->warehouse);
        $this->scoreWarehouse(65);

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);

        $w = $this->line($this->warehouse);
        $this->assertEquals(0, $w->kpi_rate);
        $this->assertEquals(0, $w->kpi_pay);
        $this->assertEquals(7000000, $w->net_salary);
    }

    public function test_manual_overrides_survive_recalculation(): void
    {
        $this->attend($this->warehouse);
        $this->attend($this->office);
        $this->scoreWarehouse(100);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);

        $line = $this->line($this->office);
        $this->actingAs($this->hr)->put(route('hr.payroll.lines.update', $line->id), [
            'manual' => ['other_income' => 500000, 'other_deduction' => 300000],
            'note' => 'Thưởng sinh nhật, trừ quỹ Lễ',
        ])->assertRedirect();

        $this->assertEquals(11000000 + 500000 - 300000, $this->line($this->office)->net_salary);

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);
        $after = $this->line($this->office);
        $this->assertEquals(500000, $after->other_income);
        $this->assertEquals(11200000, $after->net_salary);
    }

    public function test_approval_requires_kpi_and_management_role_then_locks(): void
    {
        $this->attend($this->warehouse);
        $this->attend($this->office);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);
        $period = DB::table('hr_payroll_periods')->where('payroll_month', self::MONTH)->first();

        $this->assertStringContainsString('Chưa có KPI', (string) $this->line($this->warehouse)->warnings);
        $this->actingAs($this->hr)->post(route('hr.payroll.approve', $period->id))->assertForbidden();
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $period->id))->assertSessionHas('error');

        $this->scoreWarehouse(100);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $period->id))->assertSessionHas('success');
        $this->assertSame('approved', DB::table('hr_payroll_periods')->where('id', $period->id)->value('status'));

        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH])->assertSessionHas('error');
        $line = $this->line($this->office);
        $this->actingAs($this->hr)->put(route('hr.payroll.lines.update', $line->id), ['manual' => ['other_income' => 1]])->assertSessionHas('error');
    }

    public function test_payslip_visibility(): void
    {
        $this->attend($this->warehouse);
        $this->attend($this->office);
        $this->scoreWarehouse(100);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);
        $officeLine = $this->line($this->office);

        // Bản nháp: nhân viên chưa xem được, HR xem được.
        $this->actingAs($this->office)->get(route('hr.payroll.payslip', $officeLine->id))->assertForbidden();
        $this->actingAs($this->hr)->get(route('hr.payroll.payslip', $officeLine->id))->assertOk()->assertSee('BẢN NHÁP');

        $period = DB::table('hr_payroll_periods')->where('payroll_month', self::MONTH)->first();
        $this->actingAs($this->director)->post(route('hr.payroll.approve', $period->id));

        $this->actingAs($this->office)->get(route('hr.payroll.payslip', $officeLine->id))->assertOk()->assertSee('THỰC LÃNH');
        $this->actingAs($this->office)->get(route('hr.payroll.payslip', $this->line($this->warehouse)->id))->assertForbidden();
        $this->actingAs($this->office)->get(route('hr.payroll.my'))->assertOk()->assertSee('09/2026');
        $this->actingAs($this->office)->get(route('hr.payroll.index'))->assertForbidden();
    }

    public function test_cannot_self_evaluate_and_hr_cannot_evaluate_kpi(): void
    {
        $templateId = DB::table('hr_kpi_templates')->where('code', 'ke_toan_kho')->value('id');
        $criterionId = DB::table('hr_kpi_criteria')->where('template_id', $templateId)->value('id');
        $this->profile($this->director, ['base_salary' => 1, 'kpi_template' => 'ke_toan_kho', 'effective_month' => '2026-02']);

        $this->actingAs($this->hr)
            ->post(route('hr.kpi.store'), ['month' => self::MONTH, 'template' => 'ke_toan_kho', 'scores' => [$this->warehouse->id => [$criterionId => ['achievement' => 100]]]])
            ->assertForbidden();

        $this->actingAs($this->director)
            ->post(route('hr.kpi.store'), ['month' => self::MONTH, 'template' => 'ke_toan_kho', 'scores' => [$this->director->id => [$criterionId => ['achievement' => 100]]]])
            ->assertSessionHasErrors('scores');
    }

    public function test_hcns_without_weights_cannot_be_scored(): void
    {
        // Giám đốc xoá trọng số (mặc định nạp sẵn chia đều) => chưa tính được KPI HCNS.
        $hcnsId = DB::table('hr_kpi_templates')->where('code', 'hcns')->value('id');
        DB::table('hr_kpi_criteria')->where('template_id', $hcnsId)->update(['weight' => null]);
        DB::table('hr_salary_profiles')->where('user_id', $this->office->id)->update(['kpi_salary' => 2000000, 'kpi_template' => 'hcns']);
        $this->attend($this->office);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);

        $line = $this->line($this->office);
        $this->assertNull($line->kpi_rate);
        $this->assertStringContainsString('chưa có trọng số', (string) $line->warnings);
    }

    public function test_pages_render_for_hr(): void
    {
        $this->attend($this->warehouse);
        $this->actingAs($this->hr)->post(route('hr.payroll.generate'), ['month' => self::MONTH]);

        $this->actingAs($this->hr)->get(route('hr.payroll.index', ['month' => self::MONTH]))->assertOk()->assertSee('Kế toán kho Test');
        $this->actingAs($this->hr)->get(route('hr.payroll.profiles', ['month' => self::MONTH]))->assertOk();
        $this->actingAs($this->hr)->get(route('hr.payroll.settings'))->assertOk();
        $this->actingAs($this->hr)->get(route('hr.kpi.index', ['template' => 'ke_toan_kho', 'month' => self::MONTH]))->assertOk()->assertSee('Kế toán kho Test');
        $this->actingAs($this->hr)->get(route('hr.kpi.settings', ['template' => 'hcns']))->assertOk()->assertSee('Thời gian tuyển dụng');
        $this->actingAs($this->hr)->get(route('hr.payroll.export', ['month' => self::MONTH]))->assertOk();
    }

    public function test_profile_and_settings_can_be_saved(): void
    {
        $this->actingAs($this->hr)->post(route('hr.payroll.profiles.store'), [
            'user_id' => $this->office->id, 'effective_month' => '2026-09',
            'base_salary' => 12000000, 'kpi_salary' => 0, 'decision_bonus' => 0, 'travel_allowance' => 0,
            'phone_allowance' => 0, 'meal_allowance' => 1200000, 'dependents' => 1, 'pit_mode' => 'progressive',
            'insurance_enabled' => 1, 'kpi_template' => 'hcns',
        ])->assertRedirect();
        $this->assertSame(1, DB::table('hr_salary_profiles')->where('user_id', $this->office->id)->where('effective_month', '2026-09')->count());

        $this->actingAs($this->hr)->post(route('hr.payroll.settings.save'), [
            'personal_deduction' => 15500000, 'dependent_deduction' => 6200000,
            'bhxh_employee_rate' => 8, 'bhyt_employee_rate' => 1.5, 'bhtn_employee_rate' => 1,
            'bhxh_employer_rate' => 17.5, 'bhyt_employer_rate' => 3, 'bhtn_employer_rate' => 1,
            'insurance_salary_cap' => 46800000, 'insurance_unpaid_days_limit' => 14, 'insurance_min_days' => 0, 'flat_pit_threshold' => 5000000,
            'ot_auto' => 1, 'ot_rate_weekday' => 150, 'ot_rate_restday' => 200, 'ot_rate_holiday' => 300,
            'paid_leave_types' => ['annual', 'personal'],
        ])->assertRedirect(route('hr.payroll.settings'));
        $this->assertSame('annual,personal', DB::table('hr_payroll_settings')->where('setting_key', 'paid_leave_types')->value('setting_value'));
        $this->assertSame('1', DB::table('hr_payroll_settings')->where('setting_key', 'ot_auto')->value('setting_value'));
    }
}
