<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_kpi_pay_rules', function (Blueprint $table) {
            // Nếu DB bạn không hỗ trợ JSON (rất hiếm), đổi json() thành longText()
            $table->json('bonus_config')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_kpi_pay_rules', function (Blueprint $table) {
            $table->dropColumn('bonus_config');
        });
    }
};