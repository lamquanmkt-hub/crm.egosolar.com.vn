<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_document_folders')) {
            Schema::create('hr_document_folders', function (Blueprint $table) {
                $table->id();
                $table->string('category', 50)->index();
                $table->string('name');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_document_files')) {
            Schema::create('hr_document_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('folder_id')->index();
                $table->string('category', 50)->index();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_files');
        Schema::dropIfExists('hr_document_folders');
    }
};
