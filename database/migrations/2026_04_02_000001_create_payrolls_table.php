<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('payroll_month', 7); // vd: 2026-04
            $table->decimal('standard_days', 5, 2)->default(26);
            $table->decimal('working_days', 5, 2)->default(0);

            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('advance', 15, 2)->default(0);
            $table->decimal('other_deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'payroll_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};