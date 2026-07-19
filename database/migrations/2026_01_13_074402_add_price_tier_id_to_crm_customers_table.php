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
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('price_tier_id')
                ->nullable()
                ->after('customer_type_id'); // chỉnh lại after(...) theo cột bạn đang có
            $table->index('price_tier_id', 'idx_crm_customers_price_tier_id');
            $table->foreign('price_tier_id', 'fk_crm_customers_price_tier_id')
                ->references('id')
                ->on('crm_price_tiers')
                ->nullOnDelete(); // nếu tier bị xoá -> set null cho khách
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropForeign('fk_crm_customers_price_tier_id');
            $table->dropIndex('idx_crm_customers_price_tier_id');
            $table->dropColumn('price_tier_id');
        });
    }
};
