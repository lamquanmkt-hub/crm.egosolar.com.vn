<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                if (!Schema::hasColumn('sites', 'quote_no')) {
                    $table->string('quote_no', 50)->nullable()->after('id');
                }

                if (!Schema::hasColumn('sites', 'quote_date')) {
                    $table->date('quote_date')->nullable()->after('status');
                }

                if (!Schema::hasColumn('sites', 'quote_valid_until')) {
                    $table->date('quote_valid_until')->nullable()->after('status');
                }

                if (!Schema::hasColumn('sites', 'quote_status')) {
                    $table->string('quote_status', 50)->nullable()->default('draft')->after('status');
                }

                if (!Schema::hasColumn('sites', 'quote_customer_company')) {
                    $table->string('quote_customer_company')->nullable()->after('contact_phone');
                }

                if (!Schema::hasColumn('sites', 'quote_customer_email')) {
                    $table->string('quote_customer_email')->nullable()->after('contact_phone');
                }

                if (!Schema::hasColumn('sites', 'quote_customer_tax_code')) {
                    $table->string('quote_customer_tax_code', 50)->nullable()->after('contact_phone');
                }

                if (!Schema::hasColumn('sites', 'quote_config_summary')) {
                    $table->text('quote_config_summary')->nullable()->after('note');
                }

                if (!Schema::hasColumn('sites', 'quote_application_note')) {
                    $table->text('quote_application_note')->nullable()->after('note');
                }

                if (!Schema::hasColumn('sites', 'quote_scope')) {
                    $table->longText('quote_scope')->nullable()->after('finance_note');
                }

                if (!Schema::hasColumn('sites', 'quote_commercial_terms')) {
                    $table->longText('quote_commercial_terms')->nullable()->after('finance_note');
                }

                if (!Schema::hasColumn('sites', 'quote_warranty_terms')) {
                    $table->longText('quote_warranty_terms')->nullable()->after('finance_note');
                }

                if (!Schema::hasColumn('sites', 'quote_om_terms')) {
                    $table->longText('quote_om_terms')->nullable()->after('finance_note');
                }

                if (!Schema::hasColumn('sites', 'quote_subtotal')) {
                    $table->decimal('quote_subtotal', 15, 2)->default(0)->after('contract_amount');
                }

                if (!Schema::hasColumn('sites', 'quote_discount_amount')) {
                    $table->decimal('quote_discount_amount', 15, 2)->default(0)->after('contract_amount');
                }

                if (!Schema::hasColumn('sites', 'quote_vat_percent')) {
                    $table->decimal('quote_vat_percent', 6, 2)->default(0)->after('contract_amount');
                }

                if (!Schema::hasColumn('sites', 'quote_vat_amount')) {
                    $table->decimal('quote_vat_amount', 15, 2)->default(0)->after('contract_amount');
                }

                if (!Schema::hasColumn('sites', 'quote_grand_total')) {
                    $table->decimal('quote_grand_total', 15, 2)->default(0)->after('contract_amount');
                }

                if (!Schema::hasColumn('sites', 'quote_pdf_path')) {
                    $table->string('quote_pdf_path')->nullable()->after('finance_note');
                }

                if (!Schema::hasColumn('sites', 'quote_sent_at')) {
                    $table->timestamp('quote_sent_at')->nullable()->after('updated_at');
                }

                if (!Schema::hasColumn('sites', 'quote_approved_at')) {
                    $table->timestamp('quote_approved_at')->nullable()->after('updated_at');
                }
            });
        }

        if (Schema::hasTable('site_planned_materials')) {
            Schema::table('site_planned_materials', function (Blueprint $table) {
                if (!Schema::hasColumn('site_planned_materials', 'source')) {
                    $table->string('source', 50)->default('planned')->after('site_id');
                }

                if (!Schema::hasColumn('site_planned_materials', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('source');
                }

                if (!Schema::hasColumn('site_planned_materials', 'section_code')) {
                    $table->string('section_code', 20)->nullable()->after('sort_order');
                }

                if (!Schema::hasColumn('site_planned_materials', 'section_title')) {
                    $table->string('section_title')->nullable()->after('section_code');
                }

                if (!Schema::hasColumn('site_planned_materials', 'brand')) {
                    $table->string('brand')->nullable()->after('name');
                }

                if (!Schema::hasColumn('site_planned_materials', 'model')) {
                    $table->string('model')->nullable()->after('brand');
                }

                if (!Schema::hasColumn('site_planned_materials', 'specs_text')) {
                    $table->longText('specs_text')->nullable()->after('model');
                }

                if (!Schema::hasColumn('site_planned_materials', 'qty_decimal')) {
                    $table->decimal('qty_decimal', 12, 2)->nullable()->after('qty');
                }

                if (!Schema::hasColumn('site_planned_materials', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->default(0)->after('qty_decimal');
                }

                if (!Schema::hasColumn('site_planned_materials', 'line_total')) {
                    $table->decimal('line_total', 15, 2)->default(0)->after('unit_price');
                }

                if (!Schema::hasColumn('site_planned_materials', 'image_path')) {
                    $table->string('image_path')->nullable()->after('line_total');
                }

                if (!Schema::hasColumn('site_planned_materials', 'quote_note')) {
                    $table->text('quote_note')->nullable()->after('image_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                $columns = [
                    'quote_no',
                    'quote_date',
                    'quote_valid_until',
                    'quote_status',
                    'quote_customer_company',
                    'quote_customer_email',
                    'quote_customer_tax_code',
                    'quote_config_summary',
                    'quote_application_note',
                    'quote_scope',
                    'quote_commercial_terms',
                    'quote_warranty_terms',
                    'quote_om_terms',
                    'quote_subtotal',
                    'quote_discount_amount',
                    'quote_vat_percent',
                    'quote_vat_amount',
                    'quote_grand_total',
                    'quote_pdf_path',
                    'quote_sent_at',
                    'quote_approved_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('sites', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('site_planned_materials')) {
            Schema::table('site_planned_materials', function (Blueprint $table) {
                $columns = [
                    'source',
                    'sort_order',
                    'section_code',
                    'section_title',
                    'brand',
                    'model',
                    'specs_text',
                    'qty_decimal',
                    'unit_price',
                    'line_total',
                    'image_path',
                    'quote_note',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('site_planned_materials', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
