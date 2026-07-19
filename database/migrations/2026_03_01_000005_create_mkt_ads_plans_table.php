<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_ads_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('mkt_plans')->cascadeOnDelete();

            $table->string('platform'); // facebook/google/tiktok
            $table->string('campaign_name');

            $table->unsignedBigInteger('budget')->default(0);
            $table->unsignedInteger('target_leads')->nullable();
            $table->unsignedBigInteger('target_cpl')->nullable();
            $table->decimal('target_roas', 10, 2)->nullable();

            $table->string('status')->default('planned'); // planned/running/paused/done
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['plan_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_ads_plans');
    }
};