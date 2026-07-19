<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addConditionColumn(string $tableName): void
    {
        if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'item_condition')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('item_condition')->nullable()->after('id');
            });
        }
    }

    public function up(): void
    {
        $this->addConditionColumn('hr_operation_expenses');
        $this->addConditionColumn('hr_operation_assets');
        $this->addConditionColumn('hr_operation_suppliers');
        $this->addConditionColumn('hr_operation_tasks');
    }

    public function down(): void
    {
        foreach ([
            'hr_operation_expenses',
            'hr_operation_assets',
            'hr_operation_suppliers',
            'hr_operation_tasks',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'item_condition')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('item_condition');
                });
            }
        }
    }
};
