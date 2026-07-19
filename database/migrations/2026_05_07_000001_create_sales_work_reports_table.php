<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_work_reports')) {
            return;
        }

        Schema::create('sales_work_reports', function (Blueprint $table) {
            $table->id();

            // Để dành phase sau liên kết sang khách hàng / lead.
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('lead_id')->nullable()->index();

            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('data_source_id')->nullable()->index();

            $table->string('customer_name');
            $table->string('customer_phone', 50)->nullable()->index();
            $table->string('customer_email')->nullable();
            $table->string('customer_company')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('facebook_name')->nullable();
            $table->string('facebook_link', 511)->nullable();
            $table->string('zalo_id')->nullable();
            $table->string('customer_type', 30)->default('personal');
            $table->string('region_text')->nullable();

            $table->dateTime('data_received_at')->nullable();
            $table->dateTime('first_call_at')->nullable();
            $table->dateTime('last_contact_at')->nullable();
            $table->string('contact_channel', 30)->default('call');

            $table->text('customer_need')->nullable();
            $table->decimal('system_size_kw', 10, 2)->nullable();
            $table->string('budget_range')->nullable();
            $table->string('project_timeline')->nullable();
            $table->text('consultation_summary')->nullable();
            $table->text('quoted_products')->nullable();
            $table->text('customer_feedback')->nullable();

            $table->string('status', 30)->default('new')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('outcome', 30)->nullable();
            $table->dateTime('next_followup_at')->nullable()->index();
            $table->string('next_action')->nullable();
            $table->decimal('revenue_expectation', 18, 2)->nullable();
            $table->text('lost_reason')->nullable();

            $table->json('proof_links')->nullable();
            $table->text('manager_note')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['data_received_at', 'next_followup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_work_reports');
    }
};
