<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_calculator_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('province')->nullable();
            $table->decimal('monthly_bill', 15, 2)->nullable();
            $table->decimal('monthly_kwh', 12, 2)->nullable();
            $table->decimal('recommended_kwp', 10, 2)->nullable();
            $table->decimal('investment_cost', 15, 2)->nullable();
            $table->decimal('payback_years', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_calculator_logs');
    }
};