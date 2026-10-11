<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Đợt kiểm kê tài sản theo kỳ (KPI HC06, nhóm “kiểm kê”). Danh sách tài sản chụp lúc lập đợt từ một trong hai
 * danh mục đang có (Tài sản HC & Vận hành hoặc Tài sản Tài chính) — không nối theo tên, không cộng hai danh mục.
 */
final class AssetInventories
{
    public const SOURCE_TYPE = 'hr_asset_inventory';

    public const SCOPES = [
        'hr_operation' => 'Tài sản HC & Vận hành',
        'finance' => 'Tài sản Tài chính',
    ];

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_asset_inventories') && SchemaCache::hasTable('hr_asset_inventory_items')
            && SchemaCache::hasColumn('hr_asset_inventories', 'cancelled_at') && SchemaCache::hasTable('hr_kpi_source_logs')
            && HcnsSourceReviews::ready() && HcnsSourceFiles::ready();
    }

    /** Tài sản của danh mục để chụp vào đợt kiểm kê. @return array<int, array> */
    public function assetsFor(string $scope): array
    {
        if ($scope === 'finance' && SchemaCache::hasTable('finance_assets')) {
            return DB::table('finance_assets')->where('company_id', EgoCompanyLock::id())
                ->when(SchemaCache::hasColumn('finance_assets', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('code')->get(['id', 'code', 'name', 'location', 'assigned_to'])
                ->map(fn ($a) => ['asset_source' => 'finance', 'asset_id' => (int) $a->id, 'asset_code' => $a->code, 'asset_name' => (string) $a->name,
                    'expected_holder' => trim(($a->location ?? '').($a->assigned_to ? ' · NV#'.$a->assigned_to : '')) ?: null])->all();
        }
        if ($scope === 'hr_operation' && SchemaCache::hasTable('hr_operation_assets')) {
            return DB::table('hr_operation_assets')->orderBy('id')->get(['id', 'asset_name', 'asset_type', 'assigned_to'])
                ->map(fn ($a) => ['asset_source' => 'hr_operation', 'asset_id' => (int) $a->id, 'asset_code' => 'HC-'.$a->id,
                    'asset_name' => (string) $a->asset_name.($a->asset_type ? ' ('.$a->asset_type.')' : ''), 'expected_holder' => $a->assigned_to])->all();
        }

        return [];
    }

    /** Nội dung người kiểm xác nhận: người phụ trách, hạn, kết quả từng tài sản và tệp minh chứng. */
    public function declared(object $inv): array
    {
        return [
            'handler_id' => (int) $inv->handler_id,
            'due_at' => (string) ($inv->due_at ?? ''),
            'items' => DB::table('hr_asset_inventory_items')->where('inventory_id', $inv->id)->orderBy('id')->get(['id', 'result', 'note'])
                ->map(fn ($i) => $i->id.':'.$i->result.':'.$i->note)->all(),
            'files' => app(HcnsSourceFiles::class)->fingerprintPart(self::SOURCE_TYPE, (int) $inv->id),
        ];
    }

    public function fingerprint(object $inv): string
    {
        return hash('sha256', json_encode($this->declared($inv), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, string> */
    public function missing(object $inv): array
    {
        $missing = [];
        if (empty($inv->handler_id)) {
            $missing[] = 'người phụ trách';
        }
        if (empty($inv->reviewer_id)) {
            $missing[] = 'người kiểm';
        }
        $unchecked = DB::table('hr_asset_inventory_items')->where('inventory_id', $inv->id)->whereNull('result')->count();
        $total = DB::table('hr_asset_inventory_items')->where('inventory_id', $inv->id)->count();
        if ($total === 0) {
            $missing[] = 'danh sách tài sản';
        } elseif ($unchecked > 0) {
            $missing[] = $unchecked.'/'.$total.' tài sản chưa ghi kết quả';
        }
        if (app(HcnsSourceFiles::class)->list(self::SOURCE_TYPE, (int) $inv->id)->isEmpty()) {
            $missing[] = 'biên bản/ảnh kiểm kê';
        }

        return $missing;
    }

    public static function lockedMonth(object $inv): ?string
    {
        return HcnsKpiRules::lockedMonth([(string) $inv->period_month]);
    }

    public static function lockMessage(string $month): string
    {
        return 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — đợt kiểm kê của kỳ này bị khóa.';
    }

    public function log(int $id, string $action, ?string $note, ?int $actorId): void
    {
        DB::table('hr_kpi_source_logs')->insert([
            'source_type' => self::SOURCE_TYPE, 'source_id' => $id, 'action' => $action,
            'note' => $note === null ? null : mb_substr($note, 0, 4000), 'actor_id' => $actorId, 'created_at' => now(),
        ]);
    }

    public function logs(int $id): \Illuminate\Support\Collection
    {
        return DB::table('hr_kpi_source_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.source_type', self::SOURCE_TYPE)->where('l.source_id', $id)->orderByDesc('l.id')
            ->get(['l.*', 'u.name as actor_name']);
    }

    public static function nextCode(): string
    {
        $prefix = 'KK-'.now()->format('ym').'-';
        $last = DB::table('hr_asset_inventories')->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }
}
