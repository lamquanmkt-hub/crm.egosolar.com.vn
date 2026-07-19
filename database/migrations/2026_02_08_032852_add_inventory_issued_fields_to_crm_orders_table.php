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
            $table->boolean('inventory_issued')->default(false)->after('current_department');
            $table->dateTime('inventory_issued_at')->nullable()->after('inventory_issued');
            $table->unsignedBigInteger('inventory_issued_by')->nullable()->after('inventory_issued_at');
            $table->foreign('inventory_issued_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropForeign(['inventory_issued_by']);
            $table->dropColumn([
                'inventory_issued',
                'inventory_issued_at',
                'inventory_issued_by',
            ]);
        });
    }
};
