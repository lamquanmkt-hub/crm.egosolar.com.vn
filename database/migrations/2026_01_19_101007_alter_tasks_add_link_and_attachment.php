<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'link_url')) {
                $table->string('link_url')->nullable();
            }

            if (!Schema::hasColumn('tasks', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'link_url')) {
                $table->dropColumn('link_url');
            }

            if (Schema::hasColumn('tasks', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};
