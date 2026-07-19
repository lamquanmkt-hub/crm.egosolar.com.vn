<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_actual_kpi_daily', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('date')->index();

            // Phân loại nguồn
            $table->unsignedBigInteger('channel_id')->nullable()->index();   // map mkt_channel (optional)
            $table->unsignedBigInteger('platform_id')->nullable()->index();  // map ads_platform (optional)
            $table->unsignedBigInteger('website_id')->nullable()->index();   // map mkt_website (optional)

            // Campaign từ nền tảng / tên hiển thị
            $table->string('campaign_external_id', 100)->nullable()->index();
            $table->string('campaign_name', 255)->nullable()->index();

            // Metrics chính cho Ads
            $table->decimal('spend', 18, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedInteger('leads')->default(0);

            // Optional cho SEO / doanh thu
            $table->decimal('revenue', 18, 2)->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('conversions')->default(0);

            // manual/api
            $table->string('source_type', 20)->default('manual')->index();

            // payload raw (nếu muốn)
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // unique giúp chống trùng theo ngày + campaign (có thể nới lỏng sau)
            $table->unique(['date', 'platform_id', 'campaign_external_id'], 'uniq_mkt_actual_daily_platform_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_actual_kpi_daily');
    }
};