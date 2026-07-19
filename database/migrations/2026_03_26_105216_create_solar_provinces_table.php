<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('region')->nullable();
            $table->decimal('sun_hours', 5, 2)->default(4.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_provinces');
    }
};