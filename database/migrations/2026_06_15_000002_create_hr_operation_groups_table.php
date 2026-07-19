<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_operation_groups')) {
            Schema::create('hr_operation_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_key', 80)->unique();
                $table->string('name', 190);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $defaults = [
            ['group_key' => 'office_expense', 'name' => 'Chi phí văn phòng', 'sort_order' => 1],
            ['group_key' => 'asset', 'name' => 'Quản lý tài sản', 'sort_order' => 2],
            ['group_key' => 'supplier', 'name' => 'Quản lý đơn vị cung cấp', 'sort_order' => 3],
            ['group_key' => 'admin_task', 'name' => 'Công việc HC vận hành', 'sort_order' => 4],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('hr_operation_groups')->where('group_key', $row['group_key'])->exists();

            if (!$exists) {
                DB::table('hr_operation_groups')->insert([
                    'group_key' => $row['group_key'],
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_operation_groups');
    }
};
