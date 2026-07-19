<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_request_id')
                ->constrained('payment_requests')
                ->cascadeOnDelete();

            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attachments');
    }
};
