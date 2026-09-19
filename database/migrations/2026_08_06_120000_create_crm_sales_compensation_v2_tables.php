<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_compensation_months')) {
            Schema::create('crm_compensation_months', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->string('name')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('version')->default(1);

                $table->decimal('sales_base_salary', 15, 2)->default(7000000);
                $table->decimal('sales_responsibility_allowance', 15, 2)->default(3000000);
                $table->decimal('sales_travel_allowance', 15, 2)->default(2000000);
                $table->decimal('sales_target_revenue', 15, 2)->default(500000000);
                $table->decimal('sales_threshold_percent', 8, 2)->default(80);
                $table->decimal('lead_rate_percent', 8, 4)->default(0.5);
                $table->decimal('member_rate_percent', 8, 4)->default(1);
                $table->decimal('retail_rate_percent', 8, 4)->default(1);
                $table->decimal('other_rate_percent', 8, 4)->default(1);
                $table->decimal('project_rate_percent', 8, 4)->default(3);
                $table->decimal('over_target_rate_percent', 8, 4)->default(1);
                $table->decimal('new_dealer_bonus', 15, 2)->default(2000000);
                $table->decimal('new_dealer_min_order', 15, 2)->default(30000000);
                $table->unsignedInteger('new_dealer_min_products')->default(2);

                $table->decimal('leader_base_salary', 15, 2)->default(9000000);
                $table->decimal('leader_management_allowance', 15, 2)->default(4000000);
                $table->string('leader_travel_mode', 20)->default('percent');
                $table->decimal('leader_travel_value', 15, 4)->default(1);
                $table->decimal('leader_target_revenue', 15, 2)->default(1000000000);
                $table->decimal('leader_threshold_percent', 8, 2)->default(80);
                $table->decimal('leader_team_rate_percent', 8, 4)->default(0.3);
                $table->decimal('leader_over_target_rate_percent', 8, 4)->default(1);
                $table->boolean('leader_personal_commission')->default(true);

                $table->string('calculate_on', 30)->default('paid_before_vat');
                $table->boolean('include_partial_payment')->default(true);
                $table->boolean('hold_if_debt')->default(false);
                $table->boolean('require_approved_policy')->default(true);
                $table->text('note')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'period_month'], 'crm_comp_month_company_unique');
            });
        }

        if (! Schema::hasTable('crm_compensation_staff_settings')) {
            Schema::create('crm_compensation_staff_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('role_type', 20)->default('sale')->index();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('responsibility_allowance', 15, 2)->default(0);
                $table->decimal('travel_allowance', 15, 2)->default(0);
                $table->decimal('target_revenue', 15, 2)->default(0);
                $table->decimal('kpi_percent', 8, 2)->default(100);
                $table->boolean('went_to_market')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'period_month', 'user_id'],
                    'crm_comp_staff_company_month_user_unique'
                );
            });
        }

        if (! Schema::hasTable('crm_compensation_order_overrides')) {
            Schema::create('crm_compensation_order_overrides', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('sales_id')->nullable()->index();
                $table->string('order_type', 20)->default('distribution');
                $table->string('customer_group', 20)->nullable();
                $table->decimal('rate_override_percent', 8, 4)->nullable();
                $table->decimal('commission_override_amount', 15, 2)->nullable();
                $table->boolean('is_new_dealer')->default(false);
                $table->decimal('new_dealer_bonus_override', 15, 2)->nullable();
                $table->boolean('is_excluded')->default(false);
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'period_month', 'order_id'],
                    'crm_comp_override_company_month_order_unique'
                );
            });
        }

        if (! Schema::hasTable('crm_compensation_adjustments')) {
            Schema::create('crm_compensation_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('adjustment_type', 30)->default('bonus')->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('title')->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_compensation_snapshots')) {
            Schema::create('crm_compensation_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->string('snapshot_key', 50)->default('dashboard');
                $table->unsignedInteger('policy_version')->default(1);
                $table->longText('payload');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'period_month', 'snapshot_key'],
                    'crm_comp_snapshot_company_month_key_unique'
                );
            });
        }

        if (! Schema::hasTable('crm_compensation_audit_logs')) {
            Schema::create('crm_compensation_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0)->index();
                $table->string('period_month', 7)->index();
                $table->string('action', 80)->index();
                $table->string('subject_type', 80)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->longText('before_data')->nullable();
                $table->longText('after_data')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_compensation_audit_logs');
        Schema::dropIfExists('crm_compensation_snapshots');
        Schema::dropIfExists('crm_compensation_adjustments');
        Schema::dropIfExists('crm_compensation_order_overrides');
        Schema::dropIfExists('crm_compensation_staff_settings');
        Schema::dropIfExists('crm_compensation_months');
    }
};
