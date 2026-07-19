<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        Schema::table('crm_product_stock_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_product_stock_lots', 'extra_cost')) {
                $table->decimal('extra_cost', 18, 2)->default(0)->after('cost_after_vat');
            }

            if (!Schema::hasColumn('crm_product_stock_lots', 'actual_cost_after_vat')) {
                $table->decimal('actual_cost_after_vat', 18, 2)->default(0)->after('extra_cost');
            }
        });

        DB::table('crm_product_stock_lots')
            ->where(function ($q) {
                $q->whereNull('actual_cost_after_vat')
                  ->orWhere('actual_cost_after_vat', '<=', 0);
            })
            ->update([
                'extra_cost' => DB::raw('COALESCE(extra_cost, 0)'),
                'actual_cost_after_vat' => DB::raw('COALESCE(cost_after_vat, 0) + COALESCE(extra_cost, 0)'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        Schema::table('crm_product_stock_lots', function (Blueprint $table) {
            if (Schema::hasColumn('crm_product_stock_lots', 'actual_cost_after_vat')) {
                $table->dropColumn('actual_cost_after_vat');
            }

            if (Schema::hasColumn('crm_product_stock_lots', 'extra_cost')) {
                $table->dropColumn('extra_cost');
            }
        });
    }
};
