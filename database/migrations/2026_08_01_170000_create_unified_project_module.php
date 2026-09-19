<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'project_code')) {
                $table->string('project_code', 60)->nullable()->index();
            }
            if (! Schema::hasColumn('sites', 'project_phase')) {
                $table->string('project_phase', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('sites', 'progress_percent')) {
                $table->unsignedTinyInteger('progress_percent')->default(0)->index();
            }
            if (! Schema::hasColumn('sites', 'priority')) {
                $table->string('priority', 20)->default('normal')->index();
            }
            if (! Schema::hasColumn('sites', 'lead_engineer_id')) {
                $table->unsignedBigInteger('lead_engineer_id')->nullable()->index();
            }
            if (! Schema::hasColumn('sites', 'target_completion_at')) {
                $table->date('target_completion_at')->nullable()->index();
            }
            if (! Schema::hasColumn('sites', 'legacy_source')) {
                $table->string('legacy_source', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('sites', 'legacy_source_id')) {
                $table->unsignedBigInteger('legacy_source_id')->nullable()->index();
            }
        });

        if (! Schema::hasTable('project_unified_histories')) {
            Schema::create('project_unified_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('from_value', 255)->nullable();
                $table->string('to_value', 255)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'site_id')) {
            Schema::table('tasks', function (Blueprint $table): void {
                if (! Schema::hasColumn('tasks', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable()->index();
                }
            });
        }

        $this->backfillSites();
        $this->mapProjectTestProjects();
    }

    private function backfillSites(): void
    {
        $columns = collect(Schema::getColumnListing('sites'));
        $rows = DB::table('sites')->orderBy('id')->get();

        foreach ($rows as $site) {
            $phase = $this->phaseFromSite($site);
            $update = [];

            if ($columns->contains('project_code') && empty($site->project_code)) {
                $update['project_code'] = 'DA-SITE-'.str_pad((string) $site->id, 5, '0', STR_PAD_LEFT);
            }
            if ($columns->contains('project_phase') && empty($site->project_phase)) {
                $update['project_phase'] = $phase;
            }
            if ($columns->contains('progress_percent') && (int) ($site->progress_percent ?? 0) === 0) {
                $update['progress_percent'] = $this->phaseProgress($phase);
            }
            if ($columns->contains('priority') && empty($site->priority)) {
                $update['priority'] = 'normal';
            }

            if ($update !== []) {
                DB::table('sites')->where('id', $site->id)->update($update);
            }
        }
    }

    private function mapProjectTestProjects(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        $siteColumns = collect(Schema::getColumnListing('sites'));
        $legacyRows = DB::table('project_test_projects')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        foreach ($legacyRows as $legacy) {
            $alreadyMapped = DB::table('sites')
                ->where('legacy_source', 'project_test')
                ->where('legacy_source_id', $legacy->id)
                ->exists();

            if ($alreadyMapped) {
                continue;
            }

            $phase = $this->phaseFromLegacyStatus((string) ($legacy->status ?? ''));
            $row = [
                'company_id' => $legacy->company_id ?? null,
                'created_by' => $legacy->created_by ?? null,
                'project_code' => $legacy->code ?? ('DA-LEGACY-'.$legacy->id),
                'project_type' => 'industrial',
                'name' => $legacy->name ?? ('Dự án '.$legacy->id),
                'status' => 'active',
                'stage' => $phase,
                'project_phase' => $phase,
                'progress_percent' => max(0, min(100, (int) ($legacy->progress ?? $this->phaseProgress($phase)))),
                'priority' => $legacy->priority ?? 'normal',
                'lead_engineer_id' => $legacy->lead_technician_id ?? $legacy->technical_manager_id ?? null,
                'target_completion_at' => $legacy->target_completion_at ?? null,
                'address' => ! empty($legacy->address) ? mb_substr((string) $legacy->address, 0, 255) : null,
                'contact_name' => ! empty($legacy->contact_name) ? mb_substr((string) $legacy->contact_name, 0, 255) : null,
                'contact_phone' => ! empty($legacy->contact_phone) ? mb_substr((string) $legacy->contact_phone, 0, 50) : null,
                'note' => trim(implode("\n\n", array_filter([
                    $legacy->customer_need ?? null,
                    $legacy->note ?? null,
                    'Dữ liệu được ánh xạ an toàn từ Công Trình Test new. Bảng nguồn vẫn được giữ nguyên.',
                ]))),
                'system_kwp' => $legacy->estimated_kwp ?? null,
                'system_type' => ! empty($legacy->system_type) ? mb_substr((string) $legacy->system_type, 0, 50) : null,
                'legacy_source' => 'project_test',
                'legacy_source_id' => $legacy->id,
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ];

            if (Schema::hasTable('project_test_warranties')) {
                $warranty = DB::table('project_test_warranties')->where('project_id', $legacy->id)->orderByDesc('id')->first();
                if ($warranty) {
                    $row['installed_at'] = $warranty->starts_at ?? null;
                    $row['completed_at'] = $warranty->starts_at ?? null;
                    $row['warranty_to'] = $warranty->ends_at ?? null;
                }
            }

            $insert = array_filter(
                $row,
                fn ($value, string $column) => $siteColumns->contains($column),
                ARRAY_FILTER_USE_BOTH
            );

            try {
                $siteId = DB::table('sites')->insertGetId($insert);

                if (Schema::hasTable('project_unified_histories')) {
                    DB::table('project_unified_histories')->insert([
                        'site_id' => $siteId,
                        'user_id' => null,
                        'action' => 'legacy_project_mapped',
                        'from_value' => 'project_test:'.$legacy->id,
                        'to_value' => 'site:'.$siteId,
                        'note' => 'Ánh xạ dữ liệu cũ sang hồ sơ DỰ ÁN mới; không xóa dữ liệu nguồn.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $exception) {
                // Không dừng toàn bộ migration nếu một hồ sơ cũ thiếu dữ liệu bắt buộc.
                if (Schema::hasTable('project_unified_histories')) {
                    DB::table('project_unified_histories')->insert([
                        'site_id' => 0,
                        'user_id' => null,
                        'action' => 'legacy_mapping_failed',
                        'from_value' => 'project_test:'.$legacy->id,
                        'to_value' => null,
                        'note' => mb_substr($exception->getMessage(), 0, 2000),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function phaseFromSite(object $site): string
    {
        $text = strtolower(trim(implode(' ', array_filter([
            $site->project_phase ?? null,
            $site->stage ?? null,
            $site->status ?? null,
        ]))));

        if (str_contains($text, 'warranty') || str_contains($text, 'bao_hanh') || str_contains($text, 'bảo hành')) {
            return 'om';
        }
        if (str_contains($text, 'install') || str_contains($text, 'thi_cong') || str_contains($text, 'thi công') || str_contains($text, 'accept')) {
            return 'execute';
        }
        if (str_contains($text, 'material') || str_contains($text, 'prepare') || str_contains($text, 'vat_tu') || str_contains($text, 'vật tư')) {
            return 'prepare';
        }
        if (str_contains($text, 'survey') || str_contains($text, 'design') || str_contains($text, 'khao_sat') || str_contains($text, 'khảo sát')) {
            return 'design';
        }

        if (! empty($site->warranty_to ?? null) || ! empty($site->completed_at ?? null)) {
            return 'om';
        }

        return 'init';
    }

    private function phaseFromLegacyStatus(string $status): string
    {
        return match (true) {
            in_array($status, ['survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress', 'sales_review', 'proposal_revision'], true) => 'design',
            in_array($status, ['installation_pending', 'installation_reschedule', 'materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing', 'warehouse_issued', 'assignment_pending', 'ready_install'], true) => 'prepare',
            in_array($status, ['installing', 'acceptance_pending'], true) => 'execute',
            in_array($status, ['warranty_active', 'completed'], true) => 'om',
            default => 'init',
        };
    }

    private function phaseProgress(string $phase): int
    {
        return match ($phase) {
            'design' => 30,
            'prepare' => 55,
            'execute' => 80,
            'om' => 100,
            default => 10,
        };
    }

    public function down(): void
    {
        // Không xóa dữ liệu đã ánh xạ. Rollback chỉ bỏ các cấu trúc phụ nếu thật sự cần.
        if (Schema::hasTable('project_unified_histories')) {
            Schema::dropIfExists('project_unified_histories');
        }

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'site_id')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropColumn('site_id');
            });
        }
    }
};
