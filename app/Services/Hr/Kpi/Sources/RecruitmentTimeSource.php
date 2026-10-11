<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\RecruitmentKpi;
use App\Support\EgoCompanyLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC01 — Thời gian tuyển dụng: bình quân số ngày lịch từ mốc HCNS tiếp nhận yêu cầu (đã duyệt) tới ngày ứng viên
 * nhận việc THỰC TẾ, của các lượt tuyển đã được người kiểm xác nhận trong kỳ (theo tháng nhận việc).
 * Lượt chưa nhận việc/không nhận việc/đã hủy hiện trong checklist nhưng không vào bình quân.
 */
final class RecruitmentTimeSource
{
    public const CODE = 'recruit_time';

    public static function ready(): bool
    {
        return RecruitmentKpi::ready();
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        $now ??= now();
        [$start, $end] = self::bounds($month);
        $hires = $this->query()->where('h.owner_id', $userId)
            ->where(function ($q) use ($start, $end, $month) {
                $q->where(fn ($s) => $s->where('h.status', 'started')->whereBetween('h.actual_start_date', [$start->toDateString(), $end->toDateString()]))
                    ->orWhere(fn ($s) => $s->whereIn('h.status', ['pending', 'no_show', 'cancelled'])
                        ->where(fn ($d) => $d->whereBetween('h.expected_start_date', [$start->toDateString(), $end->toDateString()])
                            ->orWhere(fn ($n) => $n->whereNull('h.expected_start_date')->whereRaw("DATE_FORMAT(h.created_at, '%Y-%m') = ?", [$month]))));
            })
            ->orderBy('h.actual_start_date')->orderBy('h.id')->get();
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();

        return $hires->map(fn ($h) => $this->row($h, $names, $now, $end))->all();
    }

