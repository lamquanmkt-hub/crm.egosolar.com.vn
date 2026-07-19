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
	    // Bảng gói hội viên
	    Schema::create('crm_membership_packages', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255); // Gói cơ bản / VIP / Premium
		    $table->text('description')->nullable();
		    $table->decimal('price', 15, 2)->default(0);
		    $table->integer('duration_months')->default(12); // Thời hạn (tháng)
		    $table->json('benefits')->nullable(); // Quyền lợi dạng JSON
		    $table->boolean('is_active')->default(true);
		    $table->timestamps();
	    });

	    // Bảng thông tin hội viên
	    Schema::create('crm_memberships', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('package_id')->constrained('crm_membership_packages')->cascadeOnDelete();
		    $table->string('membership_code', 100)->unique(); // Mã hội viên
		    $table->date('start_date'); // Ngày bắt đầu
		    $table->date('end_date'); // Ngày hết hạn
		    $table->enum('status', ['active', 'expired', 'suspended', 'cancelled'])->default('active');
		    $table->integer('points')->default(0); // Điểm tích lũy
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();

		    // Index để kiểm tra hội viên còn hiệu lực
		    $table->index(['customer_id', 'status', 'end_date']);
	    });

	    // Bảng lịch sử gia hạn/nâng cấp hội viên
	    Schema::create('crm_membership_history', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('membership_id')->constrained('crm_memberships')->cascadeOnDelete();
		    $table->enum('action_type', ['register', 'renew', 'upgrade', 'downgrade', 'suspend', 'cancel']);
		    $table->foreignId('from_package_id')->nullable()->constrained('crm_membership_packages')->nullOnDelete();
		    $table->foreignId('to_package_id')->nullable()->constrained('crm_membership_packages')->nullOnDelete();
		    $table->decimal('amount_paid', 15, 2)->default(0);
		    $table->text('note')->nullable();
		    $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });

	    // Bảng điểm thưởng/tích lũy
	    Schema::create('crm_membership_points', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('membership_id')->constrained('crm_memberships')->cascadeOnDelete();
		    $table->integer('points'); // Số điểm (+/-)
		    $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']); // Kiểu giao dịch
		    $table->string('reason', 255)->nullable(); // Lý do
		    $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete(); // Liên kết đơn hàng
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_membership_points');
	    Schema::dropIfExists('crm_membership_history');
	    Schema::dropIfExists('crm_memberships');
	    Schema::dropIfExists('crm_membership_packages');
    }
};
