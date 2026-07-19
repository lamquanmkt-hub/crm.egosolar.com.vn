<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_warehouses', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');

            $table->index('company_id');

            // Nếu DB hỗ trợ FK ổn định thì bật:
            // $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('crm_warehouses', function (Blueprint $table) {
            // Nếu có foreign key thì drop trước
            // $table->dropForeign(['company_id']);

            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
