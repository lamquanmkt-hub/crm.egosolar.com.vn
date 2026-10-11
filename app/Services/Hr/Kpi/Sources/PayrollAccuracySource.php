<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\PayrollAudits;
use App\Support\EgoCompanyLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC04 — Độ chính xác tính lương = phần đã kiểm không có lỗi ÷ phần đã kiểm, từ các đợt đối soát bảng lương HR
 * của HCNS chịu trách nhiệm, đã được người kiểm độc lập xác nhận.
 *
 * Chưa có đợt đối soát ≠ 100%; dòng “chưa xác định”, dòng chưa kiểm (khi phải kiểm đủ), đợt chưa gửi/chờ xác nhận
 * → chưa đủ. Không đếm số lần sửa bảng lương. Đơn vị (dòng/khoản), kỳ, độ phủ và lỗi đã sửa theo quy tắc nháp (H-D05).
 */
final class PayrollAccuracySource
{
    public const CODE = 'payroll_accuracy';

    public static function ready(): bool
    {
        return PayrollAudits::ready();
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        $payrollMonth = PayrollAudits::payrollMonthFor($month, $rules);
        $audits = $this->query()->where('a.owner_id', $userId)->where('a.payroll_month', $payrollMonth)
            ->where(fn ($q) => $q->where('a.company_id', EgoCompanyLock::id())->orWhereNull('a.company_id'))
            ->orderBy('a.id')->get();

        return $this->auditRows($audits, $rules);
    }

