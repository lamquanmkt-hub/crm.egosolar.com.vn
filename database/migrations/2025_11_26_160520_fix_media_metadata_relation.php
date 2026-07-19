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
	    Schema::table('media_metadata', function (Blueprint $table) {
		    // Xóa morphs mediable_* nếu đã tồn tại
		    if (Schema::hasColumn('media_metadata', 'mediable_id')) {
			    $table->dropMorphs('mediable');
		    }
		    // Thêm khóa ngoại đúng: metadata thuộc về media_files
		    $table->foreignId('media_id')
		          ->after('id')
		          ->constrained('media_files')
		          ->cascadeOnDelete();
	    });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::table('media_metadata', function (Blueprint $table) {
		    // Xóa foreign key mới
		    if (Schema::hasColumn('media_metadata', 'media_id')) {
			    $table->dropConstrainedForeignId('media_id');
		    }
		    // Khôi phục morphs nếu rollback
		    $table->morphs('mediable');
	    });
    }
};
