<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use App\Services\Warranty\RepairService;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quy trình B — SỬA CHỮA SẢN PHẨM TÍNH PHÍ (10 bước).
 * Dữ liệu test mang tiền tố [LOCAL TEST REPAIR] / [LOCAL TEST WARRANTY].
 */
final class RepairWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use WarrantyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarranty();
    }

    private function rs(): RepairService
    {
        return app(RepairService::class);
    }

    private function createRepair(?\App\Models\User $by = null, ?int $serial = null, array $o = []): object
    {
        $by ??= $this->tech;
        $serial ??= $this->soldSerial(null, ['warranty_end_at' => now()->subDays(10)->toDateString()]); // hết bảo hành
        $r = $this->actingAs($by)->post(route('ky-thuat.repair.store'), array_merge([
            'source_type' => 'site', 'site_id' => $this->siteId, 'serial_code' => $this->code($serial), 'priority' => 'normal',
            'issue_description' => '[LOCAL TEST REPAIR] Inverter không lên nguồn',
        ], $o));
        $r->assertSessionHasNoErrors();
        $c = DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->orderByDesc('id')->first();
        $this->assertNotNull($c);

        return $c;
    }

    private function diagnose(object $c): void
    {
        $this->rs()->saveDiagnosis($c->id, $this->tech, [
            'diagnosis' => 'Cháy tụ nguồn', 'diagnosis_cause' => 'Quá áp lưới', 'proposed_solution' => 'Thay tụ + board nguồn',
            'parts_needed' => 'Board nguồn', 'est_repair_hours' => 3,
        ]);
    }

    private function quote(object $c, ?int $productId = null, float $qty = 2, float $price = 500000): object
    {
        return $this->rs()->saveQuotation($c->id, $this->tech, [
            'items' => [['product_id' => $productId ?? $this->p1, 'name' => '', 'quantity' => $qty, 'unit_price' => $price]],
            'labor_amount' => 300000, 'onsite_amount' => 100000, 'shipping_amount' => 50000, 'extra_amount' => 20000, 'discount_amount' => 70000,
        ]);
    }

    private function toApprovedForRepair(): object
    {
        $c = $this->createRepair();
        $this->diagnose($c);
        $this->quote($c);
        $this->rs()->sendQuotation($c->id, $this->tech);
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'zalo', null, 'Khách chốt qua Zalo');

        return $this->claim($c->id);
    }

    private function toRepairing(): object
    {
        $c = $this->toApprovedForRepair();
        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
        $this->rs()->issueParts($c->id, $this->kho);
        $this->rs()->startRepair($c->id, $this->tech);

        return $this->claim($c->id);
    }

    private function toHandedOver(): object
    {
        $c = $this->toRepairing();
        $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Thay board nguồn', 'repair_hours_actual' => 2.5], [$this->p1 => 1]);
        $this->rs()->submitToQa($c->id, $this->tech);
        $this->rs()->recordQa($c->id, $this->tech, 'pass', 'Vdc 600V, Pac 9.8kW', null);
        $this->rs()->returnLeftoverParts($c->id, $this->kho);
        $this->rs()->handover($c->id, $this->tech, ['handover_receiver_name' => 'Anh Nam', 'handover_condition' => 'Chạy tốt', 'handover_result' => 'Đã sửa xong']);

        return $this->claim($c->id);
    }

    // ------------------------------------------------------------------ 1-2. tiếp nhận + kiểm tra

    public function test_create_repair_out_of_warranty_starts_at_diagnosing_with_audit_and_open_key(): void
    {
        $serial = $this->soldSerial(null, ['warranty_end_at' => now()->subDays(5)->toDateString()]);
        $c = $this->createRepair($this->tech, $serial);
        $this->assertSame('paid_repair', $c->claim_type);
        $this->assertSame('diagnosing', $c->status);
        $this->assertSame('out_of_warranty', $c->warranty_eligibility);
        $this->assertSame($serial, (int) $c->open_serial_key);
        $this->assertStringStartsWith('SCTP-', $c->claim_code);
        $this->assertSame(['received', 'eligibility_check'], DB::table('warranty_claim_events')->where('claim_id', $c->id)->pluck('action')->all());
    }

    public function test_in_warranty_serial_requires_out_of_scope_reason(): void
    {
        $serial = $this->soldSerial(); // còn bảo hành
        $this->actingAs($this->tech)->post(route('ky-thuat.repair.store'), ['source_type' => 'site', 'site_id' => $this->siteId, 'serial_code' => $this->code($serial), 'priority' => 'normal', 'issue_description' => 'x'])
            ->assertSessionHasErrors('serial_code');
        $c = $this->createRepair($this->tech, $serial, ['out_of_scope_reason' => 'Hỏng do sét đánh, không thuộc phạm vi bảo hành']);
        $this->assertSame('in_warranty_out_of_scope', $c->warranty_eligibility);
    }

    public function test_repair_and_exchange_cannot_be_open_on_same_serial(): void
    {
        $serial = $this->soldSerial(null, ['warranty_end_at' => now()->subDays(5)->toDateString()]);
        $this->createRepair($this->tech, $serial);
        $this->actingAs($this->tech2)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial, ['warranty_exception' => '1', 'exception_reason' => 'Ngoại lệ thử nghiệm']))
            ->assertSessionHasErrors('serial_code');
        $this->actingAs($this->tech2)->post(route('ky-thuat.repair.store'), ['serial_code' => $this->code($serial), 'priority' => 'normal', 'issue_description' => 'x'])
            ->assertSessionHasErrors('serial_code');
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->count());
    }

    public function test_legacy_endpoints_cannot_create_or_force_repair_claims(): void
    {
        $this->actingAs($this->tech)->post('/du-an/bao-tri-bao-hanh/phieu-bao-hanh', [
            'site_id' => $this->siteId, 'claim_type' => 'paid_repair', 'priority' => 'normal', 'issue_description' => 'x',
        ])->assertSessionHasErrorsIn('warrantyClaim', 'claim_type');
        $c = $this->createRepair();
        $this->actingAs($this->lead)->post('/du-an/bao-tri-bao-hanh/phieu-bao-hanh/'.$c->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('diagnosing', $this->claim($c->id)->status);
    }

    // ------------------------------------------------------------------ 3. chẩn đoán

    public function test_diagnosis_required_fields_then_moves_to_quotation_draft(): void
    {
        $c = $this->createRepair();
        $this->actingAs($this->tech)->post(route('ky-thuat.repair.diagnosis', $c->id), ['diagnosis' => 'a'])->assertSessionHasErrors(['diagnosis_cause', 'proposed_solution']);
        $this->assertSame('diagnosing', $this->claim($c->id)->status);
        $this->actingAs($this->tech2)->post(route('ky-thuat.repair.diagnosis', $c->id), ['diagnosis' => 'a', 'diagnosis_cause' => 'b', 'proposed_solution' => 'c'])->assertSessionHasErrors('workflow');
        $this->assertSame('diagnosing', $this->claim($c->id)->status);
        $this->diagnose($c);
        $x = $this->claim($c->id);
        $this->assertSame('quotation_draft', $x->status);
        $this->assertSame('Quá áp lưới', $x->diagnosis_cause);
        $this->assertSame('Board nguồn', $x->parts_needed);
    }

    // ------------------------------------------------------------------ 4. báo giá server-side + version

    public function test_quotation_total_is_computed_server_side_with_exact_formula(): void
    {
        $calc = RepairService::computeQuotation(
            [['product_id' => null, 'name' => 'Tụ 450V', 'quantity' => 2, 'unit_price' => 150000.5], ['name' => 'Board nguồn', 'quantity' => 1, 'unit_price' => 2000000]],
            300000, 100000, 50000, 20000, 70000
        );
        // linh kiện = 2*150000.5 + 2000000 = 2.300.001 ; tổng = 2.300.001 + 300k + 100k + 50k + 20k − 70k = 2.700.001
        $this->assertSame('2300001.00', $calc['parts_total']);
        $this->assertSame('2700001.00', $calc['total_amount']);
        $this->assertSame(300001.0, $calc['items'][0]['line_total']);

        $c = $this->createRepair();
        $this->diagnose($c);
        // frontend gửi total giả → bị bỏ qua
        $r = $this->actingAs($this->tech)->post(route('ky-thuat.repair.quotation', $c->id), [
            'items' => [['name' => 'Tụ', 'quantity' => 1, 'unit_price' => 1000]], 'labor_amount' => 500, 'total_amount' => 1, 'parts_total' => 1,
        ]);
        $r->assertSessionHasNoErrors();
        $q = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->first();
        $this->assertSame('1500.00', $q->total_amount);
        $this->assertSame(1, (int) $q->version);
    }

    public function test_quotation_validation_rejects_negative_and_over_discount(): void
    {
        foreach ([
            fn () => RepairService::computeQuotation([['name' => 'x', 'quantity' => 0, 'unit_price' => 1]], 0, 0, 0, 0, 0),
            fn () => RepairService::computeQuotation([['name' => 'x', 'quantity' => 1, 'unit_price' => -1]], 0, 0, 0, 0, 0),
            fn () => RepairService::computeQuotation([], -5, 0, 0, 0, 0),
            fn () => RepairService::computeQuotation([['name' => 'x', 'quantity' => 1, 'unit_price' => 100]], 0, 0, 0, 0, 101),
            fn () => RepairService::computeQuotation([['name' => 'x', 'quantity' => 'abc', 'unit_price' => 100]], 0, 0, 0, 0, 0),
        ] as $case) {
            try {
                $case();
                $this->fail('Phải từ chối dữ liệu báo giá sai');
            } catch (WarrantyException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_editing_sent_quotation_creates_new_version_and_requires_reconfirmation(): void
    {
        $c = $this->createRepair();
        $this->diagnose($c);
        $v1 = $this->quote($c);
        $this->rs()->sendQuotation($c->id, $this->tech);
        $this->assertSame('waiting_customer_confirmation', $this->claim($c->id)->status);

        $v2 = $this->quote($c, null, 1, 400000);
        $this->assertSame(2, (int) $v2->version);
        $this->assertSame('superseded', DB::table('warranty_repair_quotations')->where('id', $v1->id)->value('status'));
        $x = $this->claim($c->id);
        $this->assertSame('quotation_draft', $x->status, 'Phải xác nhận lại');
        $this->assertSame((int) $v2->id, (int) $x->current_quotation_id);
        $this->assertSame(2, DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->count());
    }

    public function test_price_change_after_customer_approval_needs_new_version_and_reconfirmation(): void
    {
        $c = $this->toApprovedForRepair();
        $old = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->first();
        $this->assertSame('approved', $old->status);
        $this->assertNotNull($old->locked_at);
        $this->rs()->saveQuotation($c->id, $this->tech, ['items' => [['name' => 'Board', 'quantity' => 1, 'unit_price' => 999]]]);
        $this->assertSame('superseded', DB::table('warranty_repair_quotations')->where('id', $old->id)->value('status'));
        $this->assertSame('quotation_draft', $this->claim($c->id)->status);
        $this->assertSame(0, DB::table('warranty_repair_parts')->where('claim_id', $c->id)->count(), 'Linh kiện theo báo giá cũ phải bị hủy');
    }

    // ------------------------------------------------------------------ 5. khách xác nhận

    public function test_customer_approval_records_method_date_person_and_locks_quotation(): void
    {
        $c = $this->createRepair();
        $this->diagnose($c);
        $this->quote($c);
        $this->rs()->sendQuotation($c->id, $this->tech);
        try {
            $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'fax', null, null);
            $this->fail('Phương thức không hợp lệ');
        } catch (WarrantyException) {
        }
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'in_person', null, 'Ký biên bản báo giá');
        $q = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->first();
        $this->assertSame('approved', $q->status);
        $this->assertSame('in_person', $q->decision_method);
        $this->assertSame((int) $this->tech->id, (int) $q->decision_by);
        $this->assertNotNull($q->decision_at);
        $this->assertNotNull($q->locked_at);
        $this->assertSame('approved_for_repair', $this->claim($c->id)->status);
        $this->assertSame(1, DB::table('warranty_repair_parts')->where('claim_id', $c->id)->count());
    }

    public function test_customer_rejection_then_revision_flow(): void
    {
        $c = $this->createRepair();
        $this->diagnose($c);
        $this->quote($c);
        $this->rs()->sendQuotation($c->id, $this->tech);
        $this->rs()->customerDecision($c->id, $this->tech, 'rejected', 'phone', null, 'Giá cao');
        $this->assertSame('quotation_rejected', $this->claim($c->id)->status);
        $this->assertSame(0, DB::table('warranty_repair_parts')->where('claim_id', $c->id)->count());
        $v2 = $this->quote($c, null, 1, 100000);
        $this->assertSame(2, (int) $v2->version);
        $this->assertSame('quotation_draft', $this->claim($c->id)->status);
    }

    // ------------------------------------------------------------------ 6. linh kiện: reserve / issue / return

    public function test_parts_reserve_issue_and_return_leftover_keep_stock_consistent(): void
    {
        $c = $this->toApprovedForRepair();
        $stock0 = $this->stockQty(); // 20
        $rs = $this->rs();

        // KT không được giữ/xuất linh kiện
        foreach ([$this->tech, $this->lead] as $u) {
            try {
                $rs->reserveParts($c->id, $u, $this->wh);
                $this->fail('Chỉ Kho được giữ linh kiện');
            } catch (WarrantyException) {
            }
        }
        $rs->reserveParts($c->id, $this->kho, $this->wh);
        $this->assertSame('waiting_parts', $this->claim($c->id)->status);
        $this->assertSame($stock0, $this->stockQty(), 'Giữ hàng chưa trừ tồn vật lý');
        $this->assertEqualsWithDelta(18.0, app(\App\Services\Warranty\StockLedger::class)->availablePartQty($this->p1, $this->wh), 0.001, 'Tồn khả dụng giảm theo số đã giữ');

        try {
            $rs->issueParts($c->id, $this->tech);
            $this->fail();
        } catch (WarrantyException) {
        }
        $rs->issueParts($c->id, $this->kho);
        $this->assertSame($stock0 - 2, $this->stockQty());
        $this->assertSame(1, DB::table('crm_stock_movements')->where('reference_id', $c->id)->where('change_qty', -2)->count());
        $part = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->first();
        $this->assertSame('issued', $part->status);
        $this->assertSame((int) $this->kho->id, (int) $part->issued_by);
        $this->assertSame((int) $this->tech->id, (int) $part->received_by);

        // xuất lần 2 bị chặn
        try {
            $rs->issueParts($c->id, $this->kho);
            $this->fail();
        } catch (WarrantyException) {
        }
        $this->assertSame($stock0 - 2, $this->stockQty());

        // dùng 1, dư 1 → hoàn kho
        $rs->startRepair($c->id, $this->tech);
        $rs->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Thay 1 board'], [$this->p1 => 1]);
        $rs->returnLeftoverParts($c->id, $this->kho);
        $this->assertSame($stock0 - 1, $this->stockQty(), 'Hoàn kho phần dư');
        $part = DB::table('warranty_repair_parts')->where('claim_id', $c->id)->first();
        $this->assertSame('closed', $part->status);
        $this->assertEqualsWithDelta(1.0, (float) $part->qty_returned, 0.001);
        try {
            $rs->returnLeftoverParts($c->id, $this->kho);
            $this->fail('Không hoàn kho 2 lần');
        } catch (WarrantyException) {
        }
        $this->assertSame($stock0 - 1, $this->stockQty());
    }

    public function test_reserve_fails_when_available_stock_is_insufficient(): void
    {
        $c = $this->createRepair();
        $this->diagnose($c);
        $this->rs()->saveQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 50, 'unit_price' => 1]]]);
        $this->rs()->sendQuotation($c->id, $this->tech);
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'phone', null, null);
        $this->expectException(WarrantyException::class);
        $this->expectExceptionMessage('Tồn khả dụng không đủ');
        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
    }

    public function test_cancel_releases_reserved_parts_but_blocks_when_parts_issued(): void
    {
        $c = $this->toApprovedForRepair();
        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
        $this->rs()->cancel($c->id, $this->lead, 'Khách không sửa nữa');
        $this->assertSame('released', DB::table('warranty_repair_parts')->where('claim_id', $c->id)->value('status'));
        $this->assertSame('cancelled', $this->claim($c->id)->status);
        $this->assertNull($this->claim($c->id)->open_serial_key);

        $d = $this->toApprovedForRepair();
        $this->rs()->reserveParts($d->id, $this->kho, $this->wh);
        $this->rs()->issueParts($d->id, $this->kho);
        $this->expectException(WarrantyException::class);
        $this->rs()->cancel($d->id, $this->lead, 'Hủy sau khi đã xuất linh kiện');
    }

    // ------------------------------------------------------------------ 7-8. sửa + QA

    public function test_cannot_start_repair_before_parts_issued(): void
    {
        $c = $this->toApprovedForRepair();
        $this->expectException(WarrantyException::class);
        $this->rs()->startRepair($c->id, $this->tech);
    }

    public function test_qa_is_mandatory_before_handover_and_failure_returns_to_repair(): void
    {
        $c = $this->toRepairing();
        $this->assertSame('repairing', $c->status);
        // không cho nhảy thẳng Đang sửa → Bàn giao
        $this->assertFalse(WarrantyFlow::canTransition('paid_repair', 'repairing', 'handed_over'));
        try {
            $this->rs()->handover($c->id, $this->tech, ['handover_receiver_name' => 'A', 'handover_condition' => 'B', 'handover_result' => 'C']);
            $this->fail('Chưa QA không được bàn giao');
        } catch (WarrantyException) {
        }
        try {
            $this->rs()->submitToQa($c->id, $this->tech);
            $this->fail('Chưa ghi nội dung sửa');
        } catch (WarrantyException) {
        }
        $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Thay board'], [$this->p1 => 2]);
        $this->rs()->submitToQa($c->id, $this->tech);
        try {
            $this->rs()->recordQa($c->id, $this->tech, 'fail', 'Vdc thấp', '');
            $this->fail('QA fail bắt buộc ghi chú');
        } catch (WarrantyException) {
        }
        $this->rs()->recordQa($c->id, $this->tech, 'fail', 'Vdc 300V', 'Sụt áp khi tải 50%');
        $this->assertSame('qa_failed', $this->claim($c->id)->status);
        $this->rs()->startRepair($c->id, $this->tech);
        $this->assertSame('repairing', $this->claim($c->id)->status);
        $this->rs()->submitToQa($c->id, $this->tech);
        $this->rs()->recordQa($c->id, $this->tech, 'pass', 'Vdc 600V', null);
        $this->assertSame('ready_handover', $this->claim($c->id)->status);
        $this->assertSame(2, DB::table('warranty_repair_qa')->where('claim_id', $c->id)->count());
    }

    // ------------------------------------------------------------------ 9-10. bàn giao + hoàn tất

    public function test_handover_then_completion_locks_final_snapshot_and_cost(): void
    {
        $c = $this->toHandedOver();
        $this->assertSame('handed_over', $c->status);
        $this->assertSame('Anh Nam', $c->handover_receiver_name);
        $this->assertSame((int) $this->tech->id, (int) $c->handed_over_by);

        try {
            $this->rs()->complete($c->id, $this->tech);
            $this->fail('KT viên không được hoàn tất');
        } catch (WarrantyException) {
        }
        $this->rs()->complete($c->id, $this->lead);
        $x = $this->claim($c->id);
        $q = DB::table('warranty_repair_quotations')->where('id', $x->current_quotation_id)->first();
        $this->assertSame('completed', $x->status);
        $this->assertSame($q->total_amount, $x->final_cost);
        $this->assertEqualsWithDelta(1_400_000.0, (float) $x->final_cost, 0.01); // 2*500k + 300k + 100k + 50k + 20k − 70k
        $this->assertNotNull($q->locked_at);
        $snap = json_decode((string) $x->completion_snapshot, true);
        $this->assertSame((float) $q->total_amount, (float) $snap['final_cost']);
        $this->assertNotEmpty($snap['quotation_items']);
        $this->assertNotEmpty($snap['parts_actual']);
        $this->assertSame((int) $this->lead->id, (int) $x->closed_by);
        $this->assertNull($x->open_serial_key);
    }

    public function test_completed_twice_is_blocked_and_closer_is_kept(): void
    {
        $c = $this->toHandedOver();
        $this->rs()->complete($c->id, $this->lead);
        $first = $this->claim($c->id);
        $events = DB::table('warranty_claim_events')->where('claim_id', $c->id)->count();
        try {
            $this->rs()->complete($c->id, $this->admin);
            $this->fail('Không complete lần 2');
        } catch (WarrantyException) {
        }
        $this->assertSame((int) $first->closed_by, (int) $this->claim($c->id)->closed_by);
        $this->assertSame($events, DB::table('warranty_claim_events')->where('claim_id', $c->id)->count());
    }

    public function test_cannot_complete_when_parts_unsettled(): void
    {
        $c = $this->toRepairing();
        $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Xong'], [$this->p1 => 1]); // dùng 1/2, dư 1 chưa hoàn
        $this->rs()->submitToQa($c->id, $this->tech);
        $this->rs()->recordQa($c->id, $this->tech, 'pass', null, null);
        $this->rs()->handover($c->id, $this->tech, ['handover_receiver_name' => 'A', 'handover_condition' => 'B', 'handover_result' => 'C']);
        $this->expectException(WarrantyException::class);
        $this->expectExceptionMessage('Linh kiện');
        $this->rs()->complete($c->id, $this->lead);
    }

    // ------------------------------------------------------------------ audit + phân quyền + runtime

    public function test_repair_audit_trail_covers_every_step(): void
    {
        $c = $this->toHandedOver();
        $this->rs()->complete($c->id, $this->lead);
        $actions = DB::table('warranty_claim_events')->where('claim_id', $c->id)->pluck('action')->all();
        foreach (['received', 'eligibility_check', 'diagnosis', 'quotation_create', 'quotation_send', 'customer_approved', 'parts_reserve', 'parts_issue', 'repair_start',
            'repair_update', 'qa_submit', 'qa_pass', 'parts_return', 'handover', 'complete'] as $a) {
            $this->assertContains($a, $actions, "Thiếu audit: $a");
        }
        $row = DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', 'customer_approved')->first();
        $this->assertSame('waiting_customer_confirmation', $row->from_status);
        $this->assertSame('approved_for_repair', $row->to_status);
        $this->assertSame((int) $this->tech->id, (int) $row->user_id);
    }

    public function test_role_permissions_on_repair_actions(): void
    {
        $c = $this->createRepair();
        $this->actingAs($this->kho)->post(route('ky-thuat.repair.diagnosis', $c->id), ['diagnosis' => 'a', 'diagnosis_cause' => 'b', 'proposed_solution' => 'c'])->assertSessionHasErrors('workflow');
        $this->actingAs($this->sales)->post(route('ky-thuat.repair.diagnosis', $c->id), ['diagnosis' => 'a', 'diagnosis_cause' => 'b', 'proposed_solution' => 'c'])->assertSessionHasErrors('workflow');
        $this->actingAs($this->tech2)->get(route('ky-thuat.repair.show', $c->id))->assertForbidden();
        $this->actingAs($this->tech)->post(route('ky-thuat.repair.cancel', $c->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('diagnosing', $this->claim($c->id)->status);
    }

    public function test_repair_pages_render_without_500_for_each_role(): void
    {
        $c = $this->toRepairing();
        foreach ([$this->tech, $this->lead, $this->kho, $this->admin] as $u) {
            $this->actingAs($u)->get(route('ky-thuat.repair.index'))->assertOk();
            $this->actingAs($u)->get(route('ky-thuat.repair.show', $c->id))->assertOk()->assertSee($c->claim_code);
        }
        $this->actingAs($this->tech)->get(route('ky-thuat.repair.index', ['status' => 'repairing', 'q' => 'x', 'from' => '2026-01-01']))->assertOk();
        $this->actingAs($this->lead)->get(route('ky-thuat.warranty-exchange.index', ['assigned_to' => $this->tech->id, 'product_id' => $this->p1, 'customer' => 'Khach']))->assertOk();
    }
}
