<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_phase2_kpi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();

            // content & onpage
            $table->integer('content_new')->default(0);
            $table->integer('content_update')->default(0);
            $table->integer('keyword_top20')->default(0);
            $table->integer('keyword_top10')->default(0);
            $table->integer('onpage_urls')->default(0);

            // internal link split
            $table->integer('internal_link_content')->default(0);
            $table->integer('internal_link_category')->default(0);
            $table->integer('internal_link_landing')->default(0);

            // anchor strategy (%)
            $table->decimal('anchor_brand_pct', 5, 2)->default(0);
            $table->decimal('anchor_partial_pct', 5, 2)->default(0);
            $table->decimal('anchor_exact_pct', 5, 2)->default(0);
            $table->decimal('anchor_url_pct', 5, 2)->default(0);

            // budget phase 2
            $table->double('budget_phase2')->default(0);

            $table->timestamps();

            // Optional FK nếu hệ thống bạn dùng (không bắt buộc)
            // $table->foreign('plan_id')->references('id')->on('mkt_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_phase2_kpi');
    }
};