    /** Lượt tuyển (đã nối yêu cầu theo dõi KPI, đúng công ty) kèm trạng thái kiểm HC01. */
    public function query()
    {
        return DB::table('hr_recruitment_hires as h')
            ->join('hr_recruitment_requests as r', 'r.id', '=', 'h.request_id')
            ->leftJoin('hr_recruitment_candidates as c', 'c.id', '=', 'h.candidate_id')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'h.id')->where('v.source_type', RecruitmentKpi::HIRE_TYPE)->where('v.criterion_code', self::CODE))
            ->where('r.kpi_tracked', true)
            ->where(fn ($q) => $q->where('h.company_id', EgoCompanyLock::id())->orWhereNull('h.company_id'))
            ->select('h.*', 'r.position', 'r.department', 'r.request_received_at', 'r.approval_status', 'c.full_name as candidate_name',
                'v.status as review_status', 'v.result', 'v.checks', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason', 'v.first_submitted_at', 'v.submission_count');
    }

    public function row(object $h, array $names, Carbon $now, ?Carbon $periodEnd = null): array
    {
        $days = RecruitmentKpi::recruitDays($h->request_received_at, $h->actual_start_date);
        $fmtD = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
        [$state, $reason, $label] = match (true) {
            $h->status === 'cancelled' => ['excluded', 'Lượt tuyển đã hủy: '.($h->cancel_reason ?: '—').' — không vào bình quân.', null],
            $h->status === 'no_show' => ['excluded', 'Ứng viên không nhận việc: '.($h->cancel_reason ?: '—').' — không phải tuyển thành công, suất vẫn mở.', null],
            $h->status === 'pending' => $periodEnd && $now->greaterThan($periodEnd)
                ? ['excluded', 'Chưa nhận việc trong tháng — sẽ tính vào tháng nhận việc thực tế.', null]
                : ['waiting', 'Chưa nhận việc (dự kiến '.$fmtD($h->expected_start_date).') — ngày dự kiến không dùng để tính.', null],
            $days === null => ['missing', 'Thiếu mốc tiếp nhận yêu cầu hoặc ngày nhận việc trước mốc tiếp nhận.', null],
            ($h->review_status ?? null) === 'confirmed' && $h->result === 'pass' => ['counted', 'Tiếp nhận '.$fmtD($h->request_received_at).' → nhận việc '.$fmtD($h->actual_start_date).' = '.$days.' ngày, đã kiểm tra.', 'Được tính — '.$days.' ngày'],
            ($h->review_status ?? null) === 'confirmed' => ['pending', 'Người kiểm ghi không đạt — sửa và gửi lại.', null],
            in_array($h->review_status ?? null, ['submitted', 'changed', 'returned'], true) => ['pending', match ($h->review_status) {
                'submitted' => 'Đã gửi kiểm ('.$days.' ngày) — chờ người kiểm.',
                'returned' => 'Trả bổ sung: '.($h->return_reason ?: '—'),
                default => 'Sửa sau khi gửi — cần gửi lại.',
            }, null],
            default => ['missing', 'Chưa gửi kiểm tra ('.$days.' ngày theo khai báo).', null],
        };

        return [
            'code' => self::CODE, 'source_id' => (int) $h->id,
            'title' => 'Lượt #'.$h->id.' · '.($h->candidate_name ?: 'Chưa chọn ứng viên').' · '.$h->position,
            'url' => route('hr.recruitment.hires.show', (int) $h->id), 'owner_id' => (int) $h->owner_id, 'owner_name' => $names[(int) $h->owner_id] ?? '—',
            'reviewer_name' => $h->reviewer_id ? ($names[(int) $h->reviewer_id] ?? '—') : null,
            'kpi_state' => $state, 'reason' => $reason, 'state_label' => $label, 'days' => $days,
            'deadline' => null, 'started_at' => $h->request_received_at ? Carbon::parse($h->request_received_at) : null,
            'actual_at' => $h->actual_start_date ? Carbon::parse($h->actual_start_date) : null,
            'work_label' => (string) config('hcns_kpi.hire_statuses.'.$h->status, $h->status), 'review_status' => $h->review_status ?? 'none',
            'confirmed_by_name' => $h->confirmed_by ? ($names[(int) $h->confirmed_by] ?? '—') : null,
            'confirmed_at' => $h->confirmed_at ? Carbon::parse($h->confirmed_at) : null,
            'request_id' => (int) $h->request_id,
        ];
    }

    public function summarize(array $rows, string $month, ?Carbon $now = null, array $rules = []): array
    {
        $now ??= now();
        $counts = self::blankCounts();
        $sum = 0;
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
            if ($row['kpi_state'] === 'counted') {
                $sum += (int) $row['days'];
            }
        }
        $n = $counts['counted'];
        $value = $n > 0 ? round($sum / $n, 2) : null;
        [, $end] = self::bounds($month);

        return [
            'numerator' => $sum, 'denominator' => $n, 'value' => $value, 'counts' => $counts,
            'measure_text' => $n > 0 ? 'Σ '.$sum.' ngày ÷ '.$n.' lượt = '.self::num($value).' ngày/lượt' : 'Chưa có lượt nhận việc đã xác nhận',
            'unit' => 'ngày',
            'data_status' => self::dataStatus($rows, $counts, $now, $end),
        ];
    }

    public static function blankCounts(): array
    {
        return array_fill_keys(['counted', 'earned', 'lost', 'pending', 'waiting', 'excluded', 'missing'], 0);
    }

    public static function dataStatus(array $rows, array $counts, Carbon $now, Carbon $end): string
    {
        return match (true) {
            $counts['counted'] + $counts['lost'] + $counts['earned'] + $counts['pending'] + $counts['missing'] + $counts['waiting'] === 0 => 'no_items',
            $counts['pending'] + $counts['missing'] > 0 => 'incomplete',
            $counts['waiting'] > 0 || $now->lessThanOrEqualTo($end) => 'open',
            default => 'complete',
        };
    }

    public static function num(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', config('app.timezone'));

        return [$start, $start->copy()->endOfMonth()];
    }
}
