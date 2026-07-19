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
	    // Bảng cấu hình quy trình duyệt đơn (do giám đốc thiết lập)
	    Schema::create('crm_approval_config', function (Blueprint $table) {
		    $table->id();
		    $table->enum('level', ['duyet1', 'duyet2']); // Cấp 1 (do GĐ chỉ định), Cấp 2 (Giám đốc)
		    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Người được phân quyền duyệt
		    $table->boolean('is_active')->default(true);
		    $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); // Giám đốc chỉ định
		    $table->timestamps();

		    // Đảm bảo mỗi level chỉ có 1 người active tại 1 thời điểm
		    $table->unique(['level', 'is_active']);
	    });

	    // Bổ sung trường vào crm_order_approvals để link với config
	    Schema::table('crm_order_approvals', function (Blueprint $table) {
		    $table->text('rejection_reason')->nullable()->after('status'); // Lý do từ chối (nếu có)
		    $table->timestamp('responded_at')->nullable()->after('approved_at'); // Thời gian phản hồi
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_order_approvals', function (Blueprint $table) {
		    $table->dropColumn(['rejection_reason', 'responded_at']);
	    });

	    Schema::dropIfExists('crm_approval_config');
    }
};
