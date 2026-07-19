<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('phone_number');
            }

            if (!Schema::hasColumn('users', 'position_id')) {
                $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropColumn('department_id');
            }

            if (Schema::hasColumn('users', 'position_id')) {
                $table->dropColumn('position_id');
            }
        });
    }
};