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

		    // Thông tin file
		    $table->string('file_name');
		    $table->string('original_name')->nullable();
		    $table->string('mime_type')->nullable();
		    $table->unsignedBigInteger('file_size')->nullable(); // bytes
		    $table->string('disk')->default('public'); // public | s3...

		    // Đường dẫn
		    $table->string('path');
		    $table->string('url')->nullable();

		    // Polymorphic: media có thể thuộc về bất kỳ model nào
		    $table->morphs('mediable'); // mediable_id, mediable_type

		    // Metadata thêm
		    $table->json('metadata')->nullable(); // ex: width, height, duration...

		    $table->timestamps();
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
