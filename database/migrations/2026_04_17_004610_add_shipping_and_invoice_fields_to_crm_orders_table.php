<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->enum('shipping_fee_payer', ['seller', 'buyer'])
                ->default('seller')
                ->after('shipping_fee');

            $table->enum('invoice_status', ['no_invoice', 'pending', 'issued'])
                ->default('no_invoice')
                ->after('shipping_fee_payer');
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee_payer', 'invoice_status']);
        });
    }
};