<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_warehouse', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id']);

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            // ✅ kho nằm trong crm_warehouses
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('crm_warehouses')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_warehouse');
    }
};
