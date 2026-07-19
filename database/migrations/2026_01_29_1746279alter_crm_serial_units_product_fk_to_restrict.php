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
        Schema::table('crm_serial_units', function (Blueprint $table) {
            // 1) Drop FK cũ (thường tên sẽ là crm_serial_units_product_id_foreign)
            $table->dropForeign(['product_id']);

            // 2) Tạo lại FK với RESTRICT
            $table->foreign('product_id')
                ->references('id')
                ->on('crm_product_catalog')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_serial_units', function (Blueprint $table) {
            // rollback: drop FK restrict
            $table->dropForeign(['product_id']);

            // tạo lại cascade như cũ
            $table->foreign('product_id')
                ->references('id')
                ->on('crm_product_catalog')
                ->cascadeOnDelete();
        });
    }
};
