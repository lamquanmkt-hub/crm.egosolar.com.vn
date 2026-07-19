<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_daily_kpi_post_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_daily_kpi_id')
                ->constrained('sales_daily_kpis')
                ->cascadeOnDelete();

            $table->text('post_url');
            $table->timestamps();

            $table->index('sales_daily_kpi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_daily_kpi_post_links');
    }
};