<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho các trang đọc của ProductController
 * (index, input, output, history, export input Excel) — chốt hành vi
 * trước khi tách logic lô/tồn/giá vào service.
 */
final class ProductPagesCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithRole('admin');
    }

    /** Id user thao tác trong fixture. */
    protected function seedActorId(): int
    {
        return (int) $this->user->id;
    }

    /** Trang danh sách sản phẩm hiển thị sản phẩm kèm SKU. */
    public function test_index_lists_products(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get('/products')
            ->assertOk()
            ->assertSee('PV-TEST-450');
    }

    /** Lọc theo kho: sản phẩm vẫn hiển thị vì có lô trong kho đó. */
    public function test_index_filters_by_warehouse(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get("/products?warehouse_id={$this->seed['warehouseId']}")
            ->assertOk()
            ->assertSee('PV-TEST-450');
    }

    /** Trang nhập kho (input) hiển thị sản phẩm. */
    public function test_input_page_renders(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get('/products/input')
            ->assertOk()
            ->assertSee('PV-TEST-450');
    }

    /** Trang xuất kho (output) render thành công. */
    public function test_output_page_renders(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get('/products/output')
            ->assertOk();
    }

    /** Trang lịch sử nhập/xuất render thành công. */
    public function test_history_page_renders(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get('/products/history')
            ->assertOk();
    }

    /** Export Excel trang nhập kho: file xlsx chứa SKU và mã lô. */
    public function test_export_input_excel_contains_lot_data(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->user)
            ->get('/products/input/export/excel')
            ->assertOk();

        $cells = $this->allCellValues($response);

        $this->assertContains('PV-TEST-450', $cells);
        $this->assertContains('LOT-TEST-1', $cells);
    }

    /**
     * /products/{id} không có method show — đã loại khỏi resource.
     * GET không khớp route nào (chỉ còn PUT/PATCH/DELETE) nên trả 405,
     * an toàn hơn 500 do gọi method không tồn tại như trước.
     */
    public function test_show_route_is_not_registered(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->get("/products/{$this->seed['productId']}")
            ->assertMethodNotAllowed();
    }

    /**
     * Đọc toàn bộ giá trị cell của mọi sheet trong file xlsx trả về.
     *
     * @return list<mixed>
     */
    private function allCellValues(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'product-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $values = [];

            foreach (IOFactory::load($path)->getAllSheets() as $sheet) {
                foreach ($sheet->toArray() as $row) {
                    foreach ($row as $value) {
                        if ($value !== null && $value !== '') {
                            $values[] = $value;
                        }
                    }
                }
            }

            return $values;
        } finally {
            @unlink($path);
        }
    }
}
