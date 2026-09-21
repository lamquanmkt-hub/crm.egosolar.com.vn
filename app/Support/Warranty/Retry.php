<?php

declare(strict_types=1);

namespace App\Support\Warranty;

use Illuminate\Database\QueryException;

/** Thử lại thao tác nghiệp vụ khi MySQL rollback vì deadlock (SQLSTATE 40001). Toàn bộ thao tác nằm trong 1 transaction nên retry an toàn. */
final class Retry
{
    public static function onDeadlock(callable $fn, int $times = 3): mixed
    {
        for ($i = 1; ; $i++) {
            try {
                return $fn();
            } catch (QueryException $e) {
                $state = (string) ($e->errorInfo[0] ?? $e->getCode());
                if ($state !== '40001' || $i >= $times) {
                    throw $e;
                }
                usleep(random_int(20_000, 120_000));
            }
        }
    }
}
