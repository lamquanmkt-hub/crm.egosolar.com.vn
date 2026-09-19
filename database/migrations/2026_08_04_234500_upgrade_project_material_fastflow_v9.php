<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_test_material_requests')) {
            Schema::table('project_test_material_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('project_test_material_requests', 'request_kind')) {
                    $table->string('request_kind', 30)->default('standard')->after('code')->index();
                }
                if (! Schema::hasColumn('project_test_material_requests', 'parent_request_id')) {
                    $table->unsignedBigInteger('parent_request_id')->nullable()->after('request_kind')->index();
                }
                if (! Schema::hasColumn('project_test_material_requests', 'aftercare_request_id')) {
                    $table->unsignedBigInteger('aftercare_request_id')->nullable()->after('parent_request_id')->index();
                }
            });
        }

        if (! Schema::hasTable('project_test_material_aftercare_requests')) {
            Schema::create('project_test_material_aftercare_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->index('project_id', 'pt_mar_project_idx');
                $table->unsignedBigInteger('material_request_id');
                $table->index('material_request_id', 'pt_mar_request_idx');
                $table->unsignedBigInteger('linked_material_request_id')->nullable();
                $table->index('linked_material_request_id', 'pt_mar_linked_req_idx');
                $table->string('code', 50);
                $table->unique('code', 'pt_mar_code_uq');
                $table->string('type', 30);
                $table->index('type', 'pt_mar_type_idx');
                $table->string('status', 30)->default('warehouse_review');
                $table->index('status', 'pt_mar_status_idx');
                $table->unsignedBigInteger('requested_by');
                $table->index('requested_by', 'pt_mar_requested_by_idx');
                $table->unsignedBigInteger('warehouse_reviewed_by')->nullable();
                $table->index('warehouse_reviewed_by', 'pt_mar_wh_review_by_idx');
                $table->unsignedBigInteger('manager_reviewed_by')->nullable();
                $table->index('manager_reviewed_by', 'pt_mar_mgr_review_by_idx');
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->index('processed_by', 'pt_mar_processed_by_idx');
                $table->text('technical_note');
                $table->text('warehouse_note')->nullable();
                $table->text('manager_note')->nullable();
                $table->string('return_disposition', 30)->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('warehouse_reviewed_at')->nullable();
                $table->timestamp('manager_reviewed_at')->nullable();
                $table->timestamp('return_processed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['material_request_id', 'status'], 'pt_mat_aftercare_parent_status_idx');
            });
        }

        if (! Schema::hasTable('project_test_material_aftercare_items')) {
            Schema::create('project_test_material_aftercare_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('aftercare_request_id');
                $table->index('aftercare_request_id', 'pt_mai_aftercare_idx');
                $table->unsignedBigInteger('material_item_id')->nullable();
                $table->index('material_item_id', 'pt_mai_material_item_idx');
                // product_id/warehouse_id: vật tư gốc đã xuất (dùng cho đổi/trả/thiếu).
                $table->unsignedBigInteger('product_id')->nullable();
                $table->index('product_id', 'pt_mai_product_idx');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->index('warehouse_id', 'pt_mai_warehouse_idx');
                // replacement_*: sản phẩm Kho đề xuất để cấp bù/đổi mới, giúp Admin thấy tồn và giá vốn trước khi duyệt.
                $table->unsignedBigInteger('replacement_product_id')->nullable();
                $table->index('replacement_product_id', 'pt_mai_repl_product_idx');
                $table->unsignedBigInteger('replacement_warehouse_id')->nullable();
                $table->index('replacement_warehouse_id', 'pt_mai_repl_warehouse_idx');
                $table->string('item_name');
                $table->string('desired_item_name')->nullable();
                $table->decimal('quantity', 14, 3)->default(1);
                $table->decimal('processed_quantity', 14, 3)->default(0);
                $table->string('unit', 40)->default('cái');
                $table->string('condition', 30)->nullable();
                $table->decimal('unit_cost_snapshot', 15, 2)->nullable();
                $table->decimal('stock_snapshot', 14, 3)->nullable();
                $table->decimal('replacement_unit_cost_snapshot', 15, 2)->nullable();
                $table->decimal('replacement_stock_snapshot', 14, 3)->nullable();
                $table->text('serials')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        // Cho phép chạy lại an toàn nếu lần cài trước dừng giữa chừng hoặc bảng đã được tạo bởi bản thử nghiệm.
        if (Schema::hasTable('project_test_material_aftercare_items')) {
            Schema::table('project_test_material_aftercare_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('project_test_material_aftercare_items', 'replacement_product_id')) {
                    $table->unsignedBigInteger('replacement_product_id')->nullable()->after('warehouse_id');
                    $table->index('replacement_product_id', 'pt_mai_repl_product_idx');
                }
                if (! Schema::hasColumn('project_test_material_aftercare_items', 'replacement_warehouse_id')) {
                    $table->unsignedBigInteger('replacement_warehouse_id')->nullable()->after('replacement_product_id');
                    $table->index('replacement_warehouse_id', 'pt_mai_repl_warehouse_idx');
                }
                if (! Schema::hasColumn('project_test_material_aftercare_items', 'replacement_unit_cost_snapshot')) {
                    $table->decimal('replacement_unit_cost_snapshot', 15, 2)->nullable()->after('stock_snapshot');
                }
                if (! Schema::hasColumn('project_test_material_aftercare_items', 'replacement_stock_snapshot')) {
                    $table->decimal('replacement_stock_snapshot', 14, 3)->nullable()->after('replacement_unit_cost_snapshot');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_test_material_aftercare_items');
        Schema::dropIfExists('project_test_material_aftercare_requests');

        if (Schema::hasTable('project_test_material_requests')) {
            Schema::table('project_test_material_requests', function (Blueprint $table): void {
                foreach (['aftercare_request_id', 'parent_request_id', 'request_kind'] as $column) {
                    if (Schema::hasColumn('project_test_material_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
