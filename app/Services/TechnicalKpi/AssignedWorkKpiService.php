<?php

namespace App\Services\TechnicalKpi;

use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only source for KS01: manager-assigned work and its first report submission. */
class AssignedWorkKpiService
{
    public function collect(int $userId, array $months, ?int $siteId = null): ?Collection
    {
        if (! SchemaCache::hasTable('technical_plan_items') || ! SchemaCache::hasTable('technical_daily_reports')) {
            return null;
        }

        $companyId = EgoCompanyLock::id();
        $items = DB::table('technical_plan_items')
            ->where('company_id', $companyId)->where('user_id', $userId)
            ->where('is_manager_assigned', 1)->whereNull('deleted_at')
            ->where(function ($q) use ($months) {
                foreach ($months as $month) {
                    $start = Carbon::parse($month.'-01')->startOfMonth();
                    // Put a job in the month of its 24-hour deadline, including overnight/month-end jobs.
                    $q->orWhereBetween('assigned_at', [$start->copy()->subHours(24), $start->copy()->endOfMonth()->subHours(24)])
                        ->orWhere(fn ($missing) => $missing->whereNull('assigned_at')->whereBetween('plan_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]));
                }
            })
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('plan_date')->orderBy('id')->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $reports = DB::table('technical_daily_reports')
            ->where('company_id', $companyId)->where('user_id', $userId)
            ->whereIn('plan_item_id', $items->pluck('id'))->whereNull('deleted_at')->get();
        // submitted_at can change on resubmission. The audit log preserves the first actual send.
        $firstSends = SchemaCache::hasTable('technical_daily_report_histories')
            ? DB::table('technical_daily_report_histories')
                ->whereIn('report_id', $reports->pluck('id'))->where('action', 'submit')
                ->selectRaw('report_id, MIN(created_at) AS first_sent_at')->groupBy('report_id')
                ->pluck('first_sent_at', 'report_id')
            : collect();
        $byItem = $reports->groupBy('plan_item_id');

        return $items->map(function ($item) use ($byItem, $firstSends) {
            $sent = ($byItem->get($item->id) ?? collect())->map(function ($report) use ($firstSends) {
                $at = $firstSends->get($report->id);
                if (! $at && in_array($report->status, ['submitted', 'approved', 'revision_requested'], true)) {
                    $at = $report->submitted_at;
                }

                return $at ? ['report_id' => (int) $report->id, 'submitted_at' => (string) $at] : null;
            })->filter()->sortBy('submitted_at')->first();
            // Báo cáo nháp chưa từng gửi: chỉ để hiển thị "có nháp", không bao giờ tính là đã nộp.
            $draft = $sent ? null : ($byItem->get($item->id) ?? collect())
                ->where('status', 'draft')->sortByDesc('id')->first();

            return [
                'plan_item_id' => (int) $item->id,
                'user_id' => (int) $item->user_id,
                'user_name' => (string) $item->user_name,
                'site_id' => $item->site_id ? (int) $item->site_id : null,
                'project' => (string) $item->title,
                'plan_date' => (string) $item->plan_date,
                'month' => $item->assigned_at ? Carbon::parse($item->assigned_at)->addHours(24)->format('Y-m') : Carbon::parse($item->plan_date)->format('Y-m'),
                'assigned_at' => $item->assigned_at,
                'status' => (string) $item->status,
                'report_id' => $sent['report_id'] ?? null,
                'submitted_at' => $sent['submitted_at'] ?? null,
                'draft_report_id' => $draft ? (int) $draft->id : null,
            ];
        });
    }

    /** Classification is deterministic for the selected month's end (or now for an open month). */
    public function evaluate(Collection $items, int $hours): array
    {
        $passed = $counted = $missing = 0;
        $evidence = [];
        foreach ($items->unique('plan_item_id') as $item) {
            $asOf = Carbon::parse($item['month'].'-01')->endOfMonth()->min(now());
            $assigned = ! empty($item['assigned_at']) ? Carbon::parse($item['assigned_at']) : null;
            $due = $assigned?->copy()->addHours($hours);
            $sent = ! empty($item['submitted_at']) ? Carbon::parse($item['submitted_at']) : null;
            $state = 'info';
            // phase: giai đoạn của việc tại thời điểm đánh giá (để hiển thị, không đổi cách chấm).
            if ($item['status'] === 'cancelled') {
                $state = 'excluded';
                $phase = 'cancelled';
                $result = 'Đã hủy — không tính';
            } elseif (! $assigned) {
                $state = 'missing';
                $phase = 'missing_assigned_at';
                $result = 'Thiếu giờ giao việc — chưa tính';
                $missing++;
            } elseif ($assigned->gt($asOf)) {
                $phase = 'not_assigned_yet';
                $result = 'Chưa giao tại thời điểm đánh giá';
            } elseif ($sent && $sent->lt($assigned)) {
                $state = 'missing';
                $phase = 'sent_before_assigned';
                $result = 'Giờ nộp trước giờ giao — cần xác minh';
                $missing++;
            } elseif ($due->gt($asOf)) {
                $phase = 'pending';
                $result = 'Còn trong hạn 24 giờ — chưa tính vào mẫu số';
            } else {
                $counted++;
                $phase = 'counted';
                $ok = $sent && $sent->lte($due) && $sent->lte($asOf);
                $state = $ok ? 'pass' : 'fail';
                $passed += $ok ? 1 : 0;
                $result = $ok ? 'Nộp đúng hạn — nhận điểm' : ($sent ? 'Nộp muộn — mất phần điểm của việc' : 'Quá hạn chưa nộp — mất phần điểm của việc');
            }
            $fmt = fn ($at) => $at ? $at->format('d/m/Y H:i:s') : '—';
            $evidence[] = $item + [
                'user' => $item['user_name'],
                'detail' => 'Giao: '.$fmt($assigned).' · Hạn: '.$fmt($due).' · Nộp lần đầu: '.$fmt($sent),
                'state' => $state, 'result' => $result,
                'phase' => $phase,
                'due_at' => $due?->toDateTimeString(),
                'as_of' => $asOf->toDateTimeString(),
                'late_seconds' => ($sent && $due && $sent->gt($due)) ? (int) $due->diffInSeconds($sent) : null,
            ];
        }
        foreach ($evidence as &$row) {
            if (in_array($row['state'], ['pass', 'fail'], true)) {
                $row['point_share_ratio'] = $counted > 0 ? 1 / $counted : null;
            }
        }
        unset($row);

        return compact('passed', 'counted', 'missing', 'evidence');
    }
}
