<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndexOnColumn(string $table, string $column): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column]
        );

        return ! empty($rows);
    }

    public function up(): void
    {
        if (! Schema::hasTable('attendance_correction_requests')) {
            Schema::create('attendance_correction_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_record_id');
                $table->unsignedBigInteger('user_id');
                $table->date('work_date');
                $table->dateTime('original_check_in_at')->nullable();
                $table->dateTime('original_check_out_at')->nullable();
                $table->dateTime('requested_check_in_at')->nullable();
                $table->dateTime('requested_check_out_at')->nullable();
                $table->dateTime('approved_check_in_at')->nullable();
                $table->dateTime('approved_check_out_at')->nullable();
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('pending_guard')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->text('review_note')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->json('before_apply_snapshot')->nullable();
                $table->json('applied_snapshot')->nullable();
                $table->timestamps();

                $table->index('attendance_record_id', 'acr_req_record_idx');
                $table->index('user_id', 'acr_req_user_idx');
                $table->index('work_date', 'acr_req_date_idx');
                $table->index('status', 'acr_req_status_idx');
                $table->index('reviewed_by', 'acr_req_reviewer_idx');
                // MySQL allows multiple NULL values in UNIQUE; pending_guard=1 protects one pending request per record.
                $table->unique(['attendance_record_id', 'pending_guard'], 'attendance_correction_one_pending_unique');
            });
        }

        if (! Schema::hasTable('attendance_correction_attachments')) {
            Schema::create('attendance_correction_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_correction_request_id');
                $table->unsignedBigInteger('uploaded_by');
                $table->string('disk', 40)->default('local');
                $table->string('original_name');
                $table->string('file_path', 1000);
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->timestamps();

                // Explicit short names avoid MySQL 64-character identifier limit.
                $table->index('attendance_correction_request_id', 'acr_att_req_idx');
                $table->index('uploaded_by', 'acr_att_uploader_idx');
            });
        }

        // Recovery for V1 partial migration: tables may already exist while the long auto-generated
        // attachment index failed. Add only missing indexes, using short names.
        if (Schema::hasTable('attendance_correction_requests')) {
            $indexes = [
                'attendance_record_id' => 'acr_req_record_idx',
                'user_id' => 'acr_req_user_idx',
                'work_date' => 'acr_req_date_idx',
                'status' => 'acr_req_status_idx',
                'reviewed_by' => 'acr_req_reviewer_idx',
            ];
            foreach ($indexes as $column => $name) {
                if (Schema::hasColumn('attendance_correction_requests', $column) && ! $this->hasIndexOnColumn('attendance_correction_requests', $column)) {
                    Schema::table('attendance_correction_requests', fn (Blueprint $table) => $table->index($column, $name));
                }
            }
        }

        if (Schema::hasTable('attendance_correction_attachments')) {
            $indexes = [
                'attendance_correction_request_id' => 'acr_att_req_idx',
                'uploaded_by' => 'acr_att_uploader_idx',
            ];
            foreach ($indexes as $column => $name) {
                if (Schema::hasColumn('attendance_correction_attachments', $column) && ! $this->hasIndexOnColumn('attendance_correction_attachments', $column)) {
                    Schema::table('attendance_correction_attachments', fn (Blueprint $table) => $table->index($column, $name));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_attachments');
        Schema::dropIfExists('attendance_correction_requests');
    }
};
