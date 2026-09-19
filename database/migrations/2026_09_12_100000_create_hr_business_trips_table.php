<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_business_trips')) {
            return;
        }

        Schema::create('hr_business_trips', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('location', 500);
            $table->text('purpose')->nullable();

            $table->decimal('daily_allowance', 15, 2)->default(0);
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->decimal('hotel_allowance', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('other_allowance', 15, 2)->default(0);
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->text('allowance_note')->nullable();

            $table->string('status', 30)->default('pending');
            $table->text('approval_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date'], 'hr_bt_user_start_idx');
            $table->index(['approver_id', 'status'], 'hr_bt_approver_status_idx');
            $table->index(['status', 'start_date', 'end_date'], 'hr_bt_status_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_business_trips');
    }
};
