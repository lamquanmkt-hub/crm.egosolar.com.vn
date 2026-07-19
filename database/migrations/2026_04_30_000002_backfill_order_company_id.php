<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_orders') || !Schema::hasTable('crm_warehouses')) {
            return;
        }

        DB::table('crm_orders')
            ->whereNull('company_id')
            ->orderBy('id')
            ->select(['id', 'warehouse_id'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $companyId = null;

                    if (!empty($order->warehouse_id)) {
                        $companyId = DB::table('crm_warehouses')
                            ->where('id', $order->warehouse_id)
                            ->value('company_id');
                    }

                    if (empty($companyId) && Schema::hasTable('crm_order_items')) {
                        $itemWarehouseId = DB::table('crm_order_items')
                            ->where('order_id', $order->id)
                            ->whereNotNull('warehouse_id')
                            ->value('warehouse_id');

                        if (!empty($itemWarehouseId)) {
                            $companyId = DB::table('crm_warehouses')
                                ->where('id', $itemWarehouseId)
                                ->value('company_id');
                        }
                    }

                    if (!empty($companyId)) {
                        DB::table('crm_orders')
                            ->where('id', $order->id)
                            ->update(['company_id' => $companyId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Không xoá company_id của đơn cũ khi rollback để tránh mất dữ liệu.
    }
};
