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
        Schema::create('material_request_items', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('material_request_id');
    $table->unsignedBigInteger('product_id'); // sản phẩm
    $table->decimal('qty', 12, 2)->default(0);
    $table->text('note')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_request_items');
    }
};
