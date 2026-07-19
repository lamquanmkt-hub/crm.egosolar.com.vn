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
       Schema::create('content_files', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('content_calendar_id');
    $table->string('file_path');
    $table->string('file_name');
    $table->string('file_type')->nullable();
    $table->unsignedBigInteger('uploaded_by');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_files');
    }
};
