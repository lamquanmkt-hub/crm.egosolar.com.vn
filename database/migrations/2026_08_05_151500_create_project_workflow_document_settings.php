<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_workflow_document_settings')) {
            return;
        }

        Schema::create('project_workflow_document_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 80);
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('step_code', 40);
            $table->string('document_code', 80);
            $table->string('label', 255);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('min_files')->default(1);
            $table->text('extensions')->nullable();
            $table->string('responsible_group', 100)->nullable();
            $table->string('conditional_key', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'step_code', 'document_code'], 'wf_docset_scope_step_code_uq');
            $table->index(['site_id', 'step_code', 'is_active'], 'wf_docset_site_step_active_idx');
            $table->index(['step_code', 'sort_order'], 'wf_docset_step_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflow_document_settings');
    }
};
