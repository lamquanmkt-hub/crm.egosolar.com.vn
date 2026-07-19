<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('request_type')->default('leave')->index();
            // leave | wfh

            $table->string('leave_type')->nullable()->index();
            // annual | unpaid | sick | personal | wfh

            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('days', 5, 2)->default(1);

            $table->text('reason')->nullable();

            $table->string('status')->default('pending')->index();
            // pending | approved | rejected | cancelled

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};