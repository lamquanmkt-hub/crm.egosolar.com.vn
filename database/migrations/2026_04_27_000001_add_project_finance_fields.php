<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Bổ sung thông tin tài chính cho công trình
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                if (!Schema::hasColumn('sites', 'contract_amount')) {
                    $table->decimal('contract_amount', 15, 2)->default(0);
                }

                if (!Schema::hasColumn('sites', 'contract_signed_at')) {
                    $table->date('contract_signed_at')->nullable();
                }

                if (!Schema::hasColumn('sites', 'finance_note')) {
                    $table->text('finance_note')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Bảng đợt thanh toán của công trình
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('site_payment_terms')) {
            Schema::create('site_payment_terms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('name');
                $table->decimal('percent', 8, 2)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->string('status')->default('pending');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('site_id');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Link phiếu thu với công trình và đợt thanh toán
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('receipts', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable();
                    $table->index('site_id');
                }

                if (!Schema::hasColumn('receipts', 'site_payment_term_id')) {
                    $table->unsignedBigInteger('site_payment_term_id')->nullable();
                    $table->index('site_payment_term_id');
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Link phiếu chi với công trình
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable();
                    $table->index('site_id');
                }

                if (!Schema::hasColumn('payments', 'cost_type')) {
                    $table->string('cost_type', 100)->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Link đề nghị thanh toán với công trình
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('payment_requests')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('payment_requests', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable();
                    $table->index('site_id');
                }

                if (!Schema::hasColumn('payment_requests', 'cost_type')) {
                    $table->string('cost_type', 100)->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Bổ sung tổng chi phí cho đơn vật tư
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('material_requests', 'total_cost')) {
                    $table->decimal('total_cost', 15, 2)->default(0);
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Bổ sung đơn giá / thành tiền cho từng dòng vật tư
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('material_request_items', 'unit')) {
                    $table->string('unit', 50)->nullable();
                }

                if (!Schema::hasColumn('material_request_items', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 2)->default(0);
                }

                if (!Schema::hasColumn('material_request_items', 'vat_percent')) {
                    $table->decimal('vat_percent', 8, 2)->default(0);
                }

                if (!Schema::hasColumn('material_request_items', 'line_total')) {
                    $table->decimal('line_total', 15, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                foreach (['unit', 'unit_cost', 'vat_percent', 'line_total'] as $column) {
                    if (Schema::hasColumn('material_request_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (Schema::hasColumn('material_requests', 'total_cost')) {
                    $table->dropColumn('total_cost');
                }
            });
        }

        if (Schema::hasTable('payment_requests')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                foreach (['site_id', 'cost_type'] as $column) {
                    if (Schema::hasColumn('payment_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                foreach (['site_id', 'cost_type'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                foreach (['site_id', 'site_payment_term_id'] as $column) {
                    if (Schema::hasColumn('receipts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('site_payment_terms');

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                foreach (['contract_amount', 'contract_signed_at', 'finance_note'] as $column) {
                    if (Schema::hasColumn('sites', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};