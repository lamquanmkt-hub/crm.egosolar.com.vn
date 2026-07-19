<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();

            // Thông tin lead
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable();

            // Thông tin marketing
            $table->string('source')->nullable();      // Facebook Ads / Google Ads / TikTok...
            $table->string('campaign')->nullable();    // tên chiến dịch
            $table->string('adset')->nullable();       // nhóm quảng cáo
            $table->string('ad')->nullable();          // mẫu QC

            // Quản trị lead
            $table->string('status')->default('new')->index(); // new, contacted, qualified, won, lost...
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index(); // gán cho sale
            $table->unsignedBigInteger('imported_by')->nullable()->index();      // ai upload
            $table->date('import_date')->nullable()->index();                    // ngày import (cuối tuần)

            $table->text('note')->nullable();

            $table->timestamps();

            // FK (nếu bảng users tồn tại)
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
