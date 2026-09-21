<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Concurrency THẬT: nhiều tiến trình PHP chạy cùng lúc (hàng rào thời gian) tranh chấp cùng serial / cùng tồn kho.
 * Không dùng DatabaseTransactions (tiến trình khác phải thấy dữ liệu đã commit) → tự dọn dữ liệu test ở tearDown.
 */
final class WarrantyConcurrencyTest extends TestCase
{
    use WarrantyFixtures;

    /** @var array<int,int> */
    private array $rolesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->purge();
        $this->rolesBefore = DB::table('roles')->pluck('id')->all();
        $this->seedWarranty();
    }

    protected function tearDown(): void
    {
        $this->purge();
        DB::table('roles')->whereNotIn('id', $this->rolesBefore ?: [0])->whereNotIn('id', DB::table('model_has_roles')->select('role_id'))->delete();
        parent::tearDown();
    }

    /** @param array<int,array<string,mixed>> $jobs */
    private function race(array $jobs): array
    {
        $at = microtime(true) + 4.0; // đủ thời gian cho mọi worker khởi động rồi xuất phát cùng lúc
        $procs = [];
        foreach ($jobs as $i => $job) {
            $cmd = [PHP_BINARY, base_path('tests/Support/warranty_worker.php'), json_encode($job + ['at' => $at])];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null);
            $procs[$i] = [$p, $pipes];
        }
        $out = [];
        foreach ($procs as $i => [$p, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $json = json_decode(trim((string) $stdout), true);
            $out[$i] = $json ?: ['ok' => false, 'business' => false, 'error' => 'no output: '.$stdout];
        }

        return $out;
    }

    private function assertOnlyBusinessFailures(array $results): void
    {
        foreach ($results as $r) {
            if (! $r['ok']) {
                $this->assertTrue((bool) ($r['business'] ?? false), 'Lỗi kỹ thuật (không phải chặn nghiệp vụ): '.($r['error'] ?? ''));
            }
        }
    }

    public function test_six_concurrent_requests_for_same_serial_create_exactly_one_exchange_claim(): void
    {
        $serial = $this->soldSerial();
        $jobs = [];
        foreach ([$this->tech, $this->tech2, $this->lead, $this->lead2, $this->admin, $this->tech] as $u) {
            $jobs[] = [
                'action' => 'exchange_create', 'user_id' => $u->id,
                'data' => ['priority' => 'normal', 'issue_description' => 'race', 'diagnosis' => 'race', 'proposed_solution' => 'race'],
                'serial' => ['serial_unit_id' => $serial, 'serial_code' => $this->code($serial)],
                'ctx' => ['site_id' => $this->siteId, 'order_id' => $this->orderId, 'customer_id' => $this->customerId, 'company_id' => $this->companyId, 'warranty_active' => true, 'assignee_id' => null, 'assignee_name' => null],
            ];
        }
        $res = $this->race($jobs);
        $this->assertOnlyBusinessFailures($res);
        $ok = collect($res)->where('ok', true)->count();
        $this->assertSame(1, $ok, 'Đúng 1 request được tạo phiếu: '.json_encode($res, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->count(), 'DB chỉ có 1 phiếu (transaction rollback các phiếu thua)');
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->whereNotNull('open_serial_key')->count());
    }

    public function test_concurrent_repair_intakes_for_same_external_serial_in_different_case_create_one_claim(): void
    {
        $variants = ['EXT-RACE-77', 'ext-race-77', '  Ext-Race-77 ', 'EXT-RACE-77', 'ext-RACE-77'];
        $jobs = [];
        foreach ($variants as $i => $v) {
            $jobs[] = [
                'action' => 'repair_create', 'user_id' => ($i % 2 ? $this->tech : $this->tech2)->id,
                'data' => ['priority' => 'normal', 'issue_description' => 'race', 'device_type' => 'Inverter', 'device_model' => 'M', 'serial_code' => $v],
                'ctx' => ['customer' => ['id' => null, 'name' => '[LOCAL TEST REPAIR] Race '.$i, 'phone' => '0944000'.$i, 'email' => null, 'address' => null, 'company' => null], 'company_id' => $this->companyId, 'assignee_id' => null, 'assignee_name' => null],
            ];
        }
        $res = $this->race($jobs);
        $this->assertOnlyBusinessFailures($res);
        $this->assertSame(1, collect($res)->where('ok', true)->count(), json_encode($res, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->where('open_serial_norm', 'EXT-RACE-77')->count());
        $this->assertSame(1, DB::table('crm_serial_warranty_claims')->whereRaw("UPPER(TRIM(serial_code)) = 'EXT-RACE-77'")->count());
    }

    public function test_two_claims_racing_for_the_same_replacement_serial_only_one_reserves_it(): void
    {
        $a = $this->createExchange($this->tech, $this->soldSerial());
        $b = $this->createExchangeForRace();
        $this->svc()->approve($a->id, $this->lead);
        $this->svc()->approve($b->id, $this->lead);
        $new = $this->stockSerial();
        $jobs = [];
        foreach ([$a->id, $b->id, $a->id, $b->id] as $cid) {
            $jobs[] = ['action' => 'reserve_serial', 'user_id' => $this->kho->id, 'claim_id' => $cid, 'serial_code' => $this->code($new), 'warehouse_id' => $this->wh];
        }
        $res = $this->race($jobs);
        $this->assertOnlyBusinessFailures($res);
        $this->assertSame(1, DB::table('warranty_serial_reservations')->where('serial_unit_id', $new)->where('status', 'active')->count(), json_encode($res, JSON_UNESCAPED_UNICODE));
        $this->assertSame('reserved', $this->state($new)->state);
        $this->assertSame(1, DB::table('solar_warranty_stock_movements')->where('serial_unit_id', $new)->whereIn('status', ['pending', 'approved'])->count());
    }

    public function test_parallel_issue_requests_deduct_stock_only_once(): void
    {
        [$c] = $this->toReserved();
        $stock = $this->stockQty();
        $jobs = [];
        for ($i = 0; $i < 5; $i++) {
            $jobs[] = ['action' => 'issue_serial', 'user_id' => $this->kho->id, 'claim_id' => $c->id];
        }
        $res = $this->race($jobs);
        $this->assertOnlyBusinessFailures($res);
        $this->assertSame(1, collect($res)->where('ok', true)->count(), json_encode($res, JSON_UNESCAPED_UNICODE));
        $this->assertSame($stock - 1, $this->stockQty(), 'Tồn giảm đúng 1 lần');
        $this->assertSame(1, DB::table('crm_stock_movements')->where('reference_id', $c->id)->where('change_qty', -1)->count());
    }

    public function test_two_repairs_racing_for_the_same_stock_cannot_over_reserve(): void
    {
        $ids = [];
        foreach ([1, 2] as $n) {
            $c = $this->createRepairForRace();
            $ids[] = $c->id;
        }
        $jobs = [];
        foreach ([$ids[0], $ids[1], $ids[0], $ids[1]] as $cid) { // mỗi phiếu cần 15, tồn 20
            $jobs[] = ['action' => 'reserve_parts', 'user_id' => $this->kho->id, 'claim_id' => $cid, 'warehouse_id' => $this->wh];
        }
        $res = $this->race($jobs);
        $this->assertOnlyBusinessFailures($res);
        $reserved = (float) DB::table('warranty_repair_parts')->whereIn('claim_id', $ids)->sum('qty_reserved');
        $this->assertEqualsWithDelta(15.0, $reserved, 0.001, 'Chỉ 1 phiếu giữ được 15/20: '.json_encode($res, JSON_UNESCAPED_UNICODE));
        $this->assertGreaterThanOrEqual(0.0, app(\App\Services\Warranty\StockLedger::class)->availablePartQty($this->p1, $this->wh));
        $this->assertSame(20, $this->stockQty(), 'Giữ hàng chưa trừ tồn vật lý');
    }

    // ---------------------------------------------------------------- helpers

    private function createExchangeForRace(): object
    {
        return $this->createExchange($this->tech2, $this->soldSerial());
    }

    private function createRepairForRace(): object
    {
        $r = $this->actingAs($this->tech)->postJson(route('ky-thuat.repair.store'), [
            'customer_name' => '[LOCAL TEST REPAIR] Race '.random_int(1000, 9999), 'customer_phone' => '0933'.random_int(100000, 999999),
            'device_type' => 'Inverter', 'device_model' => 'M', 'priority' => 'normal', 'issue_description' => 'race',
        ]);
        $r->assertOk();
        $c = DB::table('crm_serial_warranty_claims')->orderByDesc('id')->first();
        $rs = app(\App\Services\Warranty\RepairService::class);
        $rs->saveDiagnosis($c->id, $this->tech, ['diagnosis' => 'a', 'diagnosis_cause' => 'b', 'proposed_solution' => 'c']);
        $rs->saveQuotation($c->id, $this->tech, ['items' => [['product_id' => $this->p1, 'quantity' => 15, 'unit_price' => 1000]]]);
        $rs->sendQuotation($c->id, $this->tech);
        $rs->customerDecision($c->id, $this->tech, 'approved', 'phone', null, null);

        return $c;
    }

    /** Dọn dữ liệu test đã commit (chỉ theo tiền tố/mẫu test). */
    private function purge(): void
    {
        $users = DB::table('users')->where('email', 'like', '%@warranty-test.example.test')->pluck('id')->all();
        $units = DB::table('crm_serial_unit_identifiers as u')->join('crm_serial_identifiers as i', 'i.id', '=', 'u.serial_identifier_id')->where('i.code', 'like', 'WT-%')->pluck('u.serial_unit_id')->all();
        $prods = DB::table('crm_product_catalog')->where('name', 'like', '[LOCAL TEST WARRANTY]%')->pluck('id')->all();
        $claims = DB::table('crm_serial_warranty_claims')->where(function ($q) use ($units, $users): void {
            $q->whereIn('serial_unit_id', $units ?: [0])->orWhereIn('created_by', $users ?: [0]);
        })->pluck('id')->all() ?: [0];
        $u = $units ?: [0];
        $qids = DB::table('warranty_repair_quotations')->whereIn('claim_id', $claims)->pluck('id')->all() ?: [0];
        foreach ([
            ['warranty_repair_quotation_items', 'quotation_id', $qids], ['warranty_repair_quotations', 'claim_id', $claims], ['warranty_repair_parts', 'claim_id', $claims],
            ['warranty_repair_qa', 'claim_id', $claims], ['warranty_claim_events', 'claim_id', $claims], ['warranty_claim_notifications', 'claim_id', $claims],
            ['warranty_serial_reservations', 'claim_id', $claims], ['warranty_serial_replacements', 'claim_id', $claims], ['solar_warranty_claim_attachments', 'warranty_claim_id', $claims],
            ['solar_warranty_stock_movements', 'warranty_claim_id', $claims], ['crm_serial_warranty_events', 'serial_unit_id', $u], ['crm_serial_warranty_claims', 'id', $claims],
        ] as [$t, $col, $ids]) {
            DB::table($t)->whereIn($col, $ids)->delete();
        }
        DB::table('crm_stock_movements')->whereIn('product_id', $prods ?: [0])->where('reason', 'like', 'Bảo hành/Sửa chữa #%')->delete();
        $ev = array_values(array_unique(array_merge(
            DB::table('crm_serial_event_lines')->whereIn('serial_unit_id', $u)->pluck('event_id')->all(),
            DB::table('crm_inventory_events')->whereIn('created_by', $users ?: [0])->pluck('id')->all()
        ))) ?: [0];
        DB::table('crm_serial_unit_states')->whereIn('serial_unit_id', $u)->delete();
        DB::table('crm_serial_event_lines')->whereIn('event_id', $ev)->delete();
        DB::table('crm_inventory_events')->whereIn('id', $ev)->whereNotIn('id', DB::table('crm_serial_event_lines')->select('event_id'))->delete();
        DB::table('crm_serial_warranties')->whereIn('serial_unit_id', $u)->delete();
        DB::table('crm_order_item_serial_units')->whereIn('serial_unit_id', $u)->delete();
        $idents = DB::table('crm_serial_unit_identifiers')->whereIn('serial_unit_id', $u)->pluck('serial_identifier_id')->all() ?: [0];
        DB::table('crm_serial_unit_identifiers')->whereIn('serial_unit_id', $u)->delete();
        DB::table('crm_serial_identifiers')->whereIn('id', $idents)->delete();
        DB::table('crm_serial_units')->whereIn('id', $u)->delete();
        DB::table('crm_product_stock')->whereIn('product_id', $prods ?: [0])->delete();
        $orders = DB::table('crm_orders')->where('order_code', 'like', 'WT-O%')->pluck('id')->all() ?: [0];
        DB::table('crm_order_items')->whereIn('order_id', $orders)->delete();
        $cust = DB::table('crm_customers')->where('name', 'like', '[LOCAL TEST %')->pluck('id')->all() ?: [0];
        DB::table('crm_orders')->whereIn('id', $orders)->delete();
        DB::table('crm_leads')->whereIn('customer_id', $cust)->delete();
        DB::table('crm_customers')->whereIn('id', $cust)->delete();
        DB::table('crm_product_catalog')->whereIn('id', $prods ?: [0])->delete();
        DB::table('sites')->where('name', 'like', '[LOCAL TEST WARRANTY]%')->delete();
        DB::table('crm_warehouses')->where('name', 'like', '[LOCAL TEST WARRANTY]%')->delete();
        DB::table('model_has_roles')->whereIn('model_id', $users ?: [0])->where('model_type', 'App\Models\User')->delete();
        DB::table('users')->whereIn('id', $users ?: [0])->delete();
    }
}
