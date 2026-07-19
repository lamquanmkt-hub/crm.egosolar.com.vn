<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('crm_product_catalog', function (Blueprint $table) {
        $table->decimal('price_agent_vat', 15, 2)->nullable()->after('price_agent');
        $table->decimal('price_retail_vat', 15, 2)->nullable()->after('price_retail');
    });
}
    /**
     * Reverse the migrations.
     */
   public function down()
{
    Schema::table('crm_product_catalog', function (Blueprint $table) {
        $table->dropColumn(['price_agent_vat', 'price_retail_vat']);
    });
}
};
