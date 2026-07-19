<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_quotations')) {
            Schema::create('sales_quotations', function (Blueprint $table) {
                $table->id();
                $table->string('quote_code', 50)->nullable()->unique();
                $table->date('quote_date')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 50)->default('draft');

                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 50)->nullable();
                $table->string('customer_email')->nullable();
                $table->text('customer_address')->nullable();
                $table->string('billing_company_name')->nullable();
                $table->string('billing_tax_code', 50)->nullable();
                $table->text('billing_address')->nullable();

                $table->string('project_name')->nullable();
                $table->text('project_address')->nullable();
                $table->string('system_type', 100)->nullable();
                $table->decimal('system_kwp', 12, 2)->default(0);
                $table->decimal('system_kw_ac', 12, 2)->default(0);
                $table->decimal('battery_kwh', 12, 2)->default(0);
                $table->text('config_summary')->nullable();
                $table->text('application_note')->nullable();

                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('vat_percent', 6, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);

                $table->longText('payment_terms')->nullable();
                $table->longText('commercial_terms')->nullable();
                $table->longText('warranty_terms')->nullable();
                $table->longText('om_terms')->nullable();
                $table->longText('note')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->timestamp('sent_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'quote_date']);
                $table->index('customer_id');
            });
        }

        if (!Schema::hasTable('sales_quotation_items')) {
            Schema::create('sales_quotation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quotation_id');
                $table->integer('sort_order')->default(0);

                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name')->nullable();
                $table->string('sku', 100)->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->string('unit', 50)->nullable();
                $table->longText('specs_text')->nullable();
                $table->string('image_url', 500)->nullable();

                $table->decimal('qty', 12, 2)->default(0);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('vat_percent', 6, 2)->default(0);
                $table->decimal('line_subtotal', 15, 2)->default(0);
                $table->decimal('line_vat', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);

                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('quotation_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_items');
        Schema::dropIfExists('sales_quotations');
    }
};
