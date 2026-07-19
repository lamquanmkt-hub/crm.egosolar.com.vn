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
    Schema::create('marketing_budgets', function (Blueprint $table) {
        $table->id();
        $table->string('platform', 50);          // Facebook, Google, TikTok...
        $table->date('month');                   // lưu dạng 2026-01-01 (đại diện tháng)
        $table->unsignedBigInteger('budget')->default(0);       // ngân sách dự kiến
        $table->unsignedBigInteger('actual_spent')->default(0); // đã chi
        $table->string('campaign')->nullable();  // tên chiến dịch (optional)
        $table->text('note')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['month', 'platform']);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_budgets');
    }
};
