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
	    // Bảng định nghĩa các loại đánh giá
	    Schema::create('crm_customer_rating_types', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique(); // Xịn / Tiềm năng / Không tiềm năng
		    $table->string('color_code', 10)->nullable();
		    $table->integer('priority')->default(0); // Độ ưu tiên
	    });

	    // Bảng lưu lịch sử đánh giá khách hàng
	    Schema::create('crm_customer_ratings', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('rating_type_id')->constrained('crm_customer_rating_types')->cascadeOnDelete();
		    $table->text('note')->nullable();
		    $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();

		    // Index để truy vấn nhanh đánh giá mới nhất của khách
		    $table->index(['customer_id', 'created_at']);
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_customer_ratings');
	    Schema::dropIfExists('crm_customer_rating_types');
    }
};
