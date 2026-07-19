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
    Schema::table('crm_orders', function (Blueprint $table) {
        $table->string('shipping_carrier', 100)->nullable();
        $table->string('tracking_number', 100)->nullable();
        $table->string('receiver_name', 120)->nullable();
        $table->string('receiver_phone', 30)->nullable();
        $table->text('shipping_address')->nullable();
        $table->text('shipping_note')->nullable();
    });
}
    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('crm_orders', function (Blueprint $table) {
        $table->dropColumn([
            'shipping_carrier',
            'tracking_number',
            'receiver_name',
            'receiver_phone',
            'shipping_address',
            'shipping_note',
        ]);
    });
}
};
