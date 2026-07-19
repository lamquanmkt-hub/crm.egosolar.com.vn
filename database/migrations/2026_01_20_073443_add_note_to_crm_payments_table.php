<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            // Nếu cột đã tồn tại thì bỏ qua, tránh lỗi migrate lại
            if (!Schema::hasColumn('crm_payments', 'note')) {
                $table->string('note', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            if (Schema::hasColumn('crm_payments', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
