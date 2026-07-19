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
	    // Bảng theo dõi công nợ của từng khách hàng
	    Schema::create('crm_customer_debts', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
		    $table->decimal('total_amount', 15, 2); // Tổng tiền đơn hàng
		    $table->decimal('paid_amount', 15, 2)->default(0); // Đã thanh toán
		    $table->decimal('debt_amount', 15, 2)->storedAs('total_amount - paid_amount'); // Còn nợ
		    $table->date('due_date')->nullable(); // Hạn thanh toán
		    $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
		    $table->timestamps();

		    // Index để kiểm tra công nợ nhanh
		    $table->index(['customer_id', 'status']);
	    });

	    // Bảng lịch sử thanh toán công nợ
	    Schema::create('crm_debt_payment_history', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('debt_id')->constrained('crm_customer_debts')->cascadeOnDelete();
		    $table->foreignId('payment_id')->nullable()->constrained('crm_payments')->nullOnDelete();
		    $table->decimal('amount', 15, 2); // Số tiền thanh toán
		    $table->date('payment_date');
		    $table->text('note')->nullable();
		    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });

	    // Thêm trường debt_check vào crm_order_approvals
	    Schema::table('crm_order_approvals', function (Blueprint $table) {
		    $table->boolean('debt_checked')->default(false)->after('status'); // Kế toán đã check công nợ
		    $table->text('debt_note')->nullable()->after('debt_checked'); // Ghi chú về công nợ
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_order_approvals', function (Blueprint $table) {
		    $table->dropColumn(['debt_checked', 'debt_note']);
	    });

	    Schema::dropIfExists('crm_debt_payment_history');
	    Schema::dropIfExists('crm_customer_debts');
    }
};
