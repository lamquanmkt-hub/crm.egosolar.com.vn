<?php

declare(strict_types=1);

namespace Tests\Feature\Warranty;

use App\Models\User;
use App\Support\EgoCompanyLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dữ liệu dựng sẵn cho test Bảo hành / Sửa chữa. Toàn bộ nằm trong DatabaseTransactions (tự rollback).
 * Dữ liệu mang tiền tố [LOCAL TEST WARRANTY] / [LOCAL TEST REPAIR].
 */
trait WarrantyFixtures
{
    protected int $companyId;
    protected int $p1;       // sản phẩm chính (inverter)
    protected int $p2;       // sản phẩm khác model
    protected int $wh;       // kho
    protected int $siteId;
    protected int $site2Id;
    protected int $orderId;
    protected int $order2Id;
    protected int $customerId;
    protected int $orderItemId;

    protected User $tech;
    protected User $tech2;
    protected User $lead;
    protected User $lead2;
    protected User $kho;
    protected User $admin;
    protected User $sales;

    protected function seedWarranty(): void
    {
        $this->companyId = EgoCompanyLock::id();
        $now = now();
        $ts = ['created_at' => $now, 'updated_at' => $now];
        $tag = Str::upper(Str::random(6));

        $this->tech = $this->userWithRole('ky_thuat', ['email' => 'tech.'.$tag.'@warranty-test.example.test']);
        $this->tech2 = $this->userWithRole('ky_thuat', ['email' => 'tech2.'.$tag.'@warranty-test.example.test']);
        $this->lead = $this->userWithRole('technical_manager', ['email' => 'lead.'.$tag.'@warranty-test.example.test']);
        $this->lead2 = $this->userWithRole('technical_manager', ['email' => 'lead2.'.$tag.'@warranty-test.example.test']);
        $this->kho = $this->userWithRole('warehouse', ['email' => 'kho.'.$tag.'@warranty-test.example.test']);
        $this->admin = $this->userWithRole('admin', ['email' => 'admin.'.$tag.'@warranty-test.example.test']);
        $this->sales = $this->userWithRole('sales', ['email' => 'sales.'.$tag.'@warranty-test.example.test']);

        $this->p1 = (int) DB::table('crm_product_catalog')->insertGetId(['name' => '[LOCAL TEST WARRANTY] Inverter '.$tag, 'sku' => 'WT-INV-'.$tag, 'is_serialized' => 1, 'company_id' => $this->companyId] + $ts);
        $this->p2 = (int) DB::table('crm_product_catalog')->insertGetId(['name' => '[LOCAL TEST WARRANTY] Battery '.$tag, 'sku' => 'WT-BAT-'.$tag, 'is_serialized' => 1, 'company_id' => $this->companyId] + $ts);
        $this->wh = (int) DB::table('crm_warehouses')->insertGetId(['name' => '[LOCAL TEST WARRANTY] Kho '.$tag, 'company_id' => $this->companyId]);
        DB::table('crm_product_stock')->insert(['product_id' => $this->p1, 'warehouse_id' => $this->wh, 'company_id' => $this->companyId, 'qty' => 20, 'serials_json' => json_encode([])]);

        $this->customerId = (int) DB::table('crm_customers')->insertGetId(['name' => '[LOCAL TEST WARRANTY] Khach '.$tag, 'phone' => '0977'.random_int(100000, 999999), 'company_id' => $this->companyId] + $ts);
        $lead = (int) DB::table('crm_leads')->insertGetId(['customer_id' => $this->customerId] + $ts);
        $this->orderId = (int) DB::table('crm_orders')->insertGetId(['order_code' => 'WT-O1-'.$tag, 'lead_id' => $lead, 'company_id' => $this->companyId, 'order_date' => $now->toDateString()] + $ts);
        $this->order2Id = (int) DB::table('crm_orders')->insertGetId(['order_code' => 'WT-O2-'.$tag, 'lead_id' => $lead, 'company_id' => $this->companyId, 'order_date' => $now->toDateString()] + $ts);
        $this->siteId = (int) DB::table('sites')->insertGetId(['name' => '[LOCAL TEST WARRANTY] CT1 '.$tag, 'project_code' => 'WT-S1-'.$tag, 'company_id' => $this->companyId] + $ts);
        $this->site2Id = (int) DB::table('sites')->insertGetId(['name' => '[LOCAL TEST WARRANTY] CT2 '.$tag, 'project_code' => 'WT-S2-'.$tag, 'company_id' => $this->companyId] + $ts);
        $this->orderItemId = (int) DB::table('crm_order_items')->insertGetId(['order_id' => $this->orderId, 'product_name' => 'item', 'warehouse_id' => $this->wh, 'product_id' => $this->p1, 'quantity' => 5, 'unit_price' => 1000]);
    }

