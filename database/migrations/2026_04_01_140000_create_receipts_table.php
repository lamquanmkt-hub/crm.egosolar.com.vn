<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('receipt_date');
            $table->string('payer_name');
            $table->string('payer_phone')->nullable();
            $table->string('category')->default('thu_khach_hang');
            $table->string('payment_method')->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('receipt_date');
            $table->index('category');
            $table->index('payment_method');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};