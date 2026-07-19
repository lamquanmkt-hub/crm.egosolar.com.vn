<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_document_handovers')) {
            Schema::create('hr_document_handovers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->nullable()->index();
                $table->string('title', 255);
                $table->string('document_type', 120)->nullable();
                $table->string('customer_name', 190)->nullable();
                $table->string('department_name', 190)->nullable();
                $table->string('priority', 50)->default('normal')->index();
                $table->string('status', 50)->default('created')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('current_holder', 190)->nullable();
                $table->date('due_date')->nullable();
                $table->text('description')->nullable();
                $table->text('note')->nullable();
                $table->string('storage_location', 255)->nullable();

                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('archived_at')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_document_handover_histories')) {
            Schema::create('hr_document_handover_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('handover_id')->index();
                $table->string('status', 50)->nullable()->index();
                $table->string('action', 120)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_handover_histories');
        Schema::dropIfExists('hr_document_handovers');
    }
};
