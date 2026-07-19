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
        Schema::create('media_files', function (Blueprint $table) {
	        $table->id();
	        $table->string('file_name', 255);    // hashed filename
	        $table->string('file_path', 500);    // storage path
	        $table->string('mime_type', 100)->nullable();
	        $table->unsignedBigInteger('file_size')->nullable();
	        $table->string('alt_text', 255)->nullable();
	        $table->string('title', 255)->nullable();
	        $table->string('caption', 500)->nullable();
	        $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
	        $table->timestamps();
	        $table->index(['uploaded_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
