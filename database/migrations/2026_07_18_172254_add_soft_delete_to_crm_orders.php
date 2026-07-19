<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('crm_orders', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('crm_orders', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')
                    ->nullable()
                    ->after('deleted_at');
            }

            if (!Schema::hasColumn('crm_orders', 'delete_reason')) {
                $table->text('delete_reason')
                    ->nullable()
                    ->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('crm_orders', 'delete_reason')) {
                $table->dropColumn('delete_reason');
            }

            if (Schema::hasColumn('crm_orders', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }

            if (Schema::hasColumn('crm_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
