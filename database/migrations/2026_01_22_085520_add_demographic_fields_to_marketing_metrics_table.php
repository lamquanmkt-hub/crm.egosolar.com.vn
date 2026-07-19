<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            // nếu đang có cột date (daily) thì cho phép null để chuyển sang range
            if (Schema::hasColumn('marketing_metrics', 'date')) {
                $table->date('date')->nullable()->change();
            }

            // Range ngày
            $table->date('date_from')->nullable()->after('date');
            $table->date('date_to')->nullable()->after('date_from');

            // Phân loại lead
            $table->string('gender', 20)->nullable()->after('date_to');     // male/female/unknown
            $table->string('age_range', 30)->nullable()->after('gender');  // 18-24, 25-34...
            $table->string('region', 100)->nullable()->after('age_range'); // HCM, HN...

            // Số lead (dùng lại leads nếu có thì vẫn ok, nhưng thêm riêng để rõ)
            // Nếu bảng đã có 'leads' rồi thì bỏ dòng dưới.
            // $table->unsignedInteger('leads')->default(0)->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            $table->dropColumn(['date_from','date_to','gender','age_range','region']);
        });
    }
};
