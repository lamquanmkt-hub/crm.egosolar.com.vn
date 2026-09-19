<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solar_maintenance_work_items')) {
            Schema::create('solar_maintenance_work_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('maintenance_schedule_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('assignee_id')->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->unsignedInteger('actual_minutes')->nullable();
                $table->text('result_note')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('solar_maintenance_comments')) {
            Schema::create('solar_maintenance_comments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('maintenance_schedule_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('comment_type', 30)->default('internal')->index();
                $table->text('body');
                $table->json('mentions')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('solar_maintenance_attachments')
            && ! Schema::hasColumn('solar_maintenance_attachments', 'maintenance_work_item_id')) {
            Schema::table('solar_maintenance_attachments', function (Blueprint $table): void {
                if (! Schema::hasColumn('solar_maintenance_attachments', 'maintenance_work_item_id')) {
                    $table->unsignedBigInteger('maintenance_work_item_id')
                        ->nullable()
                        ->after('maintenance_schedule_id')
                        ->index('sm_attachments_work_item_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_maintenance_attachments')
            && Schema::hasColumn('solar_maintenance_attachments', 'maintenance_work_item_id')) {
            Schema::table('solar_maintenance_attachments', function (Blueprint $table): void {
                $table->dropIndex('sm_attachments_work_item_index');
                $table->dropColumn('maintenance_work_item_id');
            });
        }

        Schema::dropIfExists('solar_maintenance_comments');
        Schema::dropIfExists('solar_maintenance_work_items');
    }
};
