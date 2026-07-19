<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('crm_orders', function (Blueprint $table) {
        $table->string('invoice_company_name')->nullable();
        $table->string('invoice_tax_code', 50)->nullable();
        $table->string('invoice_address', 500)->nullable();
        $table->string('invoice_email')->nullable();
    });
}

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_company_name',
                'invoice_tax_code',
                'invoice_address',
                'invoice_email',
            ]);
        });
    }
};