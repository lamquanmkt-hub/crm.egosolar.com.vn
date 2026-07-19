<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_kpi_pay_actuals', function (Blueprint $table) {
            $table->id();

            $table->string('period', 7); // YYYY-MM
            $table->unsignedBigInteger('user_id');

            $table->unsignedInteger('actual_post')->default(0);
            $table->unsignedInteger('actual_video_ai')->default(0);
            $table->unsignedInteger('actual_video_review')->default(0);

            $table->json('trend_videos')->nullable(); // list video
            $table->json('livestreams')->nullable();  // list live

            $table->boolean('is_fraud')->default(false);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['period', 'user_id']);
            $table->index(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_kpi_pay_actuals');
    }
};