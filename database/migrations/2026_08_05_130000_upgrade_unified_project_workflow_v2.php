<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeSites();
        $this->createWorkflowTables();
        $this->upgradeWorkflowStepDelayColumns();
        $this->upgradeMaterialWorkflow();
        $this->backfillExistingProjects();
    }

    private function upgradeSites(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'workflow_version')) {
                $table->string('workflow_version', 20)->nullable()->index('site_wf_version_idx');
            }
            if (! Schema::hasColumn('sites', 'workflow_current_step')) {
                $table->string('workflow_current_step', 40)->nullable()->index('site_wf_step_idx');
            }
            if (! Schema::hasColumn('sites', 'workflow_status')) {
                $table->string('workflow_status', 40)->default('active')->index('site_wf_status_idx');
            }
            if (! Schema::hasColumn('sites', 'workflow_progress_percent')) {
                $table->unsignedTinyInteger('workflow_progress_percent')->default(0)->index('site_wf_progress_idx');
            }
            if (! Schema::hasColumn('sites', 'contract_amount_before_vat')) {
                $table->decimal('contract_amount_before_vat', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('sites', 'vat_rate')) {
                $table->decimal('vat_rate', 8, 3)->nullable();
            }
            if (! Schema::hasColumn('sites', 'contract_amount_after_vat')) {
                $table->decimal('contract_amount_after_vat', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('sites', 'handover_at')) {
                $table->date('handover_at')->nullable()->index('site_handover_idx');
            }
            if (! Schema::hasColumn('sites', 'warranty_started_at')) {
                $table->date('warranty_started_at')->nullable()->index('site_warranty_start_idx');
            }
        });
    }

    private function createWorkflowTables(): void
    {
        if (! Schema::hasTable('project_workflow_steps')) {
            Schema::create('project_workflow_steps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('step_code', 40);
                $table->unsignedTinyInteger('sequence');
                $table->string('status', 40)->default('not_assigned');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->dateTime('recommitted_due_at')->nullable();
                $table->text('delay_reason')->nullable();
                $table->text('recovery_plan')->nullable();
                $table->string('risk_level', 30)->default('normal');
                $table->unsignedBigInteger('delay_updated_by')->nullable();
                $table->dateTime('delay_updated_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('requirement')->nullable();
                $table->text('result_summary')->nullable();
                $table->text('returned_reason')->nullable();
                $table->longText('data')->nullable();
                $table->timestamps();

                $table->unique(['site_id', 'step_code'], 'wf_step_site_code_uq');
                $table->index(['site_id', 'sequence'], 'wf_step_site_seq_idx');
                $table->index(['status', 'due_at'], 'wf_step_status_due_idx');
            });
        }

        if (! Schema::hasTable('project_workflow_assignments')) {
            Schema::create('project_workflow_assignments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workflow_step_id');
                $table->unsignedBigInteger('user_id');
                $table->string('assignment_role', 30)->default('collaborator');
                $table->string('status', 30)->default('assigned');
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->text('submission_summary')->nullable();
                $table->timestamps();

                $table->unique(['workflow_step_id', 'user_id'], 'wf_assign_step_user_uq');
                $table->index(['user_id', 'status', 'is_active'], 'wf_assign_user_status_idx');
            });
        }

        if (! Schema::hasTable('project_workflow_documents')) {
            Schema::create('project_workflow_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workflow_step_id');
                $table->unsignedBigInteger('site_id');
                $table->string('document_code', 80);
                $table->string('title');
                $table->string('path', 800);
                $table->string('original_name', 500);
                $table->string('mime_type', 160)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->unsignedBigInteger('replaced_document_id')->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamps();

                $table->index(['workflow_step_id', 'document_code'], 'wf_doc_step_code_idx');
                $table->index(['site_id', 'created_at'], 'wf_doc_site_date_idx');
            });
        }

        if (! Schema::hasTable('project_workflow_approvals')) {
            Schema::create('project_workflow_approvals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workflow_step_id');
                $table->string('approval_type', 40);
                $table->string('status', 30)->default('pending');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['workflow_step_id', 'approval_type'], 'wf_approval_step_type_uq');
                $table->index(['status', 'reviewed_at'], 'wf_approval_status_idx');
            });
        }

        if (! Schema::hasTable('project_workflow_events')) {
            Schema::create('project_workflow_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('workflow_step_id')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 100);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->text('note')->nullable();
                $table->longText('payload')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index(['site_id', 'created_at'], 'wf_event_site_date_idx');
                $table->index(['workflow_step_id', 'created_at'], 'wf_event_step_date_idx');
            });
        }

        if (! Schema::hasTable('project_workflow_exceptions')) {
            Schema::create('project_workflow_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('from_step', 40);
                $table->string('to_step', 40);
                $table->string('status', 30)->default('pending');
                $table->text('reason');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->timestamps();

                $table->index(['site_id', 'status'], 'wf_exception_site_status_idx');
            });
        }
    }

    private function upgradeWorkflowStepDelayColumns(): void
    {
        if (! Schema::hasTable('project_workflow_steps')) {
            return;
        }

        Schema::table('project_workflow_steps', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_workflow_steps', 'recommitted_due_at')) {
                $table->dateTime('recommitted_due_at')->nullable();
            }
            if (! Schema::hasColumn('project_workflow_steps', 'delay_reason')) {
                $table->text('delay_reason')->nullable();
            }
            if (! Schema::hasColumn('project_workflow_steps', 'recovery_plan')) {
                $table->text('recovery_plan')->nullable();
            }
            if (! Schema::hasColumn('project_workflow_steps', 'risk_level')) {
                $table->string('risk_level', 30)->default('normal')->index('wf_step_risk_idx');
            }
            if (! Schema::hasColumn('project_workflow_steps', 'delay_updated_by')) {
                $table->unsignedBigInteger('delay_updated_by')->nullable();
            }
            if (! Schema::hasColumn('project_workflow_steps', 'delay_updated_at')) {
                $table->dateTime('delay_updated_at')->nullable();
            }
        });
    }

    private function upgradeMaterialWorkflow(): void
    {
        if (! Schema::hasTable('project_material_proposals')) {
            return;
        }

        Schema::table('project_material_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_material_proposals', 'proposal_type')) {
                $table->string('proposal_type', 30)->default('INITIAL')->index('mat_prop_type_idx');
            }
            if (! Schema::hasColumn('project_material_proposals', 'warranty_scope')) {
                $table->string('warranty_scope', 40)->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'admin_approved_by')) {
                $table->unsignedBigInteger('admin_approved_by')->nullable()->index('mat_prop_admin_by_idx');
            }
            if (! Schema::hasColumn('project_material_proposals', 'admin_approved_at')) {
                $table->dateTime('admin_approved_at')->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'admin_approval_note')) {
                $table->text('admin_approval_note')->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'recipient_confirmed_by')) {
                $table->unsignedBigInteger('recipient_confirmed_by')->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'recipient_confirmed_at')) {
                $table->dateTime('recipient_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'supervisor_confirmed_by')) {
                $table->unsignedBigInteger('supervisor_confirmed_by')->nullable();
            }
            if (! Schema::hasColumn('project_material_proposals', 'supervisor_confirmed_at')) {
                $table->dateTime('supervisor_confirmed_at')->nullable();
            }
        });
    }

    private function backfillExistingProjects(): void
    {
        if (! Schema::hasTable('sites') || ! Schema::hasTable('project_workflow_steps')) {
            return;
        }

        $steps = [
            'survey' => ['sequence' => 1, 'weight' => 10],
            'proposal' => ['sequence' => 2, 'weight' => 15],
            'contract' => ['sequence' => 3, 'weight' => 10],
            'legal' => ['sequence' => 4, 'weight' => 10],
            'construction' => ['sequence' => 5, 'weight' => 35],
            'acceptance' => ['sequence' => 6, 'weight' => 15],
            'warranty' => ['sequence' => 7, 'weight' => 5],
        ];

        $legacyMap = [
            'init' => 'survey',
            'design' => 'proposal',
            'prepare' => 'legal',
            'execute' => 'construction',
            'om' => 'warranty',
        ];

        DB::table('sites')->orderBy('id')->chunkById(200, function ($sites) use ($steps, $legacyMap): void {
            foreach ($sites as $site) {
                $legacy = strtolower(trim((string) ($site->project_phase ?? $site->stage ?? 'init')));
                $current = $legacyMap[$legacy] ?? 'survey';
                $currentSeq = $steps[$current]['sequence'];
                $now = $site->updated_at ?? now();

                foreach ($steps as $code => $definition) {
                    $exists = DB::table('project_workflow_steps')
                        ->where('site_id', $site->id)
                        ->where('step_code', $code)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $status = $definition['sequence'] < $currentSeq
                        ? 'approved'
                        : ($definition['sequence'] === $currentSeq ? 'in_progress' : 'not_assigned');

                    DB::table('project_workflow_steps')->insert([
                        'site_id' => $site->id,
                        'step_code' => $code,
                        'sequence' => $definition['sequence'],
                        'status' => $status,
                        'started_at' => $status === 'in_progress' ? $now : null,
                        'submitted_at' => $status === 'approved' ? $now : null,
                        'approved_at' => $status === 'approved' ? $now : null,
                        'created_at' => $site->created_at ?? now(),
                        'updated_at' => $now,
                    ]);
                }

                $progress = 0;
                foreach ($steps as $code => $definition) {
                    if ($definition['sequence'] < $currentSeq) {
                        $progress += $definition['weight'];
                    } elseif ($definition['sequence'] === $currentSeq) {
                        $progress += (int) round($definition['weight'] * 0.4);
                    }
                }

                $update = [
                    'workflow_version' => '2.0',
                    'workflow_current_step' => $current,
                    'workflow_status' => 'active',
                    'workflow_progress_percent' => min(100, $progress),
                ];

                if (Schema::hasColumn('sites', 'progress_percent')) {
                    $update['progress_percent'] = min(100, $progress);
                }
                if (Schema::hasColumn('sites', 'calculated_progress_percent')) {
                    $update['calculated_progress_percent'] = min(100, $progress);
                }

                DB::table('sites')->where('id', $site->id)->update($update);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflow_exceptions');
        Schema::dropIfExists('project_workflow_events');
        Schema::dropIfExists('project_workflow_approvals');
        Schema::dropIfExists('project_workflow_documents');
        Schema::dropIfExists('project_workflow_assignments');
        Schema::dropIfExists('project_workflow_steps');

        // Không tự xóa cột sites hoặc cột vật tư khi rollback để tránh mất dữ liệu vận hành.
    }
};
