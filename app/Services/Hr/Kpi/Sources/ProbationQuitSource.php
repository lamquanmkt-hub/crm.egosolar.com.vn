<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\RecruitmentKpi;
use App\Support\EgoCompanyLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC03 — Tỷ lệ nghỉ trong thử việc = số người nghỉ trong thử việc ÷ số người của cohort (đã xác định kết quả, đã
 * được kiểm). Cohort nháp: người có ngày kết thúc thử việc — hoặc ngày nghỉ thực nếu nghỉ sớm — trong tháng.
 * Không suy “nghỉ” từ tài khoản ngừng hoạt động hay thiếu chấm công; chưa xác định là thiếu dữ liệu.
 */
final class ProbationQuitSource
{
    public const CODE = 'probation_quit';

    public static function ready(): bool
    {
        return RecruitmentKpi::ready();
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        $now ??= now();
        [$start, $end] = RecruitmentTimeSource::bounds($month);
        [$s, $e] = [$start->toDateString(), $end->toDateString()];
        $r = (array) ($rules[self::CODE] ?? []);
        $hires = DB::table('hr_recruitment_hires as h')
            ->join('hr_recruitment_requests as req', 'req.id', '=', 'h.request_id')
            ->leftJoin('hr_recruitment_candidates as c', 'c.id', '=', 'h.candidate_id')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'h.id')->where('v.source_type', RecruitmentKpi::HIRE_TYPE)->where('v.criterion_code', self::CODE))
            ->where('req.kpi_tracked', true)->where('h.owner_id', $userId)->where('h.status', 'started')
            ->where(fn ($q) => $q->where('h.company_id', EgoCompanyLock::id())->orWhereNull('h.company_id'))
            ->where(function ($q) use ($s, $e) {
                // Cohort: ngày nghỉ thực (nếu nghỉ) hoặc ngày kết thúc thử việc trong tháng; thiếu ngày kết thúc → hiện ở tháng nhận việc.
                $q->where(fn ($l) => $l->where('h.probation_outcome', 'left')->whereBetween('h.probation_left_date', [$s, $e]))
                    ->orWhere(fn ($p) => $p->where(fn ($o) => $o->whereNull('h.probation_outcome')->orWhere('h.probation_outcome', '!=', 'left')->orWhereNull('h.probation_left_date'))
                        ->whereBetween('h.probation_end_date', [$s, $e]))
                    ->orWhere(fn ($m) => $m->whereNull('h.probation_end_date')->whereBetween('h.actual_start_date', [$s, $e]));
            })
            ->orderBy('h.probation_end_date')->orderBy('h.id')
            ->get(['h.*', 'req.position', 'c.full_name as candidate_name', 'v.status as review_status', 'v.result', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason']);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();

        return $hires->map(fn ($h) => $this->row($h, $r, $names, $now))->all();
    }

    public function row(object $h, array $r, array $names, Carbon $now): array
    {
        $fmtD = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
        $outcomes = (array) config('hcns_kpi.probation_outcomes', []);
        $types = (array) config('hcns_kpi.probation_left_types', []);
        $quitCounts = ($r['left_types'] ?? 'all') === 'all' || ($h->probation_left_type ?? null) === 'resigned';
        $confirmed = ($h->review_status ?? null) === 'confirmed';
        $end = $h->probation_end_date ? Carbon::parse($h->probation_end_date)->endOfDay() : null;
        [$state, $reason, $label] = match (true) {
            empty($h->probation_end_date) => ['missing', 'Chưa nhập ngày kết thúc thử việc — chưa xếp vào kỳ.', null],
            empty($h->probation_outcome) => $end && $now->lessThanOrEqualTo($end)
                ? ['waiting', 'Đang trong thời gian thử việc đến '.$fmtD($h->probation_end_date).'.', null]
                : ['missing', 'Hết thử việc '.$fmtD($h->probation_end_date).' nhưng chưa xác định kết quả — không suy từ tài khoản/chấm công.', null],
            $h->probation_outcome === 'in_probation' && $end && $now->lessThanOrEqualTo($end) => ['waiting', 'Đang thử việc đến '.$fmtD($h->probation_end_date).'.', null],
            $h->probation_outcome === 'in_probation' => ['missing', 'Đã hết thử việc '.$fmtD($h->probation_end_date).' nhưng vẫn ghi “đang thử việc” — cần xác định kết quả.', null],
            $confirmed && $h->result !== 'pass' => ['pending', 'Người kiểm ghi không đạt — sửa và gửi lại.', null],
            $confirmed && $h->probation_outcome === 'left' && $quitCounts => ['lost', 'Nghỉ ngày '.$fmtD($h->probation_left_date).' ('.($types[$h->probation_left_type] ?? '—').'): '.($h->probation_reason ?: '—').'.', 'Được tính — nghỉ trong thử việc'],
            $confirmed && $h->probation_outcome === 'left' => ['counted', 'Nghỉ ngày '.$fmtD($h->probation_left_date).' ('.($types[$h->probation_left_type] ?? '—').') — quy tắc nháp chỉ tính tự nghỉ.', 'Được tính — không thuộc loại nghỉ tính KPI'],
            $confirmed => ['counted', 'Đạt thử việc, kết thúc '.$fmtD($h->probation_end_date).'.', 'Được tính — đạt thử việc'],
            in_array($h->review_status ?? null, ['submitted', 'changed', 'returned'], true) => ['pending', match ($h->review_status) {
                'submitted' => 'Đã gửi kiểm ('.($outcomes[$h->probation_outcome] ?? '—').') — chờ người kiểm.',
                'returned' => 'Trả bổ sung: '.($h->return_reason ?: '—'),
                default => 'Sửa sau khi gửi — cần gửi lại.',
            }, null],
            default => ['missing', 'Chưa gửi kiểm kết quả thử việc ('.($outcomes[$h->probation_outcome] ?? '—').').', null],
        };

        return [
            'code' => self::CODE, 'source_id' => (int) $h->id,
            'title' => 'Lượt #'.$h->id.' · '.($names[(int) $h->employee_user_id] ?? $h->candidate_name ?? '—').' · '.$h->position,
            'url' => route('hr.recruitment.hires.show', (int) $h->id).'#kpi-hc03', 'owner_id' => (int) $h->owner_id, 'owner_name' => $names[(int) $h->owner_id] ?? '—',
            'reviewer_name' => $h->reviewer_id ? ($names[(int) $h->reviewer_id] ?? '—') : null,
            'kpi_state' => $state, 'reason' => $reason, 'state_label' => $label,
            'deadline' => $h->probation_end_date ? Carbon::parse($h->probation_end_date) : null,
            'started_at' => $h->probation_start_date ? Carbon::parse($h->probation_start_date) : null,
            'actual_at' => $h->probation_left_date ? Carbon::parse($h->probation_left_date) : null,
            'work_label' => $outcomes[$h->probation_outcome ?? ''] ?? 'Chưa xác định', 'review_status' => $h->review_status ?? 'none',
            'confirmed_by_name' => $h->confirmed_by ? ($names[(int) $h->confirmed_by] ?? '—') : null,
            'confirmed_at' => $h->confirmed_at ? Carbon::parse($h->confirmed_at) : null,
        ];
    }

    public function summarize(array $rows, string $month, ?Carbon $now = null, array $rules = []): array
    {
        $now ??= now();
        $counts = RecruitmentTimeSource::blankCounts();
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
        }
        $den = $counts['counted'] + $counts['lost'];
        $value = $den > 0 ? round($counts['lost'] / $den * 100, 2) : null;
        [, $end] = RecruitmentTimeSource::bounds($month);

        return [
            'numerator' => $counts['lost'], 'denominator' => $den, 'value' => $value, 'counts' => $counts,
            'measure_text' => $den > 0 ? $counts['lost'].' nghỉ ÷ '.$den.' người cohort = '.RecruitmentTimeSource::num($value).'%' : 'Cohort đã xác định kết quả = 0 người — chưa đủ, không tự thành 0%',
            'unit' => '%',
            'data_status' => RecruitmentTimeSource::dataStatus($rows, $counts, $now, $end),
        ];
    }
}
