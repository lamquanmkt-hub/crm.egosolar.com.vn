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
	    // Thêm cột giá đại lý, giá bán lẻ, quantity vào sản phẩm
	    Schema::table('crm_product_catalog', function (Blueprint $table) {
		    $table->decimal('price_agent', 15, 2)->default(0)->after('price');
		    $table->decimal('price_retail', 15, 2)->default(0)->after('price_agent');
		    $table->integer('quantity')->default(0)->after('price_retail');
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_product_catalog', function (Blueprint $table) {
		    $table->dropColumn(['price_agent', 'price_retail', 'quantity']);
	    });
    }
};
