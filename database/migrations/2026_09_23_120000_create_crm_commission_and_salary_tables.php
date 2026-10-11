<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('crm_commission_policies')) {
            Schema::create('crm_commission_policies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('period_month', 7)->index();
                $table->string('status')->default('active');
                $table->decimal('project_rate_percent', 8, 4)->default(4);
                $table->decimal('trade_rate_percent', 8, 4)->default(1);
                $table->decimal('panel_fixed_amount', 15, 2)->default(0);
                $table->boolean('only_paid')->default(true);
                $table->boolean('only_shipped')->default(false);
                $table->boolean('only_completed')->default(false);
                $table->boolean('hold_if_debt')->default(true);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_commission_rules')) {
            Schema::create('crm_commission_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('policy_id')->index();
                $table->string('period_month', 7)->index();
                $table->string('commission_type')->index(); // project, trade_product, solar_panel
                $table->string('target_type')->default('all'); // all, product, category, brand, keyword
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_text')->nullable();
                $table->string('base_type')->default('revenue_before_vat');
                $table->string('calculation_type')->default('percent'); // percent, fixed_per_item, fixed_per_kwp, fixed_per_order
                $table->decimal('rate_percent', 8, 4)->default(0);
                $table->decimal('fixed_amount', 15, 2)->default(0);
                $table->decimal('amount_per_unit', 15, 2)->default(0);
                $table->decimal('amount_per_kwp', 15, 2)->default(0);
                $table->decimal('from_amount', 15, 2)->nullable();
                $table->decimal('to_amount', 15, 2)->nullable();
                $table->decimal('from_qty', 15, 2)->nullable();
                $table->decimal('to_qty', 15, 2)->nullable();
                $table->integer('priority')->default(10);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_commission_lines')) {
            Schema::create('crm_commission_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('policy_id')->nullable()->index();
                $table->unsignedBigInteger('rule_id')->nullable()->index();
                $table->string('period_month', 7)->index();
                $table->string('source_type')->default('order');
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('sales_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name')->nullable();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->decimal('kwp', 15, 4)->default(0);
                $table->decimal('revenue_before_vat', 15, 2)->default(0);
                $table->decimal('revenue_after_vat', 15, 2)->default(0);
                $table->decimal('fifo_cost', 15, 2)->default(0);
                $table->decimal('gross_profit', 15, 2)->default(0);
                $table->string('commission_type')->nullable();
                $table->string('calculation_type')->nullable();
                $table->decimal('commission_base', 15, 2)->default(0);
                $table->decimal('commission_rate', 8, 4)->default(0);
                $table->decimal('commission_amount', 15, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('reason')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_sales_salary_settings')) {
            Schema::create('crm_sales_salary_settings', function (Blueprint $table) {
                $table->id();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('sales_id')->index();
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('target_revenue', 15, 2)->default(0);
                $table->decimal('target_commission', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['period_month', 'sales_id'], 'salary_period_sales_unique');
            });
        }

        if (!Schema::hasTable('crm_sales_kpi_tiers')) {
            Schema::create('crm_sales_kpi_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('sales_id')->nullable()->index();
                $table->string('tier_name')->nullable();
                $table->decimal('from_revenue', 15, 2)->default(0);
                $table->decimal('to_revenue', 15, 2)->nullable();
                $table->string('bonus_type')->default('fixed'); // fixed, percent_revenue, percent_commission, salary_percent
                $table->decimal('bonus_amount', 15, 2)->default(0);
                $table->integer('priority')->default(10);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // GHI CHÚ: Migration này tiếp quản các bảng có thể đã được tạo từ trước
        // trên production bởi code Schema::create cũ trong Service.
        // Việc xóa các bảng này trong down() là KHÔNG AN TOÀN vì có thể
        // làm mất toàn bộ dữ liệu (hoa hồng, lương) hiện tại.
        // Do đó, migration này được thiết kế KHÔNG ĐẢO NGƯỢC (hoặc phải xóa thủ công nếu chắc chắn).
        
        // Throw exception to prevent accidental data loss during rollback
        throw new \Exception('Rollback blocked: Cannot safely drop commission and salary tables as they might contain production data created before this migration existed.');
        
        // Nếu bắt buộc phải xóa (chỉ môi trường dev rỗng), hãy comment exception trên và uncomment dưới đây:
        // Schema::dropIfExists('crm_sales_kpi_tiers');
        // Schema::dropIfExists('crm_sales_salary_settings');
        // Schema::dropIfExists('crm_commission_lines');
        // Schema::dropIfExists('crm_commission_rules');
        // Schema::dropIfExists('crm_commission_policies');
    }
};
