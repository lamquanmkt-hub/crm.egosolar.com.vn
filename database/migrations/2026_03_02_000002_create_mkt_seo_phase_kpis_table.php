<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_phase_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedTinyInteger('phase_no')->index(); // 1..4

            // Meta (6 tháng)
            $table->string('phase_title')->nullable();         // VD: Foundation, Content, Push, Optimize
            $table->string('month_range')->nullable();         // VD: M1-M2, M3-M4...
            $table->text('note')->nullable();

            // KPI / Workload (optional per phase)
            $table->integer('content_new')->default(0);
            $table->integer('content_update')->default(0);

            $table->integer('keyword_top20')->default(0);
            $table->integer('keyword_top10')->default(0);

            $table->integer('onpage_urls')->default(0);

            // Internal link split
            $table->integer('internal_link_content')->default(0);
            $table->integer('internal_link_category')->default(0);
            $table->integer('internal_link_landing')->default(0);

            // Anchor (%)
            $table->decimal('anchor_brand_pct', 5, 2)->default(0);
            $table->decimal('anchor_partial_pct', 5, 2)->default(0);
            $table->decimal('anchor_exact_pct', 5, 2)->default(0);
            $table->decimal('anchor_url_pct', 5, 2)->default(0);

            // Budget phase
            $table->double('budget')->default(0);

            $table->timestamps();

            $table->unique(['plan_id','phase_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_phase_kpis');
    }
};