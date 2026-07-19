<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_plans', function (Blueprint $table) {
            $table->id();
            $table->date('month'); // YYYY-MM-01
            $table->string('name')->nullable();

            $table->unsignedBigInteger('total_budget')->default(0);

            $table->unsignedBigInteger('target_revenue')->default(0);
            $table->unsignedInteger('target_leads')->default(0);
            $table->decimal('target_roas', 10, 2)->default(0);
            $table->decimal('target_cr', 10, 2)->default(0);

            $table->string('status')->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('month');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_plans');
    }
};