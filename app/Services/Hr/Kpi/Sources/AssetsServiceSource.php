<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\AssetInventories;
use App\Services\Hr\Kpi\VppAllocationKpi;
use App\Services\Hr\OfficeIncidents;
use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC06 — Đáp ứng cấp phát VPP, kiểm kê tài sản và xử lý sự cố văn phòng đúng SLA.
 *
 * Ba nhóm nguồn giữ riêng (mỗi dòng có 'group'); mỗi nhóm có số đo đúng hạn riêng. Cách gộp ba nhóm
 * thành một tỷ lệ (H-D04) chưa chốt: mặc định "unset" → không có tỷ lệ chung, không có điểm tự động,
 * điểm chính thức vẫn nhập tay. Không lấy thống kê sự cố thay cho cả tiêu chí.
 */
final class AssetsServiceSource
{
    public const CODE = 'assets';

    public const GROUPS = ['allocation', 'inventory', 'incident'];

    public static function ready(): bool
    {
        return VppAllocationKpi::ready() || AssetInventories::ready() || self::incidentsReady();
    }

    public static function incidentsReady(): bool
    {
        return SchemaCache::hasTable('hr_office_incidents')
            && SchemaCache::hasColumn('hr_office_incidents', 'handler_id')
            && SchemaCache::hasColumn('hr_office_incidents', 'first_resolved_at')
            && SchemaCache::hasColumn('hr_office_incidents', 'approval_wait_minutes');
    }

    /** Chỉ khi quy tắc đã chọn cách gộp thì HC06 mới được lấy điểm từ nguồn. */
    public static function officialEnabled(array $rules): bool
    {
        return in_array((string) ($rules[self::CODE]['aggregation'] ?? 'unset'), ['pooled', 'average_groups'], true);
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        $now ??= now();
        $r = (array) ($rules[self::CODE] ?? []);
        [$start, $end] = $this->bounds($month);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $rows = [];

        if (VppAllocationKpi::ready()) {
            $reqs = DB::table('hr_vpp_requests as q')
                ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'q.id')->where('v.source_type', VppAllocationKpi::SOURCE_TYPE)->where('v.criterion_code', self::CODE))
                ->where('q.kpi_tracked', true)->where('q.handler_id', $userId)
                ->where(fn ($q) => $q->where('q.company_id', EgoCompanyLock::id())->orWhereNull('q.company_id'))
                ->whereBetween('q.due_at', [$start, $end])
                ->orderBy('q.due_at')->orderBy('q.id')
                ->get(['q.*', 'v.status as review_status', 'v.result', 'v.checks', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason', 'v.first_submitted_at']);
            foreach ($reqs as $q) {
                $rows[] = $this->allocationRow($q, $names, $now);
            }
        }

        if (AssetInventories::ready()) {
            $invs = DB::table('hr_asset_inventories as i')
                ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'i.id')->where('v.source_type', AssetInventories::SOURCE_TYPE)->where('v.criterion_code', self::CODE))
                ->where('i.handler_id', $userId)->where('i.period_month', $month)
                ->where(fn ($q) => $q->where('i.company_id', EgoCompanyLock::id())->orWhereNull('i.company_id'))
                ->orderBy('i.due_at')->orderBy('i.id')
                ->get(['i.*', 'v.status as review_status', 'v.result', 'v.checks', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason', 'v.first_submitted_at']);
            foreach ($invs as $i) {
                $rows[] = $this->inventoryRow($i, $names, $now);
            }
        }

        if (self::incidentsReady()) {
            $incidents = DB::table('hr_office_incidents')
                ->where('handler_id', $userId)->whereNotNull('due_at')
                ->where(fn ($q) => $q->where('company_id', EgoCompanyLock::id())->orWhereNull('company_id'))
                ->whereBetween('due_at', [$start, $end])
                ->orderBy('due_at')->orderBy('id')->get();
            foreach ($incidents as $inc) {
                $rows[] = $this->incidentRow($inc, $r, $names, $now);
            }
        }

        return $rows;
    }

