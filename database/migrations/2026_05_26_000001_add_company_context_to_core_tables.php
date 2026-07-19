<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'payment_requests',
        'finance_supplier_debts',
        'finance_customer_debts',
        'finance_assets',
        'finance_receipts',
        'finance_payments',
        'sites',
        'material_requests',
        'sales_quotations',
        'sales_work_reports',
        'customer_profiles',
        'crm_customers',
        'crm_orders',
        'crm_warehouses',
        'crm_product_catalog',
        'crm_product_stock',
        'crm_serial_unit_states',
        'tasks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }

        $this->backfillPaymentRequests();
    }

    private function backfillPaymentRequests(): void
    {
        if (
            ! Schema::hasTable('payment_requests') ||
            ! Schema::hasTable('companies') ||
            ! Schema::hasColumn('payment_requests', 'company') ||
            ! Schema::hasColumn('payment_requests', 'company_id')
        ) {
            return;
        }

        $companies = DB::table('companies')->select('id', 'name')->get();

        foreach ($companies as $company) {
            DB::table('payment_requests')
                ->whereNull('company_id')
                ->where('company', $company->name)
                ->update([
                    'company_id' => $company->id,
                    'updated_at' => now(),
                ]);
        }

        $egoVn = DB::table('companies')
            ->where('name', 'like', '%Việt Nam%')
            ->orWhere('name', 'like', '%Viet Nam%')
            ->value('id');

        $egoQt = DB::table('companies')
            ->where('name', 'like', '%Quốc Tế%')
            ->orWhere('name', 'like', '%Quoc Te%')
            ->orWhere('name', 'like', '%TMKT%')
            ->value('id');

        if ($egoVn) {
            DB::table('payment_requests')
                ->whereNull('company_id')
                ->where(function ($q) {
                    $q->where('company', 'like', '%Việt Nam%')
                      ->orWhere('company', 'like', '%Viet Nam%');
                })
                ->update([
                    'company_id' => $egoVn,
                    'updated_at' => now(),
                ]);
        }

        if ($egoQt) {
            DB::table('payment_requests')
                ->whereNull('company_id')
                ->where(function ($q) {
                    $q->where('company', 'like', '%Quốc Tế%')
                      ->orWhere('company', 'like', '%Quoc Te%')
                      ->orWhere('company', 'like', '%TMKT%');
                })
                ->update([
                    'company_id' => $egoQt,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Không tự drop company_id để tránh mất liên kết dữ liệu.
    }
};
