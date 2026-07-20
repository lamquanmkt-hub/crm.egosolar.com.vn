<?php

declare(strict_types=1);

namespace App\Services\Debug;

use Illuminate\Support\Facades\DB;

/**
 * Service debug: đọc metadata bảng từ information_schema (danh sách bảng, cột).
 */
final class TableMetadataReader
{
    /** @return array<int,string> */
    public function allTables(): array
    {
        // MariaDB/MySQL: lấy từ information_schema.tables
        $db = DB::getDatabaseName();

        return array_map(
            fn ($r) => $r->table_name,
            DB::select(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name',
                [$db]
            )
        );
    }

    /**
     * Đếm tổng số bảng trong database hiện tại.
     */
    public function countAllTables(): int
    {
        $db = DB::getDatabaseName();

        return (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
            [$db]
        )->c ?? 0);
    }

    /** @return array<int,string> */
    public function tablesLike(string $needle): array
    {
        $db = DB::getDatabaseName();
        $needle = '%'.strtolower($needle).'%';

        return array_map(
            fn ($r) => $r->table_name,
            DB::select(
                'SELECT table_name
                 FROM information_schema.tables
                 WHERE table_schema = ? AND LOWER(table_name) LIKE ?
                 ORDER BY table_name',
                [$db, $needle]
            )
        );
    }

    /** @return array<string,mixed> */
    public function tableInfo(string $tableName): array
    {
        try {
            $db = DB::getDatabaseName();
            $maxCols = (int) config('debug_schema.max_columns', 30);
            // Không ghép trực tiếp tableName vào SQL "SHOW COLUMNS FROM `...`"
            // mà query information_schema.columns + bind param
            $cols = DB::select(
                'SELECT LOWER(column_name) AS c
                 FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ?
                 ORDER BY ordinal_position
                 LIMIT '.$maxCols,
                [$db, $tableName]
            );
            $columns = array_map(fn ($r) => $r->c, $cols);
            $hasNameLike = in_array('name', $columns, true)
                || in_array('product_name', $columns, true)
                || in_array('title', $columns, true);

            return [
                'table' => $tableName,
                'has_name_like' => $hasNameLike,
                'cols' => $columns,
            ];
        } catch (\Throwable $e) {
            return [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ];
        }
    }
}
