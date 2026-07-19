<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_metrics', 'reach')) {
                $table->unsignedBigInteger('reach')->default(0);
            }

            // DB cũ không hỗ trợ JSON => dùng LONGTEXT
            if (!Schema::hasColumn('marketing_metrics', 'gender_breakdown')) {
                $table->longText('gender_breakdown')->nullable();
            }
            if (!Schema::hasColumn('marketing_metrics', 'age_breakdown')) {
                $table->longText('age_breakdown')->nullable();
            }
            if (!Schema::hasColumn('marketing_metrics', 'region_breakdown')) {
                $table->longText('region_breakdown')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            $cols = [];
            foreach (['reach','gender_breakdown','age_breakdown','region_breakdown'] as $c) {
                if (Schema::hasColumn('marketing_metrics', $c)) $cols[] = $c;
            }
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
