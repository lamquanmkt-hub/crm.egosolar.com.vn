<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date')->index();

            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();

            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('work_minutes')->default(0);

            $table->string('status')->default('absent')->index();
            // absent | checked_in | completed | late | early_leave | incomplete

            $table->text('note')->nullable();

            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};