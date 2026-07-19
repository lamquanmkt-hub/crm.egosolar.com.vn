<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_region_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('irradiation_min', 5, 2)->nullable(); // kWh/m2/day
            $table->decimal('irradiation_max', 5, 2)->nullable(); // kWh/m2/day
            $table->decimal('irradiation_default', 5, 2)->nullable(); // midpoint
            $table->integer('sun_hours_min')->nullable();
            $table->integer('sun_hours_max')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_region_profiles');
    }
};