<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add columns
        Schema::table('crm_product_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_product_stock', 'company_id')) {
                $table->unsignedBigInteger('company_id')->after('warehouse_id')->default(1)->index();
            }

            if (!Schema::hasColumn('crm_product_stock', 'last_updated')) {
                $table->timestamp('last_updated')->nullable()->after('qty');
            }
        });

        // 2) Backfill
        DB::table('crm_product_stock')->whereNull('company_id')->update(['company_id' => 1]);

        // 3) Drop unique cũ nếu tồn tại (product_id + warehouse_id)
        $oldIndexName = 'crm_product_stock_product_id_warehouse_id_unique';
        $hasOld = !empty(DB::select(
            "SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?",
            [$oldIndexName]
        ));

        if ($hasOld) {
            Schema::table('crm_product_stock', function (Blueprint $table) use ($oldIndexName) {
                $table->dropUnique($oldIndexName);
            });
        }

        // 4) Add unique mới nếu chưa có
        $newIndexName = 'crm_ps_product_company_warehouse_unique';
        $hasNew = !empty(DB::select(
            "SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?",
            [$newIndexName]
        ));

        if (!$hasNew) {
            Schema::table('crm_product_stock', function (Blueprint $table) use ($newIndexName) {
                $table->unique(['product_id', 'company_id', 'warehouse_id'], $newIndexName);
            });
        }
    }

    public function down(): void
    {
        $newIndexName = 'crm_ps_product_company_warehouse_unique';
        $hasNew = !empty(DB::select(
            "SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?",
            [$newIndexName]
        ));

        if ($hasNew) {
            Schema::table('crm_product_stock', function (Blueprint $table) use ($newIndexName) {
                $table->dropUnique($newIndexName);
            });
        }

        Schema::table('crm_product_stock', function (Blueprint $table) {
            if (Schema::hasColumn('crm_product_stock', 'company_id')) {
                $table->dropColumn('company_id');
            }

            // tuỳ bạn có muốn drop last_updated không
            // if (Schema::hasColumn('crm_product_stock', 'last_updated')) {
            //     $table->dropColumn('last_updated');
            // }
        });

        // (Optional) restore unique cũ nếu bạn muốn:
        // $oldIndexName = 'crm_product_stock_product_id_warehouse_id_unique';
        // $hasOld = !empty(DB::select("SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?", [$oldIndexName]));
        // if (!$hasOld) {
        //     Schema::table('crm_product_stock', function (Blueprint $table) use ($oldIndexName) {
        //         $table->unique(['product_id','warehouse_id'], $oldIndexName);
        //     });
        // }
    }
};
