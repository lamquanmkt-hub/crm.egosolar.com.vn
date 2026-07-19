<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_recruitment_candidates')) {
            Schema::table('hr_recruitment_candidates', function (Blueprint $table) {
                if (!Schema::hasColumn('hr_recruitment_candidates', 'zalo')) {
                    $table->string('zalo')->nullable()->after('email');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'cv_link')) {
                    $table->string('cv_link', 1000)->nullable()->after('source');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'contact_channel')) {
                    $table->string('contact_channel')->nullable()->after('cv_link');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'contacted_at')) {
                    $table->dateTime('contacted_at')->nullable()->after('contact_channel');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'suitability')) {
                    $table->string('suitability')->nullable()->after('contacted_at');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'reject_reason')) {
                    $table->text('reject_reason')->nullable()->after('status');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'archive_note')) {
                    $table->text('archive_note')->nullable()->after('reject_reason');
                }
                if (!Schema::hasColumn('hr_recruitment_candidates', 'archive_until')) {
                    $table->date('archive_until')->nullable()->after('archive_note');
                }
            });
        }

        if (Schema::hasTable('hr_recruitment_interviews')) {
            Schema::table('hr_recruitment_interviews', function (Blueprint $table) {
                if (!Schema::hasColumn('hr_recruitment_interviews', 'status')) {
                    $table->string('status')->default('scheduled')->after('interviewer');
                }
                if (!Schema::hasColumn('hr_recruitment_interviews', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable()->after('result');
                }
                if (!Schema::hasColumn('hr_recruitment_interviews', 'evaluation')) {
                    $table->text('evaluation')->nullable()->after('cancel_reason');
                }
                if (!Schema::hasColumn('hr_recruitment_interviews', 'evaluation_result')) {
                    $table->string('evaluation_result')->nullable()->after('evaluation');
                }
                if (!Schema::hasColumn('hr_recruitment_interviews', 'expected_start_date')) {
                    $table->date('expected_start_date')->nullable()->after('evaluation_result');
                }
            });
        }

        if (Schema::hasTable('hr_recruitment_offers')) {
            Schema::table('hr_recruitment_offers', function (Blueprint $table) {
                if (!Schema::hasColumn('hr_recruitment_offers', 'response_note')) {
                    $table->text('response_note')->nullable()->after('status');
                }
                if (!Schema::hasColumn('hr_recruitment_offers', 'onboarding_status')) {
                    $table->string('onboarding_status')->nullable()->after('response_note');
                }
                if (!Schema::hasColumn('hr_recruitment_offers', 'onboarding_date')) {
                    $table->date('onboarding_date')->nullable()->after('onboarding_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_recruitment_offers')) {
            Schema::table('hr_recruitment_offers', function (Blueprint $table) {
                foreach (['response_note', 'onboarding_status', 'onboarding_date'] as $column) {
                    if (Schema::hasColumn('hr_recruitment_offers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('hr_recruitment_interviews')) {
            Schema::table('hr_recruitment_interviews', function (Blueprint $table) {
                foreach (['status', 'cancel_reason', 'evaluation', 'evaluation_result', 'expected_start_date'] as $column) {
                    if (Schema::hasColumn('hr_recruitment_interviews', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('hr_recruitment_candidates')) {
            Schema::table('hr_recruitment_candidates', function (Blueprint $table) {
                foreach (['zalo', 'cv_link', 'contact_channel', 'contacted_at', 'suitability', 'reject_reason', 'archive_note', 'archive_until'] as $column) {
                    if (Schema::hasColumn('hr_recruitment_candidates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
