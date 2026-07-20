<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Carbon\Carbon;

/**
 * Hợp đồng Tích hợp tổng đài Callio (seam ra API bên ngoài — cho phép thay bằng fake khi test).
 *
 * Sinh từ implementation CallioService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface CallioServiceInterface
{
    public function enabled(): bool;

    public function fetchCallsForDate(Carbon $date, int $page = 1, int $pageSize = 15, ?string $keyword = null): array;

    public function fetchRecordingUrl(string $callId): array;
}
