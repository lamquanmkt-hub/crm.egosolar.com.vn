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
    Schema::create('order_returns', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')
      ->constrained('crm_orders')
      ->onDelete('cascade');
        $table->enum('type', ['return', 'exchange'])->default('return'); // trả / đổi
        $table->text('reason')->nullable();
        $table->enum('status', ['pending','approved','rejected','done'])->default('pending');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
