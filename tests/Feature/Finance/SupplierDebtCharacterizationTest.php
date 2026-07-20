<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Characterization test cho công nợ nhà cung cấp (Finance\SupplierDebtController):
 * danh sách, tạo/sửa/xoá công nợ và đợt thanh toán — chốt hành vi trước khi
 * tách logic sang service/repository.
 */
final class SupplierDebtCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWithRole('admin');
    }

    /** Tạo 1 công nợ nhà cung cấp trực tiếp trong DB, trả về id. */
    private function seedDebt(float $total = 10_000_000, float $paid = 0): int
    {
        return (int) DB::table('finance_supplier_debts')->insertGetId([
            'supplier_name' => 'NCC Test',
            'company_name' => 'Công ty Test',
            'document_no' => 'HD-TEST-001',
            'document_date' => now()->toDateString(),
            'debt_month' => now()->startOfMonth()->toDateString(),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => 'pending',
            'created_by' => $this->accountant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Trang danh sách công nợ hiển thị nhà cung cấp đã tạo. */
    public function test_index_lists_supplier_debts(): void
    {
        $this->seedDebt();

        $this->actingAs($this->accountant)
            ->get('/finance/supplier-debts')
            ->assertOk()
            ->assertSee('NCC Test');
    }

    /** Tạo công nợ mới qua form: bản ghi được lưu với số tiền đã chuẩn hoá. */
    public function test_store_creates_supplier_debt(): void
    {
        $this->actingAs($this->accountant)
            ->post('/finance/supplier-debts', [
                'supplier_name' => 'NCC Mới',
                'company_name' => 'Công ty TNHH Ego Việt Nam',
                'document_no' => 'HD-NEW-001',
                'document_date' => now()->toDateString(),
                'debt_month' => now()->format('Y-m'),
                'total_amount' => '15.000.000',
                'note' => 'Ghi chú test',
            ])
            ->assertRedirect();

        $debt = DB::table('finance_supplier_debts')
            ->where('document_no', 'HD-NEW-001')
            ->first();

        $this->assertNotNull($debt);
        $this->assertSame('NCC Mới', $debt->supplier_name);
        // Tiền nhập dạng "15.000.000" được chuẩn hoá về số
        $this->assertSame(15_000_000.0, (float) $debt->total_amount);
    }

    /** Cập nhật công nợ: đổi tên nhà cung cấp và số tiền. */
    public function test_update_modifies_supplier_debt(): void
    {
        $debtId = $this->seedDebt();

        $this->actingAs($this->accountant)
            ->put("/finance/supplier-debts/{$debtId}", [
                'supplier_name' => 'NCC Đã Sửa',
                'company_name' => 'Công ty TNHH Ego Việt Nam',
                'document_no' => 'HD-TEST-001',
                'document_date' => now()->toDateString(),
                'debt_month' => now()->format('Y-m'),
                'total_amount' => '20.000.000',
            ])
            ->assertRedirect();

        $debt = DB::table('finance_supplier_debts')->find($debtId);

        $this->assertSame('NCC Đã Sửa', $debt->supplier_name);
        $this->assertSame(20_000_000.0, (float) $debt->total_amount);
    }

    /**
     * Tạo đợt thanh toán mặc định (status 'planned'): đợt được ghi nhận nhưng
     * CHƯA tính vào paid_amount — công nợ chuyển sang 'partial' vì có tiền đang chờ chi.
     */
    public function test_store_planned_payment_round_does_not_count_as_paid(): void
    {
        $debtId = $this->seedDebt(total: 10_000_000);

        $this->actingAs($this->accountant)
            ->post("/finance/supplier-debts/{$debtId}/payment-rounds", [
                'amount' => '4.000.000',
                'payment_date' => now()->toDateString(),
                'note' => 'Đợt 1',
            ])
            ->assertRedirect();

        $round = DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', $debtId)
            ->first();

        $this->assertNotNull($round);
        $this->assertSame(4_000_000.0, (float) $round->amount);
        $this->assertSame('planned', $round->status);

        $debt = DB::table('finance_supplier_debts')->find($debtId);
        $this->assertSame(0.0, (float) $debt->paid_amount);
        $this->assertSame('partial', $debt->status);
    }

    /** Đợt thanh toán status 'paid' được cộng vào paid_amount của công nợ. */
    public function test_paid_payment_round_updates_debt_paid_amount(): void
    {
        $debtId = $this->seedDebt(total: 10_000_000);

        $this->actingAs($this->accountant)
            ->post("/finance/supplier-debts/{$debtId}/payment-rounds", [
                'amount' => '4.000.000',
                'payment_date' => now()->toDateString(),
                'status' => 'paid',
            ])
            ->assertRedirect();

        $debt = DB::table('finance_supplier_debts')->find($debtId);
        $this->assertSame(4_000_000.0, (float) $debt->paid_amount);
        $this->assertSame('partial', $debt->status);
    }

    /** Trả đủ toàn bộ: công nợ chuyển trạng thái 'paid'. */
    public function test_full_payment_marks_debt_as_paid(): void
    {
        $debtId = $this->seedDebt(total: 10_000_000);

        $this->actingAs($this->accountant)
            ->post("/finance/supplier-debts/{$debtId}/payment-rounds", [
                'amount' => '10.000.000',
                'payment_date' => now()->toDateString(),
                'status' => 'paid',
            ])
            ->assertRedirect();

        $debt = DB::table('finance_supplier_debts')->find($debtId);
        $this->assertSame(10_000_000.0, (float) $debt->paid_amount);
        $this->assertSame('paid', $debt->status);
    }

    /** Xoá công nợ: bản ghi biến mất khỏi DB. */
    public function test_destroy_removes_supplier_debt(): void
    {
        $debtId = $this->seedDebt();

        $this->actingAs($this->accountant)
            ->delete("/finance/supplier-debts/{$debtId}")
            ->assertRedirect();

        $this->assertNull(DB::table('finance_supplier_debts')->find($debtId));
    }
}
