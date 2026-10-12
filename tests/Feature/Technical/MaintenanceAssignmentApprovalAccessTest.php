<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MaintenanceAssignmentApprovalAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function pendingRound(?User $technician): SolarMaintenanceSchedule
    {
        $id = DB::table('solar_maintenance_schedules')->insertGetId([
            'type' => 'periodic',
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'company_id' => 2,
            'status' => 'scheduled',
            'approval_status' => 'not_submitted',
            'assignment_approval_status' => 'not_required',
            'assigned_to' => $technician?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schedule = SolarMaintenanceSchedule::withoutGlobalScopes()->findOrFail($id);
        if ($technician) {
            $schedule->assignees()->create(['user_id' => $technician->id, 'is_leader' => true]);
            $schedule->approvals()->create([
                'approval_level' => 'assignment',
                'submitted_by' => $technician->id,
                'action' => 'assignment_submitted',
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        return $schedule;
    }

    public static function approverRoles(): array
    {
        return [['technical_manager'], ['admin']];
    }

    #[DataProvider('approverRoles')]
    public function test_approver_sees_the_button_and_approval_opens_technician_acceptance(string $role): void
    {
        $approver = $this->userWithRole($role);
        $technician = $this->userWithRole('ky_thuat');
        $schedule = $this->pendingRound($technician);
        $url = route('projects-unified.maintenance.assignment.approve', $schedule);

        $this->actingAs($approver)->get(route('projects-unified.maintenance.show', $schedule))
            ->assertOk()->assertSee('action="'.$url.'"', false)
            ->assertViewHas('permissions', fn ($permissions) => $permissions['assignment_approve']
                && $permissions['assignment_admin'] === ($role === 'admin'));
        $this->post($url)->assertRedirect()->assertSessionHas('success');
        $this->assertSame('assigned', $schedule->fresh()->status);
        $approval = $schedule->approvals()->where('approval_level', 'assignment')->latest('id')->first();
        $this->assertSame('approved', $approval->status);
        $this->assertSame('assignment_approved', $approval->action);
        $this->assertSame((int) $approver->id, (int) $approval->approver_id);
        $this->assertNotNull($approval->reviewed_at);
        $this->assertNull($schedule->assignees()->first()->accepted_at);

        $this->actingAs($technician)->post(route('projects-unified.maintenance.assignment.accept', $schedule))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($schedule->assignees()->first()->accepted_at);
        $this->assertSame('in_progress', $schedule->fresh()->status);
    }

    public static function nonApproverRoles(): array
    {
        return [['ky_thuat', 200], ['technical_leader', 200], ['manager', 200], ['accounting', 200], ['kho', 403]];
    }

    #[DataProvider('nonApproverRoles')]
    public function test_other_roles_cannot_approve_even_by_posting_directly(string $role, int $viewStatus): void
    {
        $user = $this->userWithRole($role);
        $schedule = $this->pendingRound($user);
        $url = route('projects-unified.maintenance.assignment.approve', $schedule);

        $this->actingAs($user)->get(route('projects-unified.maintenance.show', $schedule))
            ->assertStatus($viewStatus)->assertDontSee('action="'.$url.'"', false);
        $this->post($url)->assertForbidden();
        $this->assertSame('scheduled', $schedule->fresh()->status);
        $this->assertSame(1, $schedule->approvals()->count());
        $this->assertSame('pending', $schedule->approvals()->first()->status);
    }

    public function test_technical_manager_cannot_approve_an_empty_assignment(): void
    {
        $manager = $this->userWithRole('technical_manager');
        $schedule = $this->pendingRound(null);

        $this->actingAs($manager)->post(route('projects-unified.maintenance.assignment.approve', $schedule))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('scheduled', $schedule->fresh()->status);
        $this->assertSame(0, $schedule->approvals()->count());
    }
}
