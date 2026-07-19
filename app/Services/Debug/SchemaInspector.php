<?php
declare(strict_types=1);
namespace App\Services\Debug;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
final class SchemaInspector
{
    public function __construct(
        private readonly TableFilter $filter,
        private readonly TableMetadataReader $reader,
    ) {}
    /** @return array<int, array<string,mixed>> */
    public function tablesInfoByGroup(string $group): array
    {
        $tables = $this->tablesByGroup($group);
        $out = [];
        foreach ($tables as $table) {
            $out[] = $this->reader->tableInfo($table);
        }
        return $out;
    }
    /** @return array<string,mixed> */
    public function tablesSummaryByGroup(string $group): array
    {
        $tables = $this->tablesByGroup($group);
        return [
            'db' => DB::getDatabaseName(),
            'tables_count' => $this->reader->countAllTables(),
            'candidate_tables' => $tables,
        ];
    }
    /** @return array<int,string> */
    public function tablesLike(string $needle): array
    {
        return $this->reader->tablesLike($needle);
    }
    /** @return array<int,string> */
    private function tablesByGroup(string $group): array
    {
        $ttl = (int) config('debug_schema.cache_seconds', 30);
        return Cache::remember("debug_schema:group:{$group}", $ttl, function () use ($group) {
            $allTables = $this->reader->allTables();
            $keywords = (array) config("debug_schema.groups.{$group}", []);
            return $this->filter->byKeywords($allTables, $keywords);
        });
    }
    public function allTables(): array
    {
        $ttl = (int) config('debug_schema.cache_seconds', 30);
        if ($ttl <= 0) {
            return $this->reader->allTables();
        }
        return \Illuminate\Support\Facades\Cache::remember(
            'debug_schema:all_tables',
            $ttl,
            fn () => $this->reader->allTables()
        );
    }
}
