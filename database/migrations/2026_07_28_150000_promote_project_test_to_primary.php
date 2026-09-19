<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        Schema::table('project_test_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_test_projects', 'legacy_site_id')) {
                $table->unsignedBigInteger('legacy_site_id')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('project_test_projects', 'legacy_payload_json')) {
                $table->longText('legacy_payload_json')->nullable()->after('note');
            }
            if (! Schema::hasColumn('project_test_projects', 'contract_amount')) {
                $table->decimal('contract_amount', 15, 2)->default(0)->after('legacy_payload_json');
            }
            if (! Schema::hasColumn('project_test_projects', 'installed_at')) {
                $table->date('installed_at')->nullable()->after('contract_amount');
            }
            if (! Schema::hasColumn('project_test_projects', 'warranty_to')) {
                $table->date('warranty_to')->nullable()->after('installed_at');
            }
            if (! Schema::hasColumn('project_test_projects', 'monitoring_link')) {
                $table->string('monitoring_link', 700)->nullable()->after('warranty_to');
            }
            if (! Schema::hasColumn('project_test_projects', 'monitoring_account')) {
                $table->string('monitoring_account')->nullable()->after('monitoring_link');
            }
            if (! Schema::hasColumn('project_test_projects', 'imported_from_legacy_at')) {
                $table->timestamp('imported_from_legacy_at')->nullable()->after('monitoring_account');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        Schema::table('project_test_projects', function (Blueprint $table): void {
            $columns = [
                'legacy_site_id',
                'legacy_payload_json',
                'contract_amount',
                'installed_at',
                'warranty_to',
                'monitoring_link',
                'monitoring_account',
                'imported_from_legacy_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('project_test_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
