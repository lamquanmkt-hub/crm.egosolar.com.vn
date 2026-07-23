<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('leave_requests', 'start_time')) {
                    $table->time('start_time')->nullable()->after('start_date');
                }

                if (! Schema::hasColumn('leave_requests', 'end_time')) {
                    $table->time('end_time')->nullable()->after('end_date');
                }
            });
        }

        if (! Schema::hasTable('leave_request_attachments')) {
            Schema::create('leave_request_attachments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('leave_request_id')->index();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('disk', 40)->default('local');
                $table->string('original_name');
                $table->string('file_path');
                $table->string('mime_type', 160)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leave_request_approval_logs')) {
            Schema::create('leave_request_approval_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('leave_request_id')->index();
                $table->string('action', 40)->index();
                $table->unsignedBigInteger('from_approver_id')->nullable()->index();
                $table->unsignedBigInteger('to_approver_id')->nullable()->index();
                $table->unsignedBigInteger('action_by')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['leave_request_id', 'created_at'], 'leave_approval_log_request_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approval_logs');
        Schema::dropIfExists('leave_request_attachments');

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $columns = [];

                if (Schema::hasColumn('leave_requests', 'start_time')) {
                    $columns[] = 'start_time';
                }

                if (Schema::hasColumn('leave_requests', 'end_time')) {
                    $columns[] = 'end_time';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
