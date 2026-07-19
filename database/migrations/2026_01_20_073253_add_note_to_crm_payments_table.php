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
    Schema::table('crm_payments', function (Blueprint $table) {
        $table->string('note', 500)->nullable()->after('method_id');
    });
}

public function down(): void
{
    Schema::table('crm_payments', function (Blueprint $table) {
        $table->dropColumn('note');
    });
}
};
