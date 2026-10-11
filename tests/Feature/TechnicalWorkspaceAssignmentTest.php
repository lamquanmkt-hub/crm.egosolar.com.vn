<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TechnicalWorkspaceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist for tests
        $roles = ['admin', 'manager', 'technical_manager', 'technical_leader', 'technician', 'sales', 'accounting'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_manager_can_view_assignments_page()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)->get(route('technical-workspace.coordination.assignments'));

        $response->assertStatus(200);
        $response->assertViewIs('technical.workspace.assignments');
    }

    public function test_technician_cannot_view_assignments_page()
    {
        $technician = User::factory()->create();
        $technician->assignRole('technician');

        $response = $this->actingAs($technician)->get(route('technical-workspace.coordination.assignments'));

        $response->assertStatus(403);
    }

    public function test_sales_cannot_access_technical_workspace()
    {
        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $response = $this->actingAs($sales)->get(route('technical-workspace.coordination.assignments'));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get(route('technical-workspace.coordination.assignments'));

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_manager_can_submit_assignment_read_only()
    {
        $manager = User::factory()->create();
        $manager->assignRole('technical_leader');

        $response = $this->actingAs($manager)->post(route('technical-workspace.coordination.assignments.store'), [
            'project_id' => 1,
            'assigned_to' => 2,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Chế độ Read-Only: Chưa được phép ghi dữ liệu thật.');
    }

    public function test_technician_cannot_submit_assignment()
    {
        $technician = User::factory()->create();
        $technician->assignRole('technician');

        $response = $this->actingAs($technician)->post(route('technical-workspace.coordination.assignments.store'), [
            'project_id' => 1,
            'assigned_to' => 2,
        ]);

        $response->assertStatus(403);
    }
}
