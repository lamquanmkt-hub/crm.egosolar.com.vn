<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_recruitment_requests')) {
            Schema::create('hr_recruitment_requests', function (Blueprint $table) {
                $table->id();
                $table->string('department')->nullable();
                $table->string('position')->nullable();
                $table->integer('quantity')->default(1);
                $table->string('reason')->nullable();
                $table->text('requirements')->nullable();
                $table->date('needed_date')->nullable();
                $table->text('job_description')->nullable();
                $table->string('expected_salary')->nullable();
                $table->string('approval_status')->default('pending');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_recruitment_candidates')) {
            Schema::create('hr_recruitment_candidates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recruitment_request_id')->nullable();
                $table->string('full_name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('position')->nullable();
                $table->string('source')->nullable();
                $table->string('status')->default('new');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('recruitment_request_id');
                $table->index('status');
                $table->index('source');
            });
        }

        if (!Schema::hasTable('hr_recruitment_interviews')) {
            Schema::create('hr_recruitment_interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->dateTime('interview_at')->nullable();
                $table->string('location')->nullable();
                $table->string('interview_form')->nullable();
                $table->string('interviewer')->nullable();
                $table->string('result')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('candidate_id');
                $table->index('interview_at');
            });
        }

        if (!Schema::hasTable('hr_recruitment_offers')) {
            Schema::create('hr_recruitment_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->date('offer_date')->nullable();
                $table->string('salary_offer')->nullable();
                $table->date('start_date')->nullable();
                $table->string('status')->default('draft');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('candidate_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_recruitment_offers');
        Schema::dropIfExists('hr_recruitment_interviews');
        Schema::dropIfExists('hr_recruitment_candidates');
        Schema::dropIfExists('hr_recruitment_requests');
    }
};
