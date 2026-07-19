<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'bank_account')) {
                $table->string('bank_account', 100)->nullable()->after('logo');
            }

            if (!Schema::hasColumn('companies', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bank_account');
            }

            if (!Schema::hasColumn('companies', 'bank_holder')) {
                $table->string('bank_holder')->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        $columns = [];

        foreach (['bank_account', 'bank_name', 'bank_holder'] as $column) {
            if (Schema::hasColumn('companies', $column)) {
                $columns[] = $column;
            }
        }

        if (!empty($columns)) {
            Schema::table('companies', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
