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
        Schema::create('crm_order_item_serial_units', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_item_id')
                ->constrained('crm_order_items')
                ->cascadeOnDelete();
            $table->foreignId('serial_unit_id')
                ->constrained('crm_serial_units')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['order_item_id', 'serial_unit_id'], 'crm_oisu_item_unit_uk');
            $table->unique(['serial_unit_id'], 'crm_oisu_unit_uk'); // chống bán trùng
            $table->index(['order_item_id'], 'crm_oisu_item_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_order_item_serial_units');
    }
};
