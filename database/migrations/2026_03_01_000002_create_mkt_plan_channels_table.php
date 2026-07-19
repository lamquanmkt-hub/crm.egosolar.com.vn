<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_plan_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('mkt_plans')->cascadeOnDelete();

            $table->string('channel_code'); // seo, facebook_ads, google_search, google_gdn, tiktok_ads, email...
            $table->string('channel_name')->nullable(); // label UI

            $table->unsignedBigInteger('budget')->default(0);

            $table->unsignedInteger('target_leads')->nullable();
            $table->unsignedBigInteger('target_revenue')->nullable();
            $table->unsignedBigInteger('target_cpl')->nullable();
            $table->decimal('target_roas', 10, 2)->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['plan_id', 'channel_code']);
            $table->index('channel_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_plan_channels');
    }
};