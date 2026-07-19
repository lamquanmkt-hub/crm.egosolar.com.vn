<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
	    Schema::create('crm_product_stock', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('product_id')->constrained('crm_product_catalog')->cascadeOnDelete();
		    $table->foreignId('warehouse_id')->constrained('crm_warehouses')->cascadeOnDelete();
		    $table->integer('qty')->default(0);
		    $table->timestamp('last_updated')->useCurrent()->useCurrentOnUpdate();
		    $table->unique(['product_id', 'warehouse_id']);
	    });

	    Schema::create('crm_stock_movements', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('product_id')->constrained('crm_product_catalog')->cascadeOnDelete();
		    $table->foreignId('warehouse_id')->constrained('crm_warehouses')->cascadeOnDelete();
		    $table->integer('change_qty');
		    $table->string('reason', 100)->nullable();
		    $table->unsignedBigInteger('reference_id')->nullable();
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_stock_movements');
	    Schema::dropIfExists('crm_product_stock');
    }
};
