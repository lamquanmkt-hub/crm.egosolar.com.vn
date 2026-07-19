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
        Schema::table('crm_payment_methods', function (Blueprint $table) {
            // Thêm cột code, cho phép null trước để không lỗi dữ liệu cũ
            $table->string('code', 50)->nullable()->after('method_name');

            // Thêm cột is_active, mặc định là true (đang hoạt động)
            $table->boolean('is_active')->default(true)->after('method_name');

            // Nếu bảng cũ chưa có description thì thêm luôn
            if (!Schema::hasColumn('crm_payment_methods', 'description')) {
                $table->text('description')->nullable()->after('method_name');
            }
        });

        // DATA MIGRATION: Cập nhật code cho các dòng dữ liệu cũ (nếu có)
        // Đây là bước quan trọng để đảm bảo tính toàn vẹn dữ liệu
        $methods = DB::table('crm_payment_methods')->get();
        foreach ($methods as $method) {
            // Tự động sinh code từ tên: "Tiền mặt" -> "TIEN_MAT"
            $code = strtoupper(Str::slug($method->method_name, '_'));
            DB::table('crm_payment_methods')
                ->where('id', $method->id)
                ->update(['code' => $code]);
        }

        // Sau khi đã điền dữ liệu, ta sửa lại cột code thành UNIQUE và NOT NULL
        Schema::table('crm_payment_methods', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['code', 'is_active', 'description']);
        });
    }
};
