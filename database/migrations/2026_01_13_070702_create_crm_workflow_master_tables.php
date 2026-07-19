<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Departments
        Schema::create('crm_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->integer('sort_order')->default(0)->index('idx_dept_sort');
            $table->boolean('is_active')->default(true)->index('idx_dept_active');
            $table->timestamps();
        });

        // 2) Map department -> role (spatie)
        Schema::create('crm_department_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id');
            $table->string('role_name', 100);

            $table->primary(['department_id', 'role_name'], 'pk_dept_role');

            $table->foreign('department_id', 'fk_dept_role_dept')
                ->references('id')->on('crm_departments')
                ->cascadeOnDelete();
        });

        // 3) Approval steps
        Schema::create('crm_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->unsignedBigInteger('department_id');
            $table->integer('step_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('department_id', 'fk_steps_dept')
                ->references('id')->on('crm_departments')
                ->cascadeOnDelete();

            $table->index(['department_id', 'step_order'], 'idx_steps_dept_order');
            $table->index(['is_active', 'step_order'], 'idx_steps_active_order');
        });

        // 4) Order workflows
        Schema::create('crm_order_workflows', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('from_department_id');
            $table->unsignedBigInteger('to_department_id');

            $table->unsignedBigInteger('next_status_type_id')->nullable();
            $table->boolean('requires_approval')->default(true);

            $table->unsignedBigInteger('approval_step_id')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique: from -> to
            $table->unique(['from_department_id', 'to_department_id'], 'uq_cow_from_to');

            // Index name ngắn để không vượt 64 ký tự
            $table->index(['from_department_id', 'is_active', 'sort_order'], 'idx_cow_from_active_sort');
            $table->index(['to_department_id', 'is_active'], 'idx_cow_to_active');
            $table->index(['next_status_type_id'], 'idx_cow_next_status');

            // Foreign keys (đặt tên ngắn)
            $table->foreign('from_department_id', 'fk_cow_from_dept')
                ->references('id')->on('crm_departments')
                ->cascadeOnDelete();

            $table->foreign('to_department_id', 'fk_cow_to_dept')
                ->references('id')->on('crm_departments')
                ->cascadeOnDelete();

            $table->foreign('next_status_type_id', 'fk_cow_next_status')
                ->references('id')->on('crm_order_status_types')
                ->nullOnDelete();

            $table->foreign('approval_step_id', 'fk_cow_step')
                ->references('id')->on('crm_approval_steps')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_workflows');
        Schema::dropIfExists('crm_approval_steps');
        Schema::dropIfExists('crm_department_roles');
        Schema::dropIfExists('crm_departments');
    }
};
