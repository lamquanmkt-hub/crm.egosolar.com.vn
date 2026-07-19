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
    Schema::table('crm_orders', function (Blueprint $table) {
        $table->string('shipping_status', 30)
              ->default('not_shipped')
              ->after('current_department')
              ->comment('not_shipped | shipping | shipped | returned');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            //
        });
    }
};
