<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Lightweight, request-local schema cache used by the synchronized modules.
 * It keeps compatibility with installations that do not yet have this helper.
 */
final class SchemaCache
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, array<int, string>> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$tables)) {
            try {
                self::$tables[$table] = Schema::hasTable($table);
            } catch (\Throwable) {
                self::$tables[$table] = false;
            }
        }

        return self::$tables[$table];
    }

    /** @return array<int, string> */
    public static function columns(string $table): array
    {
        if (! array_key_exists($table, self::$columns)) {
            try {
                self::$columns[$table] = self::hasTable($table)
                    ? array_values(Schema::getColumnListing($table))
                    : [];
            } catch (\Throwable) {
                self::$columns[$table] = [];
            }
        }

        return self::$columns[$table];
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return in_array($column, self::columns($table), true);
    }

    public static function clear(): void
    {
        self::$tables = [];
        self::$columns = [];
    }

    public static function flush(): void
    {
        self::clear();
    }
}
