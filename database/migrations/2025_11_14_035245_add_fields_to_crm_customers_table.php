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
	    Schema::table('crm_customers', function (Blueprint $table) {
		    // Phân biệt Khách hàng (lead) vs Hội viên (member)
		    $table->enum('customer_status', ['lead', 'member'])
		          ->default('lead')
		          ->after('customer_type');

		    // Các trường từ giao diện
		    $table->string('nickname', 255)
		          ->nullable()
		          ->after('name'); // BIỆT DANH
		    $table->string('group_note', 255)
		          ->nullable()
		          ->after('region_id'); // GHI CHÚ NHÓM
		    $table->foreignId('owner_id')
		          ->nullable()
		          ->after('group_note') // CHI CHỦ (người phụ trách chính)
		          ->constrained('users')
		          ->nullOnDelete();

		    // Thông tin bổ sung
		    $table->string('email', 255)
		          ->nullable()
		          ->after('phone');
		    $table->text('address')
		          ->nullable()
		          ->after('region_id');

		    // Đánh dấu khách hàng tiềm năng
		    $table->boolean('is_potential')
		          ->default(false)
		          ->after('customer_status');

		    // Timestamp
		    $table->timestamp('converted_to_member_at')
		          ->nullable()
		          ->after('is_potential'); // Ngày chuyển thành hội viên
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('crm_customers', function (Blueprint $table) {
		    $table->dropForeign(['owner_id']);
		    $table->dropColumn([
			    'customer_status',
			    'nickname',
			    'group_note',
			    'owner_id',
			    'email',
			    'address',
			    'is_potential',
			    'converted_to_member_at',
		    ]);
	    });
    }
};
