<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('payment_date');
            $table->string('payee_name');
            $table->string('payee_phone')->nullable();
            $table->string('category')->default('chi_nha_cung_cap');
            $table->string('payment_method')->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->index('category');
            $table->index('payment_method');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};