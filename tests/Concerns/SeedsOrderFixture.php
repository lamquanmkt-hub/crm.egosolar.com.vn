<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Seed bộ dữ liệu đơn hàng tối thiểu (company, kho, sản phẩm, khách, lead,
 * đơn + dòng hàng, tồn kho, serial) dùng chung cho các feature test Orders.
 *
 * Trả về map id qua thuộc tính $seed. Test dùng DatabaseTransactions
 * nên dữ liệu tự rollback sau mỗi test.
 */
trait SeedsOrderFixture
{
    /** @var array<string, int> id các bản ghi đã seed */
    private array $seed = [];

    /**
     * Seed 1 đơn ở bước kho với 1 dòng hàng serial hoá, sẵn sàng xuất kho.
     *
     * @param  int  $stockQty  Tồn kho khả dụng (0 = hết hàng)
     * @param  array<string, mixed>  $orderOverrides  Ghi đè cột crm_orders (vd: created_by, order_code)
     */
    private function seedShippableOrder(int $stockQty = 5, array $orderOverrides = []): void
    {
        $now = now();

        $companyId = (int) DB::table('companies')->insertGetId([
            'code' => 'TESTCO-'.uniqid(),
            'name' => 'Công ty Test',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $warehouseId = (int) DB::table('crm_warehouses')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Kho Test',
            'location' => 'HCM',
            'manager_id' => null,
        ]);

        $productId = (int) DB::table('crm_product_catalog')->insertGetId([
            'name' => 'Pin mặt trời Test 450W',
            'sku' => 'PV-TEST-450',
            'is_serialized' => 1,
            'unit' => 'tấm',
            'price' => 1_000_000,
            'price_retail' => 1_000_000,
            'price_retail_vat' => 1_100_000,
            'vat_percent' => 10,
            'quantity' => $stockQty,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $customerId = (int) DB::table('crm_customers')->insertGetId([
            'name' => 'Khách Test',
            'phone' => '0900000001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $creatorId = (int) ($orderOverrides['created_by'] ?? $this->seedActorId());

        $leadId = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $customerId,
            'created_by' => $creatorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $statusTypeId = (int) DB::table('crm_order_status_types')->insertGetId([
            'code' => 'wh_test_'.uniqid(),
            'name' => 'Chờ xuất kho',
            'department' => 'warehouse',
            'order_sequence' => 1,
            'is_final' => 0,
        ]);

        $orderId = (int) DB::table('crm_orders')->insertGetId(array_merge([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'order_code' => 'TEST-WH-001',
            'order_date' => $now->toDateString(),
            'current_status_type_id' => $statusTypeId,
            'current_department' => 'warehouse',
            'inventory_issued' => 0,
            'warehouse_id' => $warehouseId,
            'total_amount' => 1_000_000,
            'created_by' => $creatorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $orderOverrides));

        $orderItemId = (int) DB::table('crm_order_items')->insertGetId([
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'product_name' => 'Pin mặt trời Test 450W',
            'quantity' => 1,
            'unit_price' => 1_000_000,
        ]);

        if ($stockQty > 0) {
            DB::table('crm_product_stock')->insert([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'company_id' => $companyId,
                'qty' => $stockQty,
                'last_updated' => $now,
            ]);

            DB::table('crm_product_stock_lots')->insert([
                'product_id' => $productId,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'lot_code' => 'LOT-TEST-1',
                'received_at' => $now,
                'qty_in' => $stockQty,
                'qty_remaining' => $stockQty,
                'cost_before_vat' => 800_000,
                'cost_vat_percent' => 10,
                'cost_after_vat' => 880_000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $serialUnitId = (int) DB::table('crm_serial_units')->insertGetId([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $serialIdentifierId = (int) DB::table('crm_serial_identifiers')->insertGetId([
            'type' => 'serial',
            'code' => 'SN-TEST-0001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_serial_unit_identifiers')->insert([
            'serial_unit_id' => $serialUnitId,
            'serial_identifier_id' => $serialIdentifierId,
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_serial_unit_states')->insert([
            'serial_unit_id' => $serialUnitId,
            'warehouse_id' => $warehouseId,
            'state' => 'in_stock',
            'synced_at' => $now,
        ]);

        $this->seed = compact(
            'companyId',
            'warehouseId',
            'productId',
            'customerId',
            'leadId',
            'orderId',
            'orderItemId',
            'serialUnitId',
        );
    }

    /**
     * Id user thực hiện hành động trong fixture (mặc định: user test đang đăng nhập).
     */
    abstract protected function seedActorId(): int;
}
