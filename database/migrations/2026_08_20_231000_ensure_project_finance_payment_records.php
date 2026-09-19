<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_payment_terms')) {
            Schema::create('site_payment_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->string('name');
                $table->decimal('percent', 8, 2)->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->string('status', 50)->default('pending');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_payment_records')) {
            Schema::create('project_payment_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('payment_term_id')->nullable()->index();
                $table->unsignedBigInteger('receipt_id')->nullable()->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->date('paid_at')->index();
                $table->string('payment_method', 100)->default('bank_transfer');
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('transaction_reference')->nullable();
                $table->string('payer_name')->nullable();
                $table->text('note')->nullable();
                $table->string('attachment_path')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->string('status', 50)->default('CONFIRMED')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('receipts')) {
            $existingColumns = Schema::getColumnListing('receipts');

            Schema::table('receipts', function (Blueprint $table) use ($existingColumns): void {
                foreach (['site_id', 'site_payment_term_id', 'account_id', 'confirmed_by'] as $column) {
                    if (! in_array($column, $existingColumns, true)) {
                        $table->unsignedBigInteger($column)->nullable()->index();
                    }
                }

                foreach (['transaction_reference', 'attachment_path'] as $column) {
                    if (! in_array($column, $existingColumns, true)) {
                        $table->string($column)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Không xóa lịch sử thu tiền hay chứng từ đã ghi nhận.
    }
};
