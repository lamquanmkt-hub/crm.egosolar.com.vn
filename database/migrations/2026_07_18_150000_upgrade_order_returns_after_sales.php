<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_returns')) {
            Schema::create('order_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('type', 30)->default('return');
                $table->text('reason')->nullable();
                $table->string('status', 40)->default('draft');
                $table->timestamps();
            });
        }

        // Chuyển enum cũ sang VARCHAR để hỗ trợ workflow đầy đủ.
        try {
            DB::statement("ALTER TABLE `order_returns` MODIFY `type` VARCHAR(30) NOT NULL DEFAULT 'return'");
            DB::statement("ALTER TABLE `order_returns` MODIFY `status` VARCHAR(40) NOT NULL DEFAULT 'draft'");
        } catch (Throwable $e) {
            // Bảng mới hoặc DB không cần thay đổi kiểu.
        }

        Schema::table('order_returns', function (Blueprint $table) {
            $columns = [
                'return_code', 'company_id', 'customer_id', 'receiving_warehouse_id',
                'reason_code', 'reason_detail', 'requested_by', 'submitted_by',
                'approved_by', 'received_by', 'inspected_by', 'completed_by',
                'submitted_at', 'approved_at', 'received_at', 'inspected_at',
                'completed_at', 'total_return_amount', 'refund_amount',
                'restocking_fee', 'shipping_fee', 'refund_method', 'financial_status',
                'inventory_status', 'invoice_adjustment_status', 'note', 'metadata',
                'inventory_posted_at', 'stock_in_reference', 'deleted_at',
            ];

            if (!Schema::hasColumn('order_returns', 'return_code')) $table->string('return_code', 50)->nullable();
            if (!Schema::hasColumn('order_returns', 'company_id')) $table->unsignedBigInteger('company_id')->nullable();
            if (!Schema::hasColumn('order_returns', 'customer_id')) $table->unsignedBigInteger('customer_id')->nullable();
            if (!Schema::hasColumn('order_returns', 'receiving_warehouse_id')) $table->unsignedBigInteger('receiving_warehouse_id')->nullable();
            if (!Schema::hasColumn('order_returns', 'reason_code')) $table->string('reason_code', 50)->nullable();
            if (!Schema::hasColumn('order_returns', 'reason_detail')) $table->text('reason_detail')->nullable();
            if (!Schema::hasColumn('order_returns', 'requested_by')) $table->unsignedBigInteger('requested_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'submitted_by')) $table->unsignedBigInteger('submitted_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'approved_by')) $table->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'received_by')) $table->unsignedBigInteger('received_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'inspected_by')) $table->unsignedBigInteger('inspected_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'completed_by')) $table->unsignedBigInteger('completed_by')->nullable();
            if (!Schema::hasColumn('order_returns', 'submitted_at')) $table->dateTime('submitted_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'approved_at')) $table->dateTime('approved_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'received_at')) $table->dateTime('received_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'inspected_at')) $table->dateTime('inspected_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'completed_at')) $table->dateTime('completed_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'total_return_amount')) $table->decimal('total_return_amount', 18, 2)->default(0);
            if (!Schema::hasColumn('order_returns', 'refund_amount')) $table->decimal('refund_amount', 18, 2)->default(0);
            if (!Schema::hasColumn('order_returns', 'restocking_fee')) $table->decimal('restocking_fee', 18, 2)->default(0);
            if (!Schema::hasColumn('order_returns', 'shipping_fee')) $table->decimal('shipping_fee', 18, 2)->default(0);
            if (!Schema::hasColumn('order_returns', 'refund_method')) $table->string('refund_method', 30)->nullable();
            if (!Schema::hasColumn('order_returns', 'financial_status')) $table->string('financial_status', 40)->default('not_required');
            if (!Schema::hasColumn('order_returns', 'inventory_status')) $table->string('inventory_status', 40)->default('not_received');
            if (!Schema::hasColumn('order_returns', 'invoice_adjustment_status')) $table->string('invoice_adjustment_status', 40)->default('not_required');
            if (!Schema::hasColumn('order_returns', 'note')) $table->text('note')->nullable();
            if (!Schema::hasColumn('order_returns', 'metadata')) $table->json('metadata')->nullable();
            if (!Schema::hasColumn('order_returns', 'inventory_posted_at')) $table->dateTime('inventory_posted_at')->nullable();
            if (!Schema::hasColumn('order_returns', 'stock_in_reference')) $table->string('stock_in_reference', 80)->nullable();
            if (!Schema::hasColumn('order_returns', 'deleted_at')) $table->softDeletes();
        });

        $this->createIndexes();

        if (!Schema::hasTable('order_return_items')) {
            Schema::create('order_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_return_id');
                $table->unsignedBigInteger('order_item_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->integer('ordered_quantity')->default(0);
                $table->integer('requested_quantity')->default(0);
                $table->integer('received_quantity')->default(0);
                $table->integer('accepted_quantity')->default(0);
                $table->integer('rejected_quantity')->default(0);
                $table->integer('stock_posted_quantity')->default(0);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('vat_rate', 8, 2)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->decimal('return_amount', 18, 2)->default(0);
                $table->string('condition', 40)->nullable();
                $table->string('resolution', 40)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['order_return_id', 'order_item_id'], 'ori_return_item_unique');
                $table->index(['product_id', 'warehouse_id'], 'ori_product_warehouse_idx');
            });
        }

        if (!Schema::hasTable('order_return_serials')) {
            Schema::create('order_return_serials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_return_item_id');
                $table->unsignedBigInteger('serial_unit_id');
                $table->string('old_state', 40)->nullable();
                $table->string('inspected_state', 40)->nullable();
                $table->string('final_state', 40)->nullable();
                $table->unsignedBigInteger('inspected_by')->nullable();
                $table->dateTime('inspected_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['order_return_item_id', 'serial_unit_id'], 'ors_item_serial_unique');
                $table->index('serial_unit_id');
            });
        }

        if (!Schema::hasTable('order_return_attachments')) {
            Schema::create('order_return_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_return_id');
                $table->unsignedBigInteger('order_return_item_id')->nullable();
                $table->string('category', 40)->default('evidence');
                $table->string('file_path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['order_return_id', 'category'], 'ora_return_category_idx');
            });
        }

        if (!Schema::hasTable('order_return_approvals')) {
            Schema::create('order_return_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_return_id');
                $table->string('level', 40)->nullable();
                $table->string('action', 40);
                $table->string('status', 40)->default('pending');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
                $table->index(['order_return_id', 'status'], 'orap_return_status_idx');
            });
        }

        if (!Schema::hasTable('order_return_status_histories')) {
            Schema::create('order_return_status_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_return_id');
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->string('action', 50)->nullable();
                $table->text('reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['order_return_id', 'created_at'], 'orsh_return_created_idx');
            });
        }

        if (!Schema::hasTable('order_refunds')) {
            Schema::create('order_refunds', function (Blueprint $table) {
                $table->id();
                $table->string('refund_code', 50)->unique();
                $table->unsignedBigInteger('order_return_id');
                $table->unsignedBigInteger('order_id');
                $table->decimal('amount', 18, 2);
                $table->string('method', 30);
                $table->string('status', 40)->default('draft');
                $table->json('bank_information')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->dateTime('requested_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['order_return_id', 'status'], 'orf_return_status_idx');
                $table->index(['order_id', 'status'], 'orf_order_status_idx');
            });
        }
    }

    private function createIndexes(): void
    {
        $statements = [
            "CREATE UNIQUE INDEX order_returns_return_code_unique ON order_returns(return_code)",
            "CREATE UNIQUE INDEX order_returns_stock_ref_unique ON order_returns(stock_in_reference)",
            "CREATE INDEX order_returns_order_status_idx ON order_returns(order_id, status)",
            "CREATE INDEX order_returns_company_status_idx ON order_returns(company_id, status)",
        ];

        foreach ($statements as $statement) {
            try { DB::statement($statement); } catch (Throwable $e) { /* đã tồn tại */ }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_return_status_histories');
        Schema::dropIfExists('order_return_approvals');
        Schema::dropIfExists('order_return_attachments');
        Schema::dropIfExists('order_return_serials');
        Schema::dropIfExists('order_return_items');

        // Giữ order_returns để tránh mất dữ liệu nghiệp vụ cũ.
    }
};
