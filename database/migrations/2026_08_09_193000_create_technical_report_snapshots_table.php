<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_report_snapshots')) {
            return;
        }

        Schema::create('technical_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('report_code', 64)->unique();
            $table->string('report_type', 32)->index();
            $table->string('title');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->json('filters')->nullable();
            $table->json('summary')->nullable();
            $table->json('columns')->nullable();
            $table->longText('rows')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->string('generated_name')->nullable();
            $table->dateTime('generated_at')->index();
            $table->unsignedInteger('notification_count')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'report_type', 'generated_at'], 'tech_report_company_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_report_snapshots');
    }
};
