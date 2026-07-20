<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm index hiệu năng cho các cột thường được lọc trên bảng crm_orders.
 *
 * An toàn tuyệt đối (safe-online, additive): chỉ thêm index, không đổi dữ liệu,
 * không đổi cấu trúc cột. InnoDB tạo index online nên không khóa ghi lâu.
 * Mỗi index chỉ được tạo khi cột tồn tại và index chưa có sẵn (idempotent).
 */
return new class extends Migration
{
    /**
     * Danh sách index cần thêm: tên index => cột.
     *
     * @var array<string, string>
     */
    private array $indexes = [
        'idx_orders_order_date' => 'order_date',
        'idx_orders_shipping_status' => 'shipping_status',
        'idx_orders_current_department' => 'current_department',
        'idx_orders_invoice_status' => 'invoice_status',
    ];

    private string $table = 'crm_orders';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        foreach ($this->indexes as $name => $column) {
            if (! Schema::hasColumn($this->table, $column)) {
                continue;
            }
            if ($this->indexExists($name)) {
                continue;
            }

            Schema::table($this->table, function ($blueprint) use ($name, $column): void {
                $blueprint->index($column, $name);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        foreach (array_keys($this->indexes) as $name) {
            if (! $this->indexExists($name)) {
                continue;
            }
            Schema::table($this->table, function ($blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }

    /**
     * Kiểm tra một index đã tồn tại trên bảng hay chưa.
     */
    private function indexExists(string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$this->table, $indexName]
        );

        return (int) ($result->c ?? 0) > 0;
    }
};
