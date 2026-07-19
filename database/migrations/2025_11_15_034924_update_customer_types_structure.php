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
	    /**
	     * 1. Tạo bảng crm_customer_types
	     */
	    Schema::create('crm_customer_types', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique();
		    $table->text('description')->nullable();
		    $table->timestamps();
	    });

	    /**
	     * 2. Thêm customer_type_id vào crm_customers
	     */
	    Schema::table('crm_customers', function (Blueprint $table) {
		    $table->unsignedBigInteger('customer_type_id')
		          ->nullable()
		          ->after('id');

		    $table->foreign('customer_type_id')
		          ->references('id')
		          ->on('crm_customer_types')
		          ->nullOnDelete();
	    });

	    /**
	     * 3. Xóa cột customer_type cũ
	     */
	    Schema::table('crm_customers', function (Blueprint $table) {
		    if (Schema::hasColumn('crm_customers', 'customer_type')) {
			    $table->dropColumn('customer_type');
		    }
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    /**
	     * 1. Thêm lại cột customer_type (string)
	     */
	    Schema::table('crm_customers', function (Blueprint $table) {
		    $table->string('customer_type')->nullable();
	    });

	    /**
	     * 2. Xóa FK + cột customer_type_id
	     */
	    Schema::table('crm_customers', function (Blueprint $table) {
		    if (Schema::hasColumn('crm_customers', 'customer_type_id')) {

			    $table->dropForeign(['customer_type_id']);
			    $table->dropColumn('customer_type_id');
		    }
	    });

	    /**
	     * 3. Xóa bảng crm_customer_types
	     */
	    Schema::dropIfExists('crm_customer_types');
    }
};
