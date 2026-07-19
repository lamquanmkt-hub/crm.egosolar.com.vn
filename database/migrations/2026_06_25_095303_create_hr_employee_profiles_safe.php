<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_employee_profiles')) {
            Schema::create('hr_employee_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->unique();
                $table->string('employee_code')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('official_date')->nullable();
                $table->date('probation_start_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->string('contract_type')->nullable();
                $table->date('contract_start_date')->nullable();
                $table->date('contract_end_date')->nullable();
                $table->date('birth_date')->nullable();
                $table->string('gender')->nullable();
                $table->string('id_card')->nullable();
                $table->date('id_card_date')->nullable();
                $table->string('id_card_place')->nullable();
                $table->text('address')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_account')->nullable();
                $table->string('tax_code')->nullable();
                $table->string('insurance_number')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->text('hr_note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_employee_files')) {
            Schema::create('hr_employee_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('file_type')->nullable();
                $table->string('original_name')->nullable();
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('employee_id');
                $table->index('file_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_profiles');
    }
};
