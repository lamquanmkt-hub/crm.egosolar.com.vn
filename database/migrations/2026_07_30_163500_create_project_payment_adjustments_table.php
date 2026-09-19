<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_test_payment_adjustments')) {
            return;
        }

        Schema::create('project_test_payment_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->string('adjustment_code', 70)->unique();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('correct_amount', 15, 2);
            $table->decimal('delta_amount', 15, 2);
            $table->text('reason');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(
                ['project_id', 'status', 'created_at'],
                'pt_pay_adjust_project_status_date'
            );
        });
    }

    public function down(): void
    {
        // Không tự xóa bảng để tránh mất lịch sử điều chỉnh khi rollback code.
    }
};
