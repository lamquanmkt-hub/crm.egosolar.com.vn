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
	    // Tạo bảng danh mục sản phẩm
	    Schema::create('crm_product_categories', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255);
		    $table->text('description')->nullable();
		    $table->foreignId('parent_id')->nullable()->constrained('crm_product_categories')->nullOnDelete();
		    $table->timestamps();
	    });

	    // Thêm foreign key cho category_id trong crm_product_catalog
	    Schema::table('crm_product_catalog', function (Blueprint $table) {
		    $table->foreign('category_id')
		          ->references('id')
		          ->on('crm_product_categories')
		          ->nullOnDelete();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_product_catalog', function (Blueprint $table) {
		    $table->dropForeign(['category_id']);
	    });

	    Schema::dropIfExists('crm_product_categories');
    }
};