    public function allocationRow(object $q, array $names, Carbon $now): array
    {
        $due = $q->due_at ? Carbon::parse($q->due_at) : null;
        $delivered = $q->delivered_at ? Carbon::parse($q->delivered_at) : null;
        $checks = json_decode((string) ($q->checks ?? ''), true) ?: [];
        $labels = (array) config('hcns_kpi.allocation_checks', []);
        [$state, $reason] = match (true) {
            ($q->review_status ?? null) === 'confirmed' && $q->result !== 'pass' => ['lost', 'Kiểm tra không đạt: '.collect($checks)->filter(fn ($v) => $v !== 'pass')->keys()->map(fn ($k) => $labels[$k] ?? $k)->implode(', ').'.'],
            ($q->review_status ?? null) === 'confirmed' && $due && $delivered && $delivered->lessThanOrEqualTo($due) => ['earned', 'Giao '.$this->fmt($delivered).' ≤ hạn '.$this->fmt($due).', đã kiểm tra.'],
            ($q->review_status ?? null) === 'confirmed' => ['lost', 'Giao '.$this->fmt($delivered).' sau hạn '.$this->fmt($due).'.'],
            in_array($q->review_status ?? null, ['submitted', 'changed', 'returned'], true) => ['pending', match ($q->review_status) {
                'submitted' => 'Đã gửi kiểm tra — chờ người kiểm.',
                'returned' => 'Trả bổ sung: '.($q->return_reason ?: '—'),
                default => 'Sửa sau khi gửi — cần gửi lại.',
            }],
            default => ['missing', 'Chưa gửi kiểm tra.'],
        };

        return $this->row('allocation', (int) $q->id, ($q->code ?: 'VPP-'.$q->id).' · '.($q->receiver_name ?: $q->requester_name),
            route('hr.office-supply-process.show', (int) $q->id), (int) $q->handler_id, $names, $state, $reason, [
                'deadline' => $due, 'actual_at' => $delivered, 'started_at' => $q->request_received_at ? Carbon::parse($q->request_received_at) : null,
                'work_label' => 'Đã cấp phát', 'review_status' => $q->review_status ?? 'none',
                'confirmed_by_name' => $q->confirmed_by ? ($names[(int) $q->confirmed_by] ?? '—') : null,
                'confirmed_at' => $q->confirmed_at ? Carbon::parse($q->confirmed_at) : null,
            ]);
    }

    public function inventoryRow(object $i, array $names, Carbon $now): array
    {
        $due = $i->due_at ? Carbon::parse($i->due_at) : null;
        $completed = $i->completed_at ? Carbon::parse($i->completed_at) : null;
        $checks = json_decode((string) ($i->checks ?? ''), true) ?: [];
        $labels = (array) config('hcns_kpi.inventory_checks', []);
        $status = (string) $i->status;
        [$state, $reason] = match (true) {
            $status === 'cancelled' && $i->cancelled_at && $due && Carbon::parse($i->cancelled_at)->greaterThan($due) && ! $completed
                => ['lost', 'Hủy sau hạn khi chưa hoàn tất kiểm kê: '.($i->cancel_reason ?: '—')],
            $status === 'cancelled' => ['excluded', 'Đã hủy: '.($i->cancel_reason ?: '—')],
            ($i->review_status ?? null) === 'confirmed' && $i->result !== 'pass' => ['lost', 'Kiểm tra không đạt: '.collect($checks)->filter(fn ($v) => $v !== 'pass')->keys()->map(fn ($k) => $labels[$k] ?? $k)->implode(', ').'.'],
            ($i->review_status ?? null) === 'confirmed' && $due && $completed && $completed->lessThanOrEqualTo($due) => ['earned', 'Hoàn tất '.$this->fmt($completed).' ≤ hạn '.$this->fmt($due).', đã kiểm tra.'],
            ($i->review_status ?? null) === 'confirmed' => ['lost', 'Hoàn tất '.$this->fmt($completed).' sau hạn '.$this->fmt($due).'.'],
            in_array($i->review_status ?? null, ['submitted', 'changed', 'returned'], true) => ['pending', ($i->review_status === 'returned' ? 'Trả bổ sung: '.($i->return_reason ?: '—') : 'Đã gửi kiểm tra — chờ người kiểm.')],
            $due && $now->lessThanOrEqualTo($due) => ['waiting', 'Còn hạn đến '.$this->fmt($due).'.'],
            default => ['lost', 'Quá hạn '.$this->fmt($due).' chưa hoàn tất kiểm kê.'],
        };

        return $this->row('inventory', (int) $i->id, ($i->code ?: 'KK-'.$i->id).' · '.$i->title,
            route('hr.asset-inventories.show', (int) $i->id), (int) $i->handler_id, $names, $state, $reason, [
                'deadline' => $due, 'actual_at' => $completed, 'started_at' => $i->started_at ? Carbon::parse($i->started_at) : null,
                'work_label' => ['planned' => 'Đã lập kế hoạch', 'in_progress' => 'Đang kiểm kê', 'completed' => 'Đã hoàn tất', 'cancelled' => 'Đã hủy'][$status] ?? $status,
                'review_status' => $i->review_status ?? 'none',
                'confirmed_by_name' => $i->confirmed_by ? ($names[(int) $i->confirmed_by] ?? '—') : null,
                'confirmed_at' => $i->confirmed_at ? Carbon::parse($i->confirmed_at) : null,
            ]);
    }

