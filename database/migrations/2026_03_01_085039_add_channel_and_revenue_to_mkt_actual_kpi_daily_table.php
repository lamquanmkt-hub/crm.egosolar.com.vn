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
        Schema::table('mkt_actual_kpi_daily', function (Blueprint $table) {

            // Nếu bảng đã có channel thì sẽ không lỗi khi migrate lại
            if (!Schema::hasColumn('mkt_actual_kpi_daily', 'channel')) {
                $table->string('channel', 100)
                      ->nullable()
                      ->after('date')
                      ->comment('Tên kênh quảng cáo: Facebook, Google, TikTok...');
            }

            if (!Schema::hasColumn('mkt_actual_kpi_daily', 'revenue')) {
                $table->decimal('revenue', 15, 2)
                      ->default(0)
                      ->after('leads')
                      ->comment('Doanh thu ghi nhận từ ads');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mkt_actual_kpi_daily', function (Blueprint $table) {

            if (Schema::hasColumn('mkt_actual_kpi_daily', 'channel')) {
                $table->dropColumn('channel');
            }

            if (Schema::hasColumn('mkt_actual_kpi_daily', 'revenue')) {
                $table->dropColumn('revenue');
            }
        });
    }
};