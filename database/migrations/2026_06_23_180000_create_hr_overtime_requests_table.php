<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_overtime_requests')) {
            return;
        }

        Schema::create('hr_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->date('overtime_date')->index();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('hours', 6, 2)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'overtime_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_overtime_requests');
    }
};
