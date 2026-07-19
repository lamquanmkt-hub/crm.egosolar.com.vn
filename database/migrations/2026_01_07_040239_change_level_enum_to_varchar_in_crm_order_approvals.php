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
        Schema::table('crm_order_approvals', function (Blueprint $table) {
            $table->string('level', 50)->change();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_order_approvals', function (Blueprint $table) {
            $table->enum('level', [
                'sales',
                'ketoan',
                'duyet1',
                'duyet2',
                'kho'
            ])->change();
        });
    }
};
