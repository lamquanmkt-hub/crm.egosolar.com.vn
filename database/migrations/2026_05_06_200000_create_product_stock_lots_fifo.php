<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            Schema::create('crm_product_stock_lots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->string('lot_code')->nullable()->index();
                $table->dateTime('received_at')->nullable()->index();
                $table->integer('qty_in')->default(0);
                $table->integer('qty_remaining')->default(0)->index();
                $table->decimal('cost_before_vat', 18, 2)->default(0);
                $table->decimal('cost_vat_percent', 8, 2)->default(0);
                $table->decimal('cost_after_vat', 18, 2)->default(0);
                $table->string('source_type')->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'warehouse_id', 'company_id', 'qty_remaining'], 'idx_stock_lots_fifo');
            });
        }

        if (!Schema::hasTable('crm_order_item_stock_allocations')) {
            Schema::create('crm_order_item_stock_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('order_item_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('stock_lot_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->integer('qty')->default(0);
                $table->decimal('unit_cost_before_vat', 18, 2)->default(0);
                $table->decimal('unit_cost_after_vat', 18, 2)->default(0);
                $table->decimal('total_cost_after_vat', 18, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_item_stock_allocations');
        Schema::dropIfExists('crm_product_stock_lots');
    }
};
