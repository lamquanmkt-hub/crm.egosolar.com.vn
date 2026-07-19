<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Thêm vào các bảng có thể bạn đang dùng
        $tables = ['products', 'crm_products', 'inventory_products', 'crm_inventory_products'];

        foreach ($tables as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'vat_percent')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->decimal('vat_percent', 5, 2)->default(0)->after('price_retail');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['products', 'crm_products', 'inventory_products', 'crm_inventory_products'];

        foreach ($tables as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'vat_percent')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropColumn('vat_percent');
                });
            }
        }
    }
};
