<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solar_maintenance_schedules')) {
            Schema::create('solar_maintenance_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('site_name')->nullable();
                $table->string('address')->nullable();
                $table->string('type', 80)->index();
                $table->string('status', 80)->index()->default('scheduled');
                $table->string('priority', 50)->default('normal')->index();
                $table->date('scheduled_date')->index();
                $table->date('completed_date')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('assigned_name')->nullable();
                $table->decimal('system_kwp', 10, 2)->nullable();
                $table->string('inverter_info')->nullable();
                $table->text('issue_note')->nullable();
                $table->text('technical_note')->nullable();
                $table->text('result_note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_maintenance_schedules');
    }
};
