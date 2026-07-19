<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_commissions', function (Blueprint $table) {
            $table->id();

            // đơn hàng nào
            $table->unsignedBigInteger('order_id');

            // khách hàng nào
            $table->unsignedBigInteger('customer_id');

            // sales nào hưởng hoa hồng
            $table->unsignedBigInteger('sales_user_id');

            // số tiền dùng để tính hoa hồng
            $table->decimal('base_amount', 15, 2)->default(0);

            // % hoa hồng (vd: 0.5 = 0.5%)
            $table->decimal('rate', 8, 4)->default(0);

            // tiền hoa hồng
            $table->decimal('commission_amount', 15, 2)->default(0);

            // trạng thái
            // pending: mới tạo
            // approved: đã duyệt
            // paid: đã chi
            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
    }
};
