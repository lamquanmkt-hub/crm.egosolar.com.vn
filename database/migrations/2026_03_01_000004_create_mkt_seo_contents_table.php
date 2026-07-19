<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('mkt_plans')->cascadeOnDelete();

            $table->string('website');
            $table->unsignedTinyInteger('week_no')->default(1);

            $table->string('topic');
            $table->string('main_keyword')->nullable();
            $table->string('landing_page')->nullable();

            $table->date('publish_date')->nullable();
            $table->string('status')->default('planned'); // planned/writing/review/approved/published

            $table->unsignedBigInteger('owner_id')->nullable(); // user id
            $table->timestamps();

            $table->index(['plan_id', 'website']);
            $table->index(['plan_id', 'week_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_contents');
    }
};