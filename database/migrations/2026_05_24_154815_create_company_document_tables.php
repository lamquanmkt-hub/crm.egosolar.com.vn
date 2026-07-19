<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_document_folders')) {
            Schema::create('company_document_folders', function (Blueprint $table) {
                $table->id();
                $table->string('department', 50)->index();
                $table->foreignId('parent_id')->nullable()->constrained('company_document_folders')->nullOnDelete();
                $table->string('name');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('company_document_files')) {
            Schema::create('company_document_files', function (Blueprint $table) {
                $table->id();
                $table->string('department', 50)->index();
                $table->foreignId('folder_id')->nullable()->constrained('company_document_folders')->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('original_name');
                $table->string('path');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_files');
        Schema::dropIfExists('company_document_folders');
    }
};
