<?php
declare(strict_types=1);
namespace App\Services\Debug;
final class TableFilter
{
    /** @param array<int,string> $tables @param array<int,string> $keywords */
    public function byKeywords(array $tables, array $keywords): array
    {
        $keywords = array_values(array_filter(array_map('strtolower', $keywords)));
        return array_values(array_filter($tables, function (string $table) use ($keywords) {
            $t = strtolower($table);
            foreach ($keywords as $k) {
                if ($k !== '' && str_contains($t, $k)) {
                    return true;
                }
            }
            return false;
        }));
    }
}
