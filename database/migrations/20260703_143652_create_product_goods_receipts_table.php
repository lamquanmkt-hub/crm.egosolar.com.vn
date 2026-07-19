<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('product_goods_receipts')) {
            Schema::create('product_goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();

                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('warehouse_id');

                $table->string('supplier_name');
                $table->string('supplier_phone', 80)->nullable();
                $table->string('supplier_tax_code', 80)->nullable();
                $table->string('supplier_address', 500)->nullable();

                $table->string('invoice_no', 120)->nullable();
                $table->date('invoice_date')->nullable();

                $table->string('payment_status', 30)->default('unpaid');
                $table->date('payment_due_date')->nullable();
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('paid_amount', 18, 2)->default(0);
                $table->decimal('debt_amount', 18, 2)->default(0);

                $table->string('status', 30)->default('draft');
                $table->text('note')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'warehouse_id']);
                $table->index(['payment_status']);
                $table->index(['status']);
                $table->index(['payment_due_date']);
            });
        }

        if (!Schema::hasTable('product_goods_receipt_items')) {
            Schema::create('product_goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('receipt_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('qty', 18, 3)->default(0);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('vat_percent', 8, 2)->default(0);
                $table->decimal('amount', 18, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['receipt_id']);
                $table->index(['product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_goods_receipt_items');
        Schema::dropIfExists('product_goods_receipts');
    }
};
