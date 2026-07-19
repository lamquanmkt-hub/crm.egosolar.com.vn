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
	    // Bảng định nghĩa template import cho từng loại
	    Schema::create('crm_import_templates', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255); // "Khách hàng chuẩn", "Lead từ Facebook"...
		    $table->enum('import_type', ['customers', 'leads', 'products', 'orders']);
		    $table->json('column_mappings'); // Map cột Excel → field database
		    $table->json('validation_rules')->nullable(); // Laravel validation rules
		    $table->json('default_values')->nullable(); // Giá trị mặc định
		    $table->boolean('is_default')->default(false);
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
	    // Bảng lưu lịch sử import dữ liệu
	    Schema::create('crm_import_history', function (Blueprint $table) {
		    $table->id();
		    $table->enum('import_type', ['customers', 'leads', 'products', 'orders']); // Loại import
		    $table->foreignId('template_id')->nullable()
		          ->constrained('crm_import_templates')->nullOnDelete();
		    $table->boolean('has_header_row')->default(true);
		    $table->integer('start_row')->default(2); // Bắt đầu từ dòng nào
		    $table->json('preview_data')->nullable(); // 5 dòng đầu để preview

		    $table->string('file_name', 255); // Tên file gốc
		    $table->string('file_path', 511); // Đường dẫn file đã upload
		    $table->integer('total_rows')->default(0); // Tổng số dòng
		    $table->integer('success_rows')->default(0); // Số dòng thành công
		    $table->integer('failed_rows')->default(0); // Số dòng lỗi
		    $table->json('errors')->nullable(); // Chi tiết lỗi dạng JSON
		    $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
		    $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();

		    $table->timestamps();
	    });

	    // Bảng mapping cho việc import (để biết cột nào map với field nào)
	    Schema::create('crm_import_mappings', function (Blueprint $table) {
		    $table->id();
		    $table->enum('import_type', ['customers', 'leads', 'products', 'orders']);
		    $table->string('source_column', 255); // Tên cột trong file Excel/CSV
		    $table->string('target_field', 255); // Tên field trong database
		    $table->boolean('is_required')->default(false);
		    $table->string('data_type', 50)->nullable(); // string/integer/date/...
		    $table->text('transform_rule')->nullable(); // Rule chuyển đổi dữ liệu (nếu có)
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();

		    $table->unique(['import_type', 'source_column']);
	    });

	    // Bảng lưu các thao tác bulk update
	    Schema::create('crm_bulk_operations', function (Blueprint $table) {
		    $table->id();
		    $table->enum('operation_type', ['update', 'delete', 'assign', 'tag', 'export']); // Loại thao tác
		    $table->string('target_model', 100); // Model bị tác động (Customer, Lead, Order...)
		    $table->json('target_ids'); // Danh sách ID bị ảnh hưởng
		    $table->json('changes')->nullable(); // Chi tiết thay đổi
		    $table->integer('affected_count')->default(0); // Số bản ghi bị ảnh hưởng
		    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
		    $table->text('error_message')->nullable();
		    $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });

	    // Bảng tags để gắn nhãn cho khách hàng
	    Schema::create('crm_tags', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 100)->unique();
		    $table->string('color', 20)->nullable(); // Màu hiển thị
		    $table->text('description')->nullable();
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });

	    // Bảng pivot: khách hàng - tags (nhiều-nhiều)
	    Schema::create('crm_customer_tags', function (Blueprint $table) {
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
		    $table->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamp('tagged_at')->useCurrent();

		    $table->primary(['customer_id', 'tag_id']);
	    });


	    // Bảng lưu chi tiết từng dòng import (để xem lỗi cụ thể)
	    Schema::create('crm_import_details', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('import_history_id')->constrained('crm_import_history')->cascadeOnDelete();
		    $table->integer('row_number'); // Dòng số mấy trong file
		    $table->json('raw_data'); // Dữ liệu gốc từ Excel
		    $table->json('processed_data')->nullable(); // Dữ liệu sau khi transform
		    $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
		    $table->json('errors')->nullable(); // Lỗi validation cụ thể
		    $table->unsignedBigInteger('created_record_id')->nullable(); // ID record đã tạo (nếu thành công)
		    $table->string('created_record_type', 100)->nullable(); // Model name
		    $table->timestamps();

		    $table->index(['import_history_id', 'status']);
	    });

	    // Bảng lưu giá trị lookup để validate (VD: nguồn hợp lệ, trạng thái hợp lệ...)
	    Schema::create('crm_import_lookups', function (Blueprint $table) {
		    $table->id();
		    $table->string('lookup_type', 100); // 'source', 'status', 'customer_type'...
		    $table->string('excel_value', 255); // Giá trị trong Excel (VD: "FB Ads")
		    $table->string('database_value', 255); // Giá trị trong DB hoặc ID
		    $table->foreignId('reference_id')->nullable(); // ID trong bảng tham chiếu
		    $table->string('reference_table', 100)->nullable(); // crm_sources, crm_lead_statuses...
		    $table->boolean('is_active')->default(true);
		    $table->timestamps();

		    $table->unique(['lookup_type', 'excel_value']);
	    });

	    // Bảng cấu hình auto-mapping (AI suggest mapping)
	    Schema::create('crm_import_auto_mappings', function (Blueprint $table) {
		    $table->id();
		    $table->json('possible_column_names'); // ["Tên", "Họ tên", "Name", "Full Name"]
		    $table->string('target_field', 100); // 'name'
		    $table->string('target_table', 100); // 'crm_customers'
		    $table->integer('priority')->default(0); // Độ ưu tiên khi có nhiều match
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_customer_tags');
	    Schema::dropIfExists('crm_tags');
	    Schema::dropIfExists('crm_bulk_operations');
	    Schema::dropIfExists('crm_import_mappings');
	    Schema::dropIfExists('crm_import_history');
	    Schema::dropIfExists('crm_import_auto_mappings');
	    Schema::dropIfExists('crm_import_lookups');
	    Schema::dropIfExists('crm_import_details');
	    Schema::dropIfExists('crm_import_templates');
    }
};
