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
	    Schema::create('crm_orders', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
		    $table->string('order_code', 100)->unique();
		    $table->date('order_date')->nullable();
		    $table->string('status', 100)->default('PENDING_APPROVAL');
		    $table->foreignId('warehouse_id')->nullable()->constrained('crm_warehouses')->nullOnDelete();
		    $table->decimal('total_amount', 15, 2)->default(0);
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->dateTime('approved_at')->nullable();
		    $table->boolean('payment_recorded')->default(false);
		    $table->timestamps();
	    });

	    Schema::create('crm_order_items', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->foreignId('product_id')->constrained('crm_product_catalog')->cascadeOnDelete();
		    $table->integer('quantity');
		    $table->decimal('unit_price', 15, 2);
		    $table->decimal('line_total', 15, 2)->storedAs('quantity * unit_price');
	    });

	    Schema::create('crm_order_approvals', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->enum('level', ['sales','ketoan','duyet1','duyet2','kho']);
		    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->dateTime('approved_at')->nullable();
		    $table->enum('status', ['pending','approved','rejected'])->default('pending');
	    });

	    Schema::create('crm_payments', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->date('payment_date')->nullable();
		    $table->decimal('amount', 15, 2);
		    $table->foreignId('method_id')->nullable()->constrained('crm_payment_methods')->nullOnDelete();
		    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_payments');
	    Schema::dropIfExists('crm_order_approvals');
	    Schema::dropIfExists('crm_order_items');
	    Schema::dropIfExists('crm_orders');
    }
};
