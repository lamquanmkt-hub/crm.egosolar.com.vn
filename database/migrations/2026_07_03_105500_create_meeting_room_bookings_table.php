<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meeting_room_bookings')) {
            Schema::create('meeting_room_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('room_name', 120);
                $table->string('title');
                $table->string('organizer_name', 160)->nullable();
                $table->string('department', 160)->nullable();
                $table->unsignedInteger('attendees')->default(1);
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('status', 40)->default('pending');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['room_name', 'start_at', 'end_at'], 'mrb_room_time_idx');
                $table->index(['status', 'start_at'], 'mrb_status_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_bookings');
    }
};
