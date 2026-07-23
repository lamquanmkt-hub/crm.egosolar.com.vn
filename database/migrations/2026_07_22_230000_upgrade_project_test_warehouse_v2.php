<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_test_material_requests')) {
            Schema::table('project_test_material_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('project_test_material_requests', 'warehouse_status')) {
                    $table->string('warehouse_status', 40)->nullable()->after('status')->index();
                }
                if (! Schema::hasColumn('project_test_material_requests', 'receiver_id')) {
                    $table->unsignedBigInteger('receiver_id')->nullable()->after('issued_by')->index();
                }
                if (! Schema::hasColumn('project_test_material_requests', 'reserved_at')) {
                    $table->timestamp('reserved_at')->nullable()->after('reviewed_at');
                }
                if (! Schema::hasColumn('project_test_material_requests', 'handed_over_at')) {
                    $table->timestamp('handed_over_at')->nullable()->after('issued_at');
                }
            });
        }

        if (! Schema::hasTable('project_test_material_allocations')) {
            Schema::create('project_test_material_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_item_id')
                    ->constrained('project_test_material_items')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('allocated_quantity', 14, 3)->default(0);
                $table->decimal('reserved_quantity', 14, 3)->default(0);
                $table->decimal('issued_quantity', 14, 3)->default(0);
                $table->decimal('available_snapshot', 14, 3)->default(0);
                $table->boolean('is_serialized')->default(false);
                $table->json('selected_serial_unit_ids')->nullable();
                $table->text('selected_serial_codes')->nullable();
                $table->longText('selected_lots_json')->nullable();
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->string('status', 30)->default('matched')->index();
                $table->unsignedBigInteger('allocated_by')->nullable()->index();
                $table->unsignedBigInteger('reserved_by')->nullable()->index();
                $table->unsignedBigInteger('issued_by')->nullable()->index();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['warehouse_id', 'product_id', 'status'], 'pt_alloc_wh_product_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_test_material_allocations');

        if (Schema::hasTable('project_test_material_requests')) {
            Schema::table('project_test_material_requests', function (Blueprint $table) {
                foreach (['warehouse_status', 'receiver_id', 'reserved_at', 'handed_over_at'] as $column) {
                    if (Schema::hasColumn('project_test_material_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
