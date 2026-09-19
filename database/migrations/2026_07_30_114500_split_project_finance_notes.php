<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        Schema::table('project_test_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_test_projects', 'sales_revenue_note')) {
                $table->text('sales_revenue_note')->nullable();
            }

            if (! Schema::hasColumn('project_test_projects', 'internal_finance_note')) {
                $table->text('internal_finance_note')->nullable();
            }
        });

        if (
            Schema::hasColumn('project_test_projects', 'financial_note')
            && Schema::hasColumn('project_test_projects', 'internal_finance_note')
        ) {
            DB::table('project_test_projects')
                ->whereNull('internal_finance_note')
                ->whereNotNull('financial_note')
                ->update(['internal_finance_note' => DB::raw('financial_note')]);
        }
    }

    public function down(): void
    {
        // Không xóa cột để tránh mất ghi chú tài chính khi rollback code.
    }
};
