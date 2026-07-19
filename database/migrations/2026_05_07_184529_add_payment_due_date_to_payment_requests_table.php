<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payment_requests', 'payment_due_date')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->date('payment_due_date')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_requests', 'payment_due_date')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->dropColumn('payment_due_date');
            });
        }
    }
};