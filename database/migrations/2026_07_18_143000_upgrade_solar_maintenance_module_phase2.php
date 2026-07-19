<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        $this->addScheduleApprovalColumns();
        $this->upgradeAssigneesTable();
        $this->createApprovalsTable();
        $this->createAttachmentsTable();
        $this->createSiteDocumentsTable();
        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_site_documents');
        Schema::dropIfExists('solar_maintenance_attachments');
        Schema::dropIfExists('solar_maintenance_approvals');

        if (Schema::hasTable('solar_maintenance_assignees')) {
            foreach (['assignment_role', 'is_leader'] as $column) {
                if (Schema::hasColumn('solar_maintenance_assignees', $column)) {
                    Schema::table('solar_maintenance_assignees', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (!Schema::hasTable('solar_maintenance_schedules')) {
            return;
        }

        foreach ([
            'approval_status',
            'submitted_at',
            'submitted_by',
            'approved_at',
            'approved_by',
            'revision_requested_at',
            'revision_requested_by',
            'approval_note',
        ] as $column) {
            if (Schema::hasColumn('solar_maintenance_schedules', $column)) {
                Schema::table('solar_maintenance_schedules', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function addScheduleApprovalColumns(): void
    {
        $definitions = [
            'approval_status' => fn (Blueprint $table) => $table->string('approval_status', 40)->default('not_submitted')->index()->after('status'),
            'submitted_at' => fn (Blueprint $table) => $table->timestamp('submitted_at')->nullable()->after('approval_status'),
            'submitted_by' => fn (Blueprint $table) => $table->unsignedBigInteger('submitted_by')->nullable()->index()->after('submitted_at'),
            'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable()->after('submitted_by'),
            'approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('approved_by')->nullable()->index()->after('approved_at'),
            'revision_requested_at' => fn (Blueprint $table) => $table->timestamp('revision_requested_at')->nullable()->after('approved_by'),
            'revision_requested_by' => fn (Blueprint $table) => $table->unsignedBigInteger('revision_requested_by')->nullable()->index()->after('revision_requested_at'),
            'approval_note' => fn (Blueprint $table) => $table->text('approval_note')->nullable()->after('revision_requested_by'),
        ];

        foreach ($definitions as $column => $definition) {
            if (!Schema::hasColumn('solar_maintenance_schedules', $column)) {
                Schema::table('solar_maintenance_schedules', $definition);
            }
        }
    }

    private function upgradeAssigneesTable(): void
    {
        if (!Schema::hasTable('solar_maintenance_assignees')) {
            return;
        }

        if (!Schema::hasColumn('solar_maintenance_assignees', 'assignment_role')) {
            Schema::table('solar_maintenance_assignees', function (Blueprint $table) {
                $table->string('assignment_role', 30)->default('member')->index()->after('role');
            });
        }

        if (!Schema::hasColumn('solar_maintenance_assignees', 'is_leader')) {
            Schema::table('solar_maintenance_assignees', function (Blueprint $table) {
                $table->boolean('is_leader')->default(false)->index()->after('assignment_role');
            });
        }
    }

    private function createApprovalsTable(): void
    {
        if (Schema::hasTable('solar_maintenance_approvals')) {
            return;
        }

        Schema::create('solar_maintenance_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_schedule_id')->index();
            $table->string('approval_level', 40)->default('technical_manager')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('submitted_by')->nullable()->index();
            $table->string('action', 40)->index();
            $table->string('status', 40)->index();
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function createAttachmentsTable(): void
    {
        if (Schema::hasTable('solar_maintenance_attachments')) {
            return;
        }

        Schema::create('solar_maintenance_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_schedule_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('category', 50)->default('other')->index();
            $table->string('disk', 40)->default('local');
            $table->string('file_name', 255);
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->boolean('is_customer_visible')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createSiteDocumentsTable(): void
    {
        if (Schema::hasTable('solar_site_documents')) {
            return;
        }

        Schema::create('solar_site_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('category', 50)->default('other')->index();
            $table->string('disk', 40)->default('local');
            $table->string('file_name', 255);
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function backfill(): void
    {
        if (Schema::hasTable('solar_maintenance_assignees')) {
            DB::table('solar_maintenance_assignees')
                ->whereNull('assignment_role')
                ->orWhere('assignment_role', '')
                ->update(['assignment_role' => DB::raw('COALESCE(NULLIF(role, ""), "member")')]);

            DB::table('solar_maintenance_assignees')
                ->where(function ($query) {
                    $query->where('role', 'leader')->orWhere('assignment_role', 'leader');
                })
                ->update(['is_leader' => 1, 'assignment_role' => 'leader', 'role' => 'leader']);
        }

        DB::table('solar_maintenance_schedules')
            ->where('status', 'pending_approval')
            ->update(['approval_status' => 'pending']);

        DB::table('solar_maintenance_schedules')
            ->whereIn('status', ['completed', 'approved'])
            ->where('approval_status', 'not_submitted')
            ->update(['approval_status' => 'approved']);
    }
};
