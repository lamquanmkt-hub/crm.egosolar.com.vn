<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI kỹ thuật KS06 (bảo hành, từ 10/2026): mốc SLA và kiểm tra khiếu nại trên phiếu bảo trì/bảo hành.
 *
 * Chỉ THÊM cột nullable. Không backfill: hồ sơ cũ không có mốc khách báo/phản hồi thật nên phải tiếp tục
 * hiện "thiếu dữ liệu", không suy từ trạng thái, created_at hay updated_at.
 * Mốc hoàn thành xử lý dùng cột có sẵn `execution_finished_at` (ghi khi bấm "Hoàn tất thực hiện").
 */
return new class extends Migration
{
    private const TABLE = 'solar_maintenance_schedules';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'customer_reported_at')) {
                $table->dateTime('customer_reported_at')->nullable()->comment('Thời điểm khách báo/tiếp nhận thông tin bảo hành');
            }
            if (! Schema::hasColumn(self::TABLE, 'customer_reported_by')) {
                $table->unsignedBigInteger('customer_reported_by')->nullable()->comment('Người ghi nhận mốc khách báo');
            }
            if (! Schema::hasColumn(self::TABLE, 'first_responded_at')) {
                $table->dateTime('first_responded_at')->nullable()->comment('Thời điểm phản hồi khách hàng lần đầu');
            }
            if (! Schema::hasColumn(self::TABLE, 'first_responded_by')) {
                $table->unsignedBigInteger('first_responded_by')->nullable()->comment('Người phản hồi lần đầu');
            }
            if (! Schema::hasColumn(self::TABLE, 'complaint_status')) {
                // null = chưa kiểm tra; none = đã kiểm tra, không khiếu nại; has_complaint = có khiếu nại.
                $table->string('complaint_status', 20)->nullable()->comment('Kết quả kiểm tra khiếu nại');
            }
            if (! Schema::hasColumn(self::TABLE, 'complaint_note')) {
                $table->text('complaint_note')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'complaint_checked_by')) {
                $table->unsignedBigInteger('complaint_checked_by')->nullable()->comment('Người xác nhận kiểm tra khiếu nại');
            }
            if (! Schema::hasColumn(self::TABLE, 'complaint_checked_at')) {
                $table->dateTime('complaint_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = array_values(array_filter([
            'customer_reported_at', 'customer_reported_by', 'first_responded_at', 'first_responded_by',
            'complaint_status', 'complaint_note', 'complaint_checked_by', 'complaint_checked_at',
        ], fn ($c) => Schema::hasColumn(self::TABLE, $c)));

        if ($columns !== []) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
