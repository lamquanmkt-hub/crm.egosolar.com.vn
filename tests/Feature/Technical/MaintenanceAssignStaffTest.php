<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nút "Thêm nhân sự" ở bước Phân công của đợt bảo trì: danh sách chọn có kỹ thuật viên (role ky_thuat)
 * và Trưởng phòng lưu được phân công kể cả với đợt gắn nhãn công ty khác.
 */
final class MaintenanceAssignStaffTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUnassignedRound(int $companyId = 2): int
    {
        return (int) DB::table('solar_maintenance_schedules')->insertGetId([
            'type' => 'periodic',
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'company_id' => $companyId,
            'status' => 'scheduled',
            'approval_status' => 'not_submitted',
            'assignment_approval_status' => 'not_required',
            'created_at' => '2026-09-15 08:00:00',
            'updated_at' => now(),
        ]);
    }

    public function test_assignment_modal_lists_ky_thuat_role_staff(): void
    {
        $manager = $this->userWithRole('technical_manager', ['email' => 'qlkt.phancong@egosolar.test']);
        $staff = $this->userWithRole('ky_thuat', ['name' => 'ZZ Ky Thuat Vien Phan Cong', 'email' => 'ktv.phancong@egosolar.test']);
        $id = $this->makeUnassignedRound();

        $html = $this->actingAs($manager)->get('/du-an/bao-tri-bao-hanh/'.$id)->assertOk()->getContent();

        $this->assertStringContainsString('data-emx2-open-assignment', $html);
        $this->assertStringContainsString('ZZ Ky Thuat Vien Phan Cong', $html);
        $this->assertStringContainsString('value="'.$staff->id.'"', $html);
    }

    public function test_manager_can_save_assignment_for_a_company_one_round(): void
    {
        $manager = $this->userWithRole('technical_manager', ['email' => 'qlkt.phancong@egosolar.test']);
        $leader = $this->userWithRole('ky_thuat');
        $member = $this->userWithRole('ky_thuat');
        $id = $this->makeUnassignedRound(companyId: 1);

        $this->actingAs($manager)
            ->put(route('projects-unified.maintenance.update', ['schedule' => $id]), [
                'leader_user_id' => $leader->id,
                'member_user_ids' => [$member->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $ids = $schedule->assignees()->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        sort($ids);
        $expected = [(int) $leader->id, (int) $member->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_ordinary_staff_cannot_change_assignment(): void
    {
        $tech = $this->userWithRole('ky_thuat');
        $other = $this->userWithRole('ky_thuat');
        $id = $this->makeUnassignedRound();

        $this->actingAs($tech)
            ->put(route('projects-unified.maintenance.update', ['schedule' => $id]), [
                'leader_user_id' => $other->id,
                'member_user_ids' => [],
            ])
            ->assertForbidden();

        $this->assertSame(0, SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->assignees()->count());
    }
}
