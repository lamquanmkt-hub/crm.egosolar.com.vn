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
        /**
         * 1) crm_price_tiers
         */
        Schema::create('crm_price_tiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique(); // retail, agent_1, agent_2...
            $table->string('name', 255);
            $table->unsignedInteger('priority')->default(0)->index(); // ưu tiên chọn giá mặc định
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        /**
         * 2) crm_product_prices
         */
        Schema::create('crm_product_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('price_tier_id');
            $table->decimal('price', 15, 2)->default(0);
            // Optional lịch sử hiệu lực
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
            $table->timestamps();
            // FK
            $table->foreign('product_id')
                ->references('id')
                ->on('crm_product_catalog')
                ->onDelete('cascade');
            $table->foreign('price_tier_id')
                ->references('id')
                ->on('crm_price_tiers')
                ->onDelete('cascade');
            // Index tối ưu truy vấn giá theo sản phẩm/tier
            $table->index(['product_id', 'price_tier_id'], 'idx_product_tier');
            /**
             * Unique option:
             * - Nếu cần lịch sử: unique (product_id, price_tier_id, effective_from)
             *   (Lưu ý MySQL cho phép nhiều NULL ở effective_from; nếu muốn strict "1 giá hiện tại",
             *    bạn nên luôn set effective_from hoặc dùng unique đơn giản bên dưới.)
             */
            $table->unique(['product_id', 'price_tier_id', 'effective_from'], 'uq_product_tier_from');
            // Nếu KHÔNG cần lịch sử (chỉ 1 giá/tier/sản phẩm) thì dùng cái này và bỏ unique ở trên:
            // $table->unique(['product_id', 'price_tier_id'], 'uq_product_tier');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('crm_product_prices');
        Schema::dropIfExists('crm_price_tiers');
    }
};
