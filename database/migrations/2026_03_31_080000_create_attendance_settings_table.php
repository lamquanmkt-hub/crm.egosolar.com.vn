<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('work_start_time')->default('08:00:00');
            $table->time('work_end_time')->default('17:30:00');
            $table->integer('late_grace_minutes')->default(0);
            $table->integer('min_work_minutes')->default(480);
            $table->boolean('require_gps')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};