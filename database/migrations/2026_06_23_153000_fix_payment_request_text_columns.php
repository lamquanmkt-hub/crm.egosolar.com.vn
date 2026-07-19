<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_requests')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasColumn('payment_requests', 'payment_content')) {
                DB::statement('ALTER TABLE payment_requests MODIFY payment_content TEXT NULL');
            }

            if (Schema::hasColumn('payment_requests', 'bank_info')) {
                DB::statement('ALTER TABLE payment_requests MODIFY bank_info TEXT NULL');
            }

            if (Schema::hasColumn('payment_requests', 'department')) {
                DB::statement('ALTER TABLE payment_requests MODIFY department TEXT NULL');
            }
        }
    }

    public function down(): void
    {
        // Không rollback để tránh cắt mất dữ liệu dài đã nhập.
    }
};
