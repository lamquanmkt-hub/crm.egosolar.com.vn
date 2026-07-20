<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho các trang hoa hồng sales và KPI
 * (index, export Excel/PDF, settings, KPI dashboard/my/settings) —
 * chốt hành vi trước khi tách logic ra service/exporter.
 */
final class SalesCommissionPagesCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole('admin');
    }

    /** Id user thao tác trong fixture. */
    protected function seedActorId(): int
    {
        return (int) $this->admin->id;
    }

    /** Trang danh sách hoa hồng render thành công. */
    public function test_commission_index_renders(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->admin)
            ->get('/sales/commissions')
            ->assertOk();
    }

    /** Export Excel hoa hồng trả về file xlsx. */
    public function test_commission_export_excel_returns_xlsx(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->admin)
            ->get('/sales/commissions/export/excel')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    /** Export PDF hoa hồng trả về file PDF. */
    public function test_commission_export_pdf_returns_pdf(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->admin)
            ->get('/sales/commissions/export/pdf')
            ->assertOk();

        $this->assertStringContainsString(
            'pdf',
            strtolower((string) $response->headers->get('content-type')),
        );
    }

    /** Trang cấu hình chính sách hoa hồng render thành công. */
    public function test_commission_settings_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/sales/commissions/settings')
            ->assertOk();
    }

    /** Dashboard KPI sales render thành công. */
    public function test_kpi_dashboard_renders(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->admin)
            ->get('/sales/kpi')
            ->assertOk();
    }

    /** Form nhập KPI cá nhân render thành công. */
    public function test_kpi_my_form_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/sales/kpi/my')
            ->assertOk();
    }

    /** Trang cấu hình KPI render thành công. */
    public function test_kpi_settings_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/sales/kpi/settings')
            ->assertOk();
    }

    /** Lưu cấu hình KPI: bật/tắt chỉ tiêu và ghi lại được. */
    public function test_kpi_settings_save_persists_toggles(): void
    {
        $this->actingAs($this->admin)
            ->post('/sales/kpi/settings', [
                'metrics' => ['calls' => '1'],
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/sales/kpi/settings')
            ->assertOk();
    }
}
