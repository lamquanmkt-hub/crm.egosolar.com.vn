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
Schema::create('payment_requests', function (Blueprint $table) {
    $table->id();

    $table->string('code')->unique();
    $table->foreignId('created_by')->constrained('users');

    $table->string('receiver_name');
    $table->string('department')->nullable();
    $table->string('reason');
    $table->unsignedBigInteger('amount');
    $table->string('bank_info')->nullable();

    $table->enum('status', [
        'draft','submitted',
        'admin_approved','admin_rejected',
        'accounting_approved','accounting_rejected',
    ])->default('draft');

    $table->foreignId('admin_approved_by')->nullable()->constrained('users');
    $table->timestamp('admin_approved_at')->nullable();
    $table->text('admin_note')->nullable();

    $table->foreignId('accounting_approved_by')->nullable()->constrained('users');
    $table->timestamp('accounting_approved_at')->nullable();
    $table->text('accounting_note')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
