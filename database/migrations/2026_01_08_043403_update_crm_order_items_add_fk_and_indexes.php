<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_order_items', function (Blueprint $table) {
            // Đảm bảo warehouse_id tồn tại nhưng chưa có FK
            if (Schema::hasColumn('crm_order_items', 'warehouse_id')) {
                DB::statement("
                    UPDATE crm_order_items oi
                    JOIN crm_orders o ON o.id = oi.order_id
                    SET oi.warehouse_id = o.warehouse_id
                    WHERE oi.warehouse_id IS NULL
                ");
                // Nếu cột đang nullable và unsignedBigInteger
                $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();

                // Thêm foreign key
                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('crm_warehouses')
                    ->cascadeOnDelete();
            }
        });

        // Drop & add lại generated column line_total
        DB::statement('ALTER TABLE crm_order_items DROP COLUMN line_total');

        DB::statement("
            ALTER TABLE crm_order_items
            ADD COLUMN line_total DECIMAL(15,2)
            GENERATED ALWAYS AS (
                ROUND(quantity * unit_price * (1 - (discount_percent / 100)), 2)
            ) STORED
        ");

        // Thêm unique index
        DB::statement("
            ALTER TABLE crm_order_items
            ADD UNIQUE KEY uq_order_wh_product (order_id, warehouse_id, product_id)
        ");
    }

    public function down(): void
    {
        // Remove unique index
        DB::statement("
            ALTER TABLE crm_order_items
            DROP INDEX uq_order_wh_product
        ");

        // Drop foreign key
        Schema::table('crm_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_order_items', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
            }
        });

        // Drop generated column
        DB::statement('ALTER TABLE crm_order_items DROP COLUMN line_total');

        // Add lại line_total thường (rollback an toàn)
        Schema::table('crm_order_items', function (Blueprint $table) {
            $table->decimal('line_total', 15, 2)->after('discount_percent');
        });
    }
};
