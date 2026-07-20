<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm chứng validation công nợ NCC sau khi chuyển sang FormRequest
 * (SupplierDebtRequest / SupplierDebtPaymentRoundRequest).
 */
final class SupplierDebtValidationTest extends TestCase
{
    use DatabaseTransactions;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWithRole('admin');
    }

    /** Payload hợp lệ tối thiểu cho form công nợ. */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'supplier_name' => 'NCC Validate',
            'company_name' => 'Công ty TNHH Ego Việt Nam',
            'document_no' => 'HD-VAL-001',
            'document_date' => now()->toDateString(),
            'debt_month' => now()->format('Y-m'),
            'total_amount' => '5.000.000',
        ], $overrides);
    }

    /** Thiếu tên nhà cung cấp: báo lỗi, không tạo bản ghi. */
    public function test_supplier_name_is_required(): void
    {
        $this->actingAs($this->accountant)
            ->post('/finance/supplier-debts', $this->validPayload(['supplier_name' => '']))
            ->assertSessionHasErrors('supplier_name');

        $this->assertNull(
            DB::table('finance_supplier_debts')->where('document_no', 'HD-VAL-001')->first(),
        );
    }

    /** Công ty ngoài danh sách cho phép bị từ chối. */
    public function test_company_name_must_be_one_of_allowed_options(): void
    {
        $this->actingAs($this->accountant)
            ->post('/finance/supplier-debts', $this->validPayload(['company_name' => 'Công ty Không Có Thật']))
            ->assertSessionHasErrors('company_name');
    }

    /** debt_month sai định dạng Y-m bị từ chối. */
    public function test_debt_month_must_match_year_month_format(): void
    {
        $this->actingAs($this->accountant)
            ->post('/finance/supplier-debts', $this->validPayload(['debt_month' => '20-07-2026']))
            ->assertSessionHasErrors('debt_month');
    }

    /** Tiền nhập có dấu phân cách ngàn vẫn qua rule numeric nhờ prepareForValidation. */
    public function test_formatted_money_passes_numeric_rule(): void
    {
        $this->actingAs($this->accountant)
            ->post('/finance/supplier-debts', $this->validPayload(['total_amount' => '5.000.000']))
            ->assertSessionHasNoErrors();

        $debt = DB::table('finance_supplier_debts')->where('document_no', 'HD-VAL-001')->first();

        $this->assertNotNull($debt);
        $this->assertSame(5_000_000.0, (float) $debt->total_amount);
    }
}
