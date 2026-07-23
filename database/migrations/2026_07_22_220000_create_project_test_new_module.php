<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_test_projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->unsignedBigInteger('sales_user_id')->nullable()->index();
            $table->unsignedBigInteger('technical_manager_id')->nullable()->index();
            $table->unsignedBigInteger('lead_technician_id')->nullable()->index();
            $table->string('name');
            $table->string('address', 700)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->text('customer_need')->nullable();
            $table->string('system_type', 100)->nullable();
            $table->decimal('estimated_kwp', 12, 2)->nullable();
            $table->string('priority', 30)->default('normal');
            $table->string('status', 60)->default('survey_pending')->index();
            $table->string('current_owner_role', 60)->default('technical')->index();
            $table->timestamp('proposed_survey_at')->nullable()->index();
            $table->timestamp('survey_confirmed_at')->nullable();
            $table->timestamp('proposed_installation_at')->nullable()->index();
            $table->timestamp('installation_confirmed_at')->nullable();
            $table->date('target_completion_at')->nullable()->index();
            $table->unsignedTinyInteger('progress')->default(5);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_test_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->unsignedBigInteger('surveyed_by')->nullable()->index();
            $table->string('schedule_status', 40)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->text('site_condition')->nullable();
            $table->text('technical_notes')->nullable();
            $table->string('design_3d_file', 700)->nullable();
            $table->string('attachment_file', 700)->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->index();
            $table->decimal('proposed_kwp', 12, 2)->nullable();
            $table->text('solution_summary')->nullable();
            $table->longText('preliminary_materials_json')->nullable();
            $table->string('sales_decision', 40)->default('pending');
            $table->text('sales_feedback')->nullable();
            $table->timestamp('sales_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_material_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->unsignedBigInteger('requested_by')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->unsignedBigInteger('issued_by')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->date('needed_at')->nullable();
            $table->string('status', 50)->default('pending_admin')->index();
            $table->text('request_note')->nullable();
            $table->text('review_note')->nullable();
            $table->text('issue_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('project_test_material_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('item_name');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 40)->default('cái');
            $table->decimal('issued_quantity', 14, 3)->default(0);
            $table->text('serials')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('assigned_by')->index();
            $table->string('assignment_role', 60)->default('member');
            $table->date('work_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_test_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->index();
            $table->date('log_date')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 40)->default('working');
            $table->text('content');
            $table->string('attachment_file', 700)->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('submitted_by')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->date('accepted_at');
            $table->longText('checklist_json')->nullable();
            $table->text('device_serials')->nullable();
            $table->string('monitoring_link', 700)->nullable();
            $table->string('monitoring_account')->nullable();
            $table->string('report_file', 700)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 40)->default('approved');
            $table->timestamps();
        });

        Schema::create('project_test_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('next_maintenance_at')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('project_test_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('project_test_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 100);
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60)->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_test_histories');
        Schema::dropIfExists('project_test_warranties');
        Schema::dropIfExists('project_test_acceptances');
        Schema::dropIfExists('project_test_daily_logs');
        Schema::dropIfExists('project_test_assignments');
        Schema::dropIfExists('project_test_material_items');
        Schema::dropIfExists('project_test_material_requests');
        Schema::dropIfExists('project_test_proposals');
        Schema::dropIfExists('project_test_surveys');
        Schema::dropIfExists('project_test_projects');
    }
};
