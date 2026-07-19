<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('mkt_plans')->cascadeOnDelete();

            $table->string('website');
            $table->unsignedInteger('target_traffic')->default(0);
            $table->unsignedInteger('target_keyword_top10')->default(0);
            $table->unsignedInteger('target_leads')->default(0);
            $table->unsignedBigInteger('target_revenue')->default(0);
            $table->unsignedBigInteger('budget')->default(0);

            $table->timestamps();

            $table->index(['plan_id', 'website']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_targets');
    }
};