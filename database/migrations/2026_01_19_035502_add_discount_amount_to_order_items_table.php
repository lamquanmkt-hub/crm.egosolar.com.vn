<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Nếu bảng không tồn tại thì dừng và báo rõ
      if (!Schema::hasTable('crm_order_items')) {
    throw new RuntimeException("Table 'crm_order_items' not found. Check your real 
table name.");
}

if (!Schema::hasColumn('crm_order_items', 'discount_amount')) {
    Schema::table('crm_order_items', function (Blueprint $table) {
        $table->decimal('discount_amount', 15, 2)->default(0);
    });
}
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'discount_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
