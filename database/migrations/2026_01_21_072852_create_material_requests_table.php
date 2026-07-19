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
        Schema::create('material_requests', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('site_id');       // công trình
    $table->unsignedBigInteger('created_by');    // user tạo
    $table->string('status')->default('DRAFT');  // DRAFT/SUBMITTED/...
    $table->text('note')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requests');
    }
};
