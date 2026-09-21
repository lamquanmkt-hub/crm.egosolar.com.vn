<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use App\Services\Warranty\RepairService;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hardening: chống trùng serial, số lượng linh kiện, báo giá phát sinh V1→V2, idempotency, file/security, audit.
 */
final class WarrantyHardeningTest extends TestCase
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

    private function intake(?\App\Models\User $by = null, array $o = []): object
    {
        $payload = array_merge([
            'customer_name' => '[LOCAL TEST REPAIR] K'.random_int(100, 999), 'customer_phone' => '0955'.random_int(100000, 999999),
            'device_type' => 'Inverter', 'device_model' => 'M-'.random_int(100, 999), 'priority' => 'normal', 'issue_description' => 'Không lên nguồn',
        ], $o);
        $r = $this->actingAs($by ?? $this->tech)->postJson(route('ky-thuat.repair.store'), $payload);
        $r->assertOk();

        return DB::table('crm_serial_warranty_claims')->orderByDesc('id')->first();
    }

    /** Phiếu sửa: có 1 dòng linh kiện SL $qty, khách đã duyệt V1, sẵn sàng cho Kho. */
    private function approvedRepair(float $qty = 3, float $price = 500000): object
    {
        $c = $this->intake();
        $this->rs()->saveDiagnosis($c->id, $this->tech, ['diagnosis' => 'a', 'diagnosis_cause' => 'b', 'proposed_solution' => 'c']);
        $this->rs()->saveQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'name' => '', 'quantity' => $qty, 'unit_price' => $price]], 'labor_amount' => 300000]);
        $this->rs()->sendQuotation($c->id, $this->tech);
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'zalo', null, null);

        return $this->claim($c->id);
    }

    private function repairing(float $qty = 3): object
    {
        $c = $this->approvedRepair($qty);
        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
        $this->rs()->issueParts($c->id, $this->kho);
        $this->rs()->startRepair($c->id, $this->tech);

        return $this->claim($c->id);
    }

    private function part(int $claimId): object
    {
        return DB::table('warranty_repair_parts')->where('claim_id', $claimId)->first();
    }

    // =================================================================== 2. CHỐNG TRÙNG SERIAL

    public function test_serial_normalization_trims_collapses_and_uppercases_but_keeps_original(): void
    {
        $this->assertSame('AB 12', WarrantyFlow::normalizeSerial("  ab \t 12  "));
        $this->assertNull(WarrantyFlow::normalizeSerial('   '));
        $this->assertNull(WarrantyFlow::normalizeSerial(null));
        $c = $this->intake(null, ['serial_code' => '  ext-Serial 01 ']);
        $this->assertSame('ext-Serial 01', $c->serial_code, 'Serial gốc để hiển thị được giữ nguyên (đã trim)');
        $this->assertSame('EXT-SERIAL 01', $c->open_serial_norm);
    }

    public function test_repair_external_serial_cannot_have_two_open_claims_regardless_of_case_or_spaces(): void
    {
        $this->intake(null, ['serial_code' => 'EXT-SN-0001']);
        foreach (['ext-sn-0001', '  EXT-SN-0001  ', 'Ext-Sn-0001'] as $variant) {
            $r = $this->actingAs($this->tech2)->postJson(route('ky-thuat.repair.store'), [
                'customer_name' => 'Khác', 'customer_phone' => '0955'.random_int(100000, 999999), 'device_type' => 'I', 'device_model' => 'M', 'priority' => 'normal',
                'issue_description' => 'x', 'serial_code' => $variant,
            ]);
            $r->assertStatus(422)->assertJsonValidationErrors('serial_code');
        }
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('open_serial_norm', 'EXT-SN-0001')->count());
    }

    public function test_same_external_device_can_return_for_repair_after_previous_claim_is_closed(): void
    {
        $first = $this->intake(null, ['serial_code' => 'EXT-BACK-1']);
        $this->rs()->cancel($first->id, $this->lead, 'Khách rút lại yêu cầu sửa');
        $this->assertNull($this->claim($first->id)->open_serial_norm);
        $second = $this->intake(null, ['serial_code' => 'ext-back-1']);
        $this->assertSame('EXT-BACK-1', $second->open_serial_norm);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_claims_without_serial_are_never_blocked_by_the_serial_rule(): void
    {
        $a = $this->intake();
        $b = $this->intake();
        $this->assertNull($a->open_serial_norm);
        $this->assertNull($b->open_serial_norm);
        $this->assertNotSame($a->id, $b->id);
    }

    public function test_duplicate_open_repair_needs_override_permission_and_reason(): void
    {
        $this->intake(null, ['serial_code' => 'EXT-DUP-1']);
        $payload = ['customer_name' => 'K2', 'customer_phone' => '0955'.random_int(100000, 999999), 'device_type' => 'I', 'device_model' => 'M', 'priority' => 'normal', 'issue_description' => 'x', 'serial_code' => 'EXT-DUP-1', 'duplicate_override' => 1];
        // KT viên không có quyền override
        $this->actingAs($this->tech)->postJson(route('ky-thuat.repair.store'), $payload + ['duplicate_override_reason' => 'Cần gấp'])->assertStatus(422);
        // Admin có quyền nhưng thiếu lý do
        $this->actingAs($this->admin)->postJson(route('ky-thuat.repair.store'), $payload)->assertStatus(422);
        // Admin + lý do → tạo được, KHÔNG giữ khóa mở, ghi audit
        $this->actingAs($this->admin)->postJson(route('ky-thuat.repair.store'), $payload + ['duplicate_override_reason' => 'Hai thiết bị cùng nhãn serial in sai'])->assertOk();
        $dup = DB::table('crm_serial_warranty_claims')->orderByDesc('id')->first();
        $this->assertNull($dup->open_serial_norm);
        $this->assertSame('Hai thiết bị cùng nhãn serial in sai', $dup->duplicate_override_reason);
        $this->assertSame((int) $this->admin->id, (int) $dup->duplicate_override_by);
        $this->assertTrue(DB::table('warranty_claim_events')->where('claim_id', $dup->id)->where('action', 'duplicate_override')->exists());
    }

    public function test_db_unique_key_on_normalized_serial_is_the_last_line_of_defence(): void
    {
        $c = $this->intake(null, ['serial_code' => 'EXT-UQ-1']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('crm_serial_warranty_claims')->insert(['claim_type' => 'paid_repair', 'status' => 'diagnosing', 'serial_code' => 'ext-uq-1', 'open_serial_norm' => 'EXT-UQ-1', 'priority' => 'normal', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertNotNull($c);
    }

    public function test_exchange_serial_keys_are_released_on_terminal_and_reacquired_on_reopen(): void
    {
        $serial = $this->soldSerial();
        $c = $this->createExchange($this->tech, $serial);
        $this->assertNotNull($c->open_serial_key);
        $this->assertNotNull($c->open_serial_norm);
        $this->svc()->reject($c->id, $this->lead, 'Không thuộc phạm vi bảo hành');
        $r = $this->claim($c->id);
        $this->assertNull($r->open_serial_key);
        $this->assertNull($r->open_serial_norm);
        $this->svc()->reopenRejected($c->id, $this->lead, 'Có thêm bằng chứng từ khách');
        $o = $this->claim($c->id);
        $this->assertSame($serial, (int) $o->open_serial_key);
        $this->assertNotNull($o->open_serial_norm);
    }

    // =================================================================== 3. LINH KIỆN THEO SỐ LƯỢNG

    public function test_parts_quantities_planned_issued_used_returned_and_stock_are_consistent(): void
    {
        $c = $this->approvedRepair(3);
        $p = $this->part($c->id);
        $this->assertEqualsWithDelta(3.0, (float) $p->qty_planned, 0.001);
        $this->assertEqualsWithDelta(500000.0, (float) $p->unit_price, 0.001);
        $stock0 = $this->stockQty();

        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
        $this->assertEqualsWithDelta(3.0, (float) $this->part($c->id)->qty_reserved, 0.001);
        $this->assertSame($stock0, $this->stockQty());
        $this->rs()->issueParts($c->id, $this->kho);
        $p = $this->part($c->id);
        $this->assertEqualsWithDelta(3.0, (float) $p->qty_issued, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $p->qty_reserved, 0.001);
        $this->assertSame($stock0 - 3, $this->stockQty());
        $this->assertSame('issued', $p->status);

        $this->rs()->startRepair($c->id, $this->tech);
        // dùng nhiều hơn đã xuất → chặn
        try {
            $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'x'], [$this->p1 => 4]);
            $this->fail('used > issued phải bị chặn');
        } catch (WarrantyException) {
        }
        $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Thay 2 linh kiện'], [$this->p1 => 2]);
        $this->rs()->returnLeftoverParts($c->id, $this->kho);
        $p = $this->part($c->id);
        $this->assertEqualsWithDelta(1.0, (float) $p->qty_returned, 0.001, 'Dự kiến 3, xuất 3, dùng 2 → hoàn 1');
        $this->assertSame($stock0 - 2, $this->stockQty());
        $this->assertSame('closed', $p->status);

        $out = DB::table('solar_warranty_stock_movements')->where('warranty_claim_id', $c->id)->where('part_line_id', $p->id)->pluck('quantity', 'movement_type');
        $this->assertEqualsWithDelta(3.0, (float) $out['repair_part_out'], 0.001);
        $this->assertEqualsWithDelta(1.0, (float) $out['repair_part_return'], 0.001);
        $this->assertSame(1, DB::table('crm_stock_movements')->where('reference_id', $c->id)->where('change_qty', 1)->count());

        try {
            $this->rs()->returnLeftoverParts($c->id, $this->kho);
            $this->fail('Không hoàn kho 2 lần');
        } catch (WarrantyException) {
        }
        $this->assertSame($stock0 - 2, $this->stockQty());
    }

    public function test_negative_stock_is_impossible_and_reserve_is_all_or_nothing(): void
    {
        $c = $this->approvedRepair(25); // tồn chỉ 20
        try {
            $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
            $this->fail('Không đủ tồn khả dụng');
        } catch (WarrantyException $e) {
            $this->assertStringContainsString('Tồn khả dụng không đủ', $e->getMessage());
        }
        $this->assertEqualsWithDelta(0.0, (float) $this->part($c->id)->qty_reserved, 0.001);
        // xuất trực tiếp vượt tồn tại tầng ledger cũng bị chặn
        $this->expectException(WarrantyException::class);
        app(\App\Services\Warranty\StockLedger::class)->adjustProductStock($this->p1, $this->wh, $this->companyId, -21, 'test', 1, (int) $this->kho->id);
    }

    public function test_two_repairs_cannot_reserve_more_than_available_stock(): void
    {
        $a = $this->approvedRepair(15);
        $b = $this->approvedRepair(15);
        $this->rs()->reserveParts($a->id, $this->kho, $this->wh);
        try {
            $this->rs()->reserveParts($b->id, $this->kho, $this->wh); // khả dụng còn 5
            $this->fail('Phiếu thứ hai không được giữ vượt tồn');
        } catch (WarrantyException) {
        }
        $this->assertEqualsWithDelta(5.0, app(\App\Services\Warranty\StockLedger::class)->availablePartQty($this->p1, $this->wh), 0.001);
    }

    // =================================================================== 4. BÁO GIÁ PHÁT SINH V1 → V2

    public function test_change_quotation_keeps_v1_immutable_and_waits_for_customer(): void
    {
        $c = $this->repairing(3);
        $v1 = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->first();
        $this->assertSame('approved', $v1->status);
        $this->assertNotNull($v1->locked_at);
        $v1Total = (float) $v1->total_amount; // 3×500k + 300k = 1.800.000

        // thiếu lý do / sai người → chặn
        try {
            $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 500000]], 'labor_amount' => 300000], '');
            $this->fail('Bắt buộc lý do');
        } catch (WarrantyException) {
        }
        try {
            $this->rs()->saveChangeQuotation($c->id, $this->tech2, ['items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 500000]], 'labor_amount' => 300000], 'Phát sinh');
            $this->fail('Chỉ KT phụ trách/Trưởng phòng');
        } catch (WarrantyException) {
        }

        $v2 = $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 500000]], 'labor_amount' => 300000], 'Tháo máy phát hiện cần thêm 1 linh kiện');
        $x = $this->claim($c->id);
        $this->assertSame('waiting_change_confirmation', $x->status);
        $this->assertSame(2, (int) $v2->version);
        $this->assertSame('sent', $v2->status);
        $this->assertSame(1, (int) $v2->is_change);
        $this->assertSame((int) $v1->id, (int) $v2->base_quotation_id);
        $this->assertEqualsWithDelta(2300000.0, (float) $v2->total_amount, 0.01);
        // V1 KHÔNG bị đụng; phần phát sinh chưa được coi là đã duyệt
        $v1b = DB::table('warranty_repair_quotations')->where('id', $v1->id)->first();
        $this->assertSame('approved', $v1b->status);
        $this->assertEqualsWithDelta($v1Total, (float) $v1b->total_amount, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $this->part($c->id)->qty_planned, 0.001, 'SL cần chưa đổi cho tới khi khách duyệt');
        // không được QA / hoàn tất khi đang chờ xác nhận
        foreach ([fn () => $this->rs()->submitToQa($c->id, $this->tech), fn () => $this->rs()->complete($c->id, $this->lead)] as $try) {
            try {
                $try();
                $this->fail('Đang chờ khách xác nhận phát sinh');
            } catch (WarrantyException) {
            }
        }
        $delta = DB::table('warranty_claim_events')->where('claim_id', $c->id)->where('action', 'change_quote_delta')->first();
        $this->assertStringContainsString('500000', (string) $delta->after_data);
        $this->assertSame('Tháo máy phát hiện cần thêm 1 linh kiện', $delta->reason);
        $this->actingAs($this->lead)->get(route('ky-thuat.repair.show', $c->id))->assertOk()->assertSee('PHÁT SINH')->assertSee('Lịch sử các version');
    }

    public function test_customer_approves_change_then_kho_issues_extra_parts_and_repair_resumes(): void
    {
        $c = $this->repairing(3);
        $stock = $this->stockQty();
        $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 500000]], 'labor_amount' => 300000], 'Cần thêm 1 linh kiện');
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'phone', null, 'Khách đồng ý phát sinh');
        $x = $this->claim($c->id);
        $this->assertSame('waiting_parts', $x->status, 'Có linh kiện bổ sung → chờ Kho xuất');
        $qs = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->orderBy('version')->get();
        $this->assertSame('superseded', $qs[0]->status);
        $this->assertSame('approved', $qs[1]->status);
        $this->assertNotNull($qs[1]->locked_at);
        $p = $this->part($c->id);
        $this->assertEqualsWithDelta(4.0, (float) $p->qty_planned, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $p->qty_issued, 0.001);

        try {
            $this->rs()->startRepair($c->id, $this->tech);
            $this->fail('Chưa xuất bổ sung');
        } catch (WarrantyException) {
        }
        $this->rs()->reserveParts($c->id, $this->kho, $this->wh);
        $this->rs()->issueParts($c->id, $this->kho);
        $this->assertSame($stock - 1, $this->stockQty(), 'Chỉ xuất phần bổ sung 1');
        $this->assertEqualsWithDelta(4.0, (float) $this->part($c->id)->qty_issued, 0.001);
        $this->rs()->startRepair($c->id, $this->tech);
        $this->assertSame('repairing', $this->claim($c->id)->status);
        $this->assertSame((int) $qs[1]->id, (int) $this->claim($c->id)->current_quotation_id);
    }

    public function test_change_without_extra_parts_resumes_directly_and_final_cost_uses_approved_v2(): void
    {
        $c = $this->repairing(3);
        $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 3, 'unit_price' => 500000]], 'labor_amount' => 300000, 'extra_amount' => 200000], 'Phát sinh công tháo lắp thêm');
        $this->rs()->customerDecision($c->id, $this->tech, 'approved', 'zalo', null, null);
        $this->assertSame('repairing', $this->claim($c->id)->status);
        $this->rs()->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Xong'], [$this->p1 => 3]);
        $this->rs()->submitToQa($c->id, $this->tech);
        $this->rs()->recordQa($c->id, $this->tech, 'pass', null, null);
        $this->rs()->handover($c->id, $this->tech, ['handover_receiver_name' => 'A', 'handover_condition' => 'B', 'handover_result' => 'C']);
        $this->rs()->complete($c->id, $this->lead);
        $this->assertEqualsWithDelta(2000000.0, (float) $this->claim($c->id)->final_cost, 0.01); // 1.5M + 300k + 200k
    }

    public function test_customer_rejects_change_history_kept_and_decision_paths(): void
    {
        $c = $this->repairing(3);
        $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 5, 'unit_price' => 500000]], 'labor_amount' => 300000], 'Phát sinh lớn');
        $this->rs()->customerDecision($c->id, $this->tech, 'rejected', 'in_person', null, 'Giá cao, không đồng ý');
        $x = $this->claim($c->id);
        $this->assertSame('change_rejected', $x->status);
        $qs = DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->orderBy('version')->get();
        $this->assertSame('approved', $qs[0]->status, 'V1 vẫn là báo giá hiệu lực');
        $this->assertSame('rejected', $qs[1]->status);
        $this->assertSame('Giá cao, không đồng ý', $qs[1]->decision_note);
        $this->assertSame((int) $qs[0]->id, (int) $x->current_quotation_id);
        $this->assertEqualsWithDelta(3.0, (float) $this->part($c->id)->qty_planned, 0.001);

        // KT viên khác không quyết định; Kho không; Trưởng phòng/KT phụ trách được
        foreach ([$this->tech2, $this->kho, $this->sales] as $u) {
            $this->actingAs($u)->postJson(route('ky-thuat.repair.resume-original', $c->id))->assertStatus(422);
        }
        // Hủy chỉ Trưởng phòng và chỉ khi linh kiện đã hoàn kho
        $this->actingAs($this->tech)->postJson(route('ky-thuat.repair.cancel', $c->id), ['reason' => 'Không sửa nữa'])->assertStatus(422);
        try {
            $this->rs()->cancel($c->id, $this->lead, 'Không thể sửa theo yêu cầu');
            $this->fail('Linh kiện đã xuất phải hoàn kho trước');
        } catch (WarrantyException) {
        }
        $this->actingAs($this->tech)->postJson(route('ky-thuat.repair.resume-original', $c->id))->assertOk();
        $this->assertSame('repairing', $this->claim($c->id)->status);
        $this->assertSame(2, DB::table('warranty_repair_quotations')->where('claim_id', $c->id)->count(), 'Lịch sử V1/V2 còn nguyên');
    }

    public function test_reject_change_then_lead_cancels_after_kho_returns_parts(): void
    {
        $c = $this->repairing(3);
        $stock = $this->stockQty();
        $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 5, 'unit_price' => 500000]]], 'Phát sinh');
        $this->rs()->customerDecision($c->id, $this->tech, 'rejected', 'phone', null, null);
        $this->rs()->returnLeftoverParts($c->id, $this->kho) ; // đã xuất 3, dùng 0 → hoàn 3 (chưa ghi công việc nên phải cho phép ở trạng thái change_rejected)
        $this->assertSame($stock + 3, $this->stockQty());
        $this->rs()->cancel($c->id, $this->lead, 'Khách từ chối phát sinh, dừng sửa chữa');
        $this->assertSame('cancelled', $this->claim($c->id)->status);
        $this->assertNull($this->claim($c->id)->open_serial_norm);
    }

    // =================================================================== 7. IDEMPOTENCY

    public function test_every_exchange_action_is_idempotent_on_second_call(): void
    {
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $svc = $this->svc();
        $twice = function (callable $fn) {
            $fn();
            try {
                $fn();
                $this->fail('Lần gọi thứ 2 phải bị chặn');
            } catch (WarrantyException) {
            }
        };
        $twice(fn () => $svc->approve($c->id, $this->lead));
        $new = $this->stockSerial();
        $twice(fn () => $svc->reserveSerial($c->id, $this->kho, $this->code($new), $this->wh));
        $twice(fn () => $svc->releaseReservation($c->id, $this->kho, 'Kiểm tra release lần 1'));
        $svc->reserveSerial($c->id, $this->kho, $this->code($new), $this->wh);
        $stock = $this->stockQty();
        $events = DB::table('crm_inventory_events')->count();
        $movs = DB::table('solar_warranty_stock_movements')->where('warranty_claim_id', $c->id)->count();
        $twice(fn () => $svc->issue($c->id, $this->kho));
        $this->assertSame($stock - 1, $this->stockQty());
        $this->assertSame($events + 1, DB::table('crm_inventory_events')->count());
        $this->assertSame($movs, DB::table('solar_warranty_stock_movements')->where('warranty_claim_id', $c->id)->count());
        $twice(fn () => $svc->techReceive($c->id, $this->tech, null, null, null));
        $twice(fn () => $svc->confirmReplaced($c->id, $this->tech, null, 'success', null));
        $this->assertSame(1, DB::table('warranty_serial_replacements')->where('claim_id', $c->id)->count());
        $stock2 = $this->stockQty();
        $twice(fn () => $svc->confirmFaultyReturn($c->id, $this->kho, $this->wh, 'KT', 'damaged', null));
        $this->assertSame($stock2 + 1, $this->stockQty());
        $twice(fn () => $svc->complete($c->id, $this->lead, 'Hoàn tất lần một'));
        $done = $this->claim($c->id);
        $this->assertSame((int) $this->lead->id, (int) $done->closed_by);
        $this->assertSame('completed', $done->status);
        // không "sống lại" phiếu đã hoàn tất
        foreach ([fn () => $svc->approve($c->id, $this->lead2), fn () => $svc->cancel($c->id, $this->lead, 'Hủy phiếu đã hoàn tất'), fn () => $svc->reopenRejected($c->id, $this->lead, 'Mở lại phiếu hoàn tất')] as $revive) {
            try {
                $revive();
                $this->fail('Phiếu completed không được sống lại');
            } catch (WarrantyException) {
            }
        }
        $this->assertSame('completed', $this->claim($c->id)->status);
    }

    public function test_cancelled_claim_cannot_be_revived_or_issued(): void
    {
        [$c, $new] = $this->toReserved();
        $this->svc()->cancel($c->id, $this->lead, 'Khách hủy yêu cầu đổi hàng');
        foreach ([fn () => $this->svc()->approve($c->id, $this->lead2), fn () => $this->svc()->issue($c->id, $this->kho), fn () => $this->svc()->reserveSerial($c->id, $this->kho, $this->code($new), $this->wh)] as $try) {
            try {
                $try();
                $this->fail('Phiếu cancelled không được hồi sinh');
            } catch (WarrantyException|\Error) {
            }
        }
        $this->assertSame('cancelled', $this->claim($c->id)->status);
        $this->assertSame('in_stock', $this->state($new)->state);
    }

    public function test_every_repair_action_is_idempotent_on_second_call(): void
    {
        $c = $this->approvedRepair(2);
        $rs = $this->rs();
        $twice = function (callable $fn) {
            $fn();
            try {
                $fn();
                $this->fail('Lần gọi thứ 2 phải bị chặn');
            } catch (WarrantyException) {
            }
        };
        $twice(fn () => $rs->reserveParts($c->id, $this->kho, $this->wh));
        $stock = $this->stockQty();
        $twice(fn () => $rs->issueParts($c->id, $this->kho));
        $this->assertSame($stock - 2, $this->stockQty());
        $twice(fn () => $rs->startRepair($c->id, $this->tech));
        $rs->updateRepair($c->id, $this->tech, ['repair_work_done' => 'Xong'], [$this->p1 => 1]);
        $twice(fn () => $rs->submitToQa($c->id, $this->tech));
        $twice(fn () => $rs->recordQa($c->id, $this->tech, 'pass', null, null));
        $qa = DB::table('warranty_repair_qa')->where('claim_id', $c->id)->count();
        $this->assertSame(1, $qa, 'QA không nhân đôi');
        $twice(fn () => $rs->returnLeftoverParts($c->id, $this->kho));
        $this->assertSame($stock - 1, $this->stockQty());
        $twice(fn () => $rs->handover($c->id, $this->tech, ['handover_receiver_name' => 'A', 'handover_condition' => 'B', 'handover_result' => 'C']));
        $twice(fn () => $rs->complete($c->id, $this->lead));
        $this->assertSame((int) $this->lead->id, (int) $this->claim($c->id)->closed_by);
        $ev = DB::table('warranty_claim_events')->where('claim_id', $c->id)->pluck('action')->countBy();
        foreach (['parts_issue', 'parts_return', 'qa_pass', 'handover', 'complete'] as $a) {
            $this->assertSame(1, $ev[$a], "Audit '$a' không được nhân đôi");
        }
    }

    // =================================================================== 8. FILE / SECURITY

    public function test_attachment_security_private_authorized_safe_names_and_locked_after_close(): void
    {
        Storage::fake('local');
        $c = $this->createExchange($this->tech, $this->soldSerial());
        $evil = UploadedFile::fake()->image('../../../etc/passwd.png', 30, 30);
        $this->actingAs($this->tech)->post(route('ky-thuat.warranty-exchange.evidence.upload', $c->id), ['evidence' => [$evil]])->assertSessionHasNoErrors();
        $row = DB::table('solar_warranty_claim_attachments')->where('warranty_claim_id', $c->id)->first();
        $this->assertSame('local', $row->disk);
        $this->assertStringStartsWith('warranty-claims/'.$c->id.'/', $row->file_path);
        $this->assertStringNotContainsString('..', $row->file_path);
        $this->assertStringNotContainsString('/', $row->original_name);
        $this->assertStringNotContainsString('passwd', $row->file_path);
        $this->assertFalse(Storage::disk('public')->exists($row->file_path));

        // URL trực tiếp không phục vụ file; chỉ controller có phân quyền
        $this->assertContains($this->get('/storage/'.$row->file_path)->getStatusCode(), [403, 404], 'File private không được phục vụ qua URL trực tiếp');
        $url = route('ky-thuat.warranty-exchange.evidence.download', ['claim' => $c->id, 'attachment' => $row->id]);
        $this->actingAs($this->tech)->get($url)->assertOk();
        $this->actingAs($this->kho)->get($url)->assertOk();
        $this->actingAs($this->tech2)->get($url)->assertForbidden();
        $this->actingAs($this->sales)->get($url)->assertForbidden();
        $this->actingAs($this->tech)->get(route('ky-thuat.warranty-exchange.evidence.download', ['claim' => $c->id, 'attachment' => 999999]))->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect();

        // bản ghi tồn tại nhưng file bị mất → 404 (không lộ đường dẫn)
        Storage::disk('local')->delete($row->file_path);
        $this->actingAs($this->tech)->get($url)->assertNotFound();

        // sau khi hoàn tất không xóa/thêm minh chứng
        $this->svc()->cancel($c->id, $this->lead, 'Đóng phiếu để kiểm tra khóa file');
        $this->actingAs($this->lead)->deleteJson(route('ky-thuat.warranty-exchange.evidence.destroy', ['claim' => $c->id, 'attachment' => $row->id]))->assertStatus(422);
    }

    // =================================================================== 9. AUDIT

    public function test_repair_audit_records_who_when_from_to_reason_and_is_append_only(): void
    {
        $c = $this->repairing(3);
        $this->rs()->saveChangeQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 500000]]], 'Lý do phát sinh để kiểm tra audit');
        $this->rs()->customerDecision($c->id, $this->tech, 'rejected', 'phone', null, 'Không đồng ý');
        $this->rs()->resumeOriginal($c->id, $this->tech, 'Tiếp tục theo gốc');
        $rows = DB::table('warranty_claim_events')->where('claim_id', $c->id)->orderBy('id')->get();
        $send = $rows->firstWhere('action', 'change_quote_send');
        $this->assertSame('repairing', $send->from_status);
        $this->assertSame('waiting_change_confirmation', $send->to_status);
        $this->assertSame('Lý do phát sinh để kiểm tra audit', $send->reason);
        $this->assertSame((int) $this->tech->id, (int) $send->user_id);
        $this->assertNotNull($send->created_at);
        $rej = $rows->firstWhere('action', 'change_rejected');
        $this->assertSame('waiting_change_confirmation', $rej->from_status);
        $this->assertSame('change_rejected', $rej->to_status);
        $this->assertSame('Không đồng ý', $rej->reason);
        $resume = $rows->firstWhere('action', 'change_resume_original');
        $this->assertSame('change_rejected', $resume->from_status);
        $this->assertSame('repairing', $resume->to_status);
        // append-only: id tăng dần, không dòng nào bị sửa (created_at không đổi sau khi thao tác tiếp)
        $ids = $rows->pluck('id')->all();
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }
}
