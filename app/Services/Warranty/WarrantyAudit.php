<?php

declare(strict_types=1);

namespace App\Services\Warranty;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi lịch sử BẤT BIẾN cho phiếu Bảo hành / Sửa chữa (chỉ INSERT).
 * Mỗi dòng: action, from/to status, dữ liệu trước/sau, lý do, user, IP, đối tượng liên quan.
 */
final class WarrantyAudit
{
    public static function log(
        int $claimId,
        string $action,
        ?string $from = null,
        ?string $to = null,
        mixed $before = null,
        mixed $after = null,
        ?string $reason = null,
        ?int $userId = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): void {
        if (! Schema::hasTable('warranty_claim_events')) {
            return;
        }

        $ip = null;
        try {
            $ip = request()?->ip();
        } catch (\Throwable) {
            $ip = null;
        }

        DB::table('warranty_claim_events')->insert([
            'claim_id' => $claimId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before_data' => self::enc($before),
            'after_data' => self::enc($after),
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
            'ip_address' => $ip,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'created_at' => now(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public static function history(int $claimId)
    {
        if (! Schema::hasTable('warranty_claim_events')) {
            return collect();
        }

        return DB::table('warranty_claim_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.claim_id', $claimId)
            ->orderByDesc('e.id')
            ->get(['e.*', 'u.name as user_name']);
    }

    private static function enc(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
