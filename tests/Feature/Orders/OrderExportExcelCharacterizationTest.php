<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho export Excel danh sách đơn hàng
 * (GET /orders/export/excel) — chốt hành vi trước khi tách ra exporter.
 */
final class OrderExportExcelCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = $this->userWithRole('admin');
    }

    /** Id user thao tác trong fixture đơn hàng. */
    protected function seedActorId(): int
    {
        return (int) $this->adminUser->id;
    }

    /** Admin export: file xlsx chứa mã đơn và tên khách. */
    public function test_export_returns_xlsx_containing_order_data(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->adminUser)
            ->get('/orders/export/excel')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $this->assertStringContainsString(
            'danh-sach-don-hang-',
            (string) $response->headers->get('content-disposition'),
        );

        $cells = $this->allCellValues($response);

        $this->assertContains('TEST-WH-001', $cells);
        $this->assertContains('Khách Test', $cells);
    }

    /** Sales chỉ export được đơn của chính mình. */
    public function test_sales_export_only_includes_own_orders(): void
    {
        $sales = $this->userWithRole('sales');
        $otherUser = User::factory()->create();

        $this->seedShippableOrder(orderOverrides: [
            'order_code' => 'TEST-OTHER-999',
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($sales)
            ->get('/orders/export/excel')
            ->assertOk();

        $this->assertNotContains('TEST-OTHER-999', $this->allCellValues($response));
    }

    /**
     * Đọc toàn bộ giá trị cell của mọi sheet trong file xlsx trả về.
     *
     * @return list<mixed>
     */
    private function allCellValues(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'order-export-').'.xlsx';
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
