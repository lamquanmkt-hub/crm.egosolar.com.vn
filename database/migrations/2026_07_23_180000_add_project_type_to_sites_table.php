<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites') || Schema::hasColumn('sites', 'project_type')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'project_type')) {
                $table->string('project_type', 30)
                    ->nullable()
                    ->after('company_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sites') || ! Schema::hasColumn('sites', 'project_type')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('project_type');
        });
    }
};
