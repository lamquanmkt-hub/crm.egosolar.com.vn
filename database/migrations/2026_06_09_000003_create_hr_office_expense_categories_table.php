<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_office_expense_categories')) {
            Schema::create('hr_office_expense_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            $defaults = [
                'Văn phòng phẩm',
                'Nước uống / tiếp khách',
                'Ship / gửi hàng',
                'Vệ sinh / tạp vụ',
                'Thiết bị văn phòng',
                'Bảo trì / sửa chữa',
                'Khác',
            ];

            foreach ($defaults as $name) {
                DB::table('hr_office_expense_categories')->insert([
                    'name' => $name,
                    'slug' => str($name)->slug('_')->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_office_expense_categories');
    }
};
