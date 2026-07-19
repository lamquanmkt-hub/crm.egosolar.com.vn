<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_daily_kpis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');

            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('calls_answered')->default(0);
            $table->unsignedInteger('company_data_called')->default(0);
            $table->boolean('followed_up_all_previous')->default(false);

            $table->text('notes')->nullable();

            $table->unsignedInteger('missing_kpi_count')->default(0);
            $table->unsignedBigInteger('penalty_amount')->default(0);
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->string('status', 30)->default('incomplete');

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_daily_kpis');
    }
};