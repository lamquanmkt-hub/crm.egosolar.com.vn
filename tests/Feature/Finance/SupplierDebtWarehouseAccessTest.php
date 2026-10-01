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
 * Role Kho dùng ĐẦY ĐỦ chức năng Công nợ nhà cung cấp (xem, thêm, sửa, xóa, đợt thanh toán),
 * kể cả tài khoản kiêm role khác; role không liên quan (sales, kỹ thuật) vẫn bị chặn.
 */
final class SupplierDebtWarehouseAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/finance/supplier-debts';

    /** @param list<string> $roles @param list<string> $pagePermissions */
    private function userWithRoles(array $roles, array $pagePermissions = ['page.dashboard', 'page.warehouses']): User
    {
        $user = User::factory()->create(['is_active' => 1]);

        foreach ($roles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            if (Schema::hasColumn('roles', 'page_access_enabled')) {
                DB::table('roles')->where('id', $role->id)->update(['page_access_enabled' => 1]);
            }
            $role->syncPermissions(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $pagePermissions));
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'supplier_name' => 'NCC kho test '.uniqid(),
            'company_name' => EgoCompanyLock::name(),
            'debt_month' => '2026-09',
            'total_amount' => 10_000_000,
            'note' => 'Nhập hàng test',
        ];
    }

    public function test_warehouse_roles_open_the_list(): void
    {
        foreach ([['warehouse'], ['kho'], ['warehouse', 'sales']] as $roles) {
            $this->actingAs($this->userWithRoles($roles))->get(self::BASE)->assertOk();
        }
    }

    public function test_warehouse_can_add_edit_pay_rounds_and_delete(): void
    {
        $kho = $this->userWithRoles(['warehouse']);
        $data = $this->payload();

        // Thêm
        $this->actingAs($kho)->post(self::BASE, $data)->assertSessionHasNoErrors()->assertRedirect();
        $debt = DB::table('finance_supplier_debts')->where('supplier_name', $data['supplier_name'])->first();
        $this->assertNotNull($debt);

        // Sửa
        $this->actingAs($kho)->put(self::BASE.'/'.$debt->id, array_merge($data, ['total_amount' => 12_000_000]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(12_000_000, (float) DB::table('finance_supplier_debts')->where('id', $debt->id)->value('total_amount'), 0.01);

        // Thêm đợt thanh toán
        $this->actingAs($kho)->post(self::BASE.'/'.$debt->id.'/payment-rounds', [
            'bulk_rounds' => [['amount' => '5000000', 'payment_date' => '2026-09-20', 'note' => 'Đợt 1']],
        ])->assertSessionMissing('errors');
        $this->assertSame(1, DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debt->id)->count());

        // Xóa đợt rồi xóa công nợ
        $round = DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debt->id)->first();
        $this->actingAs($kho)->delete(self::BASE.'/payment-rounds/'.$round->id)->assertStatus(302);
        $this->actingAs($kho)->delete(self::BASE.'/'.$debt->id)->assertStatus(302);
        $this->assertNull(DB::table('finance_supplier_debts')->where('id', $debt->id)->first());
    }

    public function test_unrelated_roles_are_still_blocked(): void
    {
        $sales = $this->userWithRoles(['sales'], ['page.dashboard', 'page.sales']);
        $technical = $this->userWithRoles(['ky_thuat'], ['page.dashboard', 'page.technical']);

        foreach ([$sales, $technical] as $user) {
            $this->actingAs($user)->get(self::BASE)->assertForbidden();
            $this->actingAs($user)->post(self::BASE, $this->payload())->assertForbidden();
        }
    }
}
