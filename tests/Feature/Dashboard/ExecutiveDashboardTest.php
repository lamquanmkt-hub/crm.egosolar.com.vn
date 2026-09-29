<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\ExecutiveDashboardService;
use App\Services\Finance\OrderReceivableQuery;
use App\Support\EgoCompanyLock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Hồi quy Dashboard Giám đốc (/dashboard, ExecutiveDashboardService).
 *
 * Kiểm chứng: gỡ route tự đăng nhập, Pipeline, công nợ thương mại từ số dư chuẩn,
 * cảnh báo giao trễ, bỏ KPI "Rủi ro" tổng hợp và doanh thu ghi nhận theo ngày xuất kho.
 *
 * Chạy trên DB egosolar_test, chỉ ghi dữ liệu và rollback nhờ DatabaseTransactions
 * (không migrate/truncate/drop). Thời gian cố định 29/09/2026 để tuổi nợ/quá hạn ổn định.
 */
final class ExecutiveDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private const SEPT = ['from' => '2026-09-01', 'to' => '2026-09-30'];

    private User $admin;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00'));
        $this->admin = $this->userWithRole('admin');
        $this->sales = $this->userWithRole('sales');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* =====================================================================
     * Fixture
     * ===================================================================== */

    /**
     * Tạo 1 đơn (kèm khách + lead) thuộc công ty đang khóa, người tạo là $this->sales.
     *
     * @param  array<string, mixed>  $overrides  Ghi đè cột crm_orders
     */
    private function makeOrder(array $overrides = [], string $customerName = 'Khách Test'): int
    {
        $now = now();
        $customerId = (int) DB::table('crm_customers')->insertGetId([
            'name' => $customerName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $leadId = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $customerId,
            'created_by' => $this->sales->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('crm_orders')->insertGetId(array_merge([
            'company_id' => EgoCompanyLock::id(),
            'lead_id' => $leadId,
            'order_code' => 'DASH-'.Str::upper(Str::random(8)),
            'order_date' => '2026-09-10',
            'current_department' => 'completed',
            'inventory_issued' => 1,
            'inventory_issued_at' => '2026-09-12 09:00:00',
            'shipping_status' => 'shipped',
            'total_amount' => 1_000_000,
            'created_by' => $this->sales->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    /** Đơn chờ duyệt / chưa xuất kho ở một bộ phận. */
    private function makePendingOrder(string $department, int $amount = 1_000_000, array $overrides = []): int
    {
        return $this->makeOrder(array_merge([
            'current_department' => $department,
            'inventory_issued' => 0,
            'inventory_issued_at' => null,
            'shipping_status' => 'not_shipped',
            'total_amount' => $amount,
        ], $overrides));
    }

    private function pay(int $orderId, float $amount, string $date = '2026-09-15'): void
    {
        DB::table('crm_payments')->insert([
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Dòng công nợ cũ (metadata) — cố ý có thể lệch với đơn/thanh toán thật. */
    private function debtRow(int $orderId, float $total, float $paid, ?string $dueDate = null): void
    {
        $customerId = (int) DB::table('crm_leads')
            ->where('id', DB::table('crm_orders')->where('id', $orderId)->value('lead_id'))
            ->value('customer_id');

        DB::table('crm_customer_debts')->insert([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_date' => $dueDate,
            'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Dashboard của admin, mặc định khoanh vùng theo sales của test để tách khỏi dữ liệu khác.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function dashboard(array $filters = [], ?User $as = null): array
    {
        $filters += self::SEPT + ['sales_id' => $this->sales->id];

        return app(ExecutiveDashboardService::class)->build($as ?? $this->admin, $filters)['executive'];
    }

    /** @return array<string, array<string, mixed>> */
    private function pipelineByKey(array $dashboard): array
    {
        return collect($dashboard['pipeline'])->keyBy('key')->all();
    }

    /** @return array<string, mixed>|null */
    private function alert(array $dashboard, string $key): ?array
    {
        return collect($dashboard['alerts'])->firstWhere('key', $key);
    }

    /* =====================================================================
     * 1. Route tự đăng nhập đã bị gỡ
     * ===================================================================== */

    public function test_auto_login_dev_route_is_not_registered(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertNotContains('auto-login-dev', $uris);
        $this->get('/auto-login-dev')->assertNotFound();
        $this->assertGuest();
    }

    /* =====================================================================
     * 2. Pipeline
     * ===================================================================== */

    public function test_pipeline_always_has_all_steps_with_date_filter_in_urls(): void
    {
        $pipeline = $this->dashboard()['pipeline'];

        $this->assertSame(
            ['sales', 'sales_manager', 'accounting', 'management', 'warehouse', 'completed'],
            array_column($pipeline, 'key')
        );

        foreach ($pipeline as $step) {
            $this->assertSame(0, $step['count']);
            $this->assertStringContainsString('from_date=2026-09-01', (string) $step['url']);
            $this->assertStringContainsString('to_date=2026-09-30', (string) $step['url']);
        }
    }

    public function test_pipeline_counts_orders_by_department_for_september(): void
    {
        // Mô phỏng dữ liệu đã kiểm tra: Kế toán 2, Ban Giám đốc 1, Kho 3, Hoàn thành 10.
        foreach ([['accounting', 2], ['management', 1], ['warehouse', 3]] as [$department, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $this->makePendingOrder($department, 100_000);
            }
        }
        for ($i = 0; $i < 10; $i++) {
            $this->makeOrder(['total_amount' => 200_000]);
        }

        // Không được đếm: đơn hủy, đơn xoá mềm, đơn ngoài kỳ.
        $this->makePendingOrder('cancelled');
        $this->makePendingOrder('accounting', 100_000, ['deleted_at' => now()]);
        $this->makePendingOrder('accounting', 100_000, ['order_date' => '2026-08-31']);

        $steps = $this->pipelineByKey($this->dashboard());

        $this->assertSame(0, $steps['sales']['count']);
        $this->assertSame(0, $steps['sales_manager']['count']);
        $this->assertSame(2, $steps['accounting']['count']);
        $this->assertSame(1, $steps['management']['count']);
        $this->assertSame(3, $steps['warehouse']['count']);
        $this->assertSame(10, $steps['completed']['count']);
        $this->assertEqualsWithDelta(2_000_000.0, $steps['completed']['value'], 0.01);
    }

    public function test_pipeline_query_failure_is_reported_and_keeps_structure_and_page(): void
    {
        Exceptions::fake();
        $this->makePendingOrder('accounting');

        DB::beforeExecuting(function (string $sql): void {
            if (str_contains($sql, 'AS department')) {
                throw new \RuntimeException('Pipeline query failed (test)');
            }
        });

        $pipeline = $this->dashboard()['pipeline'];

        $this->assertCount(6, $pipeline);
        foreach ($pipeline as $step) {
            $this->assertSame(0, $step['count']);
            $this->assertSame(0.0, $step['value']);
            $this->assertSame(0, $step['overdue']);
            $this->assertNotEmpty($step['url']);
        }
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'Pipeline query failed (test)');

        $this->actingAs($this->admin)
            ->get('/dashboard?from='.self::SEPT['from'].'&to='.self::SEPT['to'])
            ->assertOk()
            ->assertSee('Pipeline vận hành đơn hàng')
            ->assertSee('Ban Giám đốc');
    }

    /* =====================================================================
     * 3. Công nợ thương mại từ số dư chuẩn
     * ===================================================================== */

    public function test_fully_paid_order_has_zero_balance_even_if_debt_table_says_owed(): void
    {
        $order = $this->makeOrder(['total_amount' => 59_400_000]);
        $this->pay($order, 44_000_000);
        $this->pay($order, 15_400_000);
        $this->debtRow($order, 59_400_000, 44_000_000); // bảng công nợ còn ghi nợ 15,4 triệu

        $receivable = $this->dashboard()['kpis']['receivable'];

        $this->assertSame(0.0, $receivable['commercial']);
        $this->assertSame([], $this->dashboard()['top_debtors']);
    }

    public function test_balance_uses_actual_order_total_not_debt_table_total(): void
    {
        $order = $this->makeOrder(['total_amount' => 39_881_000]);
        $this->pay($order, 12_000_000);
        $this->debtRow($order, 12_000_000, 12_000_000); // bảng công nợ ghi sai tổng đơn → nợ 0

        $this->assertEqualsWithDelta(27_881_000.0, $this->dashboard()['kpis']['receivable']['commercial'], 0.01);
    }

    public function test_order_with_multiple_debt_rows_is_counted_once(): void
    {
        $order = $this->makeOrder(['total_amount' => 500_000]);
        $this->debtRow($order, 500_000, 0, '2026-10-10');
        $this->debtRow($order, 500_000, 1_000_000, '2026-10-20');

        $dashboard = $this->dashboard();

        $this->assertEqualsWithDelta(500_000.0, $dashboard['kpis']['receivable']['commercial'], 0.01);
        $this->assertCount(1, $dashboard['top_debtors']);
        $this->assertEqualsWithDelta(500_000.0, $dashboard['top_debtors'][0]['debt'], 0.01);
    }

    public function test_partial_payment_balance_and_aging_and_top_debtors_share_one_source(): void
    {
        $overdue = $this->makeOrder(['total_amount' => 10_000_000], 'Khách Quá Hạn');
        $this->pay($overdue, 4_000_000);
        $this->debtRow($overdue, 10_000_000, 0, '2026-08-01'); // quá hạn > 30 ngày

        $notDue = $this->makeOrder(['total_amount' => 3_000_000], 'Khách Chưa Hạn'); // không có hạn

        $dashboard = $this->dashboard();
        $receivable = $dashboard['kpis']['receivable'];

        $this->assertEqualsWithDelta(9_000_000.0, $receivable['commercial'], 0.01);
        $this->assertEqualsWithDelta(6_000_000.0, $receivable['overdue_30'], 0.01);
        $this->assertEqualsWithDelta(3_000_000.0, $receivable['not_due'], 0.01);
        $this->assertEqualsWithDelta($receivable['commercial'], array_sum($receivable['aging']), 0.01);
        $this->assertEqualsWithDelta($receivable['commercial'], array_sum(array_column($dashboard['top_debtors'], 'debt')), 0.01);
        $this->assertSame('Khách Quá Hạn', $dashboard['top_debtors'][0]['name']);
        $this->assertEqualsWithDelta(6_000_000.0, $dashboard['top_debtors'][0]['overdue'], 0.01);
        $this->assertNotNull($notDue);
    }

    public function test_cancelled_deleted_and_unissued_orders_are_not_receivable(): void
    {
        $this->makeOrder(['current_department' => 'cancelled']);
        $this->makeOrder(['deleted_at' => now()]);
        $this->makePendingOrder('accounting', 626_000_000); // chờ duyệt: chưa phát sinh nợ

        $this->assertSame(0.0, $this->dashboard()['kpis']['receivable']['commercial']);
    }

    public function test_receivable_respects_company_and_sales_filters(): void
    {
        $this->makeOrder(['total_amount' => 1_000_000]);
        $this->makeOrder(['total_amount' => 7_000_000, 'company_id' => EgoCompanyLock::id() + 1000]);

        $otherSales = $this->userWithRole('sales');
        $this->makeOrder(['total_amount' => 2_000_000, 'created_by' => $otherSales->id]);

        $this->assertEqualsWithDelta(1_000_000.0, $this->dashboard()['kpis']['receivable']['commercial'], 0.01);
        $this->assertEqualsWithDelta(
            2_000_000.0,
            $this->dashboard(['sales_id' => $otherSales->id])['kpis']['receivable']['commercial'],
            0.01
        );

        // Sales thường chỉ thấy dữ liệu của mình dù không truyền sales_id.
        $own = app(ExecutiveDashboardService::class)->build($otherSales, self::SEPT)['executive'];
        $this->assertEqualsWithDelta(2_000_000.0, $own['kpis']['receivable']['commercial'], 0.01);
    }

    public function test_shared_receivable_query_returns_one_row_per_order(): void
    {
        $order = $this->makeOrder(['total_amount' => 800_000]);
        $this->pay($order, 300_000);
        $this->pay($order, 100_000);
        $this->debtRow($order, 800_000, 0);
        $this->debtRow($order, 800_000, 0);

        $rows = app(OrderReceivableQuery::class)->outstanding()
            ->where('o.id', $order)
            ->selectRaw('o.id, '.OrderReceivableQuery::BALANCE_EXPR.' AS balance')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(400_000.0, (float) $rows[0]->balance, 0.01);
    }

    /* =====================================================================
     * 4. Cảnh báo giao trễ
     * ===================================================================== */

    public function test_late_shipping_alert_excludes_shipped_and_not_yet_due_orders(): void
    {
        $this->makeOrder(['shipping_status' => 'shipped', 'estimated_delivery' => '2026-09-01', 'total_amount' => 5_000_000]);
        $late = $this->makeOrder(['shipping_status' => 'ready', 'estimated_delivery' => '2026-09-20', 'total_amount' => 2_000_000]);
        $this->makeOrder(['shipping_status' => 'ready', 'estimated_delivery' => '2026-10-05', 'total_amount' => 3_000_000]);

        $alert = $this->alert($this->dashboard(), 'late_shipping');

        $this->assertNotNull($alert);
        $this->assertSame(1, $alert['count']);
        $this->assertEqualsWithDelta(2_000_000.0, $alert['value'], 0.01);
        $this->assertNotNull($late);
    }

    public function test_no_late_shipping_alert_when_only_shipped_orders_are_past_eta(): void
    {
        $this->makeOrder(['shipping_status' => 'shipped', 'estimated_delivery' => '2026-09-01']);

        $this->assertNull($this->alert($this->dashboard(), 'late_shipping'));
    }

    /* =====================================================================
     * 5. Bỏ KPI "Rủi ro" tổng hợp
     * ===================================================================== */

    public function test_operations_kpi_only_has_order_and_site_counts(): void
    {
        $this->makePendingOrder('management', 72_000_000);

        $dashboard = $this->dashboard();

        $this->assertSame(['orders', 'sites'], array_keys($dashboard['kpis']['operations']));
        $this->assertNotNull($this->alert($dashboard, 'management'));

        $html = $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Giá trị liên quan rủi ro')
            ->getContent();

        $this->assertSame(1, preg_match('/<article class="exec-kpi exec-kpi--operations">(.*?)<\/article>/s', $html, $card));
        $this->assertStringContainsString('Đơn hàng', $card[1]);
        $this->assertStringContainsString('Công trình', $card[1]);
        $this->assertStringNotContainsString('cần xử lý', $card[1]);
        $this->assertStringNotContainsString('rủi ro', $card[1]);
    }

    /* =====================================================================
     * 6. Doanh thu thương mại ghi nhận theo ngày xuất kho
     * ===================================================================== */

    public function test_revenue_is_recognized_by_inventory_issued_at(): void
    {
        $this->makePendingOrder('accounting', 626_000_000);                          // 1. chưa xuất kho
        $this->makeOrder(['total_amount' => 1_000_000]);                             // 2. xuất kho trong kỳ
        $this->makeOrder([                                                           // 3. tạo trong kỳ, xuất ngoài kỳ
            'total_amount' => 50_000_000,
            'inventory_issued_at' => '2026-10-02 08:00:00',
        ]);
        $this->makeOrder([                                                           // 4. tạo kỳ trước, xuất trong kỳ
            'total_amount' => 10_000_000,
            'order_date' => '2026-08-25',
            'inventory_issued_at' => '2026-09-03 08:00:00',
        ]);
        $this->makeOrder([                                                           // 5. import thiếu ngày xuất
            'total_amount' => 70_000_000,
            'inventory_issued_at' => null,
        ]);
        $this->makeOrder(['total_amount' => 9_000_000, 'current_department' => 'cancelled']);
        $this->makeOrder(['total_amount' => 9_000_000, 'deleted_at' => now()]);

        $revenue = $this->dashboard(['source' => 'orders'])['kpis']['revenue'];

        $this->assertEqualsWithDelta(11_000_000.0, $revenue['commercial'], 0.01);
        $this->assertEqualsWithDelta(11_000_000.0, $revenue['value'], 0.01);
    }

    public function test_trend_chart_uses_same_recognition_date_as_revenue(): void
    {
        // Kỳ ≤ 18 ngày để biểu đồ (giữ tối đa 18 điểm) chứa trọn kỳ.
        $filters = ['from' => '2026-09-15', 'to' => '2026-09-30', 'source' => 'orders'];
        $this->makeOrder(['total_amount' => 4_000_000, 'order_date' => '2026-09-01', 'inventory_issued_at' => '2026-09-20 10:00:00']);
        $this->makeOrder(['total_amount' => 8_000_000, 'order_date' => '2026-09-16', 'inventory_issued_at' => '2026-10-02 10:00:00']);
        $this->makePendingOrder('warehouse', 9_000_000, ['order_date' => '2026-09-16']);

        $dashboard = $this->dashboard($filters);
        $byLabel = array_combine($dashboard['chart']['labels'], $dashboard['chart']['commercial']);

        $this->assertEqualsWithDelta(4_000_000.0, $byLabel['20/09'], 0.01);
        $this->assertEqualsWithDelta(0.0, $byLabel['16/09'], 0.01);
        $this->assertEqualsWithDelta($dashboard['kpis']['revenue']['commercial'], array_sum($dashboard['chart']['commercial']), 0.01);
    }

    public function test_collection_rate_only_uses_payments_of_recognized_orders(): void
    {
        $recognized = $this->makeOrder(['total_amount' => 10_000_000]);
        $this->pay($recognized, 4_000_000, '2026-09-15');
        $this->pay($recognized, 1_000_000, '2026-10-01'); // trả sau kỳ vẫn là tiền của đơn ghi nhận

        // Đơn cũ (xuất kho tháng 8) được trả trong tháng 9: là "tiền đã thu trong kỳ" nhưng không vào tỷ lệ.
        $old = $this->makeOrder(['total_amount' => 20_000_000, 'order_date' => '2026-08-01', 'inventory_issued_at' => '2026-08-05 08:00:00']);
        $this->pay($old, 20_000_000, '2026-09-10');

        $collected = $this->dashboard(['source' => 'orders'])['kpis']['collected'];

        $this->assertEqualsWithDelta(24_000_000.0, $collected['value'], 0.01);
        $this->assertEqualsWithDelta(5_000_000.0, $collected['recognized_paid'], 0.01);
        $this->assertEqualsWithDelta(50.0, $collected['rate'], 0.001);
    }

    public function test_recognized_paid_is_capped_at_order_total(): void
    {
        $order = $this->makeOrder(['total_amount' => 1_000_000]);
        $this->pay($order, 1_500_000);

        $collected = $this->dashboard(['source' => 'orders'])['kpis']['collected'];

        $this->assertEqualsWithDelta(1_000_000.0, $collected['recognized_paid'], 0.01);
        $this->assertEqualsWithDelta(100.0, $collected['rate'], 0.001);
    }

    public function test_revenue_keeps_company_sales_and_source_scope(): void
    {
        $this->makeOrder(['total_amount' => 1_000_000]);
        $this->makeOrder(['total_amount' => 5_000_000, 'company_id' => EgoCompanyLock::id() + 1000]);
        $otherSales = $this->userWithRole('sales');
        $this->makeOrder(['total_amount' => 3_000_000, 'created_by' => $otherSales->id]);

        $this->assertEqualsWithDelta(1_000_000.0, $this->dashboard()['kpis']['revenue']['commercial'], 0.01);
        $this->assertEqualsWithDelta(3_000_000.0, $this->dashboard(['sales_id' => $otherSales->id])['kpis']['revenue']['commercial'], 0.01);
        $this->assertSame(0.0, $this->dashboard(['source' => 'sites'])['kpis']['revenue']['commercial']);
    }

    public function test_team_debt_uses_actual_balance_not_unapproved_order_value(): void
    {
        $issued = $this->makeOrder(['total_amount' => 10_000_000]);
        $this->pay($issued, 7_000_000);
        $this->makePendingOrder('accounting', 626_000_000);

        $team = collect($this->dashboard()['team'])->keyBy('user_id');
        $member = $team->get($this->sales->id);

        $this->assertNotNull($member);
        $this->assertSame(1, $member['orders']);
        $this->assertEqualsWithDelta(10_000_000.0, $member['revenue'], 0.01);
        $this->assertEqualsWithDelta(7_000_000.0, $member['collected'], 0.01);
        $this->assertEqualsWithDelta(3_000_000.0, $member['debt'], 0.01);
        $this->assertEqualsWithDelta(70.0, $member['collection_rate'], 0.001);
    }

    public function test_dashboard_page_renders_for_admin(): void
    {
        $this->makeOrder();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Giám đốc')
            ->assertSee('Tỷ lệ thu của doanh thu ghi nhận')
            ->assertSee('Tiền đã thu trong kỳ');
    }
}
