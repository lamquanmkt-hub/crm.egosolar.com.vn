<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_office_expenses')) {
            Schema::create('hr_office_expenses', function (Blueprint $table) {
                $table->id();
                $table->date('expense_date')->index();
                $table->string('category', 80)->index();
                $table->string('title');
                $table->decimal('amount', 18, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_office_expenses');
    }
};
