<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * SEO phases (1-4) per plan
         */
        if (!Schema::hasTable('mkt_seo_phases')) {
            Schema::create('mkt_seo_phases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->tinyInteger('phase_no')->index(); // 1..4
                $table->string('title')->nullable();      // VD: "Giai đoạn 1 - Research"
                $table->text('note')->nullable();

                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();

                $table->decimal('budget', 14, 2)->default(0);

                $table->timestamps();

                $table->unique(['plan_id', 'phase_no'], 'uniq_plan_phase');
            });
        }

        /**
         * KPI key-value per phase (flexible)
         */
        if (!Schema::hasTable('mkt_seo_phase_kpis')) {
            Schema::create('mkt_seo_phase_kpis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->tinyInteger('phase_no')->index(); // 1..4

                // key-value KPI
                $table->string('kpi_key', 80); // VD: content_new, internal_link_content...
                $table->decimal('kpi_value', 14, 2)->default(0);
                $table->string('kpi_text', 255)->nullable(); // note nhỏ

                $table->timestamps();

                $table->unique(['plan_id', 'phase_no', 'kpi_key'], 'uniq_plan_phase_kpi');
                $table->index(['plan_id', 'phase_no'], 'idx_plan_phase');
            });
        }

        /**
         * Budget items per phase (optional but useful)
         */
        if (!Schema::hasTable('mkt_seo_phase_budget_items')) {
            Schema::create('mkt_seo_phase_budget_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->tinyInteger('phase_no')->index(); // 1..4

                $table->string('item')->nullable();
                $table->string('cost_text')->nullable();
                $table->decimal('cost_value', 14, 2)->default(0);
                $table->string('link')->nullable();
                $table->string('objective')->nullable();
                $table->string('timeline')->nullable(); // M1, M2-M3...
                $table->string('kpi')->nullable();
                $table->string('owner')->nullable();
                $table->text('note')->nullable();

                $table->timestamps();

                $table->index(['plan_id', 'phase_no'], 'idx_plan_phase_budget');
            });
        }

        /**
         * (Optional) If you want to ensure mkt_seo_contents.website is nullable + short for indexes
         * - You already fixed by SQL. Keep this safe block for other env.
         */
        if (Schema::hasTable('mkt_seo_contents') && Schema::hasColumn('mkt_seo_contents', 'website')) {
            Schema::table('mkt_seo_contents', function (Blueprint $table) {
                // Can't "modify" without doctrine/dbal; so we DON'T touch here.
                // Keep DB manual change or add doctrine/dbal if you want.
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_phase_budget_items');
        Schema::dropIfExists('mkt_seo_phase_kpis');
        Schema::dropIfExists('mkt_seo_phases');
    }
};