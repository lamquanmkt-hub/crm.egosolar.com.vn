<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_warehouses')) {
            Schema::table('crm_warehouses', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_warehouses', 'warehouse_type')) {
                    $table->string('warehouse_type', 40)->default('standard')->index('cw_type_idx');
                }
                if (! Schema::hasColumn('crm_warehouses', 'is_sales_selectable')) {
                    $table->boolean('is_sales_selectable')->default(true)->index('cw_sales_idx');
                }
            });
        }

        if (! Schema::hasTable('customer_consignments')) {
            Schema::create('customer_consignments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique('cc_code_uq');
                $table->unsignedBigInteger('order_id')->nullable()->index('cc_order_idx');
                $table->unsignedBigInteger('company_id')->nullable()->index('cc_company_idx');
                $table->unsignedBigInteger('customer_id')->nullable()->index('cc_customer_idx');
                $table->unsignedBigInteger('sales_user_id')->nullable()->index('cc_sales_idx');
                $table->unsignedBigInteger('consignment_warehouse_id')->nullable()->index('cc_cwh_idx');
                $table->string('status', 40)->default('draft')->index('cc_status_idx');
                $table->date('consigned_at')->nullable()->index('cc_consigned_idx');
                $table->date('expires_at')->nullable()->index('cc_expires_idx');
                $table->string('storage_location', 255)->nullable();
                $table->text('terms_note')->nullable();
                $table->string('receiver_name', 150)->nullable();
                $table->string('receiver_phone', 50)->nullable();
                $table->text('shipping_address')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index('cc_created_by_idx');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_note')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('reject_reason')->nullable();
                $table->unsignedBigInteger('revision_requested_by')->nullable();
                $table->timestamp('revision_requested_at')->nullable();
                $table->text('revision_reason')->nullable();
                $table->unsignedBigInteger('warehouse_confirmed_by')->nullable();
                $table->timestamp('warehouse_confirmed_at')->nullable();
                $table->date('warehouse_issue_date')->nullable();
                $table->text('warehouse_issue_note')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status', 'expires_at'], 'cc_co_st_exp_idx');
            });
        }

        if (! Schema::hasTable('customer_consignment_items')) {
            Schema::create('customer_consignment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_id');
                $table->unsignedBigInteger('order_item_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('source_warehouse_id');
                $table->integer('quantity');
                $table->integer('issued_quantity')->default(0);
                $table->integer('returned_quantity')->default(0);
                $table->integer('sold_quantity')->default(0);
                $table->integer('released_quantity')->default(0);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->text('item_note')->nullable();
                $table->timestamps();
                $table->foreign('consignment_id', 'cci_consignment_fk')->references('id')->on('customer_consignments')->cascadeOnDelete();
                $table->foreign('product_id', 'cci_product_fk')->references('id')->on('crm_product_catalog')->restrictOnDelete();
                $table->foreign('source_warehouse_id', 'cci_wh_fk')->references('id')->on('crm_warehouses')->restrictOnDelete();
                $table->index(['consignment_id', 'product_id'], 'cci_con_product_idx');
            });
        }

        if (! Schema::hasTable('customer_consignment_serials')) {
            Schema::create('customer_consignment_serials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_item_id');
                $table->unsignedBigInteger('serial_unit_id');
                $table->string('status', 30)->default('reserved')->index('ccs_status_idx');
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->foreign('consignment_item_id', 'ccs_item_fk')->references('id')->on('customer_consignment_items')->cascadeOnDelete();
                $table->foreign('serial_unit_id', 'ccs_serial_fk')->references('id')->on('crm_serial_units')->restrictOnDelete();
                $table->unique('serial_unit_id', 'ccs_serial_uq');
                $table->index(['consignment_item_id', 'status'], 'ccs_item_status_idx');
            });
        }

        // Các bảng đợt giao cũ vẫn được giữ để tương thích dữ liệu v1.0.
        if (! Schema::hasTable('customer_consignment_releases')) {
            Schema::create('customer_consignment_releases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_id');
                $table->string('code', 60)->unique('ccr_code_uq');
                $table->string('status', 30)->default('draft')->index('ccr_status_idx');
                $table->date('delivery_date')->nullable()->index('ccr_delivery_idx');
                $table->unsignedInteger('warranty_months')->default(60);
                $table->string('receiver_name', 150)->nullable();
                $table->string('receiver_phone', 50)->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('shipping_carrier', 120)->nullable();
                $table->string('tracking_number', 120)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index('ccr_created_idx');
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();
                $table->foreign('consignment_id', 'ccr_consignment_fk')->references('id')->on('customer_consignments')->cascadeOnDelete();
                $table->index(['consignment_id', 'status'], 'ccr_con_status_idx');
            });
        }

        if (! Schema::hasTable('customer_consignment_release_items')) {
            Schema::create('customer_consignment_release_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('release_id');
                $table->unsignedBigInteger('consignment_item_id');
                $table->integer('quantity');
                $table->timestamps();
                $table->foreign('release_id', 'ccri_release_fk')->references('id')->on('customer_consignment_releases')->cascadeOnDelete();
                $table->foreign('consignment_item_id', 'ccri_item_fk')->references('id')->on('customer_consignment_items')->restrictOnDelete();
                $table->unique(['release_id', 'consignment_item_id'], 'ccri_rel_item_uq');
            });
        }

        if (! Schema::hasTable('customer_consignment_release_serials')) {
            Schema::create('customer_consignment_release_serials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('release_item_id');
                $table->unsignedBigInteger('consignment_serial_id');
                $table->timestamps();
                $table->foreign('release_item_id', 'ccrs_rel_item_fk')->references('id')->on('customer_consignment_release_items')->cascadeOnDelete();
                $table->foreign('consignment_serial_id', 'ccrs_con_serial_fk')->references('id')->on('customer_consignment_serials')->restrictOnDelete();
                $table->unique('consignment_serial_id', 'ccrs_con_serial_uq');
            });
        }

        if (! Schema::hasTable('customer_consignment_activities')) {
            Schema::create('customer_consignment_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_id');
                $table->unsignedBigInteger('release_id')->nullable()->index('cca_release_idx');
                $table->string('action', 80)->index('cca_action_idx');
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index('cca_user_idx');
                $table->timestamps();
                $table->foreign('consignment_id', 'cca_consignment_fk')->references('id')->on('customer_consignments')->cascadeOnDelete();
            });
        }

        $this->syncPermissions();
    }

    private function syncPermissions(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        if (! Schema::hasTable($permissionsTable) || ! Schema::hasTable($rolesTable) || ! Schema::hasTable($pivotTable)) {
            return;
        }

        $permissions = [
            'page.consignments', 'menu.consignments', 'consignments.view',
            'consignments.create', 'consignments.submit', 'consignments.approve',
            'consignments.review', 'consignments.warehouse_issue', 'consignments.cancel',
        ];
        foreach ($permissions as $name) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $all = $permissions;
        $matrix = [
            'admin' => $all,
            'management' => $all,
            'manager' => $all,
            'director' => $all,
            'giam_doc' => $all,
            'ceo' => $all,
            'sales_manager' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.create', 'consignments.submit'],
            'sales' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.create', 'consignments.submit'],
            'warehouse' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.warehouse_issue'],
            'kho' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.warehouse_issue'],
        ];
        $ids = DB::table($permissionsTable)->where('guard_name', 'web')->whereIn('name', $permissions)->pluck('id', 'name');
        foreach ($matrix as $roleName => $names) {
            $roleId = DB::table($rolesTable)->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($names as $name) {
                if ($permissionId = ($ids[$name] ?? null)) {
                    DB::table($pivotTable)->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        // Không tự động xóa dữ liệu nghiệp vụ ký gửi.
    }
};
