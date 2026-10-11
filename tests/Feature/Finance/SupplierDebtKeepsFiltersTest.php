<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Công nợ nhà cung cấp: sau khi sửa / xóa / thêm đợt thanh toán, hệ thống quay về ĐÚNG danh sách đang lọc
 * (không nhảy về danh sách mặc định mất bộ lọc).
 */
final class SupplierDebtKeepsFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/finance/supplier-debts';

    private function accountant(): User
    {
        return $this->userWithRole('admin', ['is_active' => 1]);
    }

    private function payload(string $name, array $overrides = []): array
    {
        return $overrides + [
            'supplier_name' => $name,
            'company_name' => EgoCompanyLock::name(),
            'debt_month' => '2026-09',
            'total_amount' => 10_000_000,
            'note' => 'Giữ bộ lọc',
        ];
    }

    private function makeDebt(User $user, string $name): object
    {
        $this->actingAs($user)->post(self::BASE, $this->payload($name))->assertSessionHasNoErrors();

        return DB::table('finance_supplier_debts')->where('supplier_name', $name)->firstOrFail();
    }

    private function assertRedirectKeepsFilters($response): void
    {
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/finance/supplier-debts', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('all', $query['period'] ?? null, 'Mất kiểu lọc: '.$location);
        $this->assertSame('tài nguyên thiên nhiên', $query['keyword'] ?? null, 'Mất từ khóa: '.$location);
    }

    private function filters(): array
    {
        return ['ret' => ['period' => 'all', 'keyword' => 'tài nguyên thiên nhiên']];
    }

    public function test_update_returns_to_the_filtered_list(): void
    {
        $user = $this->accountant();
        $name = 'TÀI NGUYÊN THIÊN NHIÊN '.uniqid();
        $debt = $this->makeDebt($user, $name);

        $response = $this->actingAs($user)->put(self::BASE.'/'.$debt->id, $this->payload($name, ['total_amount' => 12_000_000]) + $this->filters());

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertRedirectKeepsFilters($response);
    }

    public function test_delete_returns_to_the_filtered_list(): void
    {
        $user = $this->accountant();
        $debt = $this->makeDebt($user, 'NCC xoa giu loc '.uniqid());

        $response = $this->actingAs($user)->delete(self::BASE.'/'.$debt->id, $this->filters());

        $response->assertRedirect();
        $this->assertRedirectKeepsFilters($response);
    }

    public function test_adding_a_payment_round_returns_to_the_filtered_list(): void
    {
        $user = $this->accountant();
        $debt = $this->makeDebt($user, 'NCC them dot giu loc '.uniqid());

        $response = $this->actingAs($user)->post(self::BASE.'/'.$debt->id.'/payment-rounds', [
            'bulk_rounds' => [['amount' => '1000000', 'payment_date' => '2026-09-20', 'note' => 'Đợt 1']],
        ] + $this->filters());

        $response->assertRedirect();
        $this->assertRedirectKeepsFilters($response);
    }

    public function test_deleting_a_payment_round_returns_to_the_filtered_list(): void
    {
        $user = $this->accountant();
        $debt = $this->makeDebt($user, 'NCC xoa dot giu loc '.uniqid());
        $this->actingAs($user)->post(self::BASE.'/'.$debt->id.'/payment-rounds', [
            'bulk_rounds' => [['amount' => '1000000', 'payment_date' => '2026-09-20', 'note' => 'Đợt 1']],
        ])->assertSessionMissing('errors');
        $round = DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debt->id)->firstOrFail();

        $response = $this->actingAs($user)->delete(self::BASE.'/payment-rounds/'.$round->id, $this->filters());

        $response->assertRedirect();
        $this->assertRedirectKeepsFilters($response);
    }
    public function test_without_filters_it_still_falls_back_to_the_month_list(): void
    {
        $user = $this->accountant();
        $name = 'NCC khong loc '.uniqid();
        $debt = $this->makeDebt($user, $name);

        $response = $this->actingAs($user)->put(self::BASE.'/'.$debt->id, $this->payload($name, ['total_amount' => 11_000_000]));

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('2026-09', $query['month'] ?? null);
        $this->assertArrayNotHasKey('keyword', $query);
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $user = $this->accountant();
        $name = 'NCC loc sai '.uniqid();
        $debt = $this->makeDebt($user, $name);

        $response = $this->actingAs($user)->put(self::BASE.'/'.$debt->id, $this->payload($name) + ['ret' => ['period' => 'evil', 'month' => 'khong-hop-le', 'keyword' => 'abc']]);

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('period', $query);
        $this->assertArrayNotHasKey('month', $query);
        $this->assertSame('abc', $query['keyword'] ?? null);
    }

    public function test_forms_on_the_page_carry_the_current_filters(): void
    {
        $user = $this->accountant();
        $name = 'TÀI NGUYÊN THIÊN NHIÊN '.uniqid();
        $this->makeDebt($user, $name);

        $html = $this->actingAs($user)
            ->get(self::BASE.'?period=all&keyword='.urlencode('tài nguyên thiên nhiên'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="ret[period]" value="all"', $html);
        $this->assertStringContainsString('name="ret[keyword]" value="tài nguyên thiên nhiên"', $html);
        // Nhiều form (thêm / sửa / xóa / đợt thanh toán / tệp) đều mang bộ lọc.
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'name="ret[keyword]"'));
    }
}
