<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('content_calendars', function (Blueprint $table) {
        $table->id();

        $table->date('publish_date');
        $table->string('platform');
        $table->string('content_type');
        $table->string('title');
        $table->text('description')->nullable();

        $table->unsignedBigInteger('campaign_id')->nullable();
        $table->unsignedBigInteger('created_by');

        $table->string('status')->default('draft');

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_calendars');
    }
};
