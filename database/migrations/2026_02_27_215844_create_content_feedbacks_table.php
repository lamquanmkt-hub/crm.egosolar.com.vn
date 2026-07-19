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
    Schema::create('content_feedbacks', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('content_calendar_id');
        $table->unsignedBigInteger('user_id');

        $table->text('message');              // nội dung comment
        $table->string('image_path')->nullable(); // ảnh (nếu có)

        $table->timestamps();

        $table->index('content_calendar_id');
        $table->index('user_id');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_feedbacks');
    }
};
