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
                $table->date('settlement_due_date')->nullable();
                $table->decimal('actual_spent_amount', 15, 2)->default(0);
                $table->decimal('difference_amount', 15, 2)->default(0);
                $table->text('settlement_reason')->nullable();
                $table->text('settlement_note')->nullable();
                $table->longText('attachments')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('management_approved_by')->nullable();
                $table->timestamp('management_approved_at')->nullable();
                $table->text('management_note')->nullable();
                $table->unsignedBigInteger('accounting_checked_by')->nullable();
                $table->timestamp('accounting_checked_at')->nullable();
                $table->text('accounting_note')->nullable();
                $table->unsignedBigInteger('returned_by')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->text('return_note')->nullable();
                // Cột tương thích module cũ.
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
        } else {
            Schema::table('advance_settlements', function (Blueprint $table) {
                if (! Schema::hasColumn('advance_settlements', 'settlement_due_date')) {
                    $table->date('settlement_due_date')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'settlement_reason')) {
                    $table->text('settlement_reason')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'attachments')) {
                    $table->longText('attachments')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'management_approved_by')) {
                    $table->unsignedBigInteger('management_approved_by')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'management_approved_at')) {
                    $table->timestamp('management_approved_at')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'management_note')) {
                    $table->text('management_note')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'accounting_checked_by')) {
                    $table->unsignedBigInteger('accounting_checked_by')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'accounting_checked_at')) {
                    $table->timestamp('accounting_checked_at')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'accounting_note')) {
                    $table->text('accounting_note')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'returned_by')) {
                    $table->unsignedBigInteger('returned_by')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'returned_at')) {
                    $table->timestamp('returned_at')->nullable();
                }
                if (! Schema::hasColumn('advance_settlements', 'return_note')) {
                    $table->text('return_note')->nullable();
                }
            });
        }

        // Giữ bảng chi tiết cũ để không phá dữ liệu/module lịch sử.
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
        if (! Schema::hasTable('advance_settlements')) {
            return;
        }

        $columns = [
            'settlement_due_date', 'settlement_reason', 'attachments',
            'management_approved_by', 'management_approved_at', 'management_note',
            'accounting_checked_by', 'accounting_checked_at', 'accounting_note',
            'returned_by', 'returned_at', 'return_note',
        ];

        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn('advance_settlements', $column)));
        if ($existing) {
            Schema::table('advance_settlements', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
