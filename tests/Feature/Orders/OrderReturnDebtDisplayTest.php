<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\CRM\Orders\Order;
use App\Models\User;
use App\Services\OrderReturnFinancialService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Đơn đã có phiếu trả hàng hoàn tất: công nợ hiển thị phải TRỪ giá trị hàng đã trả.
 *
 * Ca thật: đơn 39.881.000, khách trả lại inverter 27.881.000, đã thu 12.000.000
 * => còn nợ 0 (trước đây giao diện báo nợ 27.881.000).
 */
final class OrderReturnDebtDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOrder(float $total, float $paid, string $code): int
    {
        if ($this->productId === 0) {
            $now = now();
            $customerId = DB::table('crm_customers')->insertGetId(['name' => 'ZZ Khach test tra hang', 'created_at' => $now, 'updated_at' => $now]);
            $this->leadId = (int) DB::table('crm_leads')->insertGetId(['customer_id' => $customerId, 'created_at' => $now, 'updated_at' => $now]);
            $this->productId = (int) DB::table('crm_product_catalog')->insertGetId(['name' => 'ZZ San pham test', 'sku' => 'ZZ-RET-'.uniqid(), 'created_at' => $now, 'updated_at' => $now]);
            $this->warehouseId = (int) DB::table('crm_warehouses')->insertGetId(['name' => 'ZZ Kho test']);
        }
        $leadId = $this->leadId;

        $orderId = DB::table('crm_orders')->insertGetId([
            'lead_id' => $leadId,
            'order_code' => $code,
            'order_date' => now()->toDateString(),
            'total_amount' => $total,
            'inventory_issued' => 1,
            'company_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($paid > 0) {
            DB::table('crm_payments')->insert([
                'order_id' => $orderId, 'payment_date' => now()->toDateString(), 'amount' => $paid,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return (int) $orderId;
    }

    private int $productId = 0;

    private int $leadId = 0;

    private int $warehouseId = 0;

    private function addReturn(int $orderId, float $amount, string $status = 'completed', string $inventory = 'posted', float $fee = 0.0): int
    {
        // Mỗi dòng hàng một sản phẩm riêng (ràng buộc duy nhất đơn + kho + sản phẩm).
        $productId = (int) DB::table('crm_product_catalog')->insertGetId(['name' => 'ZZ SP tra hang', 'sku' => 'ZZ-RET-'.uniqid('', true)]);
        $itemId = DB::table('crm_order_items')->insertGetId([
            'order_id' => $orderId, 'product_id' => $productId, 'warehouse_id' => $this->warehouseId, 'quantity' => 1,
            'unit_price' => $amount,
        ]);
        $returnId = DB::table('order_returns')->insertGetId([
            'order_id' => $orderId, 'type' => 'return', 'status' => $status, 'inventory_status' => $inventory,
            'return_code' => 'RTN-T-'.uniqid(), 'total_return_amount' => $amount, 'restocking_fee' => $fee,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_return_items')->insert([
            'order_return_id' => $returnId, 'order_item_id' => $itemId, 'product_id' => $productId,
            'requested_quantity' => 1, 'accepted_quantity' => 1, 'stock_posted_quantity' => 1,
            'return_amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) $returnId;
    }

    public function test_remaining_debt_deducts_returned_goods(): void
    {
        $id = $this->makeOrder(39881000, 12000000, 'ORD-T-RET-1');
        $this->addReturn($id, 27881000);

        $order = Order::findOrFail($id);
        $this->assertSame(27881000.0, $order->return_credit_amount);
        $this->assertSame(12000000.0, $order->net_total_amount);
        $this->assertSame(0.0, $order->remain_amount);
    }

    public function test_order_without_return_keeps_old_debt(): void
    {
        $id = $this->makeOrder(10000000, 4000000, 'ORD-T-RET-2');

        $order = Order::findOrFail($id);
        $this->assertSame(0.0, $order->return_credit_amount);
        $this->assertSame(6000000.0, $order->remain_amount);
    }

    public function test_only_posted_and_open_returns_count_and_fee_is_deducted(): void
    {
        $id = $this->makeOrder(10000000, 0, 'ORD-T-RET-3');
        $this->addReturn($id, 3000000, 'completed', 'posted', 500000);   // tính: 2.500.000
        $this->addReturn($id, 4000000, 'rejected', 'posted');            // bỏ: bị từ chối
        $this->addReturn($id, 2000000, 'approved_waiting_return', 'pending'); // bỏ: kho chưa nhập

        $order = Order::findOrFail($id);
        $this->assertSame(2500000.0, $order->return_credit_amount);
        $this->assertSame(7500000.0, $order->remain_amount);
    }

    public function test_preload_matches_single_lookup_for_a_list(): void
    {
        $a = $this->makeOrder(5000000, 0, 'ORD-T-RET-4');
        $b = $this->makeOrder(8000000, 0, 'ORD-T-RET-5');
        $this->addReturn($b, 1000000);

        $orders = Order::whereIn('id', [$a, $b])->get();
        Order::preloadReturnCredits($orders);

        $this->assertSame(0.0, $orders->firstWhere('id', $a)->return_credit_amount);
        $this->assertSame(1000000.0, $orders->firstWhere('id', $b)->return_credit_amount);
        $this->assertSame([$b => 1000000.0], array_intersect_key(OrderReturnFinancialService::returnCreditsForOrders([$a, $b]), [$b => 1]));
    }

    public function test_debt_filter_excludes_fully_settled_order_after_return(): void
    {
        $returned = $this->makeOrder(39881000, 12000000, 'ORD-T-RET-6');
        $this->addReturn($returned, 27881000);
        $stillOwing = $this->makeOrder(5000000, 1000000, 'ORD-T-RET-7');

        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        $html = $this->actingAs($admin)->get('/orders?payment_filter=debt&search=ORD-T-RET')->assertOk()->getContent();
        $this->assertStringContainsString('ORD-T-RET-7', $html);
        $this->assertStringNotContainsString('ORD-T-RET-6', $html);

        $paid = $this->actingAs($admin)->get('/orders?payment_filter=paid&search=ORD-T-RET')->assertOk()->getContent();
        $this->assertStringContainsString('ORD-T-RET-6', $paid);
    }

    public function test_order_page_and_list_show_returned_amount_and_zero_debt(): void
    {
        $id = $this->makeOrder(39881000, 12000000, 'ORD-T-RET-8');
        $this->addReturn($id, 27881000);

        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        $list = $this->actingAs($admin)->get('/orders?search=ORD-T-RET-8')->assertOk()->getContent();
        $this->assertStringContainsString('Đã trả lại', $list);
        $this->assertStringContainsString('12.000.000 / 12.000.000', $list);
        $this->assertStringNotContainsString('Nợ 27.881.000', $list);

        $show = $this->actingAs($admin)->get('/orders/'.$id)->assertOk()->getContent();
        $this->assertStringContainsString('Đã trả lại', $show);
        $this->assertStringNotContainsString('Còn công nợ <strong>27.881.000', $show);
    }

    public function test_order_returns_dashboard_opens_without_lazy_loading_errors(): void
    {
        if (! Schema::hasTable('order_returns')) {
            $this->markTestSkipped('Không có bảng order_returns.');
        }
        $id = $this->makeOrder(1000000, 0, 'ORD-T-RET-9');
        $this->addReturn($id, 1000000);

        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        $this->actingAs($admin)->get('/order-returns')->assertOk();
    }
    public function test_dashboard_receivable_query_ignores_fully_returned_order(): void
    {
        $returned = $this->makeOrder(39881000, 12000000, 'ORD-T-RET-10');
        $this->addReturn($returned, 27881000);
        $owing = $this->makeOrder(5000000, 1000000, 'ORD-T-RET-11');

        $rows = app(\App\Services\Finance\OrderReceivableQuery::class)->outstanding()
            ->whereIn('o.id', [$returned, $owing])
            ->selectRaw('o.id, '.\App\Services\Finance\OrderReceivableQuery::balanceExpr().' AS balance')
            ->pluck('balance', 'id');

        $this->assertFalse($rows->has($returned), 'Đơn đã trả hết phần còn nợ không được là khoản phải thu.');
        $this->assertSame(4000000.0, (float) $rows[$owing]);
    }

    public function test_dashboard_receivable_balance_is_reduced_by_partial_return(): void
    {
        $id = $this->makeOrder(10000000, 2000000, 'ORD-T-RET-12');
        $this->addReturn($id, 3000000);

        $rows = app(\App\Services\Finance\OrderReceivableQuery::class)->outstanding()
            ->where('o.id', $id)
            ->selectRaw('o.id, '.\App\Services\Finance\OrderReceivableQuery::balanceExpr().' AS balance')
            ->pluck('balance', 'id');

        $this->assertSame(5000000.0, (float) $rows[$id]); // 10tr − 3tr hàng trả − 2tr đã thu
    }

    public function test_customer_debt_page_uses_net_total_after_return(): void
    {
        $id = $this->makeOrder(39881000, 12000000, 'ORD-T-RET-13');
        $this->addReturn($id, 27881000);

        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        $html = $this->actingAs($admin)->get('/finance/customer-debts?keyword=ORD-T-RET-13')->assertOk()->getContent();
        $this->assertStringContainsString('ORD-T-RET-13', $html);
        $this->assertStringNotContainsString('27.881.000', $html);

        $byCustomer = $this->actingAs($admin)->get('/finance/customer-debts/by-customer?keyword=ORD-T-RET-13')->assertOk()->getContent();
        $this->assertStringNotContainsString('27.881.000', $byCustomer);
    }
}