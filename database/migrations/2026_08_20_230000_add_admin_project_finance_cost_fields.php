<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        $existingColumns = Schema::getColumnListing('sites');

        Schema::table('sites', function (Blueprint $table) use ($existingColumns): void {
            foreach (['contract_amount', 'contract_amount_after_vat', 'labor_cost', 'transport_cost', 'other_cost'] as $column) {
                if (! in_array($column, $existingColumns, true)) {
                    $table->decimal($column, 18, 2)->default(0);
                }
            }

            if (! in_array('other_cost_note', $existingColumns, true)) {
                $table->text('other_cost_note')->nullable();
            }

            if (! in_array('finance_note', $existingColumns, true)) {
                $table->text('finance_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Không xóa dữ liệu tài chính đang sử dụng khi rollback module.
    }
};
