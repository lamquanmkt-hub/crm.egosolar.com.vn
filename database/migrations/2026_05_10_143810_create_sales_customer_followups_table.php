<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_customer_followups')) {
            return;
        }

        Schema::create('sales_customer_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_work_report_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 50)->nullable()->index();
            $table->string('action_type', 40)->default('call');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('result', 80)->nullable();
            $table->dateTime('followup_at')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_phone', 'followup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_customer_followups');
    }
};
