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
        Schema::create('crm_serial_identifiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 30)->default('serial');
            $table->string('code', 120);
            $table->timestamps();
            $table->unique(['code'], 'crm_serial_identifiers_code_uk');
            $table->index(['type', 'code'], 'crm_serial_identifiers_type_code_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_serial_identifiers');
    }
};
