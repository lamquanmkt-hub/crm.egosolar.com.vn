<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('weekly_tasks', function (Blueprint $table) {

            if (!Schema::hasColumn('weekly_tasks', 'assignee')) {
                $table->string('assignee')->nullable()->after('category');
            }

            if (!Schema::hasColumn('weekly_tasks', 'assignees')) {
                $table->json('assignees')->nullable()->after('assignee');
            }

            if (!Schema::hasColumn('weekly_tasks', 'links')) {
                $table->json('links')->nullable()->after('note');
            }

            if (!Schema::hasColumn('weekly_tasks', 'attachments')) {
                $table->json('attachments')->nullable()->after('links');
            }

            if (!Schema::hasColumn('weekly_tasks', 'progress')) {
                $table->unsignedTinyInteger('progress')->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_tasks', 'assignees')) $table->dropColumn('assignees');
            if (Schema::hasColumn('weekly_tasks', 'links')) $table->dropColumn('links');
            if (Schema::hasColumn('weekly_tasks', 'attachments')) $table->dropColumn('attachments');
        });
    }
};
