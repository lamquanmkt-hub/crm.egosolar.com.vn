<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm ràng buộc khóa ngoại (FK) cho các quan hệ đã kiểm chứng KHÔNG có dòng mồ côi.
 *
 * Nguyên tắc "không mất dữ liệu production":
 *  - Trước khi tạo mỗi FK, migration ĐẾM dòng mồ côi ngay lúc chạy. Nếu còn mồ côi,
 *    FK đó bị BỎ QUA (ghi cảnh báo) thay vì làm hỏng migration — deploy không bao giờ gãy.
 *  - FK trên cột nullable dùng ON DELETE SET NULL; cột NOT NULL dùng RESTRICT (chặn xóa
 *    cha khi còn con) để không âm thầm xóa dữ liệu.
 *  - Idempotent: bỏ qua nếu FK đã tồn tại hoặc cột/bảng không tồn tại.
 *
 * CỐ Ý KHÔNG thêm FK cho crm_order_edit_histories.order_id vì đang có ~51 dòng mồ côi
 * (order đã bị xóa cứng) — thêm FK sẽ buộc xóa lịch sử chỉnh sửa. Cần dọn dữ liệu thủ công trước.
 */
return new class extends Migration
{
    /**
     * Định nghĩa FK cần thêm.
     * Mỗi phần tử: [bảng con, cột, bảng cha, cột cha, hành động on delete, tên FK].
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    private array $foreignKeys = [
        ['customer_profiles', 'customer_id', 'crm_customers', 'id', 'set null', 'fk_cp_customer'],
        ['customer_profiles', 'price_tier_id', 'crm_price_tiers', 'id', 'set null', 'fk_cp_price_tier'],
        ['customer_profiles', 'created_by', 'users', 'id', 'set null', 'fk_cp_created_by'],
        ['crm_product_stock_lots', 'product_id', 'crm_product_catalog', 'id', 'restrict', 'fk_psl_product'],
        ['crm_product_stock_lots', 'warehouse_id', 'crm_warehouses', 'id', 'restrict', 'fk_psl_warehouse'],
        ['crm_product_stock_lots', 'company_id', 'companies', 'id', 'set null', 'fk_psl_company'],
        ['crm_order_edit_histories', 'user_id', 'users', 'id', 'set null', 'fk_oeh_user'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as [$childTable, $column, $parentTable, $parentCol, $onDelete, $fkName]) {
            if (! $this->canCreate($childTable, $column, $parentTable, $fkName)) {
                continue;
            }

            if ($this->countOrphans($childTable, $column, $parentTable, $parentCol) > 0) {
                $this->warn("Bỏ qua FK {$fkName}: bảng {$childTable}.{$column} còn dòng mồ côi — cần dọn dữ liệu trước.");

                continue;
            }

            Schema::table($childTable, function ($blueprint) use ($column, $parentTable, $parentCol, $onDelete, $fkName): void {
                $blueprint->foreign($column, $fkName)
                    ->references($parentCol)->on($parentTable)
                    ->onUpdate('cascade')
                    ->onDelete($onDelete);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys as [$childTable, $column, $parentTable, $parentCol, $onDelete, $fkName]) {
            if (! Schema::hasTable($childTable) || ! $this->foreignKeyExists($childTable, $fkName)) {
                continue;
            }
            Schema::table($childTable, function ($blueprint) use ($fkName): void {
                $blueprint->dropForeign($fkName);
            });
        }
    }

    /**
     * Điều kiện tiên quyết để có thể tạo FK.
     */
    private function canCreate(string $childTable, string $column, string $parentTable, string $fkName): bool
    {
        return Schema::hasTable($childTable)
            && Schema::hasTable($parentTable)
            && Schema::hasColumn($childTable, $column)
            && ! $this->foreignKeyExists($childTable, $fkName);
    }

    /**
     * Đếm số dòng con tham chiếu tới cha không tồn tại (mồ côi).
     */
    private function countOrphans(string $childTable, string $column, string $parentTable, string $parentCol): int
    {
        $result = DB::selectOne(
            "SELECT COUNT(*) AS c FROM `{$childTable}` c
             LEFT JOIN `{$parentTable}` p ON c.`{$column}` = p.`{$parentCol}`
             WHERE c.`{$column}` IS NOT NULL AND p.`{$parentCol}` IS NULL"
        );

        return (int) ($result->c ?? 0);
    }

    /**
     * Kiểm tra FK đã tồn tại trên bảng chưa (theo tên ràng buộc).
     */
    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = ?
               AND constraint_name = ? AND constraint_type = "FOREIGN KEY"',
            [$table, $fkName]
        );

        return (int) ($result->c ?? 0) > 0;
    }

    /**
     * Ghi cảnh báo ra output migration (nếu chạy qua console).
     */
    private function warn(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn($message);
        }
    }
};
