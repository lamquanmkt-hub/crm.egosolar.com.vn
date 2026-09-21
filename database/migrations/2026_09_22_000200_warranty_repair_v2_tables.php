<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sửa chữa sản phẩm TÍNH PHÍ (claim_type = paid_repair) — bảng mới, additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warranty_repair_quotations')) {
            Schema::create('warranty_repair_quotations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedInteger('version');
                // draft|sent|approved|rejected|superseded
                $table->string('status', 20)->default('draft')->index();
                $table->decimal('parts_total', 15, 2)->default(0);
                $table->decimal('labor_amount', 15, 2)->default(0);
                $table->decimal('onsite_amount', 15, 2)->default(0);
                $table->decimal('shipping_amount', 15, 2)->default(0);
                $table->decimal('extra_amount', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('decision', 20)->nullable();            // approved|rejected
                $table->string('decision_method', 20)->nullable();     // phone|zalo|email|in_person|other
                $table->timestamp('decision_at')->nullable();          // ngày khách xác nhận
                $table->unsignedBigInteger('decision_by')->nullable(); // người cập nhật
                $table->text('decision_note')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
                $table->unique(['claim_id', 'version'], 'uq_wrq_claim_version');
            });
        }

        if (! Schema::hasTable('warranty_repair_quotation_items')) {
            Schema::create('warranty_repair_quotation_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('quotation_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('name', 255);
                $table->string('sku', 100)->nullable();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('line_total', 15, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warranty_repair_parts')) {
            Schema::create('warranty_repair_parts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('name', 255);
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('qty_planned', 12, 3)->default(0);
                $table->decimal('qty_reserved', 12, 3)->default(0);
                $table->decimal('qty_issued', 12, 3)->default(0);
                $table->decimal('qty_used', 12, 3)->default(0);
                $table->decimal('qty_returned', 12, 3)->default(0);
                // planned|reserved|issued|closed|released
                $table->string('status', 20)->default('planned')->index();
                $table->unsignedBigInteger('reserved_by')->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->unsignedBigInteger('received_by')->nullable(); // người nhận linh kiện
                $table->timestamps();
                $table->unique(['claim_id', 'product_id'], 'uq_wrp_claim_product');
            });
        }

        if (! Schema::hasTable('warranty_repair_qa')) {
            Schema::create('warranty_repair_qa', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->string('result', 10);                 // pass|fail
                $table->text('measurements')->nullable();     // thông số kiểm tra
                $table->text('note')->nullable();
                $table->unsignedBigInteger('tested_by')->nullable();
                $table->timestamp('tested_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // additive — không xóa dữ liệu.
    }
};
