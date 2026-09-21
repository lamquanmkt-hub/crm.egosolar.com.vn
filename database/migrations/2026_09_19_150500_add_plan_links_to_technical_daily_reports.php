<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nối báo cáo ngày với kế hoạch tuần (giai đoạn 2).
 *
 * CHỈ thêm những cột chưa có. Các cột đã tồn tại từ giai đoạn 1 được dùng lại
 * nguyên trạng, KHÔNG tạo bản trùng:
 *   - `work_hours`      = thời gian thực tế (giờ)
 *   - `issues_note`     = vấn đề phát sinh
 *   - `next_plan`       = kế hoạch xử lý tiếp theo
 *   - `materials_note`  = vật tư đã dùng
 *   - `progress_percent`= % hoàn thành
 *   - `report_date`     = ngày thực hiện
 *
 * `plan_item_id` dùng nullOnDelete: XOÁ KẾ HOẠCH KHÔNG BAO GIỜ XOÁ BÁO CÁO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return;
        }

        Schema::table('technical_daily_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('technical_daily_reports', 'plan_item_id')) {
                $table->unsignedBigInteger('plan_item_id')->nullable()->after('source_id')->index();
            }

            if (! Schema::hasColumn('technical_daily_reports', 'week_plan_id')) {
                $table->unsignedBigInteger('week_plan_id')->nullable()->after('plan_item_id')->index();
            }

            if (! Schema::hasColumn('technical_daily_reports', 'is_unplanned')) {
                $table->boolean('is_unplanned')->default(false)->after('week_plan_id')->index();
            }

            if (! Schema::hasColumn('technical_daily_reports', 'unplanned_reason')) {
                $table->text('unplanned_reason')->nullable()->after('is_unplanned');
            }

            if (! Schema::hasColumn('technical_daily_reports', 'result_achieved')) {
                $table->text('result_achieved')->nullable()->after('content');
            }

            if (! Schema::hasColumn('technical_daily_reports', 'not_done_reason')) {
                $table->text('not_done_reason')->nullable()->after('issues_note');
            }
        });

        // Khoá ngoại đặt riêng để migration vẫn idempotent khi chạy lại.
        if (
            Schema::hasTable('technical_plan_items')
            && Schema::hasColumn('technical_daily_reports', 'plan_item_id')
            && ! $this->hasForeignKey('technical_daily_reports', 'tdr_plan_item_fk')
        ) {
            Schema::table('technical_daily_reports', function (Blueprint $table): void {
                $table->foreign('plan_item_id', 'tdr_plan_item_fk')
                    ->references('id')->on('technical_plan_items')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            return;
        }

        if ($this->hasForeignKey('technical_daily_reports', 'tdr_plan_item_fk')) {
            Schema::table('technical_daily_reports', function (Blueprint $table): void {
                $table->dropForeign('tdr_plan_item_fk');
            });
        }

        /*
         * Chỉ gỡ các cột KHÔNG chứa dữ liệu nghiệp vụ. Nếu đã có báo cáo dùng
         * tới cột nào thì giữ nguyên cột đó — down() không được làm mất dữ liệu.
         */
        foreach ([
            'plan_item_id', 'week_plan_id', 'is_unplanned', 'unplanned_reason',
            'result_achieved', 'not_done_reason',
        ] as $column) {
            if (! Schema::hasColumn('technical_daily_reports', $column)) {
                continue;
            }

            $inUse = $column === 'is_unplanned'
                ? \Illuminate\Support\Facades\DB::table('technical_daily_reports')->where($column, 1)->exists()
                : \Illuminate\Support\Facades\DB::table('technical_daily_reports')->whereNotNull($column)->exists();

            if ($inUse) {
                continue;
            }

            Schema::table('technical_daily_reports', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = "FOREIGN KEY"',
                [$table, $name],
            );

            return $rows !== [];
        } catch (\Throwable) {
            return false;
        }
    }
};