    /** $r: quy tắc nhóm assets (incident_measure, incident_wait). */
    public function incidentRow(object $inc, array $r, array $names, Carbon $now): array
    {
        $due = Carbon::parse($inc->due_at);
        $waitMinutes = (int) ($inc->approval_wait_minutes ?? 0);
        if (! empty($inc->waiting_since)) {
            // Đang chờ Giám đốc duyệt chi: cộng phần đang chờ tới hiện tại.
            $waitMinutes += (int) Carbon::parse($inc->waiting_since)->diffInMinutes($now);
        }
        if (($r['incident_wait'] ?? 'include') === 'exclude_approval_wait' && $waitMinutes > 0) {
            $due = $due->copy()->addMinutes($waitMinutes);
        }
        $measure = ($r['incident_measure'] ?? 'final_resolution') === 'first_resolution' ? ($inc->first_resolved_at ?: $inc->resolved_at) : $inc->resolved_at;
        $measured = $measure ? Carbon::parse($measure) : null;
        $classifyMinutes = $inc->classified_at ? Carbon::parse($inc->created_at)->diffInMinutes(Carbon::parse($inc->classified_at)) : null;
        $extra = ($classifyMinutes !== null ? ' · phân loại sau '.(int) $classifyMinutes.' phút' : '').($waitMinutes > 0 ? ' · chờ duyệt chi '.$waitMinutes.' phút' : '');
        $cancelledAt = ! empty($inc->cancelled_at) ? Carbon::parse($inc->cancelled_at) : null;
        [$state, $reason] = match (true) {
            $inc->status === 'cancelled' && $cancelledAt && $cancelledAt->greaterThan($due) && ! $measured
                => ['lost', 'Hủy '.$this->fmt($cancelledAt).' sau hạn '.$this->fmt($due).' khi chưa xử lý xong.'.$extra],
            $inc->status === 'cancelled' => ['excluded', $cancelledAt ? 'Hủy '.$this->fmt($cancelledAt).' trước hạn — loại khỏi KPI.' : 'Sự cố đã hủy (không có giờ hủy) — loại khỏi KPI.'],
            $inc->status === 'closed' && $measured && $measured->lessThanOrEqualTo($due) => ['earned', 'Xử lý xong '.$this->fmt($measured).' ≤ hạn '.$this->fmt($due).', người báo đã nghiệm thu.'.$extra],
            $inc->status === 'closed' => ['lost', 'Xử lý xong '.$this->fmt($measured).' sau hạn '.$this->fmt($due).'.'.$extra],
            $inc->status === 'waiting_acceptance' => ['pending', 'Báo xong '.$this->fmt($measured).' — chờ người báo nghiệm thu.'.$extra],
            $now->lessThanOrEqualTo($due) => ['waiting', 'Còn hạn đến '.$this->fmt($due).'.'.$extra],
            default => ['lost', 'Quá hạn '.$this->fmt($due).' chưa xử lý xong.'.$extra],
        };

        return $this->row('incident', (int) $inc->id, $inc->code.' · '.$inc->location, route('hr.incidents.show', (int) $inc->id),
            (int) $inc->handler_id, $names, $state, $reason, [
                'deadline' => $due, 'actual_at' => $measured, 'started_at' => Carbon::parse($inc->created_at),
                'work_label' => OfficeIncidents::STATUSES[$inc->status] ?? $inc->status, 'reporter_id' => (int) $inc->reporter_id,
                'level' => $inc->level ? (OfficeIncidents::LEVELS[(int) $inc->level] ?? null) : null,
                'confirmed_by_name' => $inc->accepted_by ? ($names[(int) $inc->accepted_by] ?? '—') : null,
                'confirmed_at' => $inc->accepted_at ? Carbon::parse($inc->accepted_at) : null,
            ]);
    }

