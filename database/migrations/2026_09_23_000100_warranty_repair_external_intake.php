<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sửa chữa tính phí ĐỘC LẬP: khách + thiết bị + lỗi là dữ liệu chính, không phụ thuộc Đơn hàng/Công trình/serial CRM.
 * Chỉ THÊM cột (additive) — snapshot khách/thiết bị ngay trên phiếu để thiết bị ngoài hệ thống vẫn tiếp nhận được.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            return;
        }

        Schema::table('crm_serial_warranty_claims', function (Blueprint $table): void {
            $add = function (string $col, callable $def) use ($table): void {
                if (! Schema::hasColumn('crm_serial_warranty_claims', $col)) {
                    $def($table);
                }
            };

            $add('customer_name', fn ($t) => $t->string('customer_name', 190)->nullable());
            $add('customer_phone', fn ($t) => $t->string('customer_phone', 40)->nullable()->index());
            $add('customer_email', fn ($t) => $t->string('customer_email', 190)->nullable());
            $add('customer_address', fn ($t) => $t->text('customer_address')->nullable());
            $add('customer_company', fn ($t) => $t->string('customer_company', 190)->nullable());

            $add('device_type', fn ($t) => $t->string('device_type', 120)->nullable());
            $add('device_brand', fn ($t) => $t->string('device_brand', 120)->nullable());
            $add('device_model', fn ($t) => $t->string('device_model', 190)->nullable());
            $add('device_accessories', fn ($t) => $t->text('device_accessories')->nullable());
            $add('received_condition', fn ($t) => $t->text('received_condition')->nullable());
            $add('delivered_by', fn ($t) => $t->string('delivered_by', 190)->nullable());
            $add('received_by', fn ($t) => $t->unsignedBigInteger('received_by')->nullable());
            $add('diagnosis_conclusion', fn ($t) => $t->text('diagnosis_conclusion')->nullable());
        });
    }

    public function down(): void
    {
        // additive — không xóa dữ liệu.
    }
};
