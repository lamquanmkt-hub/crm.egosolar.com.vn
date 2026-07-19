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
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->string('billing_company_name')->nullable()->after('email');
            $table->string('billing_tax_code', 50)->nullable()->after('billing_company_name');
            $table->string('billing_address', 500)->nullable()->after('billing_tax_code');
            $table->string('billing_email')->nullable()->after('billing_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_company_name',
                'billing_tax_code',
                'billing_address',
                'billing_email',
            ]);
        });
    }
};