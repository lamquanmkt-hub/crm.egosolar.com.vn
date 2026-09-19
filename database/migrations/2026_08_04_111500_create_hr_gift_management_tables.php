<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_gifts')) {
            Schema::create('hr_gifts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('sku', 80);
                $table->string('name');
                $table->string('gift_type', 120)->nullable()->index();
                $table->string('unit', 40)->default('Cái');
                $table->decimal('cost_price', 18, 2)->default(0);
                $table->decimal('minimum_stock', 18, 3)->default(0);
                $table->decimal('current_stock', 18, 3)->default(0);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['company_id', 'sku'], 'hr_gifts_company_sku_unique');
            });
        }

        if (! Schema::hasTable('hr_gift_receipts')) {
            Schema::create('hr_gift_receipts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('code', 60);
                $table->date('receipt_date')->index();
                $table->string('status', 30)->default('draft')->index();
                $table->string('supplier_name')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('submitted_by')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable()->index();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'hr_gift_receipts_company_code_unique');
            });
        }

        if (! Schema::hasTable('hr_gift_receipt_items')) {
            Schema::create('hr_gift_receipt_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('receipt_id')->index();
                $table->unsignedBigInteger('gift_id')->index();
                $table->decimal('quantity', 18, 3);
                $table->decimal('unit_cost', 18, 2)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_gift_requests')) {
            Schema::create('hr_gift_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('code', 60);
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name');
                $table->string('customer_phone', 50)->nullable();
                $table->text('delivery_address')->nullable();
                $table->string('reason')->nullable();
                $table->date('expected_delivery_date')->nullable()->index();
                $table->string('status', 30)->default('draft')->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('submitted_by')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable()->index();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('stock_deducted_at')->nullable();
                $table->unsignedBigInteger('delivery_status_updated_by')->nullable()->index();
                $table->timestamp('delivery_status_updated_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('delivery_note')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'hr_gift_requests_company_code_unique');
            });
        }

        if (! Schema::hasTable('hr_gift_request_items')) {
            Schema::create('hr_gift_request_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('gift_request_id')->index();
                $table->unsignedBigInteger('gift_id')->index();
                $table->decimal('quantity', 18, 3);
                $table->decimal('unit_cost_snapshot', 18, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_gift_stock_movements')) {
            Schema::create('hr_gift_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('gift_id')->index();
                $table->string('movement_type', 30)->index();
                $table->decimal('quantity', 18, 3);
                $table->decimal('balance_after', 18, 3);
                $table->string('source_type', 40)->index();
                $table->unsignedBigInteger('source_id')->index();
                $table->unsignedBigInteger('source_item_id')->index();
                $table->string('source_code', 60)->nullable()->index();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->unique(
                    ['source_type', 'source_item_id', 'movement_type'],
                    'hr_gift_movement_source_item_type_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_gift_stock_movements');
        Schema::dropIfExists('hr_gift_request_items');
        Schema::dropIfExists('hr_gift_requests');
        Schema::dropIfExists('hr_gift_receipt_items');
        Schema::dropIfExists('hr_gift_receipts');
        Schema::dropIfExists('hr_gifts');
    }
};
