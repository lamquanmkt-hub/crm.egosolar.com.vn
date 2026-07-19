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
    Schema::create('marketing_metrics', function (Blueprint $table) {
        $table->id();
        $table->date('date');                    // ngày chạy
        $table->string('platform', 50);          // Facebook/Google/...
        $table->string('campaign')->nullable();  // tên chiến dịch
        $table->unsignedBigInteger('spend')->default(0);   // chi tiêu
        $table->unsignedInteger('leads')->default(0);      // số lead
        $table->unsignedInteger('orders')->default(0);     // số đơn
        $table->unsignedBigInteger('revenue')->default(0); // doanh thu
        $table->text('note')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['date', 'platform']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_metrics');
    }
};
