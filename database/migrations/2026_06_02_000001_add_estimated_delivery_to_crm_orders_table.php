<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_orders') && !Schema::hasColumn('crm_orders', 'estimated_delivery')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $column = $table->date('estimated_delivery')->nullable();

                if (Schema::hasColumn('crm_orders', 'tracking_number')) {
                    $column->after('tracking_number');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'estimated_delivery')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn('estimated_delivery');
            });
        }
    }
};
