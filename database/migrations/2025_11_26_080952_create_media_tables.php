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
	    // Bảng media lưu file
	    Schema::create('media_files', function (Blueprint $table) {
		    $table->id();
		    $table->string('file_name', 255);
		    $table->string('file_path', 500);
		    $table->string('mime_type', 100);
		    $table->unsignedBigInteger('file_size')->nullable();
		    $table->string('alt_text', 255)->nullable();
		    $table->string('title', 255)->nullable();
		    $table->string('caption', 500)->nullable();
		    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
	    // Bảng polymorphic để gán ảnh cho bất kỳ model nào
	    Schema::create('media_relations', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('media_id')->constrained('media_files')->cascadeOnDelete();
		    $table->morphs('model'); // model_id + model_type
		    $table->string('usage_type', 50)->nullable(); // main_image, gallery, avatar, attachment
		    $table->integer('sort_order')->default(0);
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('media_relations');
	    Schema::dropIfExists('media_files');
    }
};
