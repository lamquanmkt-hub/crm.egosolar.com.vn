<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->after('name');
            $table->string('stage', 50)->nullable()->after('note');

            $table->decimal('system_kwp', 10, 2)->nullable()->after('note');
            $table->decimal('system_kw_ac', 10, 2)->nullable()->after('system_kwp');
            $table->string('system_type', 50)->nullable()->after('system_kw_ac');
            $table->string('phase', 50)->nullable()->after('system_type');

            $table->date('installed_at')->nullable()->after('phase');
            $table->date('warranty_to')->nullable()->after('installed_at');

            $table->string('technician_name')->nullable()->after('warranty_to');
            $table->string('monitoring_link')->nullable()->after('technician_name');
            $table->string('monitoring_account')->nullable()->after('monitoring_link');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'status','stage',
                'system_kwp','system_kw_ac','system_type','phase',
                'installed_at','warranty_to',
                'technician_name','monitoring_link','monitoring_account'
            ]);
        });
    }
};
