<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_workflow_document_settings')
            && ! Schema::hasColumn('project_workflow_document_settings', 'max_files')) {
            Schema::table('project_workflow_document_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('max_files')->nullable()->after('min_files');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_workflow_document_settings')
            && Schema::hasColumn('project_workflow_document_settings', 'max_files')) {
            Schema::table('project_workflow_document_settings', function (Blueprint $table): void {
                $table->dropColumn('max_files');
            });
        }
    }
};
