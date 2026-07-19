<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_serial_unit_states', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('warehouse_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_serial_unit_states', function (Blueprint $table) {
            if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
