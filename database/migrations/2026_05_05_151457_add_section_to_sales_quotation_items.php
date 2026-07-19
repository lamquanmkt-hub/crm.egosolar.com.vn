<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_quotation_items')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_quotation_items', 'section_key')) {
                    $table->string('section_key', 50)->default('main')->after('sort_order');
                }

                if (!Schema::hasColumn('sales_quotation_items', 'section_title')) {
                    $table->string('section_title')->nullable()->after('section_key');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_quotation_items')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_quotation_items', 'section_title')) {
                    $table->dropColumn('section_title');
                }

                if (Schema::hasColumn('sales_quotation_items', 'section_key')) {
                    $table->dropColumn('section_key');
                }
            });
        }
    }
};
