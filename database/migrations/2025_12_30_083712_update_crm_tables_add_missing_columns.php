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
        // 1. Cập nhật bảng Users (Nhân viên)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number', 20)->nullable()->after('email')->index(); // Index để tìm nhanh
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar', 500)->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password'); // Khóa tài khoản thay vì xóa
            }
        });
        // 2. Cập nhật bảng Sản phẩm (Product Catalog)
        Schema::table('crm_product_catalog', function (Blueprint $table) {
            // Yêu cầu riêng: Thêm mô tả
            if (!Schema::hasColumn('crm_product_catalog', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            // Ảnh đại diện (để load nhanh danh sách, tránh join bảng media)
            if (!Schema::hasColumn('crm_product_catalog', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('description');
            }
            // Mã vạch (Barcode)
            if (!Schema::hasColumn('crm_product_catalog', 'barcode')) {
                $table->string('barcode', 50)->nullable()->unique()->after('sku');
            }
            // Trạng thái ẩn/hiện sản phẩm
            if (!Schema::hasColumn('crm_product_catalog', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('quantity');
            }
            // Index bổ sung cho tìm kiếm tên
            $table->index('name');
        });
        // 3. Cập nhật bảng Đơn hàng (Orders) - QUAN TRỌNG: Lưu snapshot tài chính & địa chỉ
        Schema::table('crm_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_orders', 'shipping_address')) {
                // Lưu cứng địa chỉ tại thời điểm mua (tránh việc khách đổi địa chỉ làm sai đơn cũ)
                $table->text('shipping_address')->nullable()->after('warehouse_id');
            }
            if (!Schema::hasColumn('crm_orders', 'shipping_fee')) {
                $table->decimal('shipping_fee', 15, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('crm_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('shipping_fee');
            }
            if (!Schema::hasColumn('crm_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            }
            // Index bổ sung cho báo cáo (Tìm đơn theo ngày và trạng thái)
            $table->index(['created_at', 'current_status_type_id'], 'idx_orders_report');
        });
        // 4. Cập nhật chi tiết đơn hàng (Order Items) - Snapshot tên sản phẩm
        Schema::table('crm_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_order_items', 'product_name')) {
                // Lưu tên sản phẩm tại thời điểm bán (đề phòng sau này đổi tên sp)
                $table->string('product_name', 255)->nullable()->after('product_id');
            }
        });
        // 5. Cập nhật bảng Khách hàng (Customers) - Tối ưu tìm kiếm
        Schema::table('crm_customers', function (Blueprint $table) {
            // Sale hay tìm khách bằng SĐT và Email nhất -> Cần Index
            $table->index('phone');
            $table->index('email');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Xóa các cột đã thêm nếu rollback
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'avatar', 'is_active']);
        });
        Schema::table('crm_product_catalog', function (Blueprint $table) {
            $table->dropColumn(['description', 'image_url', 'barcode', 'is_active']);
            $table->dropIndex(['name']);
        });
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_address', 'shipping_fee', 'discount_amount', 'tax_amount']);
            $table->dropIndex('idx_orders_report');
        });
        Schema::table('crm_order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name']);
        });
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
        });
    }
};