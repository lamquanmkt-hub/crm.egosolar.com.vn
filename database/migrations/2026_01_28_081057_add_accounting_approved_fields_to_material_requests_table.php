<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('material_requests', function (Blueprint $table) {
        if (!Schema::hasColumn('material_requests', 'accounting_approved_at')) {
            $table->timestamp('accounting_approved_at')->nullable();
        }
        if (!Schema::hasColumn('material_requests', 'accounting_approved_by')) {
            $table->unsignedBigInteger('accounting_approved_by')->nullable();
        }
    });
}

public function down(): void
{
    Schema::table('material_requests', function (Blueprint $table) {
        if (Schema::hasColumn('material_requests', 'accounting_approved_by')) {
            $table->dropColumn('accounting_approved_by');
        }
        if (Schema::hasColumn('material_requests', 'accounting_approved_at')) {
            $table->dropColumn('accounting_approved_at');
        }
    });
}

};
