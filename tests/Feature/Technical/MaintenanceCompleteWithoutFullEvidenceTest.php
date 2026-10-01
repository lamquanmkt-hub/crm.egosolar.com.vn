<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kỹ thuật được phân công vẫn bấm "Hoàn tất đợt" được khi CHƯA đủ file minh chứng;
 * hệ thống ghi chú "chưa đủ file" vào hồ sơ thay vì chặn.
 */
final class MaintenanceCompleteWithoutFullEvidenceTest extends TestCase
{
    use DatabaseTransactions;

    private function technician(): User
    {
        return $this->userWithRole('ky_thuat');
    }

    private function makeSchedule(User $assignee, bool $itemDone = false, ?string $resultNote = 'Đã kiểm tra tại hiện trường'): int
    {
        $id = DB::table('solar_maintenance_schedules')->insertGetId([
            'type' => 'periodic',
            'scheduled_date' => now()->toDateString(),
            'company_id' => 2,
            'status' => 'in_progress',
            'approval_status' => 'not_submitted',
            'assignment_approval_status' => 'approved',
            'assigned_to' => $assignee->id,
            'result_note' => $resultNote,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('solar_maintenance_assignees')->insert([
            'maintenance_schedule_id' => $id, 'user_id' => $assignee->id,
        ]);
        DB::table('solar_maintenance_checklist_items')->insert([
            'maintenance_schedule_id' => $id, 'item_key' => 'photo', 'label' => 'Hình ảnh bảo trì',
            'is_required' => 1, 'requires_evidence' => 1, 'min_evidence' => 1, 'is_done' => $itemDone ? 1 : 0,
        ]);

        return (int) $id;
    }

    public function test_assigned_technician_can_complete_without_enough_files_and_note_is_recorded(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech);

        $this->actingAs($tech)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringContainsString('chưa đủ file minh chứng', (string) $schedule->result_note);
        $this->assertStringContainsString('Hình ảnh bảo trì (0/1 file)', (string) $schedule->result_note);
        $this->assertStringContainsString('chưa đủ file minh chứng', (string) $schedule->approval_note);
        $this->assertStringContainsString('Đã kiểm tra tại hiện trường', (string) $schedule->result_note);

        $history = DB::table('solar_maintenance_status_histories')->where('maintenance_schedule_id', $id)->where('to_status', 'completed')->value('reason');
        $this->assertStringContainsString('chưa đủ file minh chứng', (string) $history);
    }

    public function test_success_message_mentions_missing_files(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech);

        $response = $this->actingAs($tech)->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]));

        $this->assertStringContainsString('chưa đủ file', (string) session('success'));
        $response->assertRedirect();
    }

    public function test_complete_without_shortfall_adds_no_warning_note(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech, itemDone: true);

        $this->actingAs($tech)->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))->assertRedirect();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringNotContainsString('chưa đủ file', (string) $schedule->result_note);
        $this->assertStringNotContainsString('chưa đủ file', (string) session('success'));
    }

    public function test_quick_result_note_is_saved_when_schedule_has_none(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech, resultNote: null);

        $this->actingAs($tech)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]), ['result_note' => 'Đã thay bộ kẹp panel'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringContainsString('Đã thay bộ kẹp panel', (string) $schedule->result_note);
    }

    public function test_result_note_is_still_required_to_complete(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech, resultNote: null);

        $this->actingAs($tech)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertSessionHasErrors('result_note');

        $this->assertSame('in_progress', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_unassigned_technician_cannot_complete(): void
    {
        $owner = $this->technician();
        $other = $this->technician();
        $id = $this->makeSchedule($owner);

        $this->actingAs($other)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertForbidden();

        $this->assertSame('in_progress', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_designated_manager_can_complete_any_round_even_if_not_assigned(): void
    {
        $owner = $this->technician();
        $manager = $this->userWithRole('technical_manager', ['email' => 'lead.hoanthanh.test@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => ['Lead.HoanThanh.Test@egosolar.test']]);
        $id = $this->makeSchedule($owner);

        $html = $this->actingAs($manager)->get('/du-an/bao-tri-bao-hanh/'.$id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<button class="emd9-btn primary eme11-btn-complete" type="submit">/', $html);

        $this->actingAs($manager)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringContainsString('chưa đủ file minh chứng', (string) $schedule->result_note);
    }

    public function test_designated_manager_can_complete_a_company_one_round(): void
    {
        $owner = $this->technician();
        $manager = $this->userWithRole('technical_manager', ['email' => 'lead.hoanthanh.test@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => ['lead.hoanthanh.test@egosolar.test']]);
        $id = $this->makeSchedule($owner);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['company_id' => 1]);

        $this->actingAs($manager)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_any_technical_manager_can_complete_any_round_without_being_listed(): void
    {
        $owner = $this->technician();
        $manager = $this->userWithRole('technical_manager', ['email' => 'quanly.bat.ky@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => []]);

        $id = $this->makeSchedule($owner);
        $this->actingAs($manager)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_admin_can_complete_any_round(): void
    {
        $owner = $this->technician();
        $admin = $this->userWithRole('admin');
        config(['technical.maintenance_complete_any_emails' => []]);

        $id = $this->makeSchedule($owner);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['company_id' => 1]);
        $this->actingAs($admin)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_non_manager_user_who_is_not_assigned_is_forbidden(): void
    {
        $owner = $this->technician();
        $sales = $this->userWithRole('sales', ['email' => 'kinhdoanh.khong.duoc@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => ['lead.hoanthanh.test@egosolar.test']]);

        $id = $this->makeSchedule($owner);
        $this->actingAs($sales)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertForbidden();
        $this->assertSame('in_progress', SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id)->status);
    }

    public function test_designated_manager_completes_without_any_condition(): void
    {
        $owner = $this->technician();
        $listed = $this->userWithRole('technical_manager', ['email' => 'lead.hoanthanh.test@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => ['lead.hoanthanh.test@egosolar.test']]);

        // Phân công chưa duyệt + đợt mới chỉ "đã giao" + chưa có kết quả xử lý.
        $id = $this->makeSchedule($owner, resultNote: null);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update([
            'assignment_approval_status' => 'pending',
            'status' => 'assigned',
        ]);

        $this->actingAs($listed)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringContainsString('Hoàn tất trực tiếp bởi', (string) $schedule->approval_note);
        $this->assertStringContainsString('phân công chưa được duyệt', (string) $schedule->approval_note);
        $reason = DB::table('solar_maintenance_status_histories')->where('maintenance_schedule_id', $id)->where('to_status', 'completed')->value('reason');
        $this->assertStringContainsString('Hoàn tất trực tiếp bởi', (string) $reason);
    }

    public function test_designated_manager_cannot_complete_an_already_completed_round(): void
    {
        $owner = $this->technician();
        $listed = $this->userWithRole('technical_manager', ['email' => 'lead.hoanthanh.test@egosolar.test']);
        config(['technical.maintenance_complete_any_emails' => ['lead.hoanthanh.test@egosolar.test']]);
        $id = $this->makeSchedule($owner);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['status' => 'completed']);

        $this->actingAs($listed)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertSessionHasErrors('approval');
    }

    public function test_assigned_technician_completes_even_when_assignment_is_not_approved_and_round_is_company_one(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update([
            'assignment_approval_status' => 'pending',
            'company_id' => 1,
        ]);

        $html = $this->actingAs($tech)->get('/du-an/bao-tri-bao-hanh/'.$id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<button class="emd9-btn primary eme11-btn-complete" type="submit">/', $html);

        $this->actingAs($tech)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('completed', $schedule->status);
        $this->assertStringContainsString('phân công chưa được duyệt', (string) $schedule->approval_note);
    }

    public function test_unassigned_technician_is_forbidden_even_if_assignment_pending(): void
    {
        $owner = $this->technician();
        $other = $this->technician();
        $id = $this->makeSchedule($owner);
        DB::table('solar_maintenance_schedules')->where('id', $id)->update(['assignment_approval_status' => 'pending']);

        $this->actingAs($other)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertForbidden();
    }

    public function test_assigned_technician_still_needs_a_result_note(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech, resultNote: null);

        $this->actingAs($tech)
            ->post(route('projects-unified.maintenance.approval.complete', ['schedule' => $id]))
            ->assertSessionHasErrors('result_note');
    }
    public function test_default_config_lists_anh_thu(): void
    {
        $this->assertContains('anhthu@egosolar.vn', (array) config('technical.maintenance_complete_any_emails'));
    }

    public function test_complete_button_is_enabled_even_when_files_are_missing(): void
    {
        $tech = $this->technician();
        $id = $this->makeSchedule($tech);

        $html = $this->actingAs($tech)->get('/du-an/bao-tri-bao-hanh/'.$id)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button class="emd9-btn primary eme11-btn-complete" type="submit">/', $html);
        $this->assertStringContainsString('data-missing-evidence="1"', $html);
        $this->assertStringContainsString('chưa đủ file', $html);
    }
}
