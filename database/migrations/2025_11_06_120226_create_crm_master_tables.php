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
	    Schema::create('crm_regions', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique();
	    });

	    Schema::create('crm_sources', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique();
		    $table->text('description')->nullable();
	    });

	    Schema::create('crm_lead_statuses', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique();
		    $table->string('color_code', 10)->nullable();
	    });

	    Schema::create('crm_payment_methods', function (Blueprint $table) {
		    $table->id();
		    $table->string('method_name', 100);
		    $table->text('description')->nullable();
	    });

	    Schema::create('crm_warehouses', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255);
		    $table->string('location', 255)->nullable();
		    $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
	    });

	    Schema::create('crm_product_catalog', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255);
		    $table->string('sku', 100)->unique();
		    $table->string('unit', 50)->nullable();
		    $table->unsignedBigInteger('category_id')->nullable();
		    $table->decimal('price', 15, 2)->default(0);
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_product_catalog');
	    Schema::dropIfExists('crm_warehouses');
	    Schema::dropIfExists('crm_payment_methods');
	    Schema::dropIfExists('crm_lead_statuses');
	    Schema::dropIfExists('crm_sources');
	    Schema::dropIfExists('crm_regions');
    }
};
