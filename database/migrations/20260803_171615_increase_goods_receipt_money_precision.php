<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_goods_receipt_items')) {
            if (Schema::hasColumn('product_goods_receipt_items', 'unit_price')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY unit_price DECIMAL(20,4) NOT NULL DEFAULT 0
                ");
            }

            if (Schema::hasColumn('product_goods_receipt_items', 'amount')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY amount DECIMAL(20,4) NOT NULL DEFAULT 0
                ");
            }

            if (Schema::hasColumn('product_goods_receipt_items', 'vat_percent')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY vat_percent DECIMAL(8,4) NOT NULL DEFAULT 0
                ");
            }
        }

        if (Schema::hasTable('product_goods_receipts')) {
            foreach (['total_amount', 'paid_amount', 'debt_amount'] as $column) {
                if (Schema::hasColumn('product_goods_receipts', $column)) {
                    DB::statement("
                        ALTER TABLE product_goods_receipts
                        MODIFY {$column} DECIMAL(20,4) NOT NULL DEFAULT 0
                    ");
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_goods_receipt_items')) {
            if (Schema::hasColumn('product_goods_receipt_items', 'unit_price')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY unit_price DECIMAL(18,2) NOT NULL DEFAULT 0
                ");
            }

            if (Schema::hasColumn('product_goods_receipt_items', 'amount')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY amount DECIMAL(18,2) NOT NULL DEFAULT 0
                ");
            }

            if (Schema::hasColumn('product_goods_receipt_items', 'vat_percent')) {
                DB::statement("
                    ALTER TABLE product_goods_receipt_items
                    MODIFY vat_percent DECIMAL(8,2) NOT NULL DEFAULT 0
                ");
            }
        }

        if (Schema::hasTable('product_goods_receipts')) {
            foreach (['total_amount', 'paid_amount', 'debt_amount'] as $column) {
                if (Schema::hasColumn('product_goods_receipts', $column)) {
                    DB::statement("
                        ALTER TABLE product_goods_receipts
                        MODIFY {$column} DECIMAL(18,2) NOT NULL DEFAULT 0
                    ");
                }
            }
        }
    }
};
