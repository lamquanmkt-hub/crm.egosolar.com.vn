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
        Schema::table('crm_product_catalog', function (Blueprint $table) {
            $table->boolean('is_serialized')->default(false)->after('sku');
            $table->index(['is_serialized'], 'crm_product_catalog_is_serialized_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_product_catalog', function (Blueprint $table) {
            $table->dropIndex('crm_product_catalog_is_serialized_idx');
            $table->dropColumn('is_serialized');
        });
    }
};
