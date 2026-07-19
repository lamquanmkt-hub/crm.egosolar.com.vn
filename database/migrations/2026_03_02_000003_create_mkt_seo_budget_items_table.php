<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_budget_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();

            $table->string('item', 255);                   // Hạng mục
            $table->string('cost_text', 120)->nullable();  // "5,000,000/năm", "Miễn phí", ...
            $table->decimal('cost_value', 18, 0)->nullable(); // nếu nhập số
            $table->string('link', 255)->nullable();
            $table->string('objective', 255)->nullable();
            $table->string('timeline', 120)->nullable();
            $table->string('kpi', 255)->nullable();
            $table->string('owner', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('mkt_plans')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_budget_items');
    }
};