    /** Serial đã bán cho khách, còn bảo hành, gắn đơn hàng + công trình. */
    protected function soldSerial(?string $code = null, array $warranty = [], bool $linkOrder = true, ?int $product = null): int
    {
        return $this->makeSerial($code ?? 'WT-OLD-'.Str::upper(Str::random(8)), $product ?? $this->p1, 'sold', null, array_merge([
            'customer_id' => $this->customerId, 'order_id' => $this->orderId, 'site_id' => $this->siteId,
            'sold_at' => now()->subMonths(3)->toDateString(), 'warranty_months' => 60,
            'warranty_start_at' => now()->subMonths(3)->toDateString(), 'warranty_end_at' => now()->addMonths(57)->toDateString(), 'status' => 'active',
        ], $warranty), $linkOrder);
    }

    protected function expiredSerial(): int
    {
        return $this->soldSerial(null, ['warranty_end_at' => now()->subDays(20)->toDateString()]);
    }

    /** Serial tồn kho sẵn sàng làm hàng thay thế. */
    protected function stockSerial(?int $product = null, string $state = 'in_stock', ?int $wh = null): int
    {
        return $this->makeSerial('WT-NEW-'.Str::upper(Str::random(8)), $product ?? $this->p1, $state, $wh ?? $this->wh, null, false);
    }

    protected function makeSerial(string $code, int $product, string $state, ?int $wh, ?array $warranty, bool $linkOrder): int
    {
        $now = now();
        $iid = (int) DB::table('crm_serial_identifiers')->insertGetId(['type' => 'serial', 'code' => $code, 'created_at' => $now, 'updated_at' => $now]);
        $uid = (int) DB::table('crm_serial_units')->insertGetId(['product_id' => $product, 'warehouse_id' => $wh, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_serial_unit_identifiers')->insert(['serial_unit_id' => $uid, 'serial_identifier_id' => $iid, 'is_primary' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_serial_unit_states')->insert(['serial_unit_id' => $uid, 'warehouse_id' => $wh, 'company_id' => $this->companyId, 'state' => $state, 'synced_at' => $now]);
        if ($warranty) {
            DB::table('crm_serial_warranties')->insert(array_merge(['serial_unit_id' => $uid, 'created_at' => $now, 'updated_at' => $now], $warranty));
        }
        if ($linkOrder) {
            DB::table('crm_order_item_serial_units')->insert(['order_item_id' => $this->orderItemId, 'serial_unit_id' => $uid, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $uid;
    }

    protected function code(int $serialUnitId): string
    {
        return (string) DB::table('crm_serial_unit_identifiers as sui')->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.serial_unit_id', $serialUnitId)->value('si.code');
    }

    protected function state(int $serialUnitId): ?object
    {
        return DB::table('crm_serial_unit_states')->where('serial_unit_id', $serialUnitId)->first();
    }

    protected function claim(int $id): object
    {
        return DB::table('crm_serial_warranty_claims')->where('id', $id)->first();
    }

    protected function stockQty(?int $product = null): int
    {
        return (int) DB::table('crm_product_stock')->where('product_id', $product ?? $this->p1)->where('warehouse_id', $this->wh)->value('qty');
    }

    protected function exchangePayload(int $serialUnitId, array $o = []): array
    {
        return array_merge([
            'source_type' => 'site', 'site_id' => $this->siteId, 'serial_code' => $this->code($serialUnitId), 'priority' => 'normal',
            'issue_description' => '[LOCAL TEST WARRANTY] Inverter báo lỗi', 'diagnosis' => 'Đo kiểm hỏng nguồn', 'proposed_solution' => 'Đổi thiết bị cùng model',
            'estimated_cost' => '0',
        ], $o);
    }

    /** Tạo phiếu đổi hàng qua HTTP (như người dùng thật) và trả về object claim. */
    protected function createExchange(?User $by = null, ?int $serial = null, array $o = []): object
    {
        $by ??= $this->tech;
        $serial ??= $this->soldSerial();
        $r = $this->actingAs($by)->post(route('ky-thuat.warranty-exchange.store'), $this->exchangePayload($serial, $o));
        $r->assertSessionHasNoErrors();
        $c = DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $serial)->orderByDesc('id')->first();
        $this->assertNotNull($c, 'Phiếu chưa được tạo');

        return $c;
    }

    protected function svc(): \App\Services\Warranty\WarrantyExchangeService
    {
        return app(\App\Services\Warranty\WarrantyExchangeService::class);
    }

    /** Đưa phiếu tới bước Kho đã giữ hàng: trả [claim, newSerialUnitId]. */
    protected function toReserved(?User $creator = null, ?int $serial = null): array
    {
        $c = $this->createExchange($creator ?? $this->tech, $serial);
        $this->svc()->approve($c->id, $this->lead);
        $new = $this->stockSerial();
        $this->svc()->reserveSerial($c->id, $this->kho, $this->code($new), $this->wh);

        return [$this->claim($c->id), $new];
    }

    protected function toIssued(): array
    {
        [$c, $new] = $this->toReserved();
        $this->svc()->issue($c->id, $this->kho);

        return [$this->claim($c->id), $new];
    }

    protected function toReplaced(): array
    {
        [$c, $new] = $this->toIssued();
        $this->svc()->techReceive($c->id, $this->tech, null, null, null);
        $this->svc()->confirmReplaced($c->id, $this->tech, null, 'success', null);

        return [$this->claim($c->id), $new];
    }
}
