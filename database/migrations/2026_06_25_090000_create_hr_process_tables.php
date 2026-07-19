<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_process_periods')) {
            Schema::create('hr_process_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->integer('sort_order')->default(999);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_process_items')) {
            Schema::create('hr_process_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id')->nullable();
                $table->string('name');
                $table->string('responsible_person')->nullable();
                $table->text('evaluation')->nullable();
                $table->string('result')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('period_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_process_items');
        Schema::dropIfExists('hr_process_periods');
    }
};
