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
	    // Bảng lưu lịch sử tương tác (call, message, meeting)
	    Schema::create('crm_customer_interactions', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete(); // Liên kết với lead
		    $table->enum('interaction_type', ['call', 'message', 'meeting', 'email', 'note']); // Loại tương tác
		    $table->string('subject', 255)->nullable(); // Tiêu đề
		    $table->text('content')->nullable(); // Nội dung chi tiết
		    $table->dateTime('interaction_date'); // Thời gian tương tác
		    $table->integer('duration_minutes')->nullable(); // Thời lượng (phút) - dùng cho call/meeting
		    $table->enum('outcome', ['positive', 'neutral', 'negative', 'no_answer'])->nullable(); // Kết quả
		    $table->dateTime('next_followup_date')->nullable(); // Lịch follow-up tiếp theo
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // Sales thực hiện
		    $table->timestamps();

		    // Index để truy vấn lịch sử tương tác
		    $table->index(['customer_id', 'interaction_date']);
		    $table->index(['created_by', 'next_followup_date']);
	    });

	    // Bảng attachments cho interactions (file đính kèm)
	    Schema::create('crm_interaction_attachments', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('interaction_id')->constrained('crm_customer_interactions')->cascadeOnDelete();
		    $table->string('file_name', 255);
		    $table->string('file_path', 511);
		    $table->string('file_type', 50)->nullable(); // image/pdf/doc/...
		    $table->unsignedBigInteger('file_size')->nullable(); // bytes
		    $table->timestamps();
	    });

	    // Bảng nhắc nhở/task cho Sales
	    Schema::create('crm_sales_tasks', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->nullable()->constrained('crm_customers')->nullOnDelete();
		    $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
		    $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
		    $table->string('title', 255);
		    $table->text('description')->nullable();
		    $table->enum('task_type', ['call', 'meeting', 'quote', 'follow_up', 'demo', 'other']); // Loại công việc
		    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
		    $table->dateTime('due_date')->nullable(); // Hạn hoàn thành
		    $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
		    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // Sales được giao
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamp('completed_at')->nullable();
		    $table->timestamps();

		    // Index để truy vấn task theo người và deadline
		    $table->index(['assigned_to', 'status', 'due_date']);
	    });

	    // Bảng bình luận/comment cho tasks
	    Schema::create('crm_task_comments', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('task_id')->constrained('crm_sales_tasks')->cascadeOnDelete();
		    $table->text('comment');
		    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_task_comments');
	    Schema::dropIfExists('crm_sales_tasks');
	    Schema::dropIfExists('crm_interaction_attachments');
	    Schema::dropIfExists('crm_customer_interactions');
    }
};
