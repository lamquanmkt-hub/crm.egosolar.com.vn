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
	    // Bảng định nghĩa các trạng thái đơn hàng
	    Schema::create('crm_order_status_types', function (Blueprint $table) {
		    $table->id();
		    $table->string('code', 50)->unique(); // PENDING_KETOAN, APPROVED_KETOAN, PENDING_DUYET1, etc.
		    $table->string('name', 255); // Tên hiển thị
		    $table->string('department', 50); // sales / ketoan / duyet1 / duyet2 / kho / completed
		    $table->string('color', 20)->nullable();
		    $table->integer('order_sequence')->default(0); // Thứ tự trong quy trình
		    $table->boolean('is_final')->default(false); // Trạng thái cuối
	    });

	    // Bảng lịch sử thay đổi trạng thái đơn hàng (để Sales theo dõi)
	    Schema::create('crm_order_status_history', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->foreignId('status_type_id')->constrained('crm_order_status_types')->cascadeOnDelete();
		    $table->string('from_department', 50)->nullable(); // Bộ phận trước
		    $table->string('to_department', 50); // Bộ phận hiện tại
		    $table->text('note')->nullable();
		    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamp('changed_at')->useCurrent();

		    // Index để truy vấn lịch sử nhanh
		    $table->index(['order_id', 'changed_at']);
	    });

	    // Bổ sung cột current_department vào crm_orders
	    Schema::table('crm_orders', function (Blueprint $table) {
		    $table->string('current_department', 50)->default('sales')->after('status'); // Đơn đang ở bp nào
		    $table->foreignId('current_status_type_id')->nullable()->after('current_department')
		          ->constrained('crm_order_status_types')->nullOnDelete();
		    $table->timestamp('estimated_delivery')->nullable()->after('approved_at'); // Dự kiến giao hàng
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_orders', function (Blueprint $table) {
		    $table->dropForeign(['current_status_type_id']);
		    $table->dropColumn(['current_department', 'current_status_type_id', 'estimated_delivery']);
	    });

	    Schema::dropIfExists('crm_order_status_history');
	    Schema::dropIfExists('crm_order_status_types');
    }
};