    /**
     * Số đo từng nhóm + tỷ lệ chung theo cách gộp đã chọn. Cách gộp "unset" → value null kèm lý do (chưa tính).
     */
    public function summarize(array $rows, string $month, ?Carbon $now = null, array $rules = []): array
    {
        $now ??= now();
        $blank = fn () => array_fill_keys(['earned', 'lost', 'pending', 'waiting', 'excluded', 'missing'], 0);
        $counts = $blank();
        $groups = [];
        foreach (self::GROUPS as $g) {
            $groups[$g] = ['key' => $g, 'label' => (string) config('hcns_kpi.asset_groups.'.$g, $g), 'counts' => $blank()];
        }
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
            $groups[$row['group']]['counts'][$row['kpi_state']]++;
        }
        foreach ($groups as $g => $data) {
            $den = $data['counts']['earned'] + $data['counts']['lost'];
            $groups[$g] += ['numerator' => $data['counts']['earned'], 'denominator' => $den, 'value' => $den > 0 ? round($data['counts']['earned'] / $den * 100, 2) : null];
        }

        $aggregation = (string) ($rules[self::CODE]['aggregation'] ?? 'unset');
        $den = $counts['earned'] + $counts['lost'];
        [$value, $reason] = match ($aggregation) {
            'pooled' => [$den > 0 ? round($counts['earned'] / $den * 100, 2) : null, null],
            'average_groups' => (function () use ($groups) {
                $values = collect($groups)->pluck('value')->filter(fn ($v) => $v !== null);

                return [$values->isEmpty() ? null : round($values->avg(), 2), null];
            })(),
            default => [null, 'Cách gộp ba nhóm chưa chốt (H-D04) — chỉ hiện số đo từng nhóm, chưa tính điểm.'],
        };
        [, $end] = $this->bounds($month);

        return [
            'numerator' => $counts['earned'],
            'denominator' => $den,
            'value' => $value,
            'counts' => $counts,
            'groups' => array_values($groups),
            'combined_reason' => $reason,
            'data_status' => match (true) {
                $rows === [] => 'no_items',
                $counts['pending'] + $counts['missing'] > 0 => 'incomplete',
                $counts['waiting'] > 0 || $now->lessThanOrEqualTo($end) => 'open',
                default => 'complete',
            },
        ];
    }

    private function row(string $group, int $id, string $title, string $url, int $ownerId, array $names, string $state, string $reason, array $extra): array
    {
        return [
            'code' => self::CODE, 'group' => $group, 'group_label' => (string) config('hcns_kpi.asset_groups.'.$group, $group),
            'source_id' => $id, 'title' => $title, 'url' => $url, 'owner_id' => $ownerId, 'owner_name' => $names[$ownerId] ?? '—',
            'kpi_state' => $state, 'reason' => $reason,
        ] + $extra;
    }

    private function fmt(?Carbon $t): string
    {
        return $t ? $t->format('H:i d/m/Y') : '—';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', config('app.timezone'));

        return [$start, $start->copy()->endOfMonth()];
    }
}
