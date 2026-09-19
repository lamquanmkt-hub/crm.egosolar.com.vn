<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_surveys')) {
            return;
        }

        Schema::table('project_test_surveys', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_test_surveys', 'check_in_at')) {
                $table->timestamp('check_in_at')->nullable()->after('scheduled_at');
            }
            if (! Schema::hasColumn('project_test_surveys', 'check_out_at')) {
                $table->timestamp('check_out_at')->nullable()->after('check_in_at');
            }
            if (! Schema::hasColumn('project_test_surveys', 'check_in_lat')) {
                $table->decimal('check_in_lat', 10, 7)->nullable()->after('check_out_at');
            }
            if (! Schema::hasColumn('project_test_surveys', 'check_in_lng')) {
                $table->decimal('check_in_lng', 10, 7)->nullable()->after('check_in_lat');
            }
            if (! Schema::hasColumn('project_test_surveys', 'check_out_lat')) {
                $table->decimal('check_out_lat', 10, 7)->nullable()->after('check_in_lng');
            }
            if (! Schema::hasColumn('project_test_surveys', 'check_out_lng')) {
                $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
            }
            if (! Schema::hasColumn('project_test_surveys', 'actual_measurements')) {
                $table->text('actual_measurements')->nullable()->after('site_condition');
            }
            if (! Schema::hasColumn('project_test_surveys', 'site_risks')) {
                $table->text('site_risks')->nullable()->after('actual_measurements');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_test_surveys')) {
            return;
        }

        $columns = collect([
            'check_in_at', 'check_out_at', 'check_in_lat', 'check_in_lng',
            'check_out_lat', 'check_out_lng', 'actual_measurements', 'site_risks',
        ])->filter(fn (string $column): bool => Schema::hasColumn('project_test_surveys', $column))->all();

        if ($columns !== []) {
            Schema::table('project_test_surveys', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
