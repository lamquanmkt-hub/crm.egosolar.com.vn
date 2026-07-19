<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_candidate_process_rounds')) {
            Schema::create('hr_candidate_process_rounds', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->integer('sort_order')->default(999);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_candidate_process_items')) {
            Schema::create('hr_candidate_process_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('round_id')->nullable();
                $table->string('candidate_name');
                $table->string('responsible_person')->nullable();
                $table->text('evaluation')->nullable();
                $table->string('result')->nullable();
                $table->timestamps();

                $table->index('round_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_candidate_process_items');
        Schema::dropIfExists('hr_candidate_process_rounds');
    }
};
