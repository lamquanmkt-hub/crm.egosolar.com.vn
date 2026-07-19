<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_channel_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_name'); // "Facebook", "Google Search", "TikTok", ...
            $table->string('channel_code'); // facebook_ads, google_search, tiktok_ads, seo, email...
            $table->timestamps();

            $table->unique('source_name');
            $table->index('channel_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_channel_map');
    }
};