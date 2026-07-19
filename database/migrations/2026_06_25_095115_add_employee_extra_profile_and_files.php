<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumn($name, $callback): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', $name)) {
            Schema::table('employees', function (Blueprint $table) use ($callback) {
                $callback($table);
            });
        }
    }

    public function up(): void
    {
        $this->addColumn('employee_code', fn($table) => $table->string('employee_code')->nullable());
        $this->addColumn('hire_date', fn($table) => $table->date('hire_date')->nullable());
        $this->addColumn('official_date', fn($table) => $table->date('official_date')->nullable());
        $this->addColumn('probation_start_date', fn($table) => $table->date('probation_start_date')->nullable());
        $this->addColumn('probation_end_date', fn($table) => $table->date('probation_end_date')->nullable());
        $this->addColumn('contract_type', fn($table) => $table->string('contract_type')->nullable());
        $this->addColumn('contract_start_date', fn($table) => $table->date('contract_start_date')->nullable());
        $this->addColumn('contract_end_date', fn($table) => $table->date('contract_end_date')->nullable());
        $this->addColumn('birth_date', fn($table) => $table->date('birth_date')->nullable());
        $this->addColumn('gender', fn($table) => $table->string('gender')->nullable());
        $this->addColumn('id_card', fn($table) => $table->string('id_card')->nullable());
        $this->addColumn('id_card_date', fn($table) => $table->date('id_card_date')->nullable());
        $this->addColumn('id_card_place', fn($table) => $table->string('id_card_place')->nullable());
        $this->addColumn('address', fn($table) => $table->text('address')->nullable());
        $this->addColumn('bank_name', fn($table) => $table->string('bank_name')->nullable());
        $this->addColumn('bank_account', fn($table) => $table->string('bank_account')->nullable());
        $this->addColumn('tax_code', fn($table) => $table->string('tax_code')->nullable());
        $this->addColumn('insurance_number', fn($table) => $table->string('insurance_number')->nullable());
        $this->addColumn('emergency_contact_name', fn($table) => $table->string('emergency_contact_name')->nullable());
        $this->addColumn('emergency_contact_phone', fn($table) => $table->string('emergency_contact_phone')->nullable());
        $this->addColumn('hr_note', fn($table) => $table->text('hr_note')->nullable());

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
        Schema::dropIfExists('hr_employee_files');
    }
};
