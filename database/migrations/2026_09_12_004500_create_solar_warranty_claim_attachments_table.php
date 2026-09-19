<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solar_warranty_claim_attachments')) {
            return;
        }

        Schema::create('solar_warranty_claim_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('warranty_claim_id')->index();
            $table->string('category', 40)->default('evidence')->index();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_warranty_claim_attachments');
    }
};
