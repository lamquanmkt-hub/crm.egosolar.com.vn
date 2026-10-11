<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use App\Support\Warranty\WarrantyException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phân công kỹ thuật viên phụ trách phiếu ĐỔI HÀNG BẢO HÀNH.
 *
 * - Kỹ thuật viên thường tạo được phiếu và tự là người phụ trách.
 * - Chỉ Trưởng phòng KT / Giám đốc / Admin được phân công hoặc đổi người phụ trách (khi phiếu còn mở).
 * - Phiếu chưa có người phụ trách thì bắt buộc chọn khi duyệt.
 */
final class WarrantyAssignmentTest extends TestCase
{
    use DatabaseTransactions;
    use WarrantyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarranty();
    }

    private function assignUrl(object $c): string
    {
        return route('ky-thuat.warranty-exchange.assign', ['claim' => $c->id]);
    }

    private function events(object $c, string $action)
    {
        return DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', $action)->get();
    }

    private function notified(object $c, int $userId, string $type): bool
    {
        return DB::table('warranty_claim_notifications')
            ->where('claim_id', $c->id)->where('user_id', $userId)->where('type', $type)->exists();
    }

    // ------------------------------------------------------------------ tạo phiếu

    public function test_regular_technician_can_create_and_is_auto_assigned(): void
    {
        $c = $this->createExchange($this->tech);

        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
        $this->assertSame((int) $this->tech->id, (int) $c->created_by);
    }

    public function test_technician_cannot_assign_someone_else_when_creating(): void
    {
        $c = $this->createExchange($this->tech, null, ['assigned_to' => $this->tech2->id]);

        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
    }

    public function test_lead_can_create_without_assignee(): void
    {
        $c = $this->createExchange($this->lead);

        $this->assertNull($c->assigned_to);
    }

    // ------------------------------------------------------------------ phân công

    public function test_lead_can_assign_unassigned_claim_and_technician_is_notified(): void
    {
        $c = $this->createExchange($this->admin);

        $this->actingAs($this->lead)->post($this->assignUrl($c), ['assigned_to' => $this->tech->id])
            ->assertSessionHasNoErrors();

        $c = $this->claim($c->id);
        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
        $this->assertSame($this->tech->name, $c->assigned_name);
        $this->assertSame('pending_approval', $c->status, 'Phân công không được đổi trạng thái phiếu');
        $this->assertCount(1, $this->events($c, 'assign'));
        $this->assertTrue($this->notified($c, (int) $this->tech->id, 'assigned'));
    }

    public function test_admin_can_reassign_with_reason_and_both_technicians_are_notified(): void
    {
        $c = $this->createExchange($this->tech);

        $this->actingAs($this->admin)->post($this->assignUrl($c), ['assigned_to' => $this->tech2->id, 'reason' => 'KTV cũ nghỉ phép'])
            ->assertSessionHasNoErrors();

        $c = $this->claim($c->id);
        $this->assertSame((int) $this->tech2->id, (int) $c->assigned_to);
        $event = $this->events($c, 'reassign')->first();
        $this->assertNotNull($event);
        $this->assertSame('KTV cũ nghỉ phép', $event->reason);
        $this->assertSame((int) $this->admin->id, (int) $event->user_id);
        $this->assertTrue($this->notified($c, (int) $this->tech2->id, 'assigned'));
        $this->assertTrue($this->notified($c, (int) $this->tech->id, 'unassigned'));
    }

    public function test_reassign_requires_reason(): void
    {
        $c = $this->createExchange($this->tech);

        $this->actingAs($this->lead)->post($this->assignUrl($c), ['assigned_to' => $this->tech2->id])
            ->assertSessionHasErrors('workflow');

        $this->assertSame((int) $this->tech->id, (int) $this->claim($c->id)->assigned_to);
    }

    public function test_regular_technician_cannot_assign_or_reassign(): void
    {
        $c = $this->createExchange($this->tech);

        $this->actingAs($this->tech)->post($this->assignUrl($c), ['assigned_to' => $this->tech2->id, 'reason' => 'Tự chuyển việc'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame((int) $this->tech->id, (int) $this->claim($c->id)->assigned_to);
    }

    public function test_warehouse_and_sales_cannot_assign(): void
    {
        $c = $this->createExchange($this->admin);

        foreach ([$this->kho, $this->sales] as $user) {
            $this->actingAs($user)->post($this->assignUrl($c), ['assigned_to' => $this->tech->id]);
            $this->assertNull($this->claim($c->id)->assigned_to);
        }
    }

    public function test_cannot_assign_non_technician(): void
    {
        $c = $this->createExchange($this->admin);

        $this->actingAs($this->lead)->post($this->assignUrl($c), ['assigned_to' => $this->sales->id])
            ->assertSessionHasErrors('workflow');

        $this->assertNull($this->claim($c->id)->assigned_to);
    }

    public function test_cannot_assign_closed_claim(): void
    {
        $c = $this->createExchange($this->tech);
        $this->svc()->reject($c->id, $this->lead, 'Không đủ điều kiện đổi');

        $this->expectException(WarrantyException::class);
        $this->svc()->assign($c->id, $this->lead, (int) $this->tech2->id, 'Chuyển người');
    }

    public function test_assign_is_possible_mid_workflow(): void
    {
        [$c] = $this->toIssued();

        $this->svc()->assign($c->id, $this->lead, (int) $this->tech2->id, 'Chuyển KTV gần công trình');

        $c = $this->claim($c->id);
        $this->assertSame('issued', $c->status);
        $this->assertSame((int) $this->tech2->id, (int) $c->assigned_to);

        // KTV mới nhận hàng được, KTV cũ thì không.
        $this->svc()->techReceive($c->id, $this->tech2, null, null, null);
        $this->assertSame('technician_received', $this->claim($c->id)->status);
    }

    public function test_previous_technician_loses_actions_after_reassign(): void
    {
        [$c] = $this->toIssued();
        $this->svc()->assign($c->id, $this->lead, (int) $this->tech2->id, 'Chuyển KTV khác');

        $this->expectException(WarrantyException::class);
        $this->svc()->techReceive($c->id, $this->tech, null, null, null);
    }

    // ------------------------------------------------------------------ duyệt khi chưa phân công

    public function test_approve_unassigned_claim_requires_assignee(): void
    {
        $c = $this->createExchange($this->admin);

        $this->actingAs($this->lead)->post(route('ky-thuat.warranty-exchange.approve', ['claim' => $c->id]), ['approval_note' => 'OK'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame('pending_approval', $this->claim($c->id)->status);
    }

    public function test_approve_with_assignee_assigns_then_moves_to_warehouse(): void
    {
        $c = $this->createExchange($this->admin);

        $this->actingAs($this->lead)->post(route('ky-thuat.warranty-exchange.approve', ['claim' => $c->id]), [
            'approval_note' => 'Đồng ý đổi',
            'assigned_to' => $this->tech->id,
        ])->assertSessionHasNoErrors();

        $c = $this->claim($c->id);
        $this->assertSame('waiting_stock', $c->status);
        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
        $this->assertCount(1, $this->events($c, 'assign'));
    }

    public function test_lead_cannot_bypass_self_approval_by_assigning_themselves(): void
    {
        $c = $this->createExchange($this->admin);

        try {
            $this->svc()->approve($c->id, $this->lead, null, null, (int) $this->lead->id);
            $this->fail('Trưởng phòng tự phân công cho mình rồi tự duyệt phải bị chặn');
        } catch (WarrantyException $e) {
            $this->assertStringContainsString('không được tự duyệt', $e->getMessage());
        }

        $c = $this->claim($c->id);
        $this->assertSame('pending_approval', $c->status);
        $this->assertNull($c->assigned_to);
    }

    public function test_approve_keeps_existing_assignee(): void
    {
        $c = $this->createExchange($this->tech);

        $this->svc()->approve($c->id, $this->lead, null, null, (int) $this->tech2->id);

        $this->assertSame((int) $this->tech->id, (int) $this->claim($c->id)->assigned_to);
    }

    // ------------------------------------------------------------------ quyền xem

    public function test_creator_technician_still_sees_claim_after_reassign_but_cannot_act(): void
    {
        $c = $this->createExchange($this->tech);
        $this->svc()->assign($c->id, $this->lead, (int) $this->tech2->id, 'Chuyển KTV khác');

        $this->actingAs($this->tech)->get(route('ky-thuat.warranty-exchange.show', ['claim' => $c->id]))
            ->assertOk()
            ->assertSee('Đổi người phụ trách') // nhãn sự kiện trong lịch sử phiếu
            ->assertDontSee('data-wx-open="mAssign"', false) // nhưng không có nút phân công
            ->assertDontSee('Xác nhận đã nhận hàng');

        $this->actingAs($this->tech)->get(route('ky-thuat.warranty-exchange.index'))
            ->assertOk()
            ->assertSee($c->claim_code);
    }

    public function test_unrelated_technician_cannot_see_claim(): void
    {
        $c = $this->createExchange($this->admin);

        $this->actingAs($this->tech2)->get(route('ky-thuat.warranty-exchange.show', ['claim' => $c->id]))
            ->assertForbidden();
    }

    public function test_show_page_offers_assign_action_only_to_leads(): void
    {
        $c = $this->createExchange($this->admin);
        $url = route('ky-thuat.warranty-exchange.show', ['claim' => $c->id]);

        $this->actingAs($this->lead)->get($url)->assertOk()
            ->assertSee('data-wx-open="mAssign"', false)
            ->assertSee('Phân công người phụ trách')
            ->assertSee('Kỹ thuật viên phụ trách (bắt buộc');

        $this->svc()->assign($c->id, $this->lead, (int) $this->tech->id);

        $this->actingAs($this->tech)->get($url)->assertOk()
            ->assertDontSee('data-wx-open="mAssign"', false);
    }
}
