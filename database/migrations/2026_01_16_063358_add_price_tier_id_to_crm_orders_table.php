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
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('price_tier_id')
                ->nullable()
                ->after('order_code');
            $table->index('price_tier_id', 'idx_orders_price_tier');
            $table->foreign('price_tier_id', 'fk_orders_price_tier')
                ->references('id')->on('crm_price_tiers')
                ->nullOnDelete();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_price_tier');
            $table->dropIndex('idx_orders_price_tier');
            $table->dropColumn('price_tier_id');
        });
    }
};
