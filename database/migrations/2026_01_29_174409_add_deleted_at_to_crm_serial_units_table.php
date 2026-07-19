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
        Schema::table('crm_serial_units', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_serial_units', 'deleted_at')) {
                $table->softDeletes()->after('updated_at'); // tạo cột deleted_at
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_serial_units', function (Blueprint $table) {
            if (Schema::hasColumn('crm_serial_units', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
