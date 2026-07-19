<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('mkt_plans')->cascadeOnDelete();

            $table->string('channel_code'); // seo/facebook_ads/google_search/email...
            $table->string('type')->nullable(); // seo_content, ads_setup, landing_page...
            $table->string('title');

            $table->string('status')->default('todo'); // todo/doing/done/blocked
            $table->unsignedTinyInteger('priority')->default(2); // 1 high,2 normal,3 low
            $table->date('due_date')->nullable();

            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedSmallInteger('weight')->default(1); // for progress %
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['plan_id', 'channel_code']);
            $table->index(['plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_tasks');
    }
};