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
            $table->unsignedBigInteger('brand_id')
                ->nullable()
                ->after('category_id'); // chỉnh lại after(...) theo schema thực tế
            $table->index('brand_id', 'idx_crm_product_catalog_brand_id');
            $table->foreign('brand_id', 'fk_crm_product_catalog_brand_id')
                ->references('id')
                ->on('crm_brands')
                ->nullOnDelete(); // brand bị xoá => brand_id set null
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_product_catalog', function (Blueprint $table) {
            $table->dropForeign('fk_crm_product_catalog_brand_id');
            $table->dropIndex('idx_crm_product_catalog_brand_id');
            $table->dropColumn('brand_id');
        });
    }
};
