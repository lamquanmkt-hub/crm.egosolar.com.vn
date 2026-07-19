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
            if (!Schema::hasColumn('crm_product_stock_lots', 'lot_name')) {
                $table->string('lot_name')->nullable()->after('lot_code')->index();
            }
        });

        DB::table('crm_product_stock_lots')
            ->whereNull('lot_name')
            ->update([
                'lot_name' => DB::raw('lot_code'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        Schema::table('crm_product_stock_lots', function (Blueprint $table) {
            if (Schema::hasColumn('crm_product_stock_lots', 'lot_name')) {
                $table->dropColumn('lot_name');
            }
        });
    }
};
