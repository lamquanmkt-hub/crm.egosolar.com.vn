<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_order_items', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('order_id');
                $table->index(['warehouse_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_order_items', 'warehouse_id')) {
                $table->dropIndex(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
        });
    }
};