    /** Dòng nguồn của riêng một đợt (trang đợt đối soát dùng cùng logic với checklist). */
    public function rowsForAudit(int $auditId, array $rules): array
    {
        return $this->auditRows($this->query()->where('a.id', $auditId)->get(), $rules);
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table('hr_payroll_audits as a')
            ->leftJoin('hr_payroll_periods as p', 'p.id', '=', 'a.period_id')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'a.id')->where('v.source_type', PayrollAudits::SOURCE_TYPE)->where('v.criterion_code', self::CODE))
            ->select(['a.*', 'p.status as period_status', 'p.approved_at as period_approved_at', 'v.status as review_status', 'v.result',
                'v.confirmed_by', 'v.confirmed_at', 'v.return_reason', 'v.first_submitted_at', 'v.submission_count']);
    }

    /** @return array<int, array> */
    private function auditRows(\Illuminate\Support\Collection $audits, array $rules): array
    {
        $svc = app(PayrollAudits::class);
        $coverage = $rules['payroll_accuracy']['coverage'] ?? 'full';
        $unitLabel = ($rules['payroll_accuracy']['unit'] ?? 'line') === 'component' ? 'khoản' : 'dòng';
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $componentLabels = collect(PayrollAudits::components())->map(fn ($c) => $c['label'])->all();
        $rows = [];

        foreach ($audits as $a) {
            $payrollMonth = (string) $a->payroll_month;
            $base = [
                'code' => self::CODE, 'audit_id' => (int) $a->id, 'audit_code' => (string) $a->code, 'payroll_month' => $payrollMonth,
                'owner_id' => (int) $a->owner_id, 'owner_name' => $names[(int) $a->owner_id] ?? '—',
                'reviewer_name' => $a->reviewer_id ? ($names[(int) $a->reviewer_id] ?? '—') : null,
                'checker_name' => $a->checker_id ? ($names[(int) $a->checker_id] ?? '—') : null,
                'review_status' => $a->review_status ?? 'none',
                'confirmed_by_name' => $a->confirmed_by ? ($names[(int) $a->confirmed_by] ?? '—') : null,
                'confirmed_at' => $a->confirmed_at ? Carbon::parse($a->confirmed_at) : null,
                'deadline' => null, 'actual_at' => null, 'units' => 0, 'wrong' => 0,
            ];
            $url = route('hr.payroll.audits.show', (int) $a->id);
            if ($a->status === 'cancelled') {
                $rows[] = $base + ['source_id' => (int) $a->id, 'title' => $a->code.' · '.$a->title, 'url' => $url, 'kpi_state' => 'excluded',
                    'work_label' => 'Đợt đã hủy', 'reason' => 'Đã hủy: '.($a->cancel_reason ?: '—').'.', 'components_text' => null];

                continue;
            }

            $items = $svc->items((int) $a->id);
            $errors = $svc->errors((int) $a->id)->whereNull('removed_at')->groupBy('item_id');
            $lines = $svc->scopeLines($a)->keyBy('id');
            $allLines = DB::table('hr_payroll_lines')->whereIn('id', $items->pluck('line_id')->filter()->all() ?: [0])->get()->keyBy('id');
            foreach ($items as $item) {
                $itemErrors = $errors->get($item->id, collect());
                $line = $item->line_id ? $allLines->get((int) $item->line_id) : null;
                $stale = $item->result === 'ok' && (! $line || PayrollAudits::lineFingerprint($line) !== $item->line_fingerprint);
                $components = json_decode((string) $item->components, true) ?: [];
                $unit = PayrollAudits::unitResult($item, $itemErrors, $rules, $a->period_approved_at);
                $errText = $itemErrors->map(fn ($e) => ($componentLabels[$e->component] ?? $e->component).': '.mb_strimwidth((string) $e->description, 0, 80, '…'))->implode('; ');
                [$state, $reason] = match (true) {
                    $item->result === 'undetermined' || $item->result === null => ['missing', 'Chưa xác định đúng/sai — chưa vào mẫu số; cần kết luận hoặc ghi rõ lý do.'],
                    $stale => ['missing', $line ? 'Dòng lương đã đổi sau lần kiểm '.Carbon::parse($item->checked_at)->format('H:i d/m').' — cần kiểm lại.' : 'Dòng lương không còn trong kỳ (đã tính lại) — cần kiểm lại.'],
                    ($a->review_status ?? null) === 'confirmed' && $a->result === 'pass' => [$unit['wrong'] > 0 ? 'lost' : 'earned', $unit['wrong'] > 0
                        ? 'Có '.$itemErrors->count().' lỗi đã xác nhận ('.$errText.').'.($unit['excluded_fixed'] > 0 ? ' '.$unit['excluded_fixed'].' lỗi sửa trước khi duyệt lương không tính theo quy tắc.'
                            : (($fixed = $itemErrors->where('resolution_status', 'fixed')->count()) > 0 ? ' Trong đó '.$fixed.' lỗi đã sửa trên bảng lương — vẫn tính sai theo quy tắc “mọi lỗi đã xác nhận”.' : ''))
                        : ($itemErrors->isNotEmpty() ? 'Lỗi đã sửa trước khi duyệt lương — không tính theo quy tắc nháp; lịch sử lỗi giữ nguyên.' : 'Đã kiểm, không có lỗi; người kiểm đã xác nhận.')],
                    ($a->review_status ?? null) === 'confirmed' => ['pending', 'Người kiểm ghi không đạt — sửa và gửi lại.'],
                    ($a->review_status ?? null) === 'submitted' => ['pending', 'Đợt đã gửi kiểm — chờ người kiểm xác nhận.'],
                    ($a->review_status ?? null) === 'returned' => ['pending', 'Trả bổ sung: '.($a->return_reason ?: '—')],
                    ($a->review_status ?? null) === 'changed' => ['pending', 'Sửa sau khi gửi — cần gửi lại.'],
                    default => ['missing', 'Đợt chưa gửi kiểm — chưa tính.'],
                };
                $counted = in_array($state, ['earned', 'lost'], true);
                $rows[] = array_merge($base, [
                    'source_id' => (int) $item->id, 'line_id' => $item->line_id ? (int) $item->line_id : null,
                    'title' => ($item->employee_name ?: 'Dòng #'.$item->line_id).' · '.$a->code,
                    'url' => $url.'#pa-line-'.$item->line_id, 'kpi_state' => $state, 'reason' => $reason,
                    'work_label' => match ($item->result) {
                        'ok' => 'Đã kiểm — đúng', 'error' => 'Đã kiểm — có '.$itemErrors->count().' lỗi', default => 'Chưa xác định',
                    },
                    'components_text' => count($components).' khoản đã kiểm',
                    'checked_at' => $item->checked_at ? Carbon::parse($item->checked_at) : null,
                    'units' => $counted ? $unit['units'] : 0, 'wrong' => $counted ? $unit['wrong'] : 0,
                    'state_label' => match ($state) {
                        'earned' => 'Được tính — đúng ('.$unit['units'].'/'.$unit['units'].' '.$unitLabel.')',
                        'lost' => 'Được tính — có lỗi ('.$unit['wrong'].'/'.$unit['units'].' '.$unitLabel.' sai)',
                        default => null,
                    },
                ]);
            }

            $unchecked = $lines->keys()->diff($items->pluck('line_id')->filter()->map(fn ($id) => (int) $id))->count();
            if ($unchecked > 0) {
                $rows[] = $base + ['source_id' => -(int) $a->id, 'title' => $a->code.' · '.$unchecked.' dòng trong phạm vi chưa kiểm', 'url' => $url,
                    'kpi_state' => $coverage === 'full' ? 'missing' : 'excluded', 'work_label' => 'Chưa kiểm', 'components_text' => null,
                    'reason' => $coverage === 'full'
                        ? 'Phạm vi đợt có '.$lines->count().' dòng, còn '.$unchecked.' dòng chưa kiểm — quy tắc nháp yêu cầu kết luận đủ, chưa đủ.'
                        : 'Kiểm mẫu: '.$unchecked.' dòng chưa kiểm không vào mẫu số.'];
            }
        }

        return $rows;
    }

    public function summarize(array $rows, string $month, ?Carbon $now = null, array $rules = []): array
    {
        $now ??= now();
        $counts = RecruitmentTimeSource::blankCounts();
        $units = 0;
        $wrong = 0;
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
            $units += (int) ($row['units'] ?? 0);
            $wrong += (int) ($row['wrong'] ?? 0);
        }
        $ok = $units - $wrong;
        $value = $units > 0 ? round($ok / $units * 100, 2) : null;
        $unitLabel = ($rules['payroll_accuracy']['unit'] ?? 'line') === 'component' ? 'khoản' : 'dòng';
        $payrollMonth = PayrollAudits::payrollMonthFor($month, $rules);
        $periodExists = DB::table('hr_payroll_periods')->where('payroll_month', $payrollMonth)->exists();
        [, $end] = RecruitmentTimeSource::bounds($month);
        $pm = substr($payrollMonth, 5, 2).'/'.substr($payrollMonth, 0, 4);

        return [
            'numerator' => $ok, 'denominator' => $units, 'value' => $value, 'counts' => $counts,
            'measure_text' => match (true) {
                $units > 0 => $ok.'/'.$units.' '.$unitLabel.' đã kiểm không lỗi = '.RecruitmentTimeSource::num($value).'% (kỳ lương '.$pm.')',
                $rows === [] && $periodExists => 'Có bảng lương kỳ '.$pm.' nhưng chưa có đợt đối soát đã xác nhận — chưa kiểm, không tự thành 100%',
                $rows === [] => 'Không có bảng lương kỳ '.$pm.' để đối soát',
                default => 'Chưa có phần đã kiểm được xác nhận (mẫu số 0) — chưa đủ, không tự thành 100%',
            },
            'unit' => '%',
            'payroll_month' => $payrollMonth,
            'data_status' => RecruitmentTimeSource::dataStatus($rows, $counts, $now, $end),
            // “Không phát sinh” chỉ hợp lệ khi kỳ lương được đo không tồn tại; có bảng lương mà chưa đối soát là chưa kiểm.
            'allow_na' => $rows === [] && ! $periodExists,
        ];
    }
}
