<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('hr_payroll_lines')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Còn dòng lương nhập chưa liên kết nhân viên; không thể bỏ hỗ trợ này.');
        }
        Schema::table('hr_payroll_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
