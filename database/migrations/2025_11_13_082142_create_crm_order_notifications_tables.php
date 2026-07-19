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
	    // Bảng thông báo cho Sales về tiến độ đơn hàng
	    Schema::create('crm_order_notifications', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Sales nhận thông báo
		    $table->string('type', 50); // status_change / approved / rejected / shipped
		    $table->string('title', 255);
		    $table->text('message');
		    $table->boolean('is_read')->default(false);
		    $table->timestamp('read_at')->nullable();
		    $table->timestamps();

		    // Index để truy vấn thông báo chưa đọc
		    $table->index(['user_id', 'is_read', 'created_at']);
	    });

	    // Bảng cấu hình thời gian dự kiến cho từng giai đoạn
	    Schema::create('crm_order_timeline_config', function (Blueprint $table) {
		    $table->id();
		    $table->string('department', 50)->unique(); // ketoan / duyet1 / duyet2 / kho
		    $table->integer('estimated_hours')->default(24); // Thời gian dự kiến xử lý (giờ)
		    $table->boolean('notify_delay')->default(true); // Gửi thông báo khi quá hạn
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_order_timeline_config');
	    Schema::dropIfExists('crm_order_notifications');
    }
};
