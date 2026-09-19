<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table): void {
                if (! Schema::hasColumn('sites', 'phase_progress_percent')) {
                    $table->unsignedTinyInteger('phase_progress_percent')->default(0)->index();
                }
                if (! Schema::hasColumn('sites', 'calculated_progress_percent')) {
                    $table->unsignedTinyInteger('calculated_progress_percent')->default(0)->index();
                }
                if (! Schema::hasColumn('sites', 'approved_progress_percent')) {
                    $table->unsignedTinyInteger('approved_progress_percent')->default(0)->index();
                }
                if (! Schema::hasColumn('sites', 'project_phase_status')) {
                    $table->string('project_phase_status', 40)->default('in_progress')->index();
                }
                if (! Schema::hasColumn('sites', 'progress_override_percent')) {
                    $table->unsignedTinyInteger('progress_override_percent')->nullable();
                }
                if (! Schema::hasColumn('sites', 'progress_override_reason')) {
                    $table->text('progress_override_reason')->nullable();
                }
                if (! Schema::hasColumn('sites', 'progress_override_by')) {
                    $table->unsignedBigInteger('progress_override_by')->nullable()->index();
                }
                if (! Schema::hasColumn('sites', 'progress_override_at')) {
                    $table->dateTime('progress_override_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('project_phase_reviews')) {
            Schema::create('project_phase_reviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->string('phase', 40)->index();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedTinyInteger('phase_percent')->default(0);
                $table->unsignedTinyInteger('calculated_progress')->default(0);
                $table->unsignedTinyInteger('approved_progress')->default(0);
                $table->longText('checklist_snapshot')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable()->index();
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('submit_note')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['site_id', 'phase', 'status'], 'project_phase_review_lookup');
            });
        }

        $this->backfillProgress();
    }

    private function backfillProgress(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }
        $columns = collect(Schema::getColumnListing('sites'));
        if (! $columns->contains('id')) {
            return;
        }

        DB::table('sites')->orderBy('id')->chunkById(200, function ($rows) use ($columns): void {
            foreach ($rows as $row) {
                $phase = strtolower(trim((string) ($row->project_phase ?? $row->stage ?? 'init')));
                if (! in_array($phase, ['init', 'design', 'prepare', 'execute', 'om'], true)) {
                    $phase = 'init';
                }
                $base = ['init' => 0, 'design' => 10, 'prepare' => 30, 'execute' => 50, 'om' => 100][$phase];
                $weight = ['init' => 10, 'design' => 20, 'prepare' => 20, 'execute' => 50, 'om' => 0][$phase];
                $existing = max(0, min(100, (int) ($row->progress_percent ?? $base)));
                $calculated = max($base, $existing);
                $phasePercent = $phase === 'om' ? 100 : ($weight > 0 ? (int) round((($calculated - $base) / $weight) * 100) : 0);
                $phasePercent = max(0, min(100, $phasePercent));

                $update = [];
                if ($columns->contains('approved_progress_percent')) {
                    $update['approved_progress_percent'] = $base;
                }
                if ($columns->contains('calculated_progress_percent')) {
                    $update['calculated_progress_percent'] = $calculated;
                }
                if ($columns->contains('phase_progress_percent')) {
                    $update['phase_progress_percent'] = $phasePercent;
                }
                if ($columns->contains('project_phase_status') && empty($row->project_phase_status)) {
                    $update['project_phase_status'] = 'in_progress';
                }
                if ($update !== []) {
                    DB::table('sites')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phase_reviews');
        if (Schema::hasTable('sites')) {
            $columns = [
                'phase_progress_percent', 'calculated_progress_percent', 'approved_progress_percent',
                'project_phase_status', 'progress_override_percent', 'progress_override_reason',
                'progress_override_by', 'progress_override_at',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('sites', $column)));
            if ($existing !== []) {
                Schema::table('sites', fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
