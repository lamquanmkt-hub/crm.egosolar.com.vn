<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\RecruitmentKpi;
use App\Support\EgoCompanyLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC02 — Chi phí tuyển dụng bình quân = tổng chi phí thực chi hợp lệ (đã phân bổ cho đợt tuyển, đã kiểm) ÷ số lượt
 * nhận việc đã xác nhận (HC01) của cùng người phụ trách trong kỳ. Chi phí đề xuất/tạm ứng chưa quyết toán không tính;
 * hoàn tiền trừ ra; một chứng từ không phân bổ vượt số thực chi. Không có vị trí tuyển thành công → chưa đủ.
 */
final class RecruitmentCostSource
{
    public const CODE = 'recruit_cost';

    public static function ready(): bool
    {
        return RecruitmentKpi::costsReady();
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        $now ??= now();
        [$start, $end] = RecruitmentTimeSource::bounds($month);
        $kpi = app(RecruitmentKpi::class);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $rows = [];

        $costs = DB::table('hr_recruitment_costs as k')
            ->join('hr_recruitment_requests as r', 'r.id', '=', 'k.request_id')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'k.id')->where('v.source_type', RecruitmentKpi::COST_TYPE)->where('v.criterion_code', self::CODE))
            ->where('r.kpi_tracked', true)->where('k.owner_id', $userId)
            ->where(fn ($q) => $q->where('k.company_id', EgoCompanyLock::id())->orWhereNull('k.company_id'))
            ->orderBy('k.id')
            ->get(['k.*', 'r.position', 'r.department', 'v.status as review_status', 'v.result', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason']);
        foreach ($costs as $k) {
            $doc = $kpi->document((int) $k->payment_request_id);
            // Kỳ của khoản chi = ngày thực chi; chứng từ chưa thực chi hiện ở tháng gắn chứng từ.
            $date = $doc && $doc['valid'] ? Carbon::parse($doc['valid_at']) : Carbon::parse($k->created_at);
            if ($date->lessThan($start) || $date->greaterThan($end)) {
                continue;
            }
            $rows[] = $this->costRow($k, $doc, $names, $date);
        }

        $time = app(RecruitmentTimeSource::class);
        $hires = $time->query()->where('h.owner_id', $userId)->where('h.status', 'started')
            ->whereBetween('h.actual_start_date', [$start->toDateString(), $end->toDateString()])->orderBy('h.actual_start_date')->get();
        foreach ($hires as $h) {
            $row = $time->row($h, $names, $now, $end);
            $rows[] = array_merge($row, [
                'code' => self::CODE, 'group' => 'hire', 'group_label' => 'Vị trí tuyển thành công (lượt nhận việc đã xác nhận)',
                'state_label' => $row['kpi_state'] === 'counted' ? 'Được tính — 1 vị trí' : null,
                'reason' => $row['kpi_state'] === 'counted' ? 'Nhận việc thực tế '.Carbon::parse($h->actual_start_date)->format('d/m/Y').', đã xác nhận ở HC01.' : 'Lượt nhận việc chưa xác nhận ở HC01: '.$row['reason'],
            ]);
        }

        return $rows;
    }

    public function costRow(object $k, ?array $doc, array $names, Carbon $date): array
    {
        $amount = $doc ? (float) $k->allocated_amount * $doc['sign'] : 0.0;
        $money = fn ($v) => number_format((float) $v, 0, ',', '.').' đ';
        // Tổng phân bổ của chứng từ (mọi đợt) không được vượt số thực chi — ví dụ tạm ứng gắn theo số đề nghị rồi quyết toán ít hơn.
        $allocatedTotal = $doc ? app(RecruitmentKpi::class)->allocatedElsewhere((int) $k->payment_request_id) : 0.0;
        [$state, $reason] = match (true) {
            ! empty($k->removed_at) => ['excluded', 'Đã gỡ khỏi đợt tuyển: '.($k->remove_reason ?: '—').'.'],
            ! $doc => ['missing', 'Không còn chứng từ.'],
            ! $doc['valid'] => ['missing', $doc['reason']],
            $allocatedTotal > (float) $doc['valid_amount'] + 0.004 => ['missing', 'Tổng phân bổ '.$money($allocatedTotal).' vượt số thực chi '.$money($doc['valid_amount']).' — điều chỉnh số phân bổ.'],
            ($k->review_status ?? null) === 'confirmed' && $k->result === 'pass' => ['counted', $doc['type_label'].' '.$doc['code'].': phân bổ '.$money($amount).' cho đợt tuyển, đã kiểm tra.'],
            ($k->review_status ?? null) === 'confirmed' => ['pending', 'Người kiểm ghi không đạt — sửa và gửi lại.'],
            in_array($k->review_status ?? null, ['submitted', 'changed', 'returned'], true) => ['pending', match ($k->review_status) {
                'submitted' => 'Đã gửi kiểm ('.$money($amount).') — chờ người kiểm.',
                'returned' => 'Trả bổ sung: '.($k->return_reason ?: '—'),
                default => 'Sửa sau khi gửi — cần gửi lại.',
            }],
            default => ['missing', 'Chưa gửi kiểm tra khoản phân bổ '.$money($amount).'.'],
        };

        return [
            'code' => self::CODE, 'group' => 'cost', 'group_label' => 'Chi phí thực chi hợp lệ',
            'source_id' => (int) $k->id, 'title' => ($doc['code'] ?? 'Chứng từ #'.$k->payment_request_id).' · '.$k->position,
            'url' => route('hr.recruitment.costs.show', (int) $k->id), 'owner_id' => (int) $k->owner_id, 'owner_name' => $names[(int) $k->owner_id] ?? '—',
            'reviewer_name' => $k->reviewer_id ? ($names[(int) $k->reviewer_id] ?? '—') : null,
            'kpi_state' => $state, 'reason' => $reason,
            'state_label' => $state === 'counted' ? 'Được tính — '.$money($amount) : null,
            'amount' => $amount, 'doc_id' => (int) $k->payment_request_id, 'doc_created_by' => $doc['created_by'] ?? null,
            'deadline' => null, 'started_at' => null, 'actual_at' => $date,
            'work_label' => $doc ? $doc['type_label'].' · '.($doc['valid'] ? 'đã thực chi' : 'chưa thực chi') : '—',
            'review_status' => $k->review_status ?? 'none',
            'confirmed_by_name' => $k->confirmed_by ? ($names[(int) $k->confirmed_by] ?? '—') : null,
            'confirmed_at' => $k->confirmed_at ? Carbon::parse($k->confirmed_at) : null,
        ];
    }

    public function summarize(array $rows, string $month, ?Carbon $now = null, array $rules = []): array
    {
        $now ??= now();
        $counts = RecruitmentTimeSource::blankCounts();
        $groups = ['cost' => ['key' => 'cost', 'label' => 'Chi phí thực chi hợp lệ', 'counts' => RecruitmentTimeSource::blankCounts()],
            'hire' => ['key' => 'hire', 'label' => 'Vị trí tuyển thành công', 'counts' => RecruitmentTimeSource::blankCounts()]];
        $total = 0.0;
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
            $groups[$row['group']]['counts'][$row['kpi_state']]++;
            if ($row['group'] === 'cost' && $row['kpi_state'] === 'counted') {
                $total += (float) $row['amount'];
            }
        }
        $hires = $groups['hire']['counts']['counted'];
        $value = $hires > 0 ? round($total / $hires, 2) : null;
        $money = fn ($v) => number_format((float) $v, 0, ',', '.').' đ';
        $groups['cost'] += ['numerator' => $total, 'denominator' => null, 'value' => null, 'measure_text' => 'Tổng '.$money($total).' ('.$groups['cost']['counts']['counted'].' khoản đã kiểm)'];
        $groups['hire'] += ['numerator' => $hires, 'denominator' => null, 'value' => null, 'measure_text' => $hires.' vị trí đã xác nhận'];
        [, $end] = RecruitmentTimeSource::bounds($month);
        $status = RecruitmentTimeSource::dataStatus($rows, $counts, $now, $end);

        return [
            'numerator' => $total, 'denominator' => $hires, 'value' => $value, 'counts' => $counts,
            'groups' => array_values($groups),
            'measure_text' => $hires > 0 ? 'Tổng chi hợp lệ '.$money($total).' ÷ '.$hires.' vị trí = '.$money($value).'/vị trí'
                : 'Chưa có vị trí tuyển thành công đã xác nhận — chưa đủ, không tự thành 100%',
            'unit' => 'đ/vị trí',
            // Không có vị trí tuyển thành công thì chưa đủ dữ liệu (kể cả khi đã có chi phí).
            'data_status' => $hires === 0 && $status !== 'incomplete' ? 'no_items' : $status,
            // Có chi phí mà chưa có vị trí tuyển thành công thì không được đánh “Không phát sinh” để loại tiêu chí.
            'allow_na' => $rows === [],
        ];
    }
}
