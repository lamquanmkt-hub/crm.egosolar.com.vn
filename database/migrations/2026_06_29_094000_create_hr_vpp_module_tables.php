<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_vpp_requests')) {
            Schema::create('hr_vpp_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('department_name');
                $table->string('requester_name')->nullable();
                $table->string('requested_month', 20)->nullable();
                $table->text('purpose')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 50)->default('submitted')->index();

                $table->text('hr_note')->nullable();
                $table->unsignedBigInteger('hr_checked_by')->nullable();
                $table->timestamp('hr_checked_at')->nullable();

                $table->text('director_note')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->text('warehouse_note')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->timestamp('issued_at')->nullable();

                $table->string('receiver_name')->nullable();
                $table->text('received_note')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('received_at')->nullable();

                $table->text('completed_note')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_vpp_items')) {
            Schema::create('hr_vpp_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->string('item_name');
                $table->string('unit', 50)->nullable();
                $table->decimal('requested_qty', 12, 2)->default(0);
                $table->decimal('hr_qty', 12, 2)->nullable();
                $table->decimal('issued_qty', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_vpp_logs')) {
            Schema::create('hr_vpp_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->string('action')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vpp_logs');
        Schema::dropIfExists('hr_vpp_items');
        Schema::dropIfExists('hr_vpp_requests');
    }
};
