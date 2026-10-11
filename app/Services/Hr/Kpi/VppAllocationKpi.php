<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu cấp phát VPP theo dõi KPI HC06 (nhóm “cấp phát”). Phiếu cũ/không đánh dấu không vào KPI.
 * Đo: giao thực tế ≤ hạn yêu cầu, sau khi người kiểm xác nhận mốc/số lượng/ký nhận là đúng.
 */
final class VppAllocationKpi
{
    public const SOURCE_TYPE = 'hr_vpp_request';

    public const CRITERION = 'assets';

    private const COLUMNS = ['company_id', 'kpi_tracked', 'handler_id', 'reviewer_id', 'requester_user_id', 'request_received_at',
        'due_at', 'delivered_at', 'receipt_confirmed_by', 'receipt_confirmed_at'];

    public static function ready(): bool
    {
        if (! SchemaCache::hasTable('hr_vpp_requests') || ! HcnsSourceReviews::ready() || ! HcnsSourceFiles::ready()) {
            return false;
        }
        foreach (self::COLUMNS as $c) {
            if (! SchemaCache::hasColumn('hr_vpp_requests', $c)) {
                return false;
            }
        }

        return true;
    }

    /** Nội dung người kiểm xác nhận: mốc, người, vật phẩm/số lượng và tệp minh chứng. */
    public function declared(object $req): array
    {
        return [
            'handler_id' => (int) ($req->handler_id ?? 0),
            'requester_user_id' => (int) ($req->requester_user_id ?? 0),
            'request_received_at' => (string) ($req->request_received_at ?? ''),
            'due_at' => (string) ($req->due_at ?? ''),
            'delivered_at' => (string) ($req->delivered_at ?? ''),
            'items' => DB::table('hr_vpp_items')->where('request_id', $req->id)->orderBy('id')->get(['product_id', 'issued_qty'])
                ->map(fn ($i) => $i->product_id.':'.(float) $i->issued_qty)->all(),
            'files' => app(HcnsSourceFiles::class)->fingerprintPart(self::SOURCE_TYPE, (int) $req->id),
        ];
    }

    public function fingerprint(object $req): string
    {
        return hash('sha256', json_encode($this->declared($req), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, string> dữ kiện còn thiếu trước khi gửi kiểm */
    public function missing(object $req): array
    {
        $missing = [];
        foreach (['handler_id' => 'người xử lý', 'reviewer_id' => 'người kiểm', 'requester_user_id' => 'người yêu cầu',
            'request_received_at' => 'giờ nhận yêu cầu', 'due_at' => 'hạn cấp phát', 'delivered_at' => 'giờ giao thực tế'] as $field => $label) {
            if (empty($req->{$field})) {
                $missing[] = $label;
            }
        }
        if (app(HcnsSourceFiles::class)->list(self::SOURCE_TYPE, (int) $req->id)->isEmpty()) {
            $missing[] = 'biên bản/ảnh ký nhận';
        }

        return $missing;
    }

    /** Kỳ đã chốt lương theo tháng của hạn → khóa thay đổi ảnh hưởng kết quả. */
    public static function lockedMonth(object $req): ?string
    {
        return ! empty($req->due_at) ? HcnsKpiRules::lockedMonth([Carbon::parse($req->due_at)->format('Y-m')]) : null;
    }

    public static function lockMessage(string $month): string
    {
        return 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — phiếu cấp phát theo dõi KPI bị khóa. Mở lại kỳ lương theo quy trình nếu cần sửa.';
    }
}
