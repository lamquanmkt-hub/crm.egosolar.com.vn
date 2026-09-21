<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký thao tác trên phiếu Đề nghị thanh toán (ĐNTT).
 *
 * Ghi lại ai / lúc nào / hành động gì / trạng thái trước-sau / trường nào
 * đổi từ giá trị nào sang giá trị nào / lý do — đặc biệt bắt buộc cho các
 * thao tác sửa, hủy, xóa phiếu đã được duyệt hoặc đã chi.
 *
 * Lưu ý thiết kế: cột `payment_request_id` CỐ TÌNH không đặt ràng buộc
 * khóa ngoại cascade tới `payment_requests`. Lịch sử phải sống sót kể cả
 * khi phiếu bị xóa cứng — đây chính là dữ liệu cần để điều tra sau này.
 * Cột `payment_request_code` lưu kèm mã phiếu để tra cứu khi phiếu gốc
 * không còn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_request_edit_logs')) {
            return;
        }

        Schema::create('payment_request_edit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('payment_request_id')->index();
            $table->string('payment_request_code', 100)->nullable()->index();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();

            // edit | approve | reject | cancel | delete | force_delete | restore | copy
            $table->string('action_type', 40)->index();

            $table->string('status_before', 60)->nullable();
            $table->string('status_after', 60)->nullable();

            $table->string('field_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->text('reason')->nullable();

            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['payment_request_id', 'created_at'], 'pr_edit_logs_pr_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_request_edit_logs');
    }
};
