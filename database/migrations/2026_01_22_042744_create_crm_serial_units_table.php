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
        Schema::create('crm_serial_units', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('product_id')
                ->constrained('crm_product_catalog')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'id'], 'crm_serial_units_product_id_id_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_serial_units');
    }
};
