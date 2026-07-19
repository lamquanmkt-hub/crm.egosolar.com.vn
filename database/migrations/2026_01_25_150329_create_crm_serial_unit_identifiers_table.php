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
        Schema::create('crm_serial_unit_identifiers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('serial_unit_id')
                ->constrained('crm_serial_units')
                ->cascadeOnDelete();

            $table->foreignId('serial_identifier_id')
                ->constrained('crm_serial_identifiers')
                ->cascadeOnDelete();

            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->unique(['serial_unit_id', 'serial_identifier_id'], 'crm_sui_unit_identifier_uk');
            $table->index(['serial_identifier_id'], 'crm_sui_identifier_id_idx');
            $table->index(['serial_unit_id', 'is_primary'], 'crm_sui_unit_primary_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_serial_unit_identifiers');
    }
};
