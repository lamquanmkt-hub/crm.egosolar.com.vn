<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;

/**
 * Công việc "Thủ tục hành chính HCNS" (nguồn KPI HC09) trong module Công việc dùng chung.
 *
 * Chỉ việc có task_type = hcns_admin mới vào KPI. Các mốc gửi/xác nhận/hủy là cột riêng, ghi từ lúc
 * triển khai; khi DB chưa áp migration thì mọi chức năng HCNS tự ẩn, module Công việc chạy như cũ.
 */
final class HcnsAdminTasks
{
    public const TYPE = 'hcns_admin';

    private const REQUIRED_COLUMNS = [
        'hcns_procedure_type', 'original_due_at', 'draft_saved_at', 'first_submitted_at', 'last_submitted_at',
        'submission_count', 'approved_by', 'approved_at', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    /** DB đã có đủ cột nguồn HC09 chưa. */
    public static function ready(): bool
    {
        if (! SchemaCache::hasTable('tasks')) {
            return false;
        }
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! SchemaCache::hasColumn('tasks', $column)) {
                return false;
            }
        }

        return true;
    }

    public static function hasColumn(string $column): bool
    {
        return SchemaCache::hasColumn('tasks', $column);
    }

    public static function is(?object $task): bool
    {
        return $task !== null && (string) ($task->task_type ?? '') === self::TYPE;
    }

    /** @return array<string, string> */
    public static function procedureTypes(): array
    {
        return (array) config('hcns_kpi.procedure_types', []);
    }

    /**
     * Hạn từ form: ngày (Y-m-d hoặc datetime) + giờ (H:i). Thiếu giờ thì giữ 17:00 để người giao thấy và sửa,
     * không tự đẩy về 00:00 đầu ngày.
     */
    public static function dueFromInput(?string $date, ?string $time): ?Carbon
    {
        if ($date === null || trim($date) === '') {
            return null;
        }
        $day = Carbon::parse($date, config('app.timezone'));
        $time = trim((string) $time);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
            [$h, $m] = array_map('intval', explode(':', $time));

            return $day->copy()->setTime($h, $m, 0);
        }

        return $day->hour === 0 && $day->minute === 0 ? $day->copy()->setTime(17, 0, 0) : $day;
    }

    public static function isCancelled(?object $task): bool
    {
        return $task !== null && (string) ($task->status ?? '') === 'cancelled';
    }

    /**
     * Kỳ KPI đã chốt (bảng lương HR tháng đó đã duyệt) mà thủ tục thuộc về — theo hạn hiện tại hoặc hạn gốc.
     * Trả về tháng 'Y-m' bị khóa, hoặc null nếu được sửa. Chỉ áp cho thủ tục HCNS, không khóa việc thường/kỹ thuật.
     */
    public static function lockedMonth(?object $task, ?string $newDue = null): ?string
    {
        if (! self::is($task) && $newDue === null) {
            return null;
        }
        $months = collect([$task->due_at ?? null, $task->original_due_at ?? null, $newDue])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))
            ->unique()
            ->values()
            ->all();

        return HcnsKpiRules::lockedMonth($months);
    }

    public static function lockMessage(string $month): string
    {
        return 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — nguồn HC09 của thủ tục này bị khóa. Mở lại kỳ lương theo quy trình nếu cần sửa.';
    }
}
