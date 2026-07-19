<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'result_note')) {
                $table->text('result_note')->nullable();
            }
            if (!Schema::hasColumn('tasks', 'result_attachment_path')) {
                $table->string('result_attachment_path')->nullable();
            }
            if (!Schema::hasColumn('tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'result_note')) {
                $table->dropColumn('result_note');
            }
            if (Schema::hasColumn('tasks', 'result_attachment_path')) {
                $table->dropColumn('result_attachment_path');
            }
            if (Schema::hasColumn('tasks', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};

