<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('channel', 20)->index(); // seo | ads | email
            $table->string('title', 255);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo')->index(); // todo|doing|blocked|done
            $table->unsignedTinyInteger('weight')->default(1); // 1-10
            $table->boolean('is_auto')->default(true)->index(); // auto generated?
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_plan_tasks');
    }
};