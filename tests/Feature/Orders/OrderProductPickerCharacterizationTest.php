<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho nhóm API JSON hỗ trợ form đơn hàng
 * (product-catalog, product-warehouses, products-by-warehouse,
 * product-price, warehouses-by-company) — chốt hành vi trước khi
 * tách sang OrderProductPickerService.
 */
final class OrderProductPickerCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salesUser = $this->userWithRole('sales');
    }

    /** Id user thao tác trong fixture đơn hàng. */
    protected function seedActorId(): int
    {
        return (int) $this->salesUser->id;
    }

    /** Seed thêm bảng giá theo tier cho sản phẩm fixture. */
    private function seedTierPrice(float $priceBeforeVat = 900_000, float $vatPercent = 10): int
    {
        $tierId = (int) DB::table('crm_price_tiers')->insertGetId([
            'code' => 'tier_'.uniqid(),
            'name' => 'Đại lý cấp 1',
            'priority' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_product_prices')->insert([
            'product_id' => $this->seed['productId'],
            'price_tier_id' => $tierId,
            'price' => $priceBeforeVat,
            'vat_percent' => $vatPercent,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tierId;
    }

    /** product-catalog: trả sản phẩm kèm giá sau VAT, tồn serial và giá theo tier. */
    public function test_product_catalog_returns_product_with_prices_and_serial_stock(): void
    {
        $this->seedShippableOrder();
        $tierId = $this->seedTierPrice();

        $products = $this->actingAs($this->salesUser)
            ->get('/orders/product-catalog?q=PV-TEST-450')
            ->assertOk()
            ->json('products');

        $this->assertCount(1, $products);
        $product = $products[0];

        $this->assertSame($this->seed['productId'], $product['id']);
        $this->assertSame('PV-TEST-450', $product['sku']);
        $this->assertTrue($product['is_serialized']);
        // Sản phẩm serial hoá: tồn = số serial in_stock (1), không phải qty kho (5)
        $this->assertSame(1, $product['stock_total']);
        // tier price = 900k * 1.10 = 990k
        $this->assertSame(990_000, $product['tier_prices'][(string) $tierId]);
    }

    /** product-warehouses: liệt kê kho kèm tồn khả dụng của sản phẩm. */
    public function test_product_warehouses_lists_stock_per_warehouse(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->salesUser)
            ->get("/orders/product-warehouses/{$this->seed['productId']}")
            ->assertOk()
            ->json();

        $this->assertSame($this->seed['productId'], $response['product_id']);
        $this->assertTrue($response['is_serialized']);

        $warehouse = collect($response['warehouses'])
            ->firstWhere('id', $this->seed['warehouseId']);

        $this->assertNotNull($warehouse);
        // Serial hoá: available = số serial in_stock
        $this->assertSame(1, $warehouse['available_qty']);
        $this->assertTrue($warehouse['has_stock']);
    }

    /** product-warehouses: sản phẩm không tồn tại trả 404. */
    public function test_product_warehouses_returns_404_for_unknown_product(): void
    {
        $this->actingAs($this->salesUser)
            ->get('/orders/product-warehouses/999999999')
            ->assertNotFound();
    }

    /** products-by-warehouse: sản phẩm tồn 0 vẫn hiển thị, đánh dấu hết hàng. */
    public function test_products_by_warehouse_includes_zero_stock_products(): void
    {
        $this->seedShippableOrder(stockQty: 0);

        $products = $this->actingAs($this->salesUser)
            ->get("/orders/products-by-warehouse?warehouse_id={$this->seed['warehouseId']}")
            ->assertOk()
            ->json('products');

        $product = collect($products)->firstWhere('id', $this->seed['productId']);

        $this->assertNotNull($product);
        $this->assertSame(0, $product['stock']);
        $this->assertTrue($product['is_out_of_stock']);
        $this->assertFalse($product['disabled']);
        $this->assertStringContainsString('Tồn: 0', $product['text']);
    }

    /** product-price: có tier thì lấy giá tier + VAT của tier. */
    public function test_product_price_resolves_tier_price_with_vat(): void
    {
        $this->seedShippableOrder();
        $tierId = $this->seedTierPrice(priceBeforeVat: 800_000, vatPercent: 8);

        $this->actingAs($this->salesUser)
            ->get("/orders/product-price/{$this->seed['productId']}?price_tier_id={$tierId}")
            ->assertOk()
            ->assertJson([
                'price' => 864_000, // 800k * 1.08
                'vat_percent' => 8,
                'price_before_vat' => 800_000,
            ]);
    }

    /** product-price: không tier — khách lẻ lấy giá retail + VAT của catalog. */
    public function test_product_price_falls_back_to_catalog_price(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->salesUser)
            ->get("/orders/product-price/{$this->seed['productId']}")
            ->assertOk()
            ->assertJson([
                'price' => 1_100_000,
                'vat_percent' => 10,
                'price_before_vat' => 1_000_000,
            ]);
    }

    /** warehouses-by-company: chỉ trả kho gắn với company qua pivot. */
    public function test_warehouses_by_company_uses_pivot(): void
    {
        $this->seedShippableOrder();

        DB::table('company_warehouse')->insert([
            'company_id' => $this->seed['companyId'],
            'warehouse_id' => $this->seed['warehouseId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->salesUser)
            ->get("/orders/warehouses-by-company?company_id={$this->seed['companyId']}")
            ->assertOk()
            ->assertJsonCount(1, 'warehouses')
            ->assertJsonPath('warehouses.0.id', $this->seed['warehouseId']);

        $this->actingAs($this->salesUser)
            ->get('/orders/warehouses-by-company')
            ->assertOk()
            ->assertJsonCount(0, 'warehouses');
    }
}
