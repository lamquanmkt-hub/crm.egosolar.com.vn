<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tasks', 'task_type')) {
                $table->string('task_type', 40)->nullable()->after('description')->index();
            }
            if (! Schema::hasColumn('tasks', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('task_type')->index();
            }
            if (! Schema::hasColumn('tasks', 'work_item')) {
                $table->string('work_item', 255)->nullable()->after('project_id');
            }
            if (! Schema::hasColumn('tasks', 'work_location')) {
                $table->string('work_location', 700)->nullable()->after('work_item');
            }
            if (! Schema::hasColumn('tasks', 'approver_id')) {
                $table->unsignedBigInteger('approver_id')->nullable()->after('assignee_id')->index();
            }
            if (! Schema::hasColumn('tasks', 'actual_minutes')) {
                $table->unsignedInteger('actual_minutes')->nullable()->after('progress_percent');
            }
            if (! Schema::hasColumn('tasks', 'issue_note')) {
                $table->text('issue_note')->nullable()->after('result_note');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        $columns = collect([
            'task_type', 'project_id', 'work_item', 'work_location',
            'approver_id', 'actual_minutes', 'issue_note',
        ])->filter(fn (string $column): bool => Schema::hasColumn('tasks', $column))->all();

        if ($columns !== []) {
            Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
