<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advance_settlements')) {
            Schema::create('advance_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_request_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('status', 40)->default('waiting_payment');
                $table->decimal('actual_spent_amount', 15, 2)->default(0);
                $table->decimal('difference_amount', 15, 2)->default(0);
                $table->text('settlement_note')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('checked_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->text('check_note')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('complete_note')->nullable();
                $table->timestamps();

                $table->unique('payment_request_id', 'adv_set_pr_uq');
                $table->index(['status', 'payment_request_id'], 'adv_set_status_pr_idx');
            });
        }

        if (! Schema::hasTable('advance_settlement_items')) {
            Schema::create('advance_settlement_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('advance_settlement_id');
                $table->date('expense_date')->nullable();
                $table->string('content', 1000);
                $table->string('receiver_name', 500)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('proof_name', 500)->nullable();
                $table->string('proof_path', 1000)->nullable();
                $table->timestamps();

                $table->index('advance_settlement_id', 'adv_item_set_idx');
            });
        }

        if (! Schema::hasTable('salary_advance_requests')) {
            Schema::create('salary_advance_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_request_id');
                $table->unsignedBigInteger('user_id');
                $table->string('payroll_month', 7);
                $table->decimal('requested_amount', 15, 2)->default(0);
                $table->text('reason')->nullable();
                $table->date('needed_date')->nullable();
                $table->unsignedBigInteger('payroll_id')->nullable();
                $table->timestamp('applied_to_payroll_at')->nullable();
                $table->timestamps();

                $table->unique('payment_request_id', 'sal_adv_pr_uq');
                $table->index(['user_id', 'payroll_month'], 'sal_adv_user_month_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_settlement_items');
        Schema::dropIfExists('advance_settlements');
        Schema::dropIfExists('salary_advance_requests');
    }
};
