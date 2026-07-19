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
    Schema::create('site_planned_materials', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('site_id');
        $table->unsignedBigInteger('product_id')->nullable(); // vật tư (sản phẩm)
        $table->string('name')->nullable();   // fallback nếu không chọn product
        $table->string('unit')->nullable();   // đơn vị
        $table->integer('qty')->default(0);   // số lượng dự kiến
        $table->timestamps();

        $table->index(['site_id']);
        $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_planned_materials');
    }
};
