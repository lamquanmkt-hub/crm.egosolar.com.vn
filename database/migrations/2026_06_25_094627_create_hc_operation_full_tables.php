<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_operation_expenses')) {
            Schema::create('hr_operation_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('category')->nullable();
                $table->string('content');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('supplier_name')->nullable();
                $table->string('person_in_charge')->nullable();
                $table->string('payment_status')->nullable();
                $table->date('expense_date')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('expense_date');
                $table->index('payment_status');
            });
        }

        if (!Schema::hasTable('hr_operation_assets')) {
            Schema::create('hr_operation_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_name');
                $table->string('asset_type')->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('value', 15, 2)->default(0);
                $table->string('assigned_to')->nullable();
                $table->string('status')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (!Schema::hasTable('hr_operation_suppliers')) {
            Schema::create('hr_operation_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_name');
                $table->string('contact_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('service')->nullable();
                $table->string('payment_status')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('payment_status');
            });
        }

        if (!Schema::hasTable('hr_operation_tasks')) {
            Schema::create('hr_operation_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('task_type')->nullable();
                $table->string('content');
                $table->string('department')->nullable();
                $table->string('priority')->default('normal');
                $table->date('deadline')->nullable();
                $table->string('status')->default('pending');
                $table->string('assignee')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('priority');
                $table->index('status');
                $table->index('deadline');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_operation_tasks');
        Schema::dropIfExists('hr_operation_suppliers');
        Schema::dropIfExists('hr_operation_assets');
        Schema::dropIfExists('hr_operation_expenses');
    }
};
