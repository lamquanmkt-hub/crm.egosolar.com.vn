<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho flow duyệt xuất kho (approveWarehouseIssue)
 * và API serial (getShipSerials) — chốt hành vi hiện tại TRƯỚC khi tách
 * logic ra service. Mọi assert dưới đây mô tả đúng hành vi production.
 */
final class WarehouseIssueCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $warehouseUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warehouseUser = $this->userWithRole('warehouse');
    }

    /** Id user thao tác trong fixture đơn hàng. */
    protected function seedActorId(): int
    {
        return (int) $this->warehouseUser->id;
    }

    /** API getShipSerials trả về serial in_stock theo từng dòng hàng. */
    public function test_get_ship_serials_returns_available_serials_per_item(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->warehouseUser)
            ->get("/chat/orders/{$this->seed['orderId']}/ship-serials")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $items = $response->json('serials.items');

        $this->assertCount(1, $items);
        $this->assertSame($this->seed['orderItemId'], $items[0]['order_item_id']);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertSame(
            [['id' => $this->seed['serialUnitId'], 'code' => 'SN-TEST-0001', 'warehouse_id' => $this->seed['warehouseId'], 'warehouse_name' => 'Kho Test']],
            $items[0]['available_serials'],
        );
    }

    /** Duyệt xuất kho happy-path: trừ tồn, serial sold, bảo hành kích hoạt, đơn hoàn tất. */
    public function test_approve_warehouse_issue_ships_order_and_activates_warranty(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->warehouseUser)
            ->post("/orders/{$this->seed['orderId']}/warehouse-issue", [
                'actual_ship_date' => now()->toDateString(),
                'warranty_months' => 12,
                'serials' => [
                    (string) $this->seed['orderItemId'] => [(string) $this->seed['serialUnitId']],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order = DB::table('crm_orders')->find($this->seed['orderId']);
        $this->assertSame(1, (int) $order->inventory_issued);
        $this->assertSame('completed', $order->current_department);

        // Serial chuyển sold, rời kho
        $state = DB::table('crm_serial_unit_states')
            ->where('serial_unit_id', $this->seed['serialUnitId'])
            ->first();
        $this->assertSame('sold', $state->state);
        $this->assertNull($state->warehouse_id);

        // Bảo hành 12 tháng, trạng thái active, gắn đúng khách và đơn
        $warranty = DB::table('crm_serial_warranties')
            ->where('serial_unit_id', $this->seed['serialUnitId'])
            ->first();
        $this->assertNotNull($warranty);
        $this->assertSame(12, (int) $warranty->warranty_months);
        $this->assertSame('active', $warranty->status);
        $this->assertSame($this->seed['customerId'], (int) $warranty->customer_id);
        $this->assertSame($this->seed['orderId'], (int) $warranty->order_id);

        // Tồn lô bị trừ đúng 1
        $lotRemaining = (int) DB::table('crm_product_stock_lots')
            ->where('product_id', $this->seed['productId'])
            ->sum('qty_remaining');
        $this->assertSame(4, $lotRemaining);

        // Công nợ được tạo (chưa thanh toán)
        $debt = DB::table('crm_customer_debts')
            ->where('order_id', $this->seed['orderId'])
            ->first();
        $this->assertNotNull($debt);
        $this->assertSame('unpaid', $debt->status);
    }

    /** Hết tồn kho: chặn xuất, báo lỗi popup, đơn giữ nguyên. */
    public function test_approve_warehouse_issue_blocks_when_out_of_stock(): void
    {
        $this->seedShippableOrder(stockQty: 0);

        $this->actingAs($this->warehouseUser)
            ->post("/orders/{$this->seed['orderId']}/warehouse-issue", [
                'actual_ship_date' => now()->toDateString(),
                'serials' => [
                    (string) $this->seed['orderItemId'] => [(string) $this->seed['serialUnitId']],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionHas('stock_error_popup');

        $this->assertStringContainsString(
            'hết hàng',
            (string) session('error'),
        );

        $order = DB::table('crm_orders')->find($this->seed['orderId']);
        $this->assertSame(0, (int) $order->inventory_issued);
        $this->assertSame('warehouse', $order->current_department);
    }

    /** Chọn thiếu serial so với số lượng dòng hàng: chặn và báo lỗi rõ ràng. */
    public function test_approve_warehouse_issue_rejects_wrong_serial_count(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->warehouseUser)
            ->post("/orders/{$this->seed['orderId']}/warehouse-issue", [
                'actual_ship_date' => now()->toDateString(),
                'serials' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'cần chọn đúng 1 serial',
            (string) session('error'),
        );

        $order = DB::table('crm_orders')->find($this->seed['orderId']);
        $this->assertSame(0, (int) $order->inventory_issued);
    }
}
