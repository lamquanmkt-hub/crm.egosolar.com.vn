<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_recruitment_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 20);
            $table->unsignedBigInteger('record_id');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index(['entity', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_recruitment_attachments');
    }
};
