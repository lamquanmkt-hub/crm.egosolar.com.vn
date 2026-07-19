<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();

            $table->string('keyword', 255);
            $table->unsignedInteger('search_volume')->nullable();
            $table->string('group_name', 120)->nullable();
            $table->string('detail', 255)->nullable();

            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('mkt_plans')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_keywords');
    }
};