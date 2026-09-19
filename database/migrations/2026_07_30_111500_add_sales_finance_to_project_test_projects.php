<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        Schema::table('project_test_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_test_projects', 'amount_collected')) {
                $table->decimal('amount_collected', 15, 2)->default(0)->after('contract_amount');
            }
            if (! Schema::hasColumn('project_test_projects', 'extra_revenue')) {
                $table->decimal('extra_revenue', 15, 2)->default(0)->after('amount_collected');
            }
            if (! Schema::hasColumn('project_test_projects', 'labor_cost')) {
                $table->decimal('labor_cost', 15, 2)->default(0)->after('extra_revenue');
            }
            if (! Schema::hasColumn('project_test_projects', 'transport_cost')) {
                $table->decimal('transport_cost', 15, 2)->default(0)->after('labor_cost');
            }
            if (! Schema::hasColumn('project_test_projects', 'other_cost')) {
                $table->decimal('other_cost', 15, 2)->default(0)->after('transport_cost');
            }
            if (! Schema::hasColumn('project_test_projects', 'financial_note')) {
                $table->text('financial_note')->nullable()->after('other_cost');
            }
            if (! Schema::hasColumn('project_test_projects', 'financial_updated_by')) {
                $table->unsignedBigInteger('financial_updated_by')->nullable()->index()->after('financial_note');
            }
            if (! Schema::hasColumn('project_test_projects', 'financial_updated_at')) {
                $table->timestamp('financial_updated_at')->nullable()->after('financial_updated_by');
            }
        });
    }

    public function down(): void
    {
        // Giữ dữ liệu tài chính khi rollback code để tránh mất số liệu đã nhập.
    }
};
