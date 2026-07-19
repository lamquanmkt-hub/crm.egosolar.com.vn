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
        Schema::create('media_metadata', function (Blueprint $table) {
	        $table->id();
	        $table->foreignId('media_id')->constrained('media_files')->cascadeOnDelete();
	        // normalized metadata
	        $table->integer('width')->nullable();
	        $table->integer('height')->nullable();
	        $table->integer('duration')->nullable(); // seconds, for audio/video
	        $table->integer('bitrate')->nullable();
	        $table->json('metadata')->nullable(); // additional exif/json
	        $table->timestamps();
	        $table->index('media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_metadata');
    }
};
