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
    Schema::table('sites', function (Blueprint $table) {

        if (!Schema::hasColumn('sites', 'status')) {
            $table->string('status')->nullable();
        }

        if (!Schema::hasColumn('sites', 'system_kwp')) {
            $table->decimal('system_kwp', 8, 2)->nullable();
        }

        if (!Schema::hasColumn('sites', 'system_kw_ac')) {
            $table->decimal('system_kw_ac', 8, 2)->nullable();
        }

        if (!Schema::hasColumn('sites', 'system_type')) {
            $table->string('system_type')->nullable();
        }

        if (!Schema::hasColumn('sites', 'phase')) {
            $table->string('phase')->nullable();
        }

        if (!Schema::hasColumn('sites', 'installed_at')) {
            $table->date('installed_at')->nullable();
        }

        if (!Schema::hasColumn('sites', 'warranty_to')) {
            $table->date('warranty_to')->nullable();
        }

        if (!Schema::hasColumn('sites', 'technician_name')) {
            $table->string('technician_name')->nullable();
        }

        if (!Schema::hasColumn('sites', 'monitoring_link')) {
            $table->string('monitoring_link')->nullable();
        }

        if (!Schema::hasColumn('sites', 'monitoring_account')) {
            $table->string('monitoring_account')->nullable();
        }

        if (!Schema::hasColumn('sites', 'stage')) {
            $table->string('stage')->nullable();
        }
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            //
        });
    }
};
