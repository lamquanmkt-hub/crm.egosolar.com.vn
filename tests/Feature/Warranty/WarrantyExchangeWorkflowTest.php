<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quy trình A — ĐỔI HÀNG BẢO HÀNH (11 bước): tạo → duyệt → Kho giữ/xuất → KT nhận/thay → thu hồi → hoàn tất.
 */
final class WarrantyExchangeWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use WarrantyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarranty();
    }

    // ------------------------------------------------------------------ 1-3. tạo phiếu & kiểm tra serial

    public function test_valid_serial_creates_pending_claim_with_audit_and_open_key(): void
    {
        $serial = $this->soldSerial();
        $c = $this->createExchange($this->tech, $serial);

        $this->assertSame('pending_approval', $c->status);
        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
        $this->assertSame($serial, (int) $c->open_serial_key);
        $this->assertSame('in_warranty', $c->warranty_eligibility);
        $actions = DB::table('warranty_claim_events')->where('claim_id', $c->id)->pluck('action')->all();
        $this->assertSame(['received', 'eligibility_check', 'create'], $actions);
    }

    public function test_serial_is_the_only_input_and_everything_else_is_derived_from_crm(): void
    {
        $serial = $this->soldSerial();
        // client cố gửi site/order/khách sai → bị bỏ qua, dữ liệu lấy từ serial
        $c = $this->createExchange($this->tech, $serial, ['site_id' => $this->site2Id, 'order_id' => $this->order2Id, 'customer_id' => 1]);
        $this->assertSame($this->siteId, (int) $c->site_id);
        $this->assertSame($this->orderId, (int) $c->order_id);
        $this->assertSame($this->customerId, (int) $c->customer_id);
        $this->assertSame((int) $this->tech->id, (int) $c->assigned_to);
    }

    public function test_serial_info_returns_full_traceback_and_open_claim_warning(): void
    {
        $serial = $this->soldSerial();
        $r = $this->actingAs($this->tech)->getJson(route('ky-thuat.warranty-exchange.serial-info', ['code' => $this->code($serial)]));
        $r->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('info.customer_id', $this->customerId)
            ->assertJsonPath('info.order_id', $this->orderId)
            ->assertJsonPath('info.site_id', $this->siteId)
            ->assertJsonPath('info.warranty_active', true)
            ->assertJsonPath('info.open_claim', null);
        $info = $r->json('info');
        foreach (['product_name', 'sku', 'customer_name', 'customer_phone', 'order_code', 'site_name', 'site_address', 'warranty_start_at', 'warranty_end_at', 'warranty_label', 'state', 'history', 'sold_at'] as $k) {
            $this->assertArrayHasKey($k, $info);
        }
        $c = $this->createExchange($this->tech, $serial);
        $again = $this->actingAs($this->tech)->getJson(route('ky-thuat.warranty-exchange.serial-info', ['code' => $this->code($serial)]))->json('info');
        $this->assertSame($c->claim_code, $again['open_claim']['code']);
        $this->assertNotEmpty($again['history']);

        $this->actingAs($this->tech)->getJson(route('ky-thuat.warranty-exchange.serial-info', ['code' => 'KHONG-TON-TAI']))->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_open_claim_message_mentions_existing_claim_code_and_json_store_works(): void
    {
        $serial = $this->soldSerial();
        $first = $this->createExchange($this->tech, $serial);
        $r = $this->actingAs($this->tech2)->postJson(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial));
        $r->assertStatus(422)->assertJsonValidationErrors('serial_code');
        $this->assertStringContainsString($first->claim_code, $r->json('errors.serial_code.0'));

        $ok = $this->actingAs($this->tech2)->postJson(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($this->soldSerial()));
        $ok->assertOk()->assertJsonPath('ok', true);
        $this->assertStringContainsString('/de-xuat-doi-hang-bao-hanh/', $ok->json('redirect'));
    }

    public function test_action_endpoints_return_json_errors_for_popups(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->actingAs($this->lead)->postJson(route('ky-thuat.warranty-exchange.reject', $c->id), ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->tech)->postJson(route('ky-thuat.warranty-exchange.approve', $c->id))->assertStatus(422)->assertJsonPath('ok', false);
        $this->actingAs($this->lead2)->postJson(route('ky-thuat.warranty-exchange.approve', $c->id))->assertOk()->assertJsonPath('ok', true);
    }

    public function test_expired_warranty_needs_exception_with_reason(): void
    {
        $serial = $this->expiredSerial();
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial))->assertSessionHasErrors('serial_code');
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial, ['warranty_exception' => '1']))->assertSessionHasErrors('serial_code');
        $this->assertSame(0, DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->count());

        $c = $this->createExchange($this->tech, $serial, ['warranty_exception' => '1', 'exception_reason' => 'Khách VIP, lỗi do nhà sản xuất']);
        $this->assertSame(1, (int) $c->warranty_exception);
        $this->assertSame((int) $this->tech->id, (int) $c->exception_requested_by);
        $this->assertNotNull($c->exception_requested_at);
        $this->assertNull($c->exception_approved_by);
        $this->assertSame('exception', $c->warranty_eligibility);
        $this->assertTrue(DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', 'exception_requested')->exists());
    }

    public function test_exception_requester_cannot_self_approve_and_other_lead_approves_exception(): void
    {
        $c = $this->createExchange($this->lead, $this->expiredSerial(), ['warranty_exception' => '1', 'exception_reason' => 'Ngoại lệ có lý do rõ ràng']);
        $this->expectExceptionObject(new WarrantyException('Người tạo/phụ trách/đề nghị ngoại lệ không được tự duyệt phiếu của mình. Cần người khác có thẩm quyền duyệt.'));
        try {
            $this->svc()->approve($c->id, $this->lead);
        } finally {
            $this->assertSame('pending_approval', $this->claim($c->id)->status);
        }
    }

    public function test_other_lead_approval_records_exception_approver_and_moves_to_warehouse(): void
    {
        $c = $this->createExchange($this->lead, $this->expiredSerial(), ['warranty_exception' => '1', 'exception_reason' => 'Ngoại lệ có lý do rõ ràng']);
        $this->svc()->approve($c->id, $this->lead2, 'OK');
        $x = $this->claim($c->id);
        $this->assertSame('waiting_stock', $x->status);
        $this->assertSame((int) $this->lead2->id, (int) $x->approved_by);
        $this->assertSame((int) $this->lead2->id, (int) $x->exception_approved_by);
        $this->assertNotNull($x->exception_approved_at);
        $this->assertTrue(DB::table('warranty_claim_notifications')->where('claim_id', $c->id)->where('audience', 'warehouse')->exists());
    }

    public function test_admin_override_self_approval_requires_reason_and_is_audited(): void
    {
        $c = $this->createExchange($this->admin, $this->soldSerial());
        try {
            $this->svc()->approve($c->id, $this->admin);
            $this->fail('Phải yêu cầu lý do override');
        } catch (WarrantyException $e) {
            $this->assertStringContainsString('lý do', $e->getMessage());
        }
        $this->svc()->approve($c->id, $this->admin, null, 'Khẩn cấp: khách dừng nhà máy');
        $x = $this->claim($c->id);
        $this->assertSame('waiting_stock', $x->status);
        $this->assertSame('Khẩn cấp: khách dừng nhà máy', $x->override_reason);
        $this->assertTrue(DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', 'approve_override')->exists());
    }

    public function test_duplicate_open_claim_on_same_serial_is_blocked_by_service_and_db_constraint(): void
    {
        $serial = $this->soldSerial();
        $first = $this->createExchange($this->tech, $serial);
        $r = $this->actingAs($this->tech2)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial));
        $r->assertSessionHasErrors('serial_code');
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->count());

        // ràng buộc DB: ngay cả khi bỏ qua service, unique(open_serial_key) chặn bản ghi thứ hai
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('crm_serial_warranty_claims')->insert([
            'claim_type' => 'replacement', 'status' => 'pending_approval', 'serial_unit_id' => $serial, 'serial_code' => $first->serial_code,
            'open_serial_key' => $serial, 'priority' => 'normal', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_user_without_permission_cannot_create(): void
    {
        $this->actingAs($this->kho)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($this->soldSerial()))->assertForbidden();
        $this->actingAs($this->sales)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($this->soldSerial()))->assertForbidden();
    }

    // ------------------------------------------------------------------ 4. duyệt / bổ sung / từ chối

    public function test_creator_cannot_self_approve(): void
    {
        $c = $this->createExchange($this->lead, $this->soldSerial());
        $this->expectException(WarrantyException::class);
        $this->svc()->approve($c->id, $this->lead);
    }

    public function test_technician_and_warehouse_cannot_approve(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.approve', $c->id))->assertSessionHasErrors('workflow');
        $this->actingAs($this->kho)->post(route('ky-thuat.warranty-exchange.approve', $c->id))->assertSessionHasErrors('workflow');
        $this->assertSame('pending_approval', $this->claim($c->id)->status);
    }

    public function test_request_info_and_reject_require_reason(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->actingAs($this->lead)->post(route('ky-thuat.warranty-exchange.request-info', $c->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->lead)->post(route('ky-thuat.warranty-exchange.reject', $c->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending_approval', $this->claim($c->id)->status);
        $this->expectException(WarrantyException::class);
        $this->svc()->reject($c->id, $this->lead, '  ab ');
    }

    public function test_request_info_has_own_status_and_resubmit_returns_to_approval(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->svc()->requestInfo($c->id, $this->lead, 'Thiếu ảnh chụp thông số đo');
        $x = $this->claim($c->id);
        $this->assertSame('needs_more_information', $x->status);
        $this->assertSame('Thiếu ảnh chụp thông số đo', $x->decision_reason);
        $this->assertSame((int) $this->lead->id, (int) $x->decision_by);

        // người không liên quan không gửi lại được
        try {
            $this->svc()->resubmit($c->id, $this->tech2, ['diagnosis' => 'x']);
            $this->fail();
        } catch (WarrantyException) {
            $this->assertSame('needs_more_information', $this->claim($c->id)->status);
        }
        $this->svc()->resubmit($c->id, $this->tech, ['diagnosis' => 'Đã đo lại: điện áp DC thấp']);
        $y = $this->claim($c->id);
        $this->assertSame('pending_approval', $y->status);
        $this->assertSame('Đã đo lại: điện áp DC thấp', $y->diagnosis);
    }

    public function test_reject_releases_open_key_and_allows_new_claim(): void
    {
        $serial = $this->soldSerial();
        $c = $this->createExchange($this->tech, $serial);
        $this->svc()->reject($c->id, $this->lead, 'Lỗi do khách lắp sai, không thuộc BH');
        $x = $this->claim($c->id);
        $this->assertSame('rejected', $x->status);
        $this->assertNull($x->open_serial_key);
        $this->assertNotNull($this->createExchange($this->tech2, $serial));
    }

    public function test_approval_never_overwrites_original_approver(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update(['approved_by' => $this->lead2->id, 'approved_at' => '2026-01-01 08:00:00']);
        $this->svc()->approve($c->id, $this->lead);
        $x = $this->claim($c->id);
        $this->assertSame((int) $this->lead2->id, (int) $x->approved_by);
        $this->assertSame('2026-01-01 08:00:00', $x->approved_at);
    }

    public function test_status_cannot_be_forced_through_legacy_status_endpoint(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $url = '/du-an/bao-tri-bao-hanh/phieu-bao-hanh/'.$c->id.'/status';
        $this->actingAs($this->tech)->post($url, ['status' => 'completed', 'actual_cost' => 1, 'approval_note' => 'hack'])->assertSessionHasErrors('status');
        $this->actingAs($this->lead)->post($url, ['status' => 'approved'])->assertSessionHasErrors('status');
        $x = $this->claim($c->id);
        $this->assertSame('pending_approval', $x->status);
        $this->assertNull($x->approval_note);
        $this->assertSame(0.0, (float) $x->actual_cost);
    }

    // ------------------------------------------------------------------ 5-6. Kho chọn serial + reserve

    public function test_warehouse_reserves_matching_serial_and_it_is_really_held(): void
    {
        [$c, $new] = $this->toReserved();
        $this->assertSame('reserved', $c->status);
        $this->assertSame($new, (int) $c->reserved_serial_unit_id);
        $this->assertSame('reserved', $this->state($new)->state);
        $res = DB::table('warranty_serial_reservations')->where('claim_id', $c->id)->first();
        $this->assertSame('active', $res->status);
        $this->assertSame($new, (int) $res->active_key);
        $mv = DB::table('solar_warranty_stock_movements')->where('id', $res->movement_id)->first();
        $this->assertSame('approved', $mv->status);
        $this->assertSame('warranty_out', $mv->movement_type);
    }

    public function test_replacement_must_be_same_product_model(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->svc()->approve($c->id, $this->lead);
        $battery = $this->stockSerial($this->p2);
        $this->expectException(WarrantyException::class);
        $this->expectExceptionMessage('CÙNG sản phẩm');
        try {
            $this->svc()->reserveSerial($c->id, $this->kho, $this->code($battery), $this->wh);
        } finally {
            $this->assertSame('in_stock', $this->state($battery)->state);
        }
    }

    public function test_serial_reserved_for_another_claim_is_rejected_and_db_constraint_holds(): void
    {
        [$c1, $new] = $this->toReserved();
        $c2 = $this->createExchange($this->tech2, $this->soldSerial());
        $this->svc()->approve($c2->id, $this->lead);
        try {
            $this->svc()->reserveSerial($c2->id, $this->kho, $this->code($new), $this->wh);
            $this->fail('Serial đã giữ cho phiếu khác phải bị chặn');
        } catch (WarrantyException) {
            $this->assertSame('waiting_stock', $this->claim($c2->id)->status);
        }
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('warranty_serial_reservations')->insert([
            'serial_unit_id' => $new, 'claim_id' => $c2->id, 'status' => 'active', 'active_key' => $new, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_sold_or_wrong_state_serial_cannot_be_reserved(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->svc()->approve($c->id, $this->lead);
        $sold = $this->stockSerial(null, 'sold');
        $this->expectException(WarrantyException::class);
        $this->svc()->reserveSerial($c->id, $this->kho, $this->code($sold), $this->wh);
    }

    public function test_only_warehouse_role_can_reserve_and_issue(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $this->svc()->approve($c->id, $this->lead);
        $new = $this->stockSerial();
        foreach ([$this->tech, $this->lead, $this->sales] as $u) {
            try {
                $this->svc()->reserveSerial($c->id, $u, $this->code($new), $this->wh);
                $this->fail('Người không thuộc Kho không được chọn serial');
            } catch (WarrantyException $e) {
                $this->assertStringContainsString('Kho', $e->getMessage());
            }
        }
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.reserve', $c->id), ['warehouse_id' => $this->wh, 'serial_code' => $this->code($new)])->assertSessionHasErrors('workflow');
        $this->assertSame('in_stock', $this->state($new)->state);
    }

    public function test_swapping_reserved_serial_releases_the_previous_one(): void
    {
        [$c, $first] = $this->toReserved();
        $second = $this->stockSerial();
        $this->svc()->reserveSerial($c->id, $this->kho, $this->code($second), $this->wh);
        $this->assertSame('in_stock', $this->state($first)->state);
        $this->assertSame('reserved', $this->state($second)->state);
        $this->assertSame('released', DB::table('warranty_serial_reservations')->where('serial_unit_id', $first)->value('status'));
        $this->assertSame('active', DB::table('warranty_serial_reservations')->where('serial_unit_id', $second)->value('status'));
        $this->assertSame($second, (int) $this->claim($c->id)->reserved_serial_unit_id);
    }

    public function test_cancelling_reserved_claim_releases_reservation_and_blocks_issue(): void
    {
        [$c, $new] = $this->toReserved();
        $this->svc()->cancel($c->id, $this->lead, 'Khách hủy yêu cầu đổi hàng');
        $x = $this->claim($c->id);
        $this->assertSame('cancelled', $x->status);
        $this->assertNull($x->open_serial_key);
        $this->assertSame('in_stock', $this->state($new)->state);
        $this->assertSame('released', DB::table('warranty_serial_reservations')->where('claim_id', $c->id)->value('status'));
        $this->assertSame('cancelled', DB::table('solar_warranty_stock_movements')->where('warranty_claim_id', $c->id)->value('status'));

        $before = $this->stockQty();
        try {
            $this->svc()->issue($c->id, $this->kho);
            $this->fail('Phiếu đã hủy không được xuất kho');
        } catch (WarrantyException) {
            $this->assertSame($before, $this->stockQty());
            $this->assertSame('in_stock', $this->state($new)->state);
        }
    }

    public function test_release_by_warehouse_requires_reason_and_returns_to_waiting_stock(): void
    {
        [$c, $new] = $this->toReserved();
        try {
            $this->svc()->releaseReservation($c->id, $this->kho, '');
            $this->fail();
        } catch (WarrantyException) {
        }
        $this->svc()->releaseReservation($c->id, $this->kho, 'Serial bị phát hiện trầy xước');
        $this->assertSame('waiting_stock', $this->claim($c->id)->status);
        $this->assertSame('in_stock', $this->state($new)->state);
    }

    // ------------------------------------------------------------------ 7. xuất kho

    public function test_issue_updates_serial_inventory_stock_and_is_not_repeatable(): void
    {
        [$c, $new] = $this->toReserved();
        $qty0 = $this->stockQty();
        $events0 = DB::table('crm_inventory_events')->count();
        $this->svc()->issue($c->id, $this->kho, 'Giao cho anh Ba');

        $x = $this->claim($c->id);
        $this->assertSame('issued', $x->status);
        $this->assertSame($new, (int) $x->replacement_serial_unit_id);
        $this->assertSame((int) $this->kho->id, (int) $x->issued_by);
        $this->assertNotNull($x->issued_at);
        $this->assertSame('sold', $this->state($new)->state);
        $this->assertNull($this->state($new)->warehouse_id);
        $this->assertSame($qty0 - 1, $this->stockQty());
        $this->assertSame($events0 + 1, DB::table('crm_inventory_events')->count());
        $this->assertSame('consumed', DB::table('warranty_serial_reservations')->where('claim_id', $c->id)->value('status'));
        $this->assertSame('completed', DB::table('solar_warranty_stock_movements')->where('warranty_claim_id', $c->id)->value('status'));
        $this->assertSame(1, DB::table('crm_stock_movements')->where('reference_id', $c->id)->where('change_qty', -1)->count());

        // bấm lần 2 → chặn, không trừ tồn/ghi event lần nữa
        try {
            $this->svc()->issue($c->id, $this->kho);
            $this->fail('Không được xuất kho 2 lần');
        } catch (WarrantyException) {
        }
        $this->assertSame($qty0 - 1, $this->stockQty());
        $this->assertSame($events0 + 1, DB::table('crm_inventory_events')->count());
    }

    // ------------------------------------------------------------------ 8. kỹ thuật nhận & thay

    public function test_only_assigned_technician_can_receive_and_confirm_replacement(): void
    {
        [$c, $new] = $this->toIssued();
        foreach ([$this->tech2, $this->kho, $this->sales] as $u) {
            try {
                $this->svc()->techReceive($c->id, $u, null, null, null);
                $this->fail('Chỉ KT phụ trách được nhận hàng');
            } catch (WarrantyException) {
            }
        }
        $this->svc()->techReceive($c->id, $this->tech, null, 'Anh Ba (Kho)', 'Hàng nguyên seal');
        $x = $this->claim($c->id);
        $this->assertSame('technician_received', $x->status);
        $this->assertSame((int) $this->tech->id, (int) $x->tech_received_by);
        $this->assertSame('Anh Ba (Kho)', $x->tech_delivered_by);

        // Kho xuất hàng chưa đồng nghĩa đã thay: chưa có cặp serial
        $this->assertSame(0, DB::table('warranty_serial_replacements')->where('claim_id', $c->id)->count());
    }

    public function test_confirm_replaced_stores_serial_pair_both_directions_and_warranty(): void
    {
        [$c, $new] = $this->toReplaced();
        $this->assertSame('waiting_faulty_return', $c->status);
        $pair = DB::table('warranty_serial_replacements')->where('claim_id', $c->id)->first();
        $this->assertSame((int) $c->serial_unit_id, (int) $pair->old_serial_unit_id);
        $this->assertSame($new, (int) $pair->new_serial_unit_id);
        $this->assertSame($this->siteId, (int) $pair->site_id);
        $this->assertSame($this->orderId, (int) $pair->order_id);
        $this->assertSame($this->customerId, (int) $pair->customer_id);
        $this->assertSame((int) $this->p1, (int) $pair->product_id);
        $this->assertSame((int) $this->tech->id, (int) $pair->technician_id);

        // truy vấn hai chiều
        $this->assertSame($new, (int) DB::table('warranty_serial_replacements')->where('old_serial_unit_id', $c->serial_unit_id)->value('new_serial_unit_id'));
        $this->assertSame((int) $c->serial_unit_id, (int) DB::table('warranty_serial_replacements')->where('new_serial_unit_id', $new)->value('old_serial_unit_id'));

        $w = DB::table('crm_serial_warranties')->where('serial_unit_id', $new)->first();
        $this->assertSame($this->siteId, (int) $w->site_id);
        $this->assertSame($this->customerId, (int) $w->customer_id);
        $this->assertSame((int) $c->serial_unit_id, (int) $w->replaced_serial_unit_id);
        $this->assertSame((int) $c->id, (int) $w->replacement_claim_id);
        $this->assertSame('replaced', DB::table('crm_serial_warranties')->where('serial_unit_id', $c->serial_unit_id)->value('status'));
    }

    // ------------------------------------------------------------------ 9. thu hồi thiết bị lỗi

    public function test_faulty_return_by_warehouse_updates_serial_and_stock(): void
    {
        [$c] = $this->toReplaced();
        $qty0 = $this->stockQty();
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'Anh Tư (KT)', 'damaged', 'Cháy nguồn');
        $x = $this->claim($c->id);
        $this->assertSame('faulty_returned', $x->status);
        $this->assertSame('returned', $x->faulty_return_status);
        $this->assertSame('damaged', $this->state((int) $c->serial_unit_id)->state);
        $this->assertSame($this->wh, (int) $this->state((int) $c->serial_unit_id)->warehouse_id);
        $this->assertSame((int) $this->kho->id, (int) $x->faulty_received_by);
        $this->assertSame('Anh Tư (KT)', $x->faulty_returned_by);
        $this->assertSame($qty0 + 1, $this->stockQty());

        $this->expectException(WarrantyException::class);
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'x', 'damaged', null);
    }

    public function test_exchange_first_return_later_is_tracked_and_completion_requires_deferral(): void
    {
        [$c] = $this->toReplaced();
        $this->assertSame('pending', $c->faulty_return_status);
        try {
            $this->svc()->complete($c->id, $this->lead, 'Đã thay xong');
            $this->fail('Chưa thu hồi và chưa hoãn → không được hoàn tất');
        } catch (WarrantyException) {
        }
        // kỹ thuật/kho không được hoãn
        try {
            $this->svc()->deferFaultyReturn($c->id, $this->tech, 'Khách chưa tháo kịp');
            $this->fail();
        } catch (WarrantyException) {
        }
        $this->svc()->deferFaultyReturn($c->id, $this->lead, 'Khách chưa tháo kịp, hẹn tuần sau');
        $this->assertSame('deferred', $this->claim($c->id)->faulty_return_status);
        $this->svc()->complete($c->id, $this->lead, 'Đã thay xong, thu hồi sau');
        $done = $this->claim($c->id);
        $this->assertSame('completed', $done->status);
        $this->assertSame('deferred', $done->faulty_return_status, 'Không tự coi là đã thu hồi');

        // theo dõi được thiết bị chưa trả về
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('faulty_return_status', 'deferred')->where('id', $c->id)->count());

        // thu hồi muộn sau hoàn tất: chỉ cập nhật dữ liệu thu hồi, không đổi trạng thái/người đóng
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'Anh Tư', 'normal', 'Trả muộn');
        $late = $this->claim($c->id);
        $this->assertSame('completed', $late->status);
        $this->assertSame('returned', $late->faulty_return_status);
        $this->assertSame($done->closed_by, $late->closed_by);
    }

    // ------------------------------------------------------------------ 11. hoàn tất

    public function test_complete_requires_all_conditions_and_resolution(): void
    {
        [$c] = $this->toReplaced();
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'KT', 'damaged', null);
        try {
            $this->svc()->complete($c->id, $this->tech, 'Xong');
            $this->fail('KT viên không được hoàn tất');
        } catch (WarrantyException) {
        }
        try {
            $this->svc()->complete($c->id, $this->lead, '');
            $this->fail('Thiếu kết quả xử lý');
        } catch (WarrantyException) {
        }
        $this->svc()->complete($c->id, $this->lead, 'Đã thay RP, thu hồi lỗi', 1200000.0);
        $x = $this->claim($c->id);
        $this->assertSame('completed', $x->status);
        $this->assertSame((int) $this->lead->id, (int) $x->closed_by);
        $this->assertNull($x->open_serial_key);
        $this->assertNotNull($x->completion_snapshot);
    }

    public function test_incomplete_claim_cannot_be_completed(): void
    {
        [$c] = $this->toIssued();
        $this->expectException(WarrantyException::class);
        $this->svc()->complete($c->id, $this->lead, 'Xong');
    }

    public function test_completed_twice_is_blocked_and_closer_is_not_overwritten(): void
    {
        [$c] = $this->toReplaced();
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'KT', 'damaged', null);
        $this->svc()->complete($c->id, $this->lead, 'Hoàn tất lần đầu');
        $first = $this->claim($c->id);
        $events = DB::table('warranty_claim_events')->where('claim_id', $c->id)->count();
        try {
            $this->svc()->complete($c->id, $this->admin, 'Hoàn tất lần hai');
            $this->fail('completed → completed phải bị chặn');
        } catch (WarrantyException) {
        }
        $x = $this->claim($c->id);
        $this->assertSame((int) $first->closed_by, (int) $x->closed_by);
        $this->assertSame($first->closed_at, $x->closed_at);
        $this->assertSame('Hoàn tất lần đầu', $x->resolution);
        $this->assertSame($events, DB::table('warranty_claim_events')->where('claim_id', $c->id)->count());
    }

    public function test_state_machine_whitelist_blocks_skipping(): void
    {
        $this->assertFalse(WarrantyFlow::canTransition('replacement', 'pending_approval', 'completed'));
        $this->assertFalse(WarrantyFlow::canTransition('replacement', 'approved', 'issued'));
        $this->assertFalse(WarrantyFlow::canTransition('replacement', 'issued', 'completed'));
        $this->assertFalse(WarrantyFlow::canTransition('replacement', 'cancelled', 'waiting_stock'));
        $this->assertFalse(WarrantyFlow::canTransition('replacement', 'completed', 'completed'));
        $this->assertTrue(WarrantyFlow::canTransition('replacement', 'reserved', 'waiting_stock'));
    }

    // ------------------------------------------------------------------ file minh chứng

    public function test_evidence_is_private_validated_authorized_and_locked_after_completion(): void
    {
        Storage::fake('local');
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $png = UploadedFile::fake()->image('loi.png', 40, 40);
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.evidence.upload', $c->id), ['evidence' => [$png]])->assertSessionHasNoErrors();

        $row = DB::table('solar_warranty_claim_attachments')->where('warranty_claim_id', $c->id)->first();
        $this->assertSame('local', $row->disk, 'Phải lưu private disk');
        $this->assertStringStartsWith('warranty-claims/'.$c->id.'/', $row->file_path);
        $this->assertNotSame('loi.png', basename($row->file_path));
        Storage::disk('local')->assertExists($row->file_path);
        $this->assertFalse(Storage::disk('public')->exists($row->file_path));
        $this->assertTrue(DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', 'evidence_upload')->exists());

        // loại file không hợp lệ
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.evidence.upload', $c->id), ['evidence' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')]])->assertSessionHasErrors('evidence');
        // file php đội lốt .jpg: kiểm MIME THẬT theo nội dung (UploadedFile thật, không phải bản fake đoán theo đuôi)
        $tmp = tempnam(sys_get_temp_dir(), 'wxe');
        file_put_contents($tmp, '<?php system($_GET[1]); ?>');
        $disguised = new UploadedFile($tmp, 'fake.jpg', 'image/jpeg', null, true);
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.evidence.upload', $c->id), ['evidence' => [$disguised]])->assertSessionHasErrors('evidence');
        $this->assertSame(1, DB::table('solar_warranty_claim_attachments')->where('warranty_claim_id', $c->id)->count());

        // phân quyền tải
        $url = route('ky-thuat.warranty-exchange.evidence.download', ['claim' => $c->id, 'attachment' => $row->id]);
        $this->actingAs($this->tech)->get($url)->assertOk();
        $this->actingAs($this->lead)->get($url)->assertOk();
        $this->actingAs($this->tech2)->get($url)->assertForbidden();
        $this->actingAs($this->sales)->get($url)->assertForbidden();

        // sau hoàn tất/hủy: không thêm/xóa
        $this->svc()->cancel($c->id, $this->lead, 'Hủy để kiểm tra khóa file');
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.evidence.upload', $c->id), ['evidence' => [UploadedFile::fake()->image('b.png')]])->assertSessionHasErrors('evidence');
        $this->actingAs($this->lead)->delete(route('ky-thuat.warranty-exchange.evidence.destroy', ['claim' => $c->id, 'attachment' => $row->id]))->assertSessionHasErrors('evidence');
        $this->assertNull(DB::table('solar_warranty_claim_attachments')->where('id', $row->id)->value('deleted_at'));
    }

    // ------------------------------------------------------------------ audit + UI runtime

    public function test_full_flow_writes_complete_audit_trail(): void
    {
        [$c, $new] = $this->toReplaced();
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'KT', 'damaged', null);
        $this->svc()->complete($c->id, $this->lead, 'Hoàn tất đầy đủ');

        $rows = DB::table('warranty_claim_events')->where('claim_id', $c->id)->orderBy('id')->get();
        $actions = $rows->pluck('action')->all();
        foreach (['received', 'eligibility_check', 'create', 'approve', 'to_warehouse', 'reserve', 'issue', 'tech_receive', 'confirm_replaced', 'faulty_return', 'complete'] as $a) {
            $this->assertContains($a, $actions, "Thiếu audit: $a");
        }
        $approve = $rows->firstWhere('action', 'approve');
        $this->assertSame('pending_approval', $approve->from_status);
        $this->assertSame('approved', $approve->to_status);
        $this->assertSame((int) $this->lead->id, (int) $approve->user_id);
        $this->assertNotNull($approve->after_data);
        $issue = $rows->firstWhere('action', 'issue');
        $this->assertSame((int) $this->kho->id, (int) $issue->user_id);
        $this->assertSame('reserved', $issue->from_status);
        $this->assertSame('issued', $issue->to_status);
    }

    public function test_pages_render_for_each_role_without_500(): void
    {
        [$c] = $this->toReserved();
        foreach ([$this->tech, $this->lead, $this->kho, $this->admin] as $u) {
            $this->actingAs($u)->get(route('ky-thuat.warranty-exchange.index'))->assertOk();
            $this->actingAs($u)->get(route('ky-thuat.warranty-exchange.show', $c->id))->assertOk();
        }
        $this->actingAs($this->kho)->get(route('ky-thuat.warranty-exchange.warehouse-queue'))->assertOk()->assertSee($c->claim_code);
        $this->actingAs($this->lead)->get(route('ky-thuat.warranty-exchange.warehouse-queue'))->assertOk();
        $this->actingAs($this->tech)->get(route('ky-thuat.warranty-exchange.warehouse-queue'))->assertForbidden();
        $this->actingAs($this->tech2)->get(route('ky-thuat.warranty-exchange.show', $c->id))->assertForbidden();
    }

    public function test_internal_note_hidden_from_sales_and_warehouse_actions_hidden_from_technician(): void
    {
        [$c] = $this->toReserved();
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update(['internal_note' => 'GHI-CHU-NOI-BO-KY-THUAT']);
        $this->actingAs($this->sales)->get(route('ky-thuat.warranty-exchange.show', $c->id))->assertOk()->assertDontSee('GHI-CHU-NOI-BO-KY-THUAT');
        $this->actingAs($this->tech)->get(route('ky-thuat.warranty-exchange.show', $c->id))->assertOk()->assertSee('GHI-CHU-NOI-BO-KY-THUAT')->assertDontSee('XÁC NHẬN XUẤT KHO');
        $this->actingAs($this->kho)->get(route('ky-thuat.warranty-exchange.show', $c->id))->assertOk()->assertSee('XÁC NHẬN XUẤT KHO');
    }

    // ------------------------------------------------------------------ tương thích phiếu cũ (trước v2)

    public function test_legacy_approved_claim_can_continue_with_new_warehouse_flow(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update(['status' => 'approved', 'approved_by' => $this->lead->id, 'approved_at' => now()]);
        $new = $this->stockSerial();
        $this->svc()->reserveSerial($c->id, $this->kho, $this->code($new), $this->wh);
        $this->assertSame('reserved', $this->claim($c->id)->status);
        $this->assertSame('reserved', $this->state($new)->state);
    }

    public function test_legacy_replacing_claim_without_issue_dates_can_be_finished(): void
    {
        $serial = $this->soldSerial();
        $c = $this->createExchange($this->tech, $serial);
        $new = $this->stockSerial();
        // dữ liệu kiểu cũ: đã xuất serial thay thế nhưng chưa có issued_at/tech_received_at/reservation
        DB::table('crm_serial_unit_states')->where('serial_unit_id', $new)->update(['state' => 'sold', 'warehouse_id' => null]);
        DB::table('crm_serial_warranty_claims')->where('id', $c->id)->update([
            'status' => 'replacing', 'approved_by' => $this->lead->id, 'approved_at' => now(),
            'replacement_serial_unit_id' => $new, 'replacement_serial_code' => $this->code($new),
        ]);
        $this->svc()->confirmReplaced($c->id, $this->tech, null, 'success', null);
        $this->svc()->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'KT', 'damaged', null);
        $this->svc()->complete($c->id, $this->lead, 'Hoàn tất phiếu cũ');
        $this->assertSame('completed', $this->claim($c->id)->status);
        $this->assertSame(1, DB::table('warranty_serial_replacements')->where('claim_id', $c->id)->count());
    }
